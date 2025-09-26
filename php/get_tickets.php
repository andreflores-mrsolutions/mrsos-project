<?php
include("conexion.php");
session_start();
if (!isset($_SESSION['usId'])) {
    echo json_encode(['error' => 'Sesión no iniciada']);
    exit;
}
$usId = $_SESSION['usId'];
$clId = $_SESSION['clId'];

$sql = "SELECT t.tiId, t.tiDescripcion, t.tiEstatus, t.tiNivelCriticidad, t.tiFechaCreacion, t.tiProceso, t.eqId, e.eqModelo, e.eqVersion, e.maId, m.maNombre, pe.peSN 
        FROM ticket_soporte t
        JOIN equipos e ON t.eqId = e.eqId
        JOIN polizasequipo pe ON $clId = pe.pcId AND e.eqId = pe.eqId
        JOIN marca m ON e.maId = m.maId
        WHERE t.usId = ? AND t.tiEstatus = 'Abierto'
        ORDER BY t.tiFechaCreacion DESC
        LIMIT 3";

$stmt = $conectar->prepare($sql);
$stmt->bind_param("i", $usId);
$stmt->execute();
$result = $stmt->get_result();

$tickets = [];

while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
}

echo json_encode($tickets);

