<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['clId'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Cliente no autenticado'
    ]);
    exit;
}

require 'conexion.php'; // $conectar (mysqli)

$clId = (int)$_SESSION['clId'];

// ==========================
// 1) Tipo de póliza vigente
// ==========================
$stmt = $conectar->prepare("
    SELECT pcTipoPoliza
    FROM polizascliente
    WHERE clId = ?
      AND pcEstatus = 'Activo'
    ORDER BY pcFechaFin DESC
    LIMIT 1
");
$stmt->bind_param("i", $clId);
$stmt->execute();
$result = $stmt->get_result();
$tipoPoliza = $result->num_rows > 0
    ? $result->fetch_assoc()['pcTipoPoliza']
    : 'Sin póliza';

// ==========================
// 2) Tickets abiertos
// ==========================
$stmt = $conectar->prepare("
    SELECT COUNT(*) AS total
    FROM ticket_soporte
    WHERE clId = ?
      AND tiEstatus = 'Abierto'
");
$stmt->bind_param("i", $clId);
$stmt->execute();
$result = $stmt->get_result();
$ticketsAbiertos = (int)$result->fetch_assoc()['total'];

// ==========================
// 3) Equipos en póliza
//    (join polizascliente + polizasequipo)
// ==========================
$stmt = $conectar->prepare("
    SELECT COUNT(DISTINCT pe.eqId) AS total
    FROM polizasequipo pe
    INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
    WHERE pc.clId = ?
      AND pc.pcEstatus = 'Activo'
      AND pe.peEstatus = 'Activo'
");
$stmt->bind_param("i", $clId);
$stmt->execute();
$result = $stmt->get_result();
$equipos = (int)$result->fetch_assoc()['total'];

// ==========================
// 4) Lista de tickets abiertos (opcional)
// ==========================
$stmt = $conectar->prepare("
    SELECT tiId, tiDescripcion, tiFechaCreacion, tiNivelCriticidad
    FROM ticket_soporte
    WHERE clId = ?
      AND tiEstatus = 'Abierto'
    ORDER BY tiFechaCreacion DESC
");
$stmt->bind_param("i", $clId);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ==========================
// Respuesta
// ==========================
echo json_encode([
    'success'         => true,
    'poliza'          => $tipoPoliza,
    'ticketsAbiertos' => $ticketsAbiertos,
    'equipos'         => $equipos,
    'tickets'         => $tickets
]);
