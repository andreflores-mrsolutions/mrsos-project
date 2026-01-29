<?php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once 'conexion.php';
session_start();

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido');
  }

  $tiId   = isset($_POST['tiId']) ? (int)$_POST['tiId'] : 0;
  $accion = $_POST['accion'] ?? '';
  $quien  = $_POST['quien']  ?? 'CLIENTE';
  $motivo = trim($_POST['motivo'] ?? '');

  $fecha  = $_POST['fecha']  ?? '';
  $hora   = $_POST['hora']   ?? '';
  $durMin = isset($_POST['duracionMin']) ? (int)$_POST['duracionMin'] : null;

  $reqAcceso   = isset($_POST['requiereAcceso']) ? (int)$_POST['requiereAcceso'] : 0;
  $extraAcceso = trim($_POST['extraAcceso'] ?? '');

  if ($tiId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
  }

  // ¿Quién realiza la acción? (cliente / MR)
  $origen = 'cliente';
  if (!empty($_SESSION['usRol']) && in_array($_SESSION['usRol'], ['MRA', 'MR'], true)) {
    $origen = 'mr';
  }

  $autorId  = $_SESSION['usId']      ?? null;
  $autorNom = $_SESSION['usNombre']  ?? ($_SESSION['usUsuario'] ?? null);

  // -------------------------------------------------
  // 1) Acciones especiales (cancelar)
  // -------------------------------------------------
  if ($accion === 'cancelar') {
    $sql = "UPDATE ticket_soporte
                SET tiVisitaEstado       = 'cancelado',
                    tiVisitaCancelBy     = ?,
                    tiVisitaCancelMotivo = ?,
                    tiVisitaCancelFecha  = NOW()
                WHERE tiId = ?";
    $st = $conectar->prepare($sql);
    if (!$st) {
      throw new Exception("Error prepare cancelar visita: " . $conectar->error);
    }
    $st->bind_param('ssi', $origen, $motivo, $tiId);
    $st->execute();

    if ($st->affected_rows < 0) {
      throw new Exception('No se pudo actualizar el ticket (cancelar visita).');
    }

    // --- Enviar notificación de cancelación de visita ---
    notificarCambioVisita($tiId, 'cancelar', $origen, $fecha, $hora, 0, $reqAcceso, $extraAcceso, $motivo);

    echo json_encode(['success' => true]);
    exit;
  }

  // -------------------------------------------------
  // 2) Resto de acciones (proponer / asignar)
  // -------------------------------------------------
  $modo   = '';
  $estado = '';
  $conf   = 0;

  if ($accion === 'proponer') {
    if ($quien === 'CLIENTE') {
      $modo   = 'propuesta_cliente';
      $estado = 'pendiente_mr';
    } else {
      $modo   = 'propuesta_ingeniero';
      $estado = 'pendiente_cliente';
    }
    $conf = 0;
  } elseif ($accion === 'asignar') {
    if ($quien === 'CLIENTE') {
      if ($extraAcceso == true) {
        $modo = 'datos_extra';
      } else {
        $modo = 'requiere_folio';
      }
    } else {
      if ($extraAcceso == true) {
        $modo = 'datos_extra';
      } else {
        $modo = 'requiere_folio';
      }
    }
    $estado = 'requiere_folio';
    $conf   = 1;
  } else {
    throw new Exception('Acción no soportada');
  }

  $sql = "UPDATE ticket_soporte
            SET tiVisitaFecha        = ?,
                tiVisitaHora         = ?,
                tiVisitaModo         = ?,
                tiVisitaEstado       = ?,
                tiVisitaConfirmada   = ?,
                tiVisitaDuracionMins = ?,
                tiVisitaAutorId      = ?,
                tiVisitaAutorNombre  = ?,
                tiAccesoRequiereDatos= ?,
                tiAccesoExtraTexto   = ?
            WHERE tiId = ?";

  $st = $conectar->prepare($sql);
  if (!$st) {
    throw new Exception('Prepare error: ' . $conectar->error);
  }

  $dur = $durMin > 0 ? $durMin : null;

  $st->bind_param(
    'ssssiiisisi',
    $fecha,
    $hora,
    $modo,
    $estado,
    $conf,
    $dur,
    $autorId,
    $autorNom,
    $reqAcceso,
    $extraAcceso,
    $tiId
  );
  $st->execute();

  if ($st->affected_rows < 0) {
    throw new Exception('No se pudo actualizar el ticket.');
  }

  // --- Enviar notificación de propuesta/asignación de visita ---
  notificarCambioVisita($tiId, $accion, $origen, $fecha, $hora, $dur, $reqAcceso, $extraAcceso, $motivo);

  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  echo json_encode([
    'success' => false,
    'error'   => $e->getMessage()
  ]);
}

/**
 * Envía un correo notificando cambios en la visita del ticket.
 *
 * $accion: 'proponer' | 'asignar' | 'cancelar'
 * $origen: 'cliente' | 'mr'
 */
function notificarCambioVisita(
  int $tiId,
  string $accion,
  string $origen,
  ?string $fecha,
  ?string $hora,
  ?int $duracionMin,
  int $reqAcceso,
  string $extraAcceso,
  string $motivoCancel = ''
): void {
  // Si quieres poder desactivar notificaciones rápido:
  $ENVIAR_CORREO = true;
  if (!$ENVIAR_CORREO) return;

  // Si no hay sesión o correo del usuario, no enviamos nada
  $correoUsuario = $_SESSION['usCorreo'] ?? null;
  if (!$correoUsuario || !filter_var($correoUsuario, FILTER_VALIDATE_EMAIL)) {
    return;
  }

  // ========= Branding / textos base =========
  $NOMBRE_EMPRESA    = 'MR Solutions';
  $URL_LOGO          = 'https://www.ventasdeseguridad.com/media/com_mtree/images/listings/o/2018_03_16_19_03_54_Logo.jpg';
  $DIRECCION_EMPRESA = 'Alhambra 813 Bis, Portales Sur, Benito Juárez, 03300 Ciudad de México, CDMX';

  // Pequeña descripción de la acción
  $accionTxt = '';
  if ($accion === 'proponer') {
    $accionTxt = 'Se ha propuesto una nueva ventana/visita.';
  } elseif ($accion === 'asignar') {
    $accionTxt = 'Se ha asignado una ventana/visita.';
  } elseif ($accion === 'cancelar') {
    $accionTxt = 'Se ha cancelado la visita programada.';
  }

  $origenTxt = ($origen === 'mr') ? 'Soporte MR Solutions' : 'el cliente';

  $fechaTxt = $fecha ?: '—';
  $horaTxt  = $hora  ?: '—';
  $durTxt   = ($duracionMin && $duracionMin > 0)
    ? $duracionMin . ' minuto(s) aprox.'
    : 'No especificado';

  $accesoTxt = $reqAcceso ? 'Sí, se requieren datos para el acceso.' : 'No se requieren datos adicionales de acceso.';
  if ($extraAcceso) {
    $accesoTxt .= " Detalle extra: {$extraAcceso}";
  }

  $motivoTxt = '';
  if ($accion === 'cancelar' && $motivoCancel) {
    $motivoTxt = "Motivo de cancelación: {$motivoCancel}";
  }

  // ======= PHPMailer (como en el reset, pero adaptado) =======
  require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/PHPMailer.php';
  require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/SMTP.php';
  require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/Exception.php';

  $mail = new PHPMailer\PHPMailer\PHPMailer(true);

  // === Logs ===
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
  }
  $logFile  = $logDir . '/mail_visita.log';
  $smtpLog  = '';
  $debugOut = function ($str) use (&$smtpLog) {
    $smtpLog .= $str . "\n";
  };

  // Para pruebas: si lo pones a true, no envía realmente
  $DRY_RUN = false;

  try {
    // SMTP (igual que en reset)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'lizma0116@gmail.com';
    $mail->Password   = 'acxh hduf vpqd xgnn';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->SMTPDebug   = 0;
    $mail->Debugoutput = $debugOut;

    // Destinatarios
    $mail->setFrom('lizma0116@gmail.com', $NOMBRE_EMPRESA . ' Notificaciones');
    $mail->addReplyTo('lizma0116@gmail.com', 'Mesa de Ayuda');
    $mail->addAddress($correoUsuario);             // usuario que hizo la acción
    // Si quieres copiar siempre a soporte:
    // $mail->addCC('soporte@mrsolutions.com.mx', 'Soporte MR Solutions');

    // Asunto + preheader
    $mail->Subject = "Actualización de visita – Ticket #{$tiId}";
    $preheader = "{$accionTxt} (Ticket #{$tiId})";

    // Paleta (mismas que reset)
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
              Actualización de visita del ticket #{$tiId}
            </h1>
            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:{$text};">
              {$accionTxt}
            </p>
            <p style="margin:0 0 16px 0;font-size:13px;line-height:1.6;color:{$text};">
              Acción realizada por: <strong>{$origenTxt}</strong>.
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:13px;color:{$text};margin-bottom:16px;">
              <tr>
                <td style="padding:4px 0;"><strong>Fecha de visita:</strong></td>
                <td style="padding:4px 0;">{$fechaTxt}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;"><strong>Hora de visita:</strong></td>
                <td style="padding:4px 0;">{$horaTxt}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;"><strong>Duración estimada:</strong></td>
                <td style="padding:4px 0;">{$durTxt}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;"><strong>Acceso al sitio:</strong></td>
                <td style="padding:4px 0;">{$accesoTxt}</td>
              </tr>
            </table>

HTML;

    if ($motivoTxt !== '') {
      $mail->Body .= <<<HTML
            <p style="margin:0 0 16px 0;font-size:13px;line-height:1.6;color:{$text};">
              {$motivoTxt}
            </p>
HTML;
    }

    $mail->Body .= <<<HTML
            <hr style="border:none;border-top:1px solid {$border};margin:16px 0;">
            <p style="margin:0;font-size:12px;line-height:1.6;color:{$muted};">
              Si tienes dudas sobre esta visita o necesitas reprogramarla, por favor responde a este correo o contacta a la mesa de ayuda.
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
      "Actualización de visita – Ticket #{$tiId}\n\n" .
      "{$accionTxt}\n" .
      "Acción realizada por: {$origenTxt}\n\n" .
      "Fecha: {$fechaTxt}\n" .
      "Hora: {$horaTxt}\n" .
      "Duración estimada: {$durTxt}\n" .
      "Acceso al sitio: {$accesoTxt}\n\n" .
      ($motivoTxt ? $motivoTxt . "\n\n" : "") .
      "Si necesitas reprogramar o cancelar, contacta a la mesa de ayuda.\n";

    if ($DRY_RUN === true) {
      // Solo para pruebas
      return;
    }

    $mail->send();
  } catch (Throwable $ex) {
    // Log, pero NO rompemos el flujo principal
    $line = "[" . date('Y-m-d H:i:s') . "] tiId={$tiId} ex=" . $ex->getMessage() . "\nSMTP:\n" . $smtpLog . "\n\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
    error_log("[MRSoS mail visita] " . $line);
  }
}
