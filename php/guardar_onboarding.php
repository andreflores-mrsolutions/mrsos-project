<?php
session_start();
require __DIR__ . '/conexion.php';

if (empty($_SESSION['usId']) || empty($_SESSION['forzarCambioPass'])) {
    header('Location: ../login/login.php');
    exit;
}

$usId       = (int)($_POST['usId'] ?? 0);
$pass1      = $_POST['pass1'] ?? '';
$pass2      = $_POST['pass2'] ?? '';
$usNombre   = trim($_POST['usNombre'] ?? '');
$usAPaterno = trim($_POST['usAPaterno'] ?? '');
$usAMaterno = trim($_POST['usAMaterno'] ?? '');
$usCorreo   = trim($_POST['usCorreo'] ?? '');
$usTelefono = trim($_POST['usTelefono'] ?? '');
$usUsername = trim($_POST['usUsername'] ?? '');

if ($usId !== (int)$_SESSION['usId']) {
    die('Sesión no válida.');
}

/* === Validaciones básicas === */
if ($pass1 === '' || $pass2 === '' || $pass1 !== $pass2) {
    die('Las contraseñas no coinciden o están vacías.');
}
if (
    strlen($pass1) < 8 ||
    !preg_match('/[A-Z]/', $pass1) ||
    !preg_match('/[a-z]/', $pass1) ||
    !preg_match('/[0-9]/', $pass1) ||
    !preg_match('/[!@#$%^&*()_\-+={}[\]:;"\'<>,.?\/~`\\\\|]/', $pass1)
) {
    die('La contraseña no cumple con los requisitos mínimos.');
}
if ($usNombre === '' || $usAPaterno === '' || $usCorreo === '') {
    die('Nombre, apellido y correo son obligatorios.');
}
if (!filter_var($usCorreo, FILTER_VALIDATE_EMAIL)) {
    die('Correo no válido.');
}
if ($usUsername === '') {
    die('Nombre de usuario requerido.');
}
if (!preg_match('/^[A-Za-z0-9_-]{3,20}$/', $usUsername)) {
    die('Nombre de usuario con formato inválido.');
}
$malas = ['puta', 'puto', 'mierda', 'pendejo', 'pendeja', 'idiota'];
$lower = mb_strtolower($usUsername, 'UTF-8');
foreach ($malas as $m) {
    if (str_contains($lower, $m)) {
        die('Nombre de usuario no permitido.');
    }
}

/* === Verificar que el username sea único === */
$stmt = $conectar->prepare("SELECT usId FROM usuarios WHERE usUsername = ? AND usId <> ?");
$stmt->bind_param("si", $usUsername, $usId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    die('El nombre de usuario ya está en uso.');
}

/* === Manejo de avatar (opcional) === */
$avatarFilename = null;
if (!empty($_FILES['usAvatar']['name'])) {
    $file     = $_FILES['usAvatar'];
    $tmpName  = $file['tmp_name'];
    $size     = $file['size'];
    $error    = $file['error'];
    $name     = $file['name'];

    if ($error === UPLOAD_ERR_OK && $tmpName) {
        if ($size > 2 * 1024 * 1024) { // 2MB
            die('La imagen de perfil no debe superar los 2MB.');
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            die('Formato de imagen no permitido. Usa JPG, PNG o WEBP.');
        }

        // Guardar con el username final
        $uploadDir = __DIR__ . '/../img/Usuario/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $finalName = $usUsername . '.' . $ext;
        $destPath  = $uploadDir . $finalName;

        if (!move_uploaded_file($tmpName, $destPath)) {
            die('No se pudo guardar la imagen de perfil.');
        }

        $avatarFilename = $finalName;
    }
}

/* === Actualizar usuario === */
$hash = password_hash($pass1, PASSWORD_DEFAULT);

if ($avatarFilename) {
    $stmt = $conectar->prepare("
        UPDATE usuarios
        SET usPass = ?,
            usNombre = ?,
            usAPaterno = ?,
            usAMaterno = ?,
            usCorreo = ?,
            usTelefono = ?,
            usUsername = ?,
            usImagen = ?,
            usEstatus = 'Activo'
        WHERE usId = ?
    ");
    $stmt->bind_param(
        "ssssssssi",
        $hash,
        $usNombre,
        $usAPaterno,
        $usAMaterno,
        $usCorreo,
        $usTelefono,
        $usUsername,
        $avatarFilename,
        $usId
    );
} else {
    $stmt = $conectar->prepare("
        UPDATE usuarios
        SET usPass = ?,
            usNombre = ?,
            usAPaterno = ?,
            usAMaterno = ?,
            usCorreo = ?,
            usTelefono = ?,
            usUsername = ?,
            usEstatus = 'Activo'
        WHERE usId = ?
    ");
    $stmt->bind_param(
        "sssssssi",
        $hash,
        $usNombre,
        $usAPaterno,
        $usAMaterno,
        $usCorreo,
        $usTelefono,
        $usUsername,
        $usId
    );
}

if (!$stmt->execute()) {
    die('Error al guardar los cambios.');
}

/* Actualizar sesión básica */
$_SESSION['usNombre']   = $usNombre;
$_SESSION['usAPaterno'] = $usAPaterno;
$_SESSION['usAMaterno'] = $usAMaterno;
$_SESSION['usCorreo']   = $usCorreo;
$_SESSION['usTelefono'] = $usTelefono;
$_SESSION['usUsername'] = $usUsername;
$_SESSION['usEstatus']  = 'Activo';
if ($avatarFilename) {
    $_SESSION['usImagen'] = $avatarFilename;
}

unset($_SESSION['forzarCambioPass']);

/* === Enviar correo de bienvenida === */
/* === Enviar correo de bienvenida con PHPMailer === */

// Branding / datos base
$NOMBRE_EMPRESA    = 'MR Solutions';
$URL_LOGO          = 'https://mrsolutions.com.mx/wp-content/uploads/2025/02/logo_2021.webp';
$DIRECCION_EMPRESA = 'Alhambra 813 Bis, Portales Sur, Benito Juárez, 03300 Ciudad de México, CDMX';

// 1) Includes de PHPMailer
require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/Exception.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

// 2) Logs opcionales
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile  = $logDir . '/mail_bienvenida.log';
$smtpLog  = '';
$debugOut = function ($str) use (&$smtpLog) {
    $smtpLog .= $str . "\n";
};

// Si quieres probar sin enviar de verdad, pon true
$DRY_RUN = false;

try {
    // 3) Config SMTP (igual que en el reset)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'lizma0116@gmail.com';       // remitente
    $mail->Password   = 'acxh hduf vpqd xgnn';       // App Password
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Debug: 0 en producción
    $mail->SMTPDebug   = 0;
    $mail->Debugoutput = $debugOut;

    // 4) From / To
    $mail->setFrom('lizma0116@gmail.com', $NOMBRE_EMPRESA . ' Notificaciones');
    $mail->addReplyTo('lizma0116@gmail.com', 'Mesa de Ayuda');
    $mail->addAddress($usCorreo, $usNombre);

    // 5) Asunto + preheader
    $mail->Subject = 'Bienvenido a MR SoS';
    $preheader     = 'Tu cuenta ha sido configurada. Ya puedes levantar tickets y gestionar tus equipos desde MR SoS.';

    // Paleta
    $primary    = '#4F46E5';
    $primaryHov = '#4338CA';
    $text       = '#111827';
    $muted      = '#6B7280';
    $bg         = '#F3F4F6';
    $card       = '#FFFFFF';
    $border     = '#E5E7EB';

    // 6) Cuerpo HTML
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
        <!-- Header -->
        <tr>
          <td style="padding:20px 24px;border-bottom:1px solid {$border};">
            <table width="100%">
              <tr>
                <td align="left">
                  <img src="{$URL_LOGO}" alt="{$NOMBRE_EMPRESA}" height="36" style="display:block;border:0;outline:none;text-decoration:none;height:36px;">
                </td>
                <td align="right" style="font-size:12px;color:{$muted};">Bienvenida a la plataforma</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Contenido principal -->
        <tr>
          <td style="padding:24px;">
            <h1 style="margin:0 0 8px 0;font-size:20px;line-height:1.3;color:{$text};">
              Hola {$usNombre}, ¡Bienvenido a MRSOS!
            </h1>
            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:{$text};">
              Tu cuenta ha sido configurada correctamente. A partir de ahora podrás:
            </p>
            <ul style="margin:0 0 16px 20px;padding:0;font-size:14px;line-height:1.6;color:{$text};">
              <li>Levantar tickets de soporte para tus equipos en póliza.</li>
              <li>Consultar el estado y la historia de tus casos.</li>
              <li>Agendar reuniones y coordinar acciones con nuestro equipo.</li>
            </ul>

            <p style="margin:0 0 20px 0;font-size:14px;line-height:1.6;color:{$text};">
              Te invitamos a comenzar explorando tu panel principal:
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
              <tr>
                <td align="center" bgcolor="{$primary}" style="border-radius:10px;">
                  <a href="https://mrsolutions.com.mx" target="_blank"
                     style="display:inline-block;padding:12px 20px;color:#ffffff;background:{$primary};border-radius:10px;font-size:14px;font-weight:bold;">
                    Ir a MR SoS
                  </a>
                </td>
              </tr>
            </table>

            <p style="margin:0;font-size:12px;line-height:1.6;color:{$muted};">
              Si no reconoces esta creación de cuenta, por favor contacta a nuestro equipo de soporte.
            </p>
          </td>
        </tr>

        <!-- Footer -->
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
        "Bienvenido a MR SoS - {$NOMBRE_EMPRESA}\n\n" .
        "Hola {$usNombre},\n\n" .
        "Tu cuenta ha sido configurada correctamente. Ya puedes levantar tickets, revisar el estado de tus equipos\n" .
        "y coordinar con nuestro equipo de soporte desde la plataforma.\n\n" .
        "Gracias por confiar en MR Solutions.\n";

    if ($DRY_RUN === false) {
        $mail->send();
    }
} catch (Throwable $ex) {
    // No rompemos el flujo del usuario si el correo falla, solo lo registramos
    $line = "[" . date('Y-m-d H:i:s') . "] usId={$usId} ex=" . $ex->getMessage() . "\nSMTP:\n" . $smtpLog . "\n\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
    error_log("[MRSoS mail bienvenida] " . $line);
}


/* Redirigir a la plataforma */

$_SESSION = [];

// Borrar cookie de sesión si existe
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

// ¿AJAX?
$isAjax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// Si dejaste cookies “remember me”, bórralas aquí también.
// setcookie('remember_me', '', time() - 3600, '/');

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Navegación normal
header('Location: ../login/login.php');

exit;
