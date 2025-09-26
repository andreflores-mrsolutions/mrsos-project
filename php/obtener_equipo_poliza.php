<?php
include("conexion.php");
session_start();

$clId = $_SESSION['clId'] ?? null;  // Asegúrate de almacenar clId en sesión
if (!$clId) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

header('Content-Type: application/json');

// Consulta mejorada con un subquery para verificar si existe ticket activo para cada equipo
$sql = "SELECT e.eqId, e.eqModelo, e.eqTipoEquipo, e.eqVersion, e.maId, m.maNombre, p.pcTipoPoliza, pe.peSN,
               (SELECT COUNT(*) 
                FROM ticket_soporte t 
                WHERE t.eqId = e.eqId AND (t.tiEstatus = 'Abierto' OR t.tiProceso IN ('asignacion', 'revision inicial', 'logs', 'meet', 'revision especial', 'espera refaccion', 'asignacion fecha', 'fecha asignada', 'espera ventana', 'espera visita', 'en camino', 'espera documentacion', 'encuesta satisfaccion'))
               ) AS tieneTicket
        FROM polizasequipo pe
        JOIN polizascliente p ON pe.pcId = p.pcId
        JOIN equipos e ON pe.eqId = e.eqId
        JOIN marca m ON e.maId = m.maId
        WHERE p.clId = ? AND p.pcEstatus = 'Activo' AND pe.peEstatus = 'Activo'";

$stmt = $conectar->prepare($sql);
$stmt->bind_param("i", $clId);
$stmt->execute();
$result = $stmt->get_result();

$equipos = [];
while ($row = $result->fetch_assoc()) {
    $equipos[] = $row;
}

echo json_encode(['success' => true, 'equipos' => $equipos]);
?>
