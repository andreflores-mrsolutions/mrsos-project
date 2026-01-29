<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// RUTAS IGUALES A LAS QUE YA USAS EN EL RESET
require_once __DIR__ . '/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/Exception.php';

/**
 * Enviar notificación genérica de MRSoS.
 *
 * @return array ['success' => bool, 'error' => string|null]
 */
function mrs_mail_notificacion(
    string $toEmail,
    string $toName,
    string $subject,
    string $preheader,
    string $innerHtml,
    string $altText = ''
): array {
    $NOMBRE_EMPRESA    = 'MR Solutions';
    $DOMINIO           = 'http://localhost'; // o tu dominio en prod
    $URL_LOGO          = 'https://www.ventasdeseguridad.com/media/com_mtree/images/listings/o/2018_03_16_19_03_54_Logo.jpg';
    $DIRECCION_EMPRESA = 'Alhambra 813 Bis, Portales Sur, Benito Juárez, 03300 Ciudad de México, CDMX';

    // SMTP – usa lo mismo que ya tienes
    $SMTP_HOST = 'smtp.gmail.com';
    $SMTP_USER = 'lizma0116@gmail.com';
    $SMTP_PASS = 'acxh hduf vpqd xgnn'; // tu app password
    $SMTP_PORT = 587;

    $FROM_EMAIL = $SMTP_USER;
    $FROM_NAME  = $NOMBRE_EMPRESA . ' Notificaciones';

    // Paleta
    $primary    = '#4F46E5';
    $text       = '#111827';
    $muted      = '#6B7280';
    $bg         = '#F3F4F6';
    $card       = '#FFFFFF';
    $border     = '#E5E7EB';

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logFile  = $logDir . '/mail_notificaciones.log';
    $smtpLog  = '';
    $debugOut = function ($str) use (&$smtpLog) { $smtpLog .= $str . "\n"; };

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER;
        $mail->Password   = $SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $SMTP_PORT;

        $mail->SMTPDebug   = 0; // 0 en prod
        $mail->Debugoutput = $debugOut;

        $mail->setFrom($FROM_EMAIL, $FROM_NAME);
        $mail->addReplyTo($FROM_EMAIL, 'Mesa de Ayuda');
        if ($toName !== '') {
            $mail->addAddress($toEmail, $toName);
        } else {
            $mail->addAddress($toEmail);
        }

        $mail->Subject = $subject;
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
            {$innerHtml}
            <hr style="border:none;border-top:1px solid {$border};margin:24px 0;">
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

        if ($altText === '') {
            $altText = strip_tags(str_replace('<br>', "\n", $innerHtml));
        }
        $mail->AltBody = $altText;

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (\Throwable $e) {
        $line = "[" . date('Y-m-d H:i:s') . "] to={$toEmail} subject=\"{$subject}\" ex=" . $e->getMessage() . "\nSMTP:\n" . $smtpLog . "\n\n";
        @file_put_contents($logFile, $line, FILE_APPEND);
        error_log("[MRSoS mail notif] " . $line);

        return ['success' => false, 'error' => 'smtp_send_failed'];
    }
}
