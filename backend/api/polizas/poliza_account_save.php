<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$pdo = db();
$in = read_json_body();
$pcId = (int)($in['pcId'] ?? 0);
$usIdResp = (int)($in['usId'] ?? 0);

if ($pcId <= 0) json_fail('pcId requerido');
if ($usIdResp <= 0) json_fail('usId responsable requerido');

$st = $pdo->prepare("SELECT pcId, clId FROM polizascliente WHERE pcId=? LIMIT 1");
$st->execute([$pcId]);
$pc = $st->fetch(PDO::FETCH_ASSOC);
if (!$pc) json_fail('Póliza no existe');

$clId = (int)$pc['clId'];
$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = current_usRol();

if (!mr_can_access_client($pdo, $usId, $usRol, $clId)) {
  json_fail('Sin acceso al cliente');
}

// upsert simple por pcId
$st = $pdo->prepare("SELECT cuId FROM cuentas WHERE pcId=? LIMIT 1");
$st->execute([$pcId]);
$cuId = (int)($st->fetchColumn() ?: 0);

try {
  if ($cuId > 0) {
    $pdo->prepare("UPDATE cuentas SET clId=?, usId=? WHERE cuId=? LIMIT 1")
        ->execute([$clId, $usIdResp, $cuId]);
  } else {
    $pdo->prepare("INSERT INTO cuentas (clId, pcId, usId) VALUES (?, ?, ?)")
        ->execute([$clId, $pcId, $usIdResp]);
    $cuId = (int)$pdo->lastInsertId();
  }

  json_ok(['cuId' => $cuId, 'pcId' => $pcId, 'usId' => $usIdResp]);
} catch (Throwable $e) {
  json_fail('Error guardando responsable de cuenta');
}