<?php
include("conexion.php");
session_start();
$usId = $_SESSION['usId'];
$clId = $_SESSION['clId'];

// Obtener filtros desde GET o POST
$estado = $_GET['estado'] ?? '';
$marca = $_GET['marca'] ?? '';
$proceso = $_GET['proceso'] ?? '';
$tipoEquipo = $_GET['tipoEquipo'] ?? '';

// Base Query
$sql = "SELECT t.tiId, t.tiDescripcion, t.tiEstatus, t.tiProceso, t.tiNivelCriticidad, t.tiFechaCreacion, 
        e.eqModelo, e.eqTipo, m.maNombre, pe.peSN 
        FROM ticket_soporte t
        JOIN equipos e ON t.eqId = e.eqId
        JOIN polizasequipo pe ON $clId = pe.pcId AND e.eqId = pe.eqId
        JOIN marca m ON e.maId = m.maId
        WHERE t.usId = ?";

// Aplicar filtros dinámicos
$params = [$usId];
$types = "i";

if ($estado && $estado !== "Todo") {
    $sql .= " AND t.tiEstatus = ?";
    $params[] = $estado;
    $types .= "s";
}

if ($marca) {
    $sql .= " AND m.maNombre = ?";
    $params[] = $marca;
    $types .= "s";
}

if ($proceso) {
    $sql .= " AND t.tiProceso = ?";
    $params[] = $proceso;
    $types .= "s";
}

if ($tipoEquipo) {
    $sql .= " AND e.eqTipo = ?";
    $params[] = $tipoEquipo;
    $types .= "s";
}

$sql .= " ORDER BY t.tiFechaCreacion DESC LIMIT 20";

$stmt = $conectar->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$tickets = [];
while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}

header('Content-Type: application/json');
echo json_encode($tickets);
?>
