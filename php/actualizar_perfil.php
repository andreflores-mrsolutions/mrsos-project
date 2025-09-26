<?php
session_start();
include("conexion.php");

header('Content-Type: application/json');

// Validar entrada segura
$campo = $_POST['campo'] ?? '';
$valor = $_POST['valor'] ?? '';

$camposPermitidos = ['usNombre', 'usAPaterno', 'usAMaterno', 'usTelefono', 'usUsername'];
if (!in_array($campo, $camposPermitidos)) {
    echo json_encode(['success' => false, 'error' => 'Campo inválido.']);
    exit;
}

$usId = $_SESSION['usId'] ?? null;
if (!$usId) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado.']);
    exit;
}

// Actualizar campo en la base de datos
$sql = "UPDATE usuarios SET $campo = ? WHERE usId = ?";
$stmt = $conectar->prepare($sql);
$stmt->bind_param("si", $valor, $usId);

if ($stmt->execute()) {
    // Actualizar valor en $_SESSION
    $_SESSION[$campo] = $valor;

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al actualizar la base de datos.']);
}
?>
