<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include("conexion.php");
session_start();

// Para probar, puedes forzar ?clId=123 por GET si la sesión está vacía.
$clId = $_SESSION['clId'] ?? ($_GET['clId'] ?? null);

if (!$clId) {
  echo json_encode(['success'=>false, 'error'=>'clId no disponible en sesión', 'sesion'=>$_SESSION]);
  exit;
}

// Sin fechas, solo para verificar que “algo” regresa
$sql = "SELECT t.tiId, t.tiFechaCreacion, t.tiEstatus, t.tiProceso, t.tiTipoTicket
        FROM ticket_soporte t
        WHERE t.clId = ?
        ORDER BY t.tiFechaCreacion DESC
        LIMIT 50";
$stmt = $conectar->prepare($sql);
$stmt->bind_param("i", $clId);
$stmt->execute();
$res = $stmt->get_result();

$tickets = [];
while ($r = $res->fetch_assoc()) $tickets[] = $r;
$stmt->close();

// También un COUNT(*) total por si el LIMIT confunde
$sqlC = "SELECT COUNT(*) total FROM ticket_soporte WHERE clId = ?";
$stmtC = $conectar->prepare($sqlC);
$stmtC->bind_param("i", $clId);
$stmtC->execute();
$total = $stmtC->get_result()->fetch_assoc()['total'] ?? 0;
$stmtC->close();

echo json_encode([
  'success' => true,
  'clId'    => (int)$clId,
  'total'   => (int)$total,
  'tickets' => $tickets
]);
