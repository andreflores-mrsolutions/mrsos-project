<?php
session_start();
require __DIR__ . '/conexion.php';
$usId       = (int)($_SESSION['usId'] ?? 0);
$usNombre   = trim($_POST['usNombre'] ?? '');
$usAPaterno = trim($_POST['usAPaterno'] ?? '');
$usAMaterno = trim($_POST['usAMaterno'] ?? '');
$usCorreo   = trim($_POST['usCorreo'] ?? '');
$usTelefono = trim($_POST['usTelefono'] ?? '');
$usUsername = trim($_POST['usUsername'] ?? '');

if ($usId !== (int)$_SESSION['usId']) {
    echo json_encode(['error' => 'Sesión no válida.']);
    exit;
}

if ($usNombre === '' || $usAPaterno === '' || $usCorreo === '') {
    echo json_encode(['error' => 'Nombre, apellido y correo son obligatorios.']);
    exit;
}
if (!filter_var($usCorreo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Correo no válido.']);
    exit;
}
if ($usUsername === '') {
    echo json_encode(['error' => 'Nombre de usuario requerido.']);
    exit;
}
if (!preg_match('/^[A-Za-z0-9_-]{3,20}$/', $usUsername)) {
    echo json_encode(['error' => 'Nombre de usuario con formato inválido.']);
    exit;
}
$malas = ['puta', 'puto', 'mierda', 'pendejo', 'pendeja', 'idiota'];
$lower = mb_strtolower($usUsername, 'UTF-8');
foreach ($malas as $m) {
    if (str_contains($lower, $m)) {
        echo json_encode(['error' => 'Nombre de usuario no permitido.']);
        exit;
    }
}

/* === Verificar que el username sea único === */
$stmt = $conectar->prepare("SELECT usId FROM usuarios WHERE usUsername = ? AND usId <> ?");
$stmt->bind_param("si", $usUsername, $usId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    echo json_encode(['error' => 'El nombre de usuario ya está en uso.']);
    exit;
}

/* === Manejo de avatar (opcional) -> SIEMPRE a JPG === */
$avatarFilename = null;

if (!empty($_FILES['usAvatar']['name'])) {
    $file    = $_FILES['usAvatar'];
    $tmpName = $file['tmp_name'];
    $size    = $file['size'];
    $error   = $file['error'];

    if ($error === UPLOAD_ERR_OK && $tmpName) {

        // 2MB max
        if ($size > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'La imagen de perfil no debe superar los 2MB.']);
            exit;
        }

        // Validar MIME real (más seguro que extensión)
        $info = @getimagesize($tmpName);
        if (!$info || empty($info['mime'])) {
            echo json_encode(['success' => false, 'error' => 'Archivo de imagen inválido.']);
            exit;
        }

        $mime = strtolower($info['mime']);
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMime, true)) {
            echo json_encode(['success' => false, 'error' => 'Formato no permitido. Usa JPG, PNG o WEBP.']);
            exit;
        }

        // Crear recurso imagen según MIME
        switch ($mime) {
            case 'image/jpeg':
                $src = @imagecreatefromjpeg($tmpName);
                break;
            case 'image/png':
                $src = @imagecreatefrompng($tmpName);
                break;
            case 'image/webp':
                $src = @imagecreatefromwebp($tmpName);
                break;
            default:
                $src = false;
        }

        if (!$src) {
            echo json_encode(['success' => false, 'error' => 'No se pudo procesar la imagen.']);
            exit;
        }

        // Directorio destino
        $uploadDir = __DIR__ . '/../img/Usuario/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        // ✅ Siempre JPG
        $finalName = $usUsername . '.jpg';
        $destPath  = $uploadDir . $finalName;

        // Convertir: si trae transparencia (png/webp), ponemos fondo blanco
        $w = imagesx($src);
        $h = imagesy($src);

        $bg = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($bg, 255, 255, 255);
        imagefill($bg, 0, 0, $white);

        imagecopy($bg, $src, 0, 0, 0, 0, $w, $h);

        // Guardar JPG con calidad 85
        $ok = imagejpeg($bg, $destPath, 85);

        imagedestroy($src);
        imagedestroy($bg);

        if (!$ok) {
            echo json_encode(['success' => false, 'error' => 'No se pudo guardar la imagen de perfil.']);
            exit;
        }

        $avatarFilename = 1; // ej: AndreFC47.jpg
    }
}

/* === Actualizar usuario === */


if ($avatarFilename) {
    $stmt = $conectar->prepare("
        UPDATE usuarios
        SET 
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
        "sssssssi",
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
        SET 
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
        "ssssssi",
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
    echo json_encode(['error' => 'Error al guardar los cambios.']);
    exit;
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

// Navegación normal
echo json_encode(['success' => true]);

$stmt = $conectar->prepare("SELECT * FROM usuarios WHERE usId = ?");
$stmt->bind_param("s", $usId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Error al cargar datos del usuario.']);
    exit;
}
$user = $result->fetch_object();

if ($user->usNotifMail != '1') {
    // No enviamos correo si el usuario está en estado NewPass
    exit;
}
/* === Enviar correo de bienvenida === */
/* === Enviar correo de bienvenida con PHPMailer === */

// Branding / datos base
$NOMBRE_EMPRESA    = 'MR Solutions';
$URL_LOGO          = 'https://www.ventasdeseguridad.com/media/com_mtree/images/listings/o/2018_03_16_19_03_54_Logo.jpg';
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
    $mail->Subject = 'Actualización de datos de cuenta';
    $preheader     = 'Tu cuenta ha sido actualizada correctamente. Gracias por confiar en MR Solutions.';

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
        "Datos actualizados" .
        "Hola {$usNombre},\n\n" .
        "Tu cuenta ha sido actualizada correctamente.\n" .
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




exit;
function convertToJpg($tmpPath, $destPath, $quality = 85) {
    $info = getimagesize($tmpPath);
    if (!$info) return false;

    switch ($info['mime']) {
        case 'image/jpeg':
            $img = imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $img = imagecreatefrompng($tmpPath);
            break;
        case 'image/webp':
            $img = imagecreatefromwebp($tmpPath);
            break;
        case 'image/gif':
            $img = imagecreatefromgif($tmpPath);
            break;
        default:
            return false;
    }

    // Fondo blanco (por transparencias PNG)
    $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
    $white = imagecolorallocate($bg, 255, 255, 255);
    imagefill($bg, 0, 0, $white);
    imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));

    imagejpeg($bg, $destPath, $quality);

    imagedestroy($img);
    imagedestroy($bg);

    return true;
}
