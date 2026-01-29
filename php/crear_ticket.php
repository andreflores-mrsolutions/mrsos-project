<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require 'conexion.php';
session_start();

$usId = $_SESSION['usId'] ?? null;
$clId = $_SESSION['clId'] ?? null;

if (!$usId || !$clId) {
  echo json_encode(['success'=>false, 'error'=>'No autenticado']); 
  exit;
}

$peId        = intval($_POST['peId'] ?? 0);
$severidad   = trim($_POST['severidad'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$contacto    = trim($_POST['contacto'] ?? '');
$telefono    = trim($_POST['telefono'] ?? '');
$email       = trim($_POST['email'] ?? '');
$ticket      = trim($_POST['ticket'] ?? '');

if (!$peId || !$severidad || !$descripcion || !$contacto || !$telefono || !$email) {
  echo json_encode(['success'=>false, 'error'=>'Faltan datos']); 
  exit;
}

/* Deriva eqId y csId desde la póliza del peId */
$sqlInfo = "
  SELECT pe.eqId, pc.csId
  FROM polizasequipo pe
  JOIN polizascliente pc ON pc.pcId = pe.pcId
  WHERE pe.peId = ? AND pc.clId = ?
";
$stmt = $conectar->prepare($sqlInfo);
$stmt->bind_param("ii", $peId, $clId);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$info) { 
  echo json_encode(['success'=>false,'error'=>'peId inválido']); 
  exit; 
}
$eqId = (int)$info['eqId'];
$csId = $info['csId'] ? (int)$info['csId'] : null;

/* Insert del ticket */
if(!$ticket) {
    $ticket = "Servicio";
}else {
    $ticket = trim($_POST['ticket'] ?? '');
}
$sql = "
INSERT INTO ticket_soporte
 (clId, usId, eqId, peId, csId,
  tiTipoTicket, tiDescripcion, tiEstatus, tiProceso, tiNivelCriticidad,
  tiFechaCreacion, tiNombreContacto, tiNumeroContacto, tiCorreoContacto)
VALUES
 (?, ?, ?, ?, ?, ?, ?, 'Abierto', 'asignacion', ?, CURDATE(), ?, ?, ?)
";
$stmt = $conectar->prepare($sql);
$stmt->bind_param(
  "iiiiissssss",
  $clId, $usId, $eqId, $peId, $csId,
  $ticket, $descripcion, $severidad, $contacto, $telefono, $email
);
$ok = $stmt->execute();
$id = $stmt->insert_id;
$stmt->close();

// Best effort: enviar correo de notificación
if ($ok && $id > 0) {
  notificarNuevoTicket(
    $id,
    $clId,
    $csId,
    $peId,
    $severidad,
    $ticket,
    $descripcion,
    $contacto,
    $telefono,
    $email
  );
}

echo json_encode(['success'=>$ok, 'tiId'=>$id]);


/**
 * Envía correo de creación de ticket.
 *
 * No rompe el flujo principal si falla: solo loguea el error.
 */

function prefixCliente($nombre) {
    if (!$nombre) return 'TI';
    $clean = iconv('UTF-8','ASCII//TRANSLIT',$nombre); 
    $clean = preg_replace('/[^A-Za-z]/','', $clean);
    return strtoupper(substr($clean, 0, 3));
}
function notificarNuevoTicket(
  int $tiId,
  int $clId,
  ?int $csId,
  int $peId,
  string $severidad,
  string $ticket,
  string $descripcion,
  string $contacto,
  string $telefono,
  string $emailContacto
): void {
  // Desactivar rápido si lo necesitas:
  $ENVIAR_CORREO = false;
  if (!$ENVIAR_CORREO) return;

  // No tiene sentido enviar si el correo del contacto no es válido
  if (!filter_var($emailContacto, FILTER_VALIDATE_EMAIL)) {
    return;
  }

  global $conectar;

  // ================= Info adicional del ticket =================
  $clNombre   = '';
  $csNombre   = '';
  $eqModelo   = '';
  $peSN       = '';
  $fechaCrea  = '';

  try {
    $sqlDet = "
      SELECT 
        t.tiFechaCreacion,
        t.tiNivelCriticidad,
        t.tiDescripcion,
        c.clNombre,
        cs.csNombre,
        e.eqModelo,
        pe.peSN
      FROM ticket_soporte t
      LEFT JOIN clientes c       ON c.clId  = t.clId
      LEFT JOIN cliente_sede cs  ON cs.csId = t.csId
      LEFT JOIN polizasequipo pe ON pe.peId = t.peId
      LEFT JOIN equipos e        ON e.eqId  = t.eqId
      WHERE t.tiId = ?
      LIMIT 1
    ";
    $st = $conectar->prepare($sqlDet);
    if ($st) {
      $st->bind_param('i', $tiId);
      $st->execute();
      $det = $st->get_result()->fetch_assoc();
      $st->close();

      if ($det) {
        $fechaCrea = $det['tiFechaCreacion'] ?? '';
        $clNombre  = $det['clNombre']        ?? '';
        $csNombre  = $det['csNombre']        ?? '';
        $eqModelo  = $det['eqModelo']        ?? '';
        $peSN      = $det['peSN']            ?? '';
      }
    }
  } catch (Throwable $e) {
    // Si falla este SELECT, seguimos con datos básicos
  }
$prefix = prefixCliente($clNombre);
$codigoTicket = "{$prefix}-{$tiId}";

  // ================= Branding / texto base =================
  $NOMBRE_EMPRESA    = 'MR Solutions';
  $URL_LOGO          = 'https://www.ventasdeseguridad.com/media/com_mtree/images/listings/o/2018_03_16_19_03_54_Logo.jpg';
  $DIRECCION_EMPRESA = 'Alhambra 813 Bis, Portales Sur, Benito Juárez, 03300 Ciudad de México, CDMX';

  $correoUsuario = $_SESSION['usCorreo'] ?? null; // usuario logueado
  $nombreUsuario = $_SESSION['usNombre'] ?? ($_SESSION['usUsuario'] ?? 'Usuario');

  $clienteTxt = $clNombre ?: "ID cliente {$clId}";
  $sedeTxt    = $csNombre ?: ($csId ? "Sede #{$csId}" : 'Sin sede asociada');
  $equipoTxt  = $eqModelo ?: "Equipo #{$peId}";
  $snTxt      = $peSN     ?: 'SN no registrado';

  $fechaTxt   = $fechaCrea ?: date('Y-m-d');
  $sevTxt     = $severidad ?: 'No especificada';

  $accionTxt  = "Se ha creado un nuevo ticket de soporte en {$NOMBRE_EMPRESA}.";

  // ================= PHPMailer =================
  require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/PHPMailer.php';
  require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/SMTP.php';
  require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/Exception.php';

  $mail = new PHPMailer\PHPMailer\PHPMailer(true);

  // Logs
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
  $logFile  = $logDir . '/mail_nuevo_ticket.log';
  $smtpLog  = '';
  $debugOut = function ($str) use (&$smtpLog) { $smtpLog .= $str . "\n"; };

  // Para pruebas: true = no envía realmente
  $DRY_RUN = false;

  try {
    // SMTP (como en reset)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'lizma0116@gmail.com';
    $mail->Password   = 'acxh hduf vpqd xgnn';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->SMTPDebug   = 0;
    $mail->Debugoutput = $debugOut;

    // Remitente
    $mail->setFrom('lizma0116@gmail.com', $NOMBRE_EMPRESA.' Notificaciones');
    $mail->addReplyTo('lizma0116@gmail.com', 'Mesa de Ayuda');

    // Destinatarios
    $mail->addAddress($emailContacto, $contacto); // contacto del ticket
    if ($correoUsuario && filter_var($correoUsuario, FILTER_VALIDATE_EMAIL)) {
      $mail->addCC($correoUsuario, $nombreUsuario); // copia al usuario logueado
    }
    // Si quieres siempre a soporte:
    // $mail->addCC('soporte@mrsolutions.com.mx', 'Soporte MR Solutions');

    // Asunto / preheader
    $mail->Subject = "Nuevo ticket creado – {$codigoTicket}";
    $preheader = "Se ha creado el {$ticket} {$codigoTicket} para {$clienteTxt}.";

    // Paleta
    $primary    = '#4F46E5';
    $text       = '#111827';
    $muted      = '#6B7280';
    $bg         = '#F3F4F6';
    $card       = '#FFFFFF';
    $border     = '#E5E7EB';

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
              Nuevo ticket de soporte #{$tiId}
            </h1>
            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:{$text};">
              {$accionTxt}
            </p>
            <p style="margin:0 0 16px 0;font-size:13px;line-height:1.6;color:{$text};">
              Cliente: <strong>{$clienteTxt}</strong><br>
              Sede: <strong>{$sedeTxt}</strong>
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:13px;color:{$text};margin-bottom:16px;">
              <tr>
                <td style="padding:4px 0;"><strong>Ticket:</strong></td>
                <td style="padding:4px 0;">{$codigoTicket}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;"><strong>Fecha de creación:</strong></td>
                <td style="padding:4px 0;">{$fechaTxt}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;"><strong>Equipo:</strong></td>
                <td style="padding:4px 0;">{$equipoTxt}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;"><strong>Serial:</strong></td>
                <td style="padding:4px 0;">{$snTxt}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;"><strong>Severidad:</strong></td>
                <td style="padding:4px 0;">{$sevTxt}</td>
              </tr>
            </table>

            <p style="margin:0 0 8px 0;font-size:13px;line-height:1.6;color:{$text};">
              <strong>Descripción del incidente:</strong><br>
              {$descripcion}
            </p>

            <hr style="border:none;border-top:1px solid {$border};margin:16px 0;">

            <p style="margin:0 0 8px 0;font-size:13px;line-height:1.6;color:{$text};">
              <strong>Datos de contacto:</strong><br>
              Nombre: {$contacto}<br>
              Teléfono: {$telefono}<br>
              Correo: {$emailContacto}
            </p>

            <p style="margin:16px 0 0 0;font-size:12px;line-height:1.6;color:{$muted};">
              Un ingeniero de MR Solutions dará seguimiento a este ticket. 
              Si necesitas añadir más información, responde a este correo o ingresa a la plataforma MRSoS.
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

    $mail->AltBody =
      "Nuevo ticket de soporte {$codigoTicket}\n\n" .
      "{$accionTxt}\n\n" .
      "Cliente: {$clienteTxt}\n" .
      "Sede: {$sedeTxt}\n" .
      "Ticket: {$codigoTicket}\n" .
      "Fecha: {$fechaTxt}\n" .
      "Equipo: {$equipoTxt}\n" .
      "Serial: {$snTxt}\n" .
      "Severidad: {$sevTxt}\n\n" .
      "Descripción:\n{$descripcion}\n\n" .
      "Contacto: {$contacto}\n" .
      "Teléfono: {$telefono}\n" .
      "Correo: {$emailContacto}\n\n" .
      "Un ingeniero de MR Solutions dará seguimiento a este ticket.\n";

    if ($DRY_RUN === true) {
      return;
    }

    $mail->send();

  } catch (Throwable $ex) {
    $line = "[" . date('Y-m-d H:i:s') . "] tiId={$tiId} ex=" . $ex->getMessage() . "\nSMTP:\n" . $smtpLog . "\n\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
    error_log("[MRSoS mail nuevo ticket] " . $line);
  }
}
