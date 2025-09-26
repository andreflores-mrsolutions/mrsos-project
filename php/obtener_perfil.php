<?php
include("conexion.php");
session_start();

header('Content-Type: application/json');

$usId = $_SESSION['usId'] ?? null;
if (!$usId) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$sql = "SELECT nombre, apellidoPaterno, apellidoMaterno, telefono, username, imagen FROM usuarios WHERE usId = ?";
$stmt = $conectar->prepare($sql);
$stmt->bind_param("i", $usId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'datos' => $row]);
} else {
    echo json_encode(['success' => false, 'error' => 'Perfil no encontrado']);
}
?>
