<?php
include("conexion.php");
session_start();

header('Content-Type: application/json');

$usId = $_SESSION['usId'] ?? null;
$nuevaPassword = $_POST['nuevaPassword'] ?? '';

if (!$usId || !$nuevaPassword) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// Encripta la contraseña (hash seguro)
$hashedPassword = password_hash($nuevaPassword, PASSWORD_DEFAULT);

$sql = "UPDATE usuarios SET usPass = ? WHERE usId = ?";
$stmt = $conectar->prepare($sql);
$stmt->bind_param("si", $hashedPassword, $usId);
if ($stmt->execute()) {
    // Destruir sesión para forzar login
    session_destroy();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos']);
}
?>
