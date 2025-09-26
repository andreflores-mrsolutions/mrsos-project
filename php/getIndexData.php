<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usId'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

include("conexion.php");

$usId = $_SESSION['usId'];

// Obtener nombre del usuario
$stmt = $conectar->prepare("SELECT usNombre, usAPaterno, usAMaterno, clId, usImagen FROM usuarios WHERE usId = ?");
$stmt->bind_param("i", $usId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
}

$user = $result->fetch_assoc();
$nombreCompleto = $user['usNombre'];
$clId = $user['clId'];

// Tipo de póliza del cliente
$stmt = $conectar->prepare("SELECT pcTipoPoliza FROM polizascliente WHERE clId = ? ORDER BY pcFechaFin DESC LIMIT 1");
$stmt->bind_param("i", $clId);
$stmt->execute();
$result = $stmt->get_result();
$tipoPoliza = ($result->num_rows > 0) ? $result->fetch_assoc()['pcTipoPoliza'] : 'Sin póliza';

// Contar tickets abiertos
$stmt = $conectar->prepare("SELECT COUNT(*) AS total FROM ticket_soporte WHERE clId = ? AND tiEstatus = 'Abierto'");
$stmt->bind_param("i", $clId);
$stmt->execute();
$result = $stmt->get_result();
$ticketsAbiertos = $result->fetch_assoc()['total'];

// Contar equipos en póliza
$stmt = $conectar->prepare("SELECT COUNT(DISTINCT eqId) AS total FROM polizasequipo WHERE clId = ? AND peEstatus = 'Activo'");
$stmt->bind_param("i", $clId);
$stmt->execute();
$result = $stmt->get_result();
$equipos = $result->fetch_assoc()['total'];

// Obtener lista de tickets abiertos
$stmt = $conectar->prepare("SELECT tiId, tiDescripcion, tiFechaCreacion, tiNivelCriticidad FROM ticket_soporte WHERE clId = ? AND tiEstatus = 'Abierto' ORDER BY tiFechaCreacion DESC");
$stmt->bind_param("i", $clId);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Respuesta
echo json_encode([
    'success' => true,
    'nombre' => $nombreCompleto,
    'poliza' => $tipoPoliza,
    'ticketsAbiertos' => $ticketsAbiertos,
    'equipos' => $equipos,
    'tickets' => $tickets
]);
