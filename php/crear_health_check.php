<?php
include "conexion.php";
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usId'], $_SESSION['clId'])) {
  echo json_encode(['success' => false, 'error' => 'No autenticado']);
  exit;
}

$usId = (int)$_SESSION['usId'];
$clId = (int)$_SESSION['clId'];

$csId = isset($_POST['csId']) ? (int)$_POST['csId'] : 0;
$hcFechaHora = $_POST['hcFechaHora'] ?? '';
$hcDuracionMins = isset($_POST['hcDuracionMins']) ? (int)$_POST['hcDuracionMins'] : 240;

$hcNombreContacto = trim($_POST['hcNombreContacto'] ?? '');
$hcNumeroContacto = trim($_POST['hcNumeroContacto'] ?? '');
$hcCorreoContacto = trim($_POST['hcCorreoContacto'] ?? '');

$equiposJson = $_POST['equipos'] ?? '[]';
$equipos = json_decode($equiposJson, true);

if ($csId <= 0 || $hcFechaHora === '' || empty($equipos) || !is_array($equipos)) {
  echo json_encode(['success' => false, 'error' => 'Faltan datos']);
  exit;
}

if ($hcNombreContacto === '' || $hcNumeroContacto === '' || $hcCorreoContacto === '') {
  echo json_encode(['success' => false, 'error' => 'Faltan datos de contacto']);
  exit;
}

// Validar datetime básico
if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $hcFechaHora)) {
  echo json_encode(['success' => false, 'error' => 'Formato de fecha inválido']);
  exit;
}

$conectar->begin_transaction();

try {
  // 1) Cabecera Health Check
  $stmtH = $conectar->prepare("
    INSERT INTO health_check
    (clId, csId, usId, hcFechaHora, hcDuracionMins, hcNombreContacto, hcNumeroContacto, hcCorreoContacto)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $stmtH->bind_param(
    "iiisisss",
    $clId, $csId, $usId, $hcFechaHora, $hcDuracionMins,
    $hcNombreContacto, $hcNumeroContacto, $hcCorreoContacto
  );
  $stmtH->execute();
  $hcId = $stmtH->insert_id;

  // 2) Preparar inserts
  $stmtTicket = $conectar->prepare("
    INSERT INTO ticket_soporte
    (clId, csId, usId, eqId, peId, tiDescripcion, tiEstatus, tiProceso, tiTipoTicket, tiExtra,
     tiNivelCriticidad, tiFechaCreacion, tiVisita, tiVisitaDuracionMins,
     tiNombreContacto, tiNumeroContacto, tiCorreoContacto, usIdIng, estatus)
    VALUES
    (?, ?, ?, ?, ?, ?, 'Abierto', 'visita', 'Preventivo', '--',
     '3', CURDATE(), ?, ?,
     ?, ?, ?, 1002, 'Activo')
  ");

  $stmtItem = $conectar->prepare("
    INSERT INTO health_check_items (hcId, eqId, peId, tiId)
    VALUES (?, ?, ?, ?)
  ");

  $tiIds = [];

  foreach ($equipos as $e) {
    $eqId = isset($e['eqId']) ? (int)$e['eqId'] : 0;
    $peId = isset($e['peId']) ? (int)$e['peId'] : null;

    if ($eqId <= 0) continue;

    $desc = "Health Check (mantenimiento preventivo) programado";

    $stmtTicket->bind_param(
      "iiiisssisss",
      $clId, $csId, $usId,
      $eqId, $peId,
      $desc,
      $hcFechaHora,
      $hcDuracionMins,
      $hcNombreContacto,
      $hcNumeroContacto,
      $hcCorreoContacto
    );
    $stmtTicket->execute();
    $tiId = $stmtTicket->insert_id;
    $tiIds[] = $tiId;

    $stmtItem->bind_param("iiii", $hcId, $eqId, $peId, $tiId);
    $stmtItem->execute();
  }

  if (count($tiIds) === 0) {
    throw new Exception("No se seleccionaron equipos válidos");
  }

  $conectar->commit();

  echo json_encode([
    'success' => true,
    'hcId' => $hcId,
    'tickets' => $tiIds
  ]);
} catch (Throwable $ex) {
  $conectar->rollback();
  echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
}
