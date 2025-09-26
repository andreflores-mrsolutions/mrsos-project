<?php
include("conexion.php");  // Asegúrate de que este archivo tenga la conexión a tu BD
session_start();
$clId = $_SESSION['clId'];
header('Content-Type: application/json');
$input = json_decode(file_get_contents("php://input"), true);

$estado = $input['estado'] ?? '';
$marca = $input['marca'] ?? '';
$proceso = $input['proceso'] ?? '';
$tipoEquipo = $input['tipoEquipo'] ?? '';


$sql = "SELECT t.tiId, t.tiDescripcion, t.tiEstatus, t.tiNivelCriticidad, t.tiFechaCreacion, t.tiProceso, t.eqId, e.eqModelo, e.eqVersion, e.maId, m.maNombre, pe.peSN, t.tiTipoTicket, t.tiExtra
        FROM ticket_soporte t
        JOIN equipos e ON t.eqId = e.eqId
        JOIN polizasequipo pe ON $clId = pe.pcId AND e.eqId = pe.eqId
        JOIN marca m ON e.maId = m.maId
        WHERE 1=1 ";

$params = [];
$types = "";

// Filtros dinámicos
if (!empty($estado)) {
    $sql .= "AND t.tiEstatus = ? ";
    $params[] = $estado;
    $types .= "s";
}
if (!empty($marca)) {
    $sql .= "AND m.maNombre = ? ";
    $params[] = $marca;
    $types .= "s";
}
if (!empty($proceso)) {
    $sql .= "AND t.tiNivelCriticidad = ? ";
    $params[] = $proceso;
    $types .= "s";
}
if (!empty($tipoEquipo)) {
    $sql .= "AND e.eqTipoEquipo = ? ";
    $params[] = $tipoEquipo;
    $types .= "s";
}

$sql .= "ORDER BY t.tiFechaCreacion DESC LIMIT 10";  // Puedes ajustar el límite

$stmt = $conectar->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$tickets = [];

while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}

echo json_encode($tickets);
?>
