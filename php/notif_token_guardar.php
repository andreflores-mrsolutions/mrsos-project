<?php
require_once "conexion.php";
require_once "auth_guard.php";
require_login();
header('Content-Type: application/json; charset=utf-8');

$usId = $_SESSION['usId'] ?? 0;
if (!$usId) {
  echo json_encode(['success'=>false,'message'=>'No autorizado']);
  exit;
}

$platform = $_POST['platform'] ?? 'android';
$token    = $_POST['token'] ?? '';
$deviceId = $_POST['deviceId'] ?? '';

if (!$token || strlen($token) < 30) {
  echo json_encode(['success'=>false,'message'=>'Token inválido']);
  exit;
}
if (!$deviceId) {
  echo json_encode(['success'=>false,'message'=>'deviceId faltante']);
  exit;
}


/* 1️⃣ Si el device ya existe → solo actualiza token */
$stmt = $conectar->prepare("
  SELECT id FROM user_push_devices
  WHERE deviceId = ? AND usId = ?
");
$stmt->bind_param("si", $deviceId, $usId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
  $stmt = $conectar->prepare("
    UPDATE user_push_devices
    SET token = ?, lastUsedAt = CURRENT_TIMESTAMP
    WHERE deviceId = ? AND usId = ?
  ");
  $stmt->bind_param("ssi", $token, $deviceId, $usId);
  $stmt->execute();

  echo json_encode(['success'=>true,'message'=>'Dispositivo actualizado']);
  exit;
}

/* 2️⃣ Contar dispositivos actuales */
$stmt = $conectar->prepare("
  SELECT id FROM user_push_devices
  WHERE usId = ?
  ORDER BY createdAt ASC
");
$stmt->bind_param("i", $usId);
$stmt->execute();
$res = $stmt->get_result();

/* 3️⃣ Si ya hay 3 → borrar el más antiguo */
if ($res->num_rows >= 5) {
  $old = $res->fetch_assoc();
  $stmtDel = $conectar->prepare("DELETE FROM user_push_devices WHERE id = ?");
  $stmtDel->bind_param("i", $old['id']);
  $stmtDel->execute();
}

/* 4️⃣ Insertar nuevo dispositivo */
$stmt = $conectar->prepare("
  INSERT INTO user_push_devices (usId, platform, token, deviceId)
  VALUES (?, ?, ?, ?)
");
$stmt->bind_param("isss", $usId, $platform, $token, $deviceId);
$stmt->execute();

echo json_encode(['success'=>true,'message'=>'Dispositivo registrado']);
