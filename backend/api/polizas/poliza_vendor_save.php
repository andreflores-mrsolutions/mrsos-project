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
$usIdVendedor = (int)($in['usId'] ?? 0);

if ($pcId <= 0) json_fail('pcId requerido');
if ($usIdVendedor <= 0) json_fail('usId vendedor requerido');

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

// 1 activo por póliza
$pdo->beginTransaction();
try {
  $pdo->prepare("UPDATE polizavendedor SET pvEstatus='Inactivo' WHERE pcId=? AND pvEstatus='Activo'")
      ->execute([$pcId]);

  $pdo->prepare("INSERT INTO polizavendedor (pcId, usId, pvEstatus) VALUES (?, ?, 'Activo')")
      ->execute([$pcId, $usIdVendedor]);

  $pdo->commit();
  json_ok(['pcId' => $pcId, 'usId' => $usIdVendedor]);
} catch (Throwable $e) {
  $pdo->rollBack();
  json_fail('Error guardando vendedor de póliza');
}