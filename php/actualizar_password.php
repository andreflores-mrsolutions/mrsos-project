<?php
include("conexion.php");
session_start();

header('Content-Type: application/json');

$usId = $_SESSION['usId'] ?? null;
if (!$usId) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$nueva = $_POST['nueva'] ?? '';
if (!$nueva) {
    echo json_encode(['success' => false, 'error' => 'Contraseña requerida']);
    exit;
}

// Cifrado seguro (hash)
$hash = password_hash($nueva, PASSWORD_BCRYPT);

$sql = "UPDATE usuarios SET password = ? WHERE usId = ?";
$stmt = $conectar->prepare($sql);
$stmt->bind_param("si", $hash, $usId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al cambiar contraseña']);
}
?>
