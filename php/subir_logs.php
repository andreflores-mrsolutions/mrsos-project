<?php
// ../php/subir_logs.php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/conexion.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'Método no permitido']);
  exit;
}

$usId = $_SESSION['usId'] ?? null;
$clId = $_SESSION['clId'] ?? null;
if (!$usId || !$clId) {
  echo json_encode(['success' => false, 'error' => 'No autenticado']);
  exit;
}

$tiId = isset($_POST['tiId']) ? (int)$_POST['tiId'] : 0;
if ($tiId <= 0) {
  echo json_encode(['success' => false, 'error' => 'tiId inválido']);
  exit;
}

if (!isset($_FILES['logs'])) {
  echo json_encode(['success' => false, 'error' => 'No se recibió archivo']);
  exit;
}

$file = $_FILES['logs'];
if ($file['error'] !== UPLOAD_ERR_OK) {
  $errMap = [
    UPLOAD_ERR_INI_SIZE => 'Archivo supera el límite del servidor.',
    UPLOAD_ERR_FORM_SIZE => 'Archivo supera el límite del formulario.',
    UPLOAD_ERR_PARTIAL => 'Archivo subido parcialmente.',
    UPLOAD_ERR_NO_FILE => 'No se seleccionó archivo.',
    UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal.',
    UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo.',
    UPLOAD_ERR_EXTENSION => 'Extensión bloqueada por el servidor.'
  ];
  $msg = $errMap[$file['error']] ?? 'Error al subir el archivo.';
  echo json_encode(['success' => false, 'error' => $msg]);
  exit;
}

// Autoría del ticket para este cliente
$authOk = false;
if ($stmt = $conectar->prepare("SELECT tiId, clId FROM ticket_soporte WHERE tiId=? AND clId=? LIMIT 1")) {
  $stmt->bind_param("ii", $tiId, $clId);
  $stmt->execute();
  $rs = $stmt->get_result();
  $ticketRow = $rs->fetch_assoc();
  $authOk = (bool)$ticketRow;
  $stmt->close();
}
if (!$authOk) {
  echo json_encode(['success' => false, 'error' => 'No autorizado para subir logs a este ticket']);
  exit;
}

// Validaciones de archivo
$maxBytes = 100 * 1024 * 1024; // 100 MB
if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
  echo json_encode(['success' => false, 'error' => 'Archivo vacío o excede 100MB']);
  exit;
}

$origName = $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowedExt = ['log', 'txt', 'zip', 'gz', 'tar', '7z', 'rar'];
if (!in_array($ext, $allowedExt, true)) {
  echo json_encode(['success' => false, 'error' => 'Extensión no permitida']);
  exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowedMime = [
  'text/plain',
  'application/zip',
  'application/x-7z-compressed',
  'application/x-rar',
  'application/x-rar-compressed',
  'application/gzip',
  'application/x-gzip',
  'application/x-tar'
];
if (!in_array($mime, $allowedMime, true) && !in_array($ext, ['log', 'txt'], true)) {
  echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido']);
  exit;
}

// Ruta de destino
$baseDir  = realpath(__DIR__ . '/..'); // raíz del proyecto
$relDir   = "uploads/logs/" . $tiId;
$destDir  = $baseDir . DIRECTORY_SEPARATOR . $relDir;
if (!is_dir($destDir)) {
  if (!@mkdir($destDir, 0755, true)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo crear directorio de destino']);
    exit;
  }
}

$slug   = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($origName, PATHINFO_FILENAME));
$stored = sprintf('log_%s_%s.%s', $tiId, uniqid('', true), $ext);
$destAbs = $destDir . DIRECTORY_SEPARATOR . $stored;
$destRel = $relDir . '/' . $stored;

if (!@move_uploaded_file($file['tmp_name'], $destAbs)) {
  echo json_encode(['success' => false, 'error' => 'No se pudo mover el archivo al destino']);
  exit;
}

// Variables para bind_param
$tipo         = 'log';
$origNameSafe = (string)$origName;
$storedSafe   = (string)$stored;
$mimeSafe     = (string)($mime ?? '');
$tamInt       = (int)$file['size'];
$rutaSafe     = (string)$destRel;
$tiIdInt      = (int)$tiId;
$usIdInt      = (int)($usId ?? 0);

// Registrar en ticket_archivos si existe
$insertOk = false;
if ($stmt = $conectar->prepare("
  INSERT INTO ticket_archivos
    (tiId, taTipo, taNombreOriginal, taNombreAlmacenado, taMime, taTamano, taRuta, usId)
  VALUES (?,?,?,?,?,?,?,?)
")) {
  $stmt->bind_param(
    "issssisi",
    $tiIdInt,
    $tipo,
    $origNameSafe,
    $storedSafe,
    $mimeSafe,
    $tamInt,
    $rutaSafe,
    $usIdInt
  );
  $insertOk = $stmt->execute();
  $stmt->close();
}

// Fallback: anotar en tiExtra si no existe ticket_archivos o falló insert
if (!$insertOk) {
  if ($stmt = $conectar->prepare("
      UPDATE ticket_soporte
      SET tiExtra = CONCAT(COALESCE(tiExtra,''), ?)
      WHERE tiId=?
  ")) {
    $nota = " LOGS: " . $destRel;
    $stmt->bind_param("si", $nota, $tiIdInt);
    $stmt->execute();
    $stmt->close();
  }
}

$procesoActualizado = false;
if ($stmt = $conectar->prepare("
    UPDATE ticket_soporte
    SET tiProceso='revision especial'
    WHERE tiId=? AND LOWER(tiProceso)='logs'
")) {
  $stmt->bind_param("i", $tiIdInt);
  $stmt->execute();
  $procesoActualizado = $stmt->affected_rows > 0;
  $stmt->close();
}

if (isset($_SESSION['usNotifMail']) && $_SESSION['usNotifMail'] === 1) {


  /* =======================================================
 *  ENVÍO DE CORREO DE NOTIFICACIÓN (best-effort)
 * ======================================================= */
  $mailEnviado = false;

  try {
    // 1) Datos del ticket + cliente para código tipo ENE-14
    $sqlTk = "
      SELECT t.tiDescripcion,
             t.tiNombreContacto,
             t.tiCorreoContacto,
             t.clId,
             c.clNombre
      FROM ticket_soporte t
      LEFT JOIN clientes c ON c.clId = t.clId
      WHERE t.tiId = ? AND t.clId = ?
      LIMIT 1
    ";
    $st = $conectar->prepare($sqlTk);
    if ($st) {
      $st->bind_param("ii", $tiIdInt, $clId);
      $st->execute();
      $tkRes   = $st->get_result();
      $ticket  = $tkRes->fetch_assoc();
      $st->close();
    } else {
      $ticket = null;
    }

    if ($ticket) {
      $clienteNombre = $ticket['clNombre'] ?? '';
      $pref = '';

      if ($clienteNombre !== '') {
        $tmp = iconv('UTF-8', 'ASCII//TRANSLIT', $clienteNombre);
        $tmp = preg_replace('/[^A-Za-z]/', '', $tmp);
        $pref = strtoupper(substr($tmp, 0, 3));
      }
      if ($pref === '') {
        $pref = 'TIC';
      }
      $codigoTicket = $pref . '-' . $tiIdInt;

      $tiDescripcion    = $ticket['tiDescripcion'] ?? 'Sin descripción';
      $tiContactoNombre = $ticket['tiNombreContacto'] ?? '';
      $tiContactoCorreo = $ticket['tiCorreoContacto'] ?? '';

      // 2) Usuario logueado (destinatario principal)
      $qU = $conectar->prepare("SELECT usNombre, usCorreo FROM usuarios WHERE usId=? LIMIT 1");
      $qU->bind_param("i", $usIdInt);
      $qU->execute();
      $resU = $qU->get_result()->fetch_assoc();
      $qU->close();

      if ($resU && filter_var($resU['usCorreo'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $destCorreo = $resU['usCorreo'];
        $destNombre = $resU['usNombre'] ?: 'Usuario';

        // 3) PHPMailer
        require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Branding / datos generales
        $NOMBRE_EMPRESA    = 'MR Solutions';
        $URL_LOGO          = 'https://www.ventasdeseguridad.com/media/com_mtree/images/listings/o/2018_03_16_19_03_54_Logo.jpg';
        $DIRECCION_EMPRESA = 'Alhambra 813 Bis, Portales Sur, Benito Juárez, 03300 Ciudad de México, CDMX';

        // Logs SMTP
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
          @mkdir($logDir, 0775, true);
        }
        $logFile  = $logDir . '/mail_logs.log';
        $smtpLog  = '';
        $debugOut = function ($str) use (&$smtpLog) {
          $smtpLog .= $str . "\n";
        };

        $DRY_RUN = false; // true si quieres probar sin enviar

        // SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lizma0116@gmail.com';
        $mail->Password   = 'acxh hduf vpqd xgnn'; // App Password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->SMTPDebug   = 0;
        $mail->Debugoutput = $debugOut;

        $mail->setFrom('lizma0116@gmail.com', $NOMBRE_EMPRESA . ' Notificaciones');
        $mail->addReplyTo('lizma0116@gmail.com', 'Mesa de Ayuda');
        $mail->addAddress($destCorreo, $destNombre);

        // Copia al contacto del ticket
        if ($tiContactoCorreo && filter_var($tiContactoCorreo, FILTER_VALIDATE_EMAIL)) {
          $mail->addCC($tiContactoCorreo, $tiContactoNombre ?: $tiContactoCorreo);
        }

        // Asunto / preheader
        $mail->Subject = "Logs recibidos para {$codigoTicket}";
        $preheader     = "Se han recibido nuevos archivos de logs para el ticket {$codigoTicket}.";

        // Paleta
        $primary = '#4F46E5';
        $text    = '#111827';
        $muted   = '#6B7280';
        $bg      = '#F3F4F6';
        $card    = '#FFFFFF';
        $border  = '#E5E7EB';

        $descHtml  = htmlspecialchars($tiDescripcion, ENT_QUOTES, 'UTF-8');
        $fileHtml  = htmlspecialchars($origName, ENT_QUOTES, 'UTF-8');
        $rutaHtml  = htmlspecialchars($destRel, ENT_QUOTES, 'UTF-8');

        // HTML
        $mail->isHTML(true);
        $mail->Body = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{$mail->Subject}</title>
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <style>a{text-decoration:none}</style>
</head>
<body style="margin:0;padding:0;background:{$bg};">
<span style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;color:transparent;">
  {$preheader}
</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{$bg};padding:24px 0;">
  <tr>
    <td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:{$card};border:1px solid {$border};border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
        <tr>
          <td style="padding:20px 24px;border-bottom:1px solid {$border};">
            <table width="100%">
              <tr>
                <td align="left">
                  <img src="{$URL_LOGO}" alt="{$NOMBRE_EMPRESA}" height="36" style="display:block;border:0;outline:none;text-decoration:none;height:36px;">
                </td>
                <td align="right" style="font-size:12px;color:{$muted};">Notificación automática</td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:24px;">
            <h1 style="margin:0 0 8px 0;font-size:20px;line-height:1.3;color:{$text};">
              Logs recibidos para tu ticket
            </h1>
            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:{$text};">
              Se ha registrado la carga de archivos de logs para el siguiente ticket:
            </p>
            <ul style="margin:0 0 16px 18px;padding:0;font-size:14px;line-height:1.6;color:{$text};">
              <li><strong>Ticket:</strong> {$codigoTicket}</li>
              <li><strong>Archivo:</strong> {$fileHtml}</li>
              <li><strong>Ruta interna:</strong> {$rutaHtml}</li>
            </ul>
            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:{$text};">
              <strong>Descripción del ticket:</strong><br>
              {$descHtml}
            </p>
            <hr style="border:none;border-top:1px solid {$border};margin:24px 0 12px 0;">
            <p style="margin:0;font-size:12px;line-height:1.6;color:{$muted};">
              Nuestro equipo de soporte revisará estos archivos para continuar con el diagnóstico.
              Si tienes dudas, por favor responde a este correo o comunícate con la Mesa de Ayuda de {$NOMBRE_EMPRESA}.
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 24px;border-top:1px solid {$border};">
            <p style="margin:0;font-size:11px;color:{$muted};">
              © {$NOMBRE_EMPRESA}. Todos los derechos reservados.
              <br>{$DIRECCION_EMPRESA}
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        // Texto plano
        $mail->AltBody =
          "Logs recibidos - {$NOMBRE_EMPRESA}\n\n" .
          "Ticket: {$codigoTicket}\n" .
          "Archivo: {$origName}\n" .
          "Ruta interna: {$destRel}\n\n" .
          "Descripción del ticket:\n{$tiDescripcion}\n\n" .
          "Nuestro equipo de soporte revisará estos archivos para continuar con el diagnóstico.\n";

        if ($DRY_RUN === false) {
          $mail->send();
          $line = "[" . date('Y-m-d H:i:s') . "] tiId={$tiIdInt}, archivo={$origName}, dest={$destCorreo}\nSMTP:\n" . $smtpLog . "\n\n";
          @file_put_contents($logFile, $line, FILE_APPEND);
          $mailEnviado = true;
        }
      }
    }
  } catch (\Throwable $eMail) {
    @error_log("[MRSoS mail logs] tiId={$tiIdInt} error=" . $eMail->getMessage());
  }

  // Respuesta final
  echo json_encode([
    'success'            => true,
    'fileUrl'            => $destRel,
    'fileName'           => $origName,
    'procesoActualizado' => $procesoActualizado,
    'nuevoProceso'       => $procesoActualizado ? 'revision especial' : null,
    'mailEnviado'        => $mailEnviado
  ]);
  exit;
}
// Respuesta final
echo json_encode([
  'success'            => true,
  'fileUrl'            => $destRel,
  'fileName'           => $origName,
  'procesoActualizado' => $procesoActualizado,
  'nuevoProceso'       => $procesoActualizado ? 'revision especial' : null,
  // 'mailEnviado'        => $mailEnviado
]);
