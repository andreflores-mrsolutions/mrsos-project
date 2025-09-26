<?php
header('Content-Type: application/json');
require_once __DIR__ . '/conexion.php';
session_start();

$usId = $_SESSION['usId'] ?? null;
$clId = $_SESSION['clId'] ?? null;
if (!$usId || !$clId) { echo json_encode(['success'=>false, 'error'=>'No autenticado']); exit; }

$peId        = intval($_POST['peId'] ?? 0);
$severidad   = trim($_POST['severidad'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$contacto    = trim($_POST['contacto'] ?? '');
$telefono    = trim($_POST['telefono'] ?? '');
$email       = trim($_POST['email'] ?? '');

if (!$peId || !$severidad || !$descripcion || !$contacto || !$telefono || !$email) {
  echo json_encode(['success'=>false, 'error'=>'Faltan datos']); exit;
}

/* Deriva eqId y csId desde la póliza del peId */
$sqlInfo = "
  SELECT pe.eqId, pc.csId
  FROM polizasequipo pe
  JOIN polizascliente pc ON pc.pcId = pe.pcId
  WHERE pe.peId = ? AND pc.clId = ?
";
$stmt = $conectar->prepare($sqlInfo);
$stmt->bind_param("ii", $peId, $clId);
$stmt->execute(); $info = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$info) { echo json_encode(['success'=>false,'error'=>'peId inválido']); exit; }
$eqId = (int)$info['eqId'];
$csId = $info['csId'] ? (int)$info['csId'] : null;

/* Insert del ticket */
$sql = "
INSERT INTO ticket_soporte
 (clId, usId, eqId, peId, csId,
  tiDescripcion, tiEstatus, tiProceso, tiNivelCriticidad,
  tiFechaCreacion, tiNombreContacto, tiNumeroContacto, tiCorreoContacto)
VALUES
 (?, ?, ?, ?, ?, ?, 'Abierto', 'asignacion', ?, CURDATE(), ?, ?, ?)
";
$stmt = $conectar->prepare($sql);
$stmt->bind_param(
  "iiiiisssss",
  $clId, $usId, $eqId, $peId, $csId,
  $descripcion, $severidad, $contacto, $telefono, $email
);
$ok = $stmt->execute();
$id = $stmt->insert_id;
$stmt->close();

echo json_encode(['success'=>$ok, 'tiId'=>$id]);
