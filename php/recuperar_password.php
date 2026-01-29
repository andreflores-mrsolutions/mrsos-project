<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
  // ======= 1) Conexión =======
  $DB_HOST = 'localhost';
  $DB_NAME = 'u140302554_mrsos';
  $DB_USER = 'u140302554_mrsos';
  $DB_PASS = 'MRsolutions552312#$';
  $DB_CHARSET = 'utf8mb4';
  $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // ======= 2) Entrada =======
  $usId     = isset($_POST['usId']) ? (int)$_POST['usId'] : 0;
  $usCorreo = isset($_POST['usCorreo']) ? trim((string)$_POST['usCorreo']) : '';

  if ($usId <= 0 || !filter_var($usCorreo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']); exit;
  }

  // ======= 3) Usuario válido =======
  $stmt = $pdo->prepare("
    SELECT usId, usCorreo, usEstatus
    FROM usuarios
    WHERE usId = ? AND usCorreo = ?
    LIMIT 1
  ");
  $stmt->execute([$usId, $usCorreo]);
  $user = $stmt->fetch();

  if (!$user || !in_array($user['usEstatus'], ['Activo','NewPass'], true)) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado o inactivo']); exit;
  }

  // ======= 4) Generar token BIGINT (18 dígitos) + expiración =======
  $tokenLen = 18; // seguro para BIGINT
  $token = '';
  for ($i = 0; $i < $tokenLen; $i++) { $token .= random_int(0, 9); }

  $expira = (new DateTime('now'))->add(new DateInterval('PT30M'))->format('Y-m-d H:i:s'); // +30 min

  $upd = $pdo->prepare("UPDATE usuarios SET usResetToken = ?, usResetTokenExpira = ? WHERE usId = ?");
  $upd->execute([$token, $expira, $usId]);

  // ======= 5) Branding / Config general del mail =======
  $NOMBRE_EMPRESA    = 'MR Solutions';
  // En dev: localhost; en prod: https://tu-dominio.com (sin slash final)
  $DOMINIO           = 'http://localhost';
  $RUTA_RESET        = '/login/recuperar_password.php'; // archivo que valida token y permite cambiar pass
  $resetLink         = "{$DOMINIO}{$RUTA_RESET}?token={$token}&usId={$usId}";

  // Logo (idealmente URL pública https)
  $URL_LOGO          = 'https://www.ventasdeseguridad.com/media/com_mtree/images/listings/o/2018_03_16_19_03_54_Logo.jpg';
  $DIRECCION_EMPRESA = 'Alhambra 813 Bis, Portales Sur, Benito Juárez, 03300 Ciudad de México, CDMX';

  // ======= 6) PHPMailer =======
  require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/PHPMailer.php';
  require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/SMTP.php';
  require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/Exception.php';

  $mail = new PHPMailer\PHPMailer\PHPMailer(true);

  // === Logs ===
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
  $logFile  = $logDir . '/mail_reset.log';
  $smtpLog  = '';
  $debugOut = function ($str) use (&$smtpLog) { $smtpLog .= $str . "\n"; };

  // Bandera de prueba sin envío real (deja en false en producción)
  $DRY_RUN = false;

  try {
    // === SMTP (Ajusta si cambias proveedor) ===
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'lizma0116@gmail.com';     // Cuenta remitente (mismo dominio recomendado)
    $mail->Password   = 'acxh hduf vpqd xgnn';     // App Password (Gmail)
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Debug (0 en prod)
    $mail->SMTPDebug   = 0;
    $mail->Debugoutput = $debugOut;

    // From / Reply-To / To
    $mail->setFrom('lizma0116@gmail.com', $NOMBRE_EMPRESA.' Notificaciones');
    $mail->addReplyTo('lizma0116@gmail.com', 'Mesa de Ayuda'); // cambia a soporte@tu-dominio.com en prod
    $mail->addAddress($usCorreo);

    // Asunto / Preheader
    $mail->Subject  = 'Restablecimiento de contraseña';
    $preheader      = 'Usa este enlace para cambiar tu contraseña. El enlace vence en 30 minutos.';

    // Paleta
    $primary    = '#4F46E5'; // índigo
    $primaryHov = '#4338CA';
    $text       = '#111827';
    $muted      = '#6B7280';
    $bg         = '#F3F4F6';
    $card       = '#FFFFFF';
    $border     = '#E5E7EB';

    // HTML (600px, tablas e inline CSS)
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
            <h1 style="margin:0 0 8px 0;font-size:20px;line-height:1.3;color:{$text};">Restablece tu contraseña</h1>
            <p style="margin:0 0 16px 0;font-size:14px;line-height:1.6;color:{$text};">
              Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en <strong>{$NOMBRE_EMPRESA}</strong>.
            </p>
            <p style="margin:0 0 24px 0;font-size:14px;line-height:1.6;color:{$text};">
              Haz clic en el siguiente botón para continuar. Este enlace es válido por <strong>30 minutos</strong>.
            </p>
            <table role="presentation" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center" bgcolor="{$primary}" style="border-radius:10px;">
                  <a href="{$resetLink}" target="_blank"
                     style="display:inline-block;padding:12px 20px;color:#ffffff;background:{$primary};border-radius:10px;font-size:14px;font-weight:bold;">
                    Cambiar mi contraseña
                  </a>
                </td>
              </tr>
            </table>
            <p style="margin:24px 0 0 0;font-size:12px;line-height:1.6;color:{$muted};">
              Si el botón no funciona, copia y pega este enlace en tu navegador:
              <br>
              <a href="{$resetLink}" target="_blank" style="color:{$primary};word-break:break-all;">{$resetLink}</a>
            </p>
            <hr style="border:none;border-top:1px solid {$border};margin:24px 0;">
            <p style="margin:0;font-size:12px;line-height:1.6;color:{$muted};">
              Si no solicitaste este cambio, puedes ignorar este mensaje. Tu contraseña actual seguirá siendo válida.
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
      "Restablecimiento de contraseña - {$NOMBRE_EMPRESA}\n\n".
      "Usa este enlace para cambiar tu contraseña (vence en 30 minutos):\n".
      "{$resetLink}\n\n".
      "Si no solicitaste este cambio, ignora este correo.\n";

    // Dry-run para probar lógica sin enviar
    if ($DRY_RUN === true) {
      echo json_encode(['success' => true, 'note' => 'dry-run, sin enviar correo']); exit;
    }

    // Enviar
    $mail->send();
    echo json_encode(['success' => true]); exit;

  } catch (Throwable $ex) {
    // Si falla el envío, puedes decidir si invalidas token o lo dejas para reintento
    $pdo->prepare("UPDATE usuarios SET usResetToken = 0, usResetTokenExpira = '1970-01-01 00:00:00' WHERE usId = ?")->execute([$usId]);

    $line = "[".date('Y-m-d H:i:s')."] usId={$usId} ex=".$ex->getMessage()."\nSMTP:\n".$smtpLog."\n\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
    error_log("[MRSoS mail reset] ".$line);

    echo json_encode([
      'success' => false,
      'error'   => 'No fue posible enviar el correo',
      'code'    => 'smtp_send_failed'
      // En dev, si quieres: 'detail' => $ex->getMessage()
    ]);
    exit;
  }

} catch (Throwable $e) {
  echo json_encode(['success' => false, 'error' => 'Error interno']); exit;
}
