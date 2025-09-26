<?php
include("conexion.php");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$repassword = $_POST['repassword'] ?? '';

if (empty($token) || empty($password) || empty($repassword)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    exit;
}

if ($password !== $repassword) {
    echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden.']);
    exit;
}

// Buscar usuario por token
$stmt = $conectar->prepare("SELECT usId FROM usuarios WHERE usResetToken = ? AND usResetTokenExpira >= NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Token inválido o expirado.']);
    exit;
}

$user = $result->fetch_object();
$usId = $user->usId;

// Encriptar nueva contraseña (en esta versión usando md5, pero debería ser password_hash en producción)
$nuevaPass = password_hash($password, PASSWORD_DEFAULT);

// Actualizar contraseña y eliminar token
$update = $conectar->prepare("UPDATE usuarios SET usPass = ?, usResetToken = NULL, usResetTokenExpira = NULL WHERE usId = ?");
$update->bind_param("ss", $nuevaPass, $usId);
$update->execute();

if ($update->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la contraseña.']);
    exit;
}

// Todo correcto
echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente.']);
