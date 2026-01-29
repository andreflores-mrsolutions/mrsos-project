<?php
include "conexion.php";
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usId'], $_SESSION['clId'])) {
  echo json_encode(['success' => false, 'error' => 'No autenticado']);
  exit;
}

$clId = (int)$_SESSION['clId'];
$hcId = isset($_GET['hcId']) ? (int)$_GET['hcId'] : 0;

if ($hcId <= 0) {
  echo json_encode(['success' => false, 'error' => 'Falta hcId']);
  exit;
}

/* =========================
   1) Cabecera health check
========================= */
$stmt = $conectar->prepare("
  SELECT 
    hc.hcId, hc.csId, hc.hcFechaHora, hc.hcDuracionMins,
    cs.csNombre
  FROM health_check hc
  INNER JOIN cliente_sede cs ON cs.csId = hc.csId
  WHERE hc.hcId = ? AND hc.clId = ?
  LIMIT 1
");
$stmt->bind_param("ii", $hcId, $clId);
$stmt->execute();
$r = $stmt->get_result();

if ($r->num_rows === 0) {
  echo json_encode(['success' => false, 'error' => 'Health Check no encontrado']);
  exit;
}

$hc = $r->fetch_assoc();

/* =========================
   2) Equipos del health check
   (ajusta nombres de tablas/joins si varían en tu BD)
========================= */
$stmt2 = $conectar->prepare("
  SELECT
    hci.eqId,
    hci.peId,
    pe.peSN,
    eq.eqModelo,
    eq.eqVersion,
    ma.maNombre
  FROM health_check_items hci
  LEFT JOIN polizasequipo pe ON pe.peId = hci.peId
  LEFT JOIN equipos eq ON eq.eqId = hci.eqId
  LEFT JOIN marca ma ON ma.maId = eq.maId
  WHERE hci.hcId = ?
  ORDER BY hci.hciId ASC
");
$stmt2->bind_param("i", $hcId);
$stmt2->execute();
$equipos = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
  'success' => true,
  'hc' => $hc,
  'equipos' => $equipos
]);
