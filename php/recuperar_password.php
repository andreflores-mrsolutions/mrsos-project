<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../lib/enviar_mail_php-main/vendor/autoload.php';

$mail = new PHPMailer(true);
header("Content-Type: application/json");

include("conexion.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$correo = trim($_POST['usCorreo'] ?? '');
$id     = trim($_POST['usId'] ?? '');

if (empty($correo) || empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Correo o ID no proporcionado']);
    exit;
}

// Verifica si existe el usuario activo con ese correo e ID
$stmt = $conectar->prepare("SELECT * FROM usuarios WHERE usCorreo = ? AND usId = ? AND usEstatus = 'Activo'");
$stmt->bind_param("ss", $correo, $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado o inactivo']);
    exit;
}

$user = $result->fetch_object();

$usNombre   = $user->usNombre;
$usAPaterno = $user->usAPaterno;
$usAMaterno = $user->usAMaterno;
$nombre     = $usNombre . ' ' . $usAPaterno . ' ' . $usAMaterno;

$token  = bin2hex(random_bytes(32));
$expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Guardar token y expiración
$update = $conectar->prepare("UPDATE usuarios SET usResetToken = ?, usResetTokenExpira = ? WHERE usCorreo = ? AND usId = ?");
$update->bind_param("ssss", $token, $expira, $correo, $id);
$update->execute();

if ($update->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No se pudo generar el token']);
    exit;
}

// Enlace de recuperación
$resetLink = "https://tusitio.com/reset-password.php?token=$token";

// Envío del correo
try {
    $mail->SMTPDebug = SMTP::DEBUG_OFF; // En producción: OFF
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'emailmr';       // Reemplazar con cuenta real
    $mail->Password = 'contrasena';    // Reemplazar con contraseña real
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('emailmr', 'MRSoS');
    $mail->addAddress($correo, $nombre);
    // $mail->addBCC('andre.flores@mrsolutions.com.mx');

    $mail->isHTML(true);
    $mail->CharSet = "UTF-8";
    $mail->Encoding = "quoted-printable";
    $mail->Subject = 'MRSoS - Recuperación de Contraseña';
    $mail->Body = '
        <p>Gracias por usar <strong>MRSoS</strong>.</p>
        <p>Para cambiar tu contraseña, da clic en el siguiente enlace:</p>
        <p><a href="' . $resetLink . '">' . $resetLink . '</a></p>
        <p>Este enlace expirará en 1 hora.</p>
    ';

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Token generado. Enlace de recuperación enviado.',
        // 'debug_link' => $resetLink // Solo en desarrollo
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar el correo: ' . $mail->ErrorInfo
    ]);
}
