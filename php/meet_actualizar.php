<?php
// php/meet_actualizar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once 'conexion.php';

try {
  if (empty($_SESSION['usId'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
  }

  $usId = (int)$_SESSION['usId'];

  // ====== ENTRADA ======
  $tiId     = isset($_POST['tiId']) ? (int)$_POST['tiId'] : 0;
  $accion   = $_POST['accion']      ?? '';        // 'proponer' | 'asignar' | 'cancelar'
  $quien    = $_POST['quien']       ?? 'CLIENTE'; // 'CLIENTE' | 'MR'
  $fecha    = $_POST['fecha']       ?? '';        // YYYY-MM-DD
  $hora     = $_POST['hora']        ?? '';        // HH:MM
  $plat     = $_POST['plataforma']  ?? '';        // 'Meet' | 'Teams' | etc
  $link     = $_POST['link']        ?? '';        // URL opcional
  $motivo   = $_POST['motivo']      ?? '';        // sólo para cancelación (opcional)

  if ($tiId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
  }

  $accion = strtolower($accion);   // proponer/asignar/cancelar
  $quien  = strtoupper($quien);    // CLIENTE/MR

  // ====== ORIGEN / AUTOR ======
  $origen = 'cliente';
  if (!empty($_SESSION['usRol']) && in_array($_SESSION['usRol'], ['MRA', 'MR'], true)) {
    $origen = 'mr';
  }

  $autor = trim(
    ($quien === 'MR')
      ? ($_SESSION['usNombre'] ?? ($_SESSION['usUsuario'] ?? 'MR'))
      : ($_SESSION['usNombre'] ?? 'Cliente')
  );

  // ====== OBTENER TICKET + CLIENTE (para ENE-14 / correo) ======
  $sqlTicket = "
      SELECT t.tiId,
             t.clId,
             t.tiDescripcion,
             t.tiNombreContacto,
             t.tiCorreoContacto,
             t.tiMeetFecha,
             t.tiMeetHora,
             t.tiMeetPlataforma,
             t.tiMeetEnlace,
             t.tiMeetModo,
             t.tiMeetEstado,
             c.clNombre
      FROM ticket_soporte t
      LEFT JOIN clientes c ON c.clId = t.clId
      WHERE t.tiId = ?
      LIMIT 1
    ";
  $st = $conectar->prepare($sqlTicket);
  if (!$st) {
    throw new Exception('Error prepare ticket: ' . $conectar->error);
  }
  $st->bind_param('i', $tiId);
  $st->execute();
  $rs = $st->get_result();
  $ticket = $rs->fetch_assoc();
  $st->close();

  if (!$ticket) {
    echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
    exit;
  }

  // ====== Código tipo ENE-14 ======
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
  $codigoTicket = $pref . '-' . $tiId;

  // ====== LÓGICA DE ACCIÓN ======
  $estadoFinal = $ticket['tiMeetEstado'] ?? '';
  $modoFinal   = $ticket['tiMeetModo']   ?? '';
  $fechaFinal  = $ticket['tiMeetFecha']  ?? '';
  $horaFinal   = $ticket['tiMeetHora']   ?? '';
  $platFinal   = $ticket['tiMeetPlataforma'] ?? '';
  $linkFinal   = $ticket['tiMeetEnlace']     ?? '';

  // === RAMA CANCELAR MEET ===
  if ($accion === 'cancelar') {
    $sql = "UPDATE ticket_soporte
                SET tiMeetEstado       = 'cancelado',
                    tiMeetCancelBy     = ?,
                    tiMeetCancelMotivo = ?,
                    tiMeetCancelFecha  = NOW()
                WHERE tiId = ?";
    $st = $conectar->prepare($sql);
    if (!$st) {
      throw new Exception("Error prepare cancelar: " . $conectar->error);
    }
    $st->bind_param('ssi', $origen, $motivo, $tiId);
    $st->execute();
    $st->close();

    $estadoFinal = 'cancelado';
    // modo/fecha/hora/plataforma/enlace se quedan como estaban

  } else {
    // === PROPOSER / ASIGNAR REQUIEREN FECHA/HORA/PLATAFORMA ===
    if ($fecha === '' || $hora === '' || $plat === '') {
      echo json_encode(['success' => false, 'error' => 'Fecha, hora y plataforma son obligatorias']);
      exit;
    }

    // tiMeetModo
    //   propuesta_cliente / propuesta_mr / asignado_cliente / asignado_mr
    if ($accion === 'proponer' && $quien === 'CLIENTE')      $modo = 'propuesta_cliente';
    elseif ($accion === 'proponer' && $quien === 'MR')       $modo = 'propuesta_mr';
    elseif ($accion === 'asignar'   && $quien === 'CLIENTE') $modo = 'asignado_cliente';
    elseif ($accion === 'asignar'   && $quien === 'MR')      $modo = 'asignado_mr';
    else {
      echo json_encode(['success' => false, 'error' => 'Acción no soportada']);
      exit;
    }

    // Estado del meet (simple por ahora)
    $estado = 'pendiente';

    $sql = "UPDATE ticket_soporte
                SET tiMeetFecha       = ?,
                    tiMeetHora        = ?,
                    tiMeetPlataforma  = ?,
                    tiMeetEnlace      = ?,
                    tiMeetModo        = ?,
                    tiMeetEstado      = ?,
                    tiMeetAutorNombre = ?
                WHERE tiId = ?";

    $stmt = $conectar->prepare($sql);
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conectar->error);
    }

    $stmt->bind_param(
      "sssssssi",
      $fecha,
      $hora,
      $plat,
      $link,
      $modo,
      $estado,
      $autor,
      $tiId
    );
    $stmt->execute();
    $stmt->close();

    $estadoFinal = $estado;
    $modoFinal   = $modo;
    $fechaFinal  = $fecha;
    $horaFinal   = $hora;
    $platFinal   = $plat;
    $linkFinal   = $link;
  }

  /* =======================================================
     *  ENVÍO DE CORREO DE NOTIFICACIÓN (best-effort)
     * ======================================================= */
  try {
    // 1) Usuario logueado (destinatario principal)
    $qU = $conectar->prepare("SELECT usNombre, usCorreo, usNotifMail, usNotifTicketCambio FROM usuarios WHERE usId=? LIMIT 1");
    $qU->bind_param("i", $usId);
    $qU->execute();
    $resU = $qU->get_result()->fetch_assoc();
    $qU->close();
    if ($resU['usNotifMail'] == 1 && $resU['usNotifTicketCambio'] == 1) {
      if ($resU && filter_var($resU['usCorreo'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $destCorreo = $resU['usCorreo'];
        $destNombre = $resU['usNombre'] ?: 'Usuario';

        // 2) Info básica del ticket
        $tiDescripcion    = $ticket['tiDescripcion'] ?? 'Sin descripción';
        $tiContactoNombre = $ticket['tiNombreContacto'] ?? '';
        $tiContactoCorreo = $ticket['tiCorreoContacto'] ?? '';

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
        $logFile  = $logDir . '/mail_meet.log';
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
        if ($accion === 'cancelar') {
          $mail->Subject = "Reunión cancelada para {$codigoTicket}";
          $preheader     = "Se ha cancelado la reunión asociada al ticket {$codigoTicket}.";
        } elseif ($accion === 'proponer') {
          $mail->Subject = "Propuesta de reunión para {$codigoTicket}";
          $preheader     = "Se ha registrado una propuesta de reunión para el ticket {$codigoTicket}.";
        } else { // asignar
          $mail->Subject = "Reunión asignada para {$codigoTicket}";
          $preheader     = "Se ha registrado una reunión para el ticket {$codigoTicket}.";
        }

        // Paleta
        $primary = '#4F46E5';
        $text    = '#111827';
        $muted   = '#6B7280';
        $bg      = '#F3F4F6';
        $card    = '#FFFFFF';
        $border  = '#E5E7EB';

        $descHtml  = htmlspecialchars($tiDescripcion, ENT_QUOTES, 'UTF-8');
        $fechaHtml = $fechaFinal ?: '—';
        $horaHtml  = $horaFinal  ?: '—';
        $platHtml  = $platFinal  ?: '—';
        $linkHtml  = $linkFinal  ?: '—';

        $estadoLegible = ucfirst(str_replace('_', ' ', $estadoFinal));
        $modoLegible   = ucfirst(str_replace('_', ' ', $modoFinal));

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
              Actualización de reunión del ticket {$codigoTicket}
            </h1>
            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:{$text};">
              Se ha registrado un cambio en la reunión asociada a tu ticket:
            </p>
            <ul style="margin:0 0 16px 18px;padding:0;font-size:14px;line-height:1.6;color:{$text};">
              <li><strong>Ticket:</strong> {$codigoTicket}</li>
              <li><strong>Estado del meet:</strong> {$estadoLegible}</li>
              <li><strong>Modo:</strong> {$modoLegible}</li>
              <li><strong>Fecha:</strong> {$fechaHtml}</li>
              <li><strong>Hora:</strong> {$horaHtml}</li>
              <li><strong>Plataforma:</strong> {$platHtml}</li>
              <li><strong>Link:</strong> {$linkHtml}</li>
            </ul>
            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:{$text};">
              <strong>Descripción del ticket:</strong><br>
              {$descHtml}
            </p>
HTML;

        if ($accion === 'cancelar' && $motivo !== '') {
          $motivoHtml = nl2br(htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8'));
          $mail->Body .= <<<HTML
            <p style="margin:12px 0 0 0;font-size:13px;line-height:1.6;color:{$text};">
              <strong>Motivo de cancelación:</strong><br>
              {$motivoHtml}
            </p>
HTML;
        }

        $mail->Body .= <<<HTML
            <hr style="border:none;border-top:1px solid {$border};margin:24px 0 12px 0;">
            <p style="margin:0;font-size:12px;line-height:1.6;color:{$muted};">
              Si tienes dudas sobre la reunión, por favor responde a este correo
              o comunícate con la Mesa de Ayuda de {$NOMBRE_EMPRESA}.
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
          "Actualización de reunión - {$NOMBRE_EMPRESA}\n\n" .
          "Ticket: {$codigoTicket}\n" .
          "Estado meet: {$estadoLegible}\n" .
          "Modo: {$modoLegible}\n" .
          "Fecha: {$fechaHtml}\n" .
          "Hora: {$horaHtml}\n" .
          "Plataforma: {$platHtml}\n" .
          "Link: {$linkHtml}\n\n" .
          "Descripción del ticket:\n{$tiDescripcion}\n\n";

        if ($accion === 'cancelar' && $motivo !== '') {
          $mail->AltBody .= "Motivo de cancelación:\n{$motivo}\n\n";
        }

        if ($DRY_RUN === false) {
          $mail->send();
          $line = "[" . date('Y-m-d H:i:s') . "] tiId={$tiId}, accion={$accion}, dest={$destCorreo}\nSMTP:\n" . $smtpLog . "\n\n";
          @file_put_contents($logFile, $line, FILE_APPEND);
        }
      }
    }
  } catch (\Throwable $eMail) {
    @error_log("[MRSoS mail meet] tiId={$tiId} error=" . $eMail->getMessage());
  }

  // ====== RESPUESTA JSON ======
  echo json_encode([
    'success' => true,
    'meet' => [
      'tiMeetFecha'       => $fechaFinal,
      'tiMeetHora'        => $horaFinal,
      'tiMeetPlataforma'  => $platFinal,
      'tiMeetEnlace'      => $linkFinal,
      'tiMeetModo'        => $modoFinal,
      'tiMeetEstado'      => $estadoFinal,
      'tiMeetAutorNombre' => $autor,
    ]
  ]);
  exit;
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'error' => 'Error interno']);
  exit;
}
