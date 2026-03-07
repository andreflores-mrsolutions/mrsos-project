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
if ($pcId <= 0) json_fail('pcId requerido');

$st = $pdo->prepare("SELECT pcId, clId FROM polizascliente WHERE pcId=? LIMIT 1");
$st->execute([$pcId]);
$pc = $st->fetch(PDO::FETCH_ASSOC);
if (!$pc) json_fail('Póliza no existe');

$clId = (int)$pc['clId'];
$usId = (int)($_SESSION['usId'] ?? 0);
$rol  = current_usRol();
if (!mr_can_access_client($pdo, $usId, $rol, $clId)) json_fail('Sin acceso al cliente');

$items = $in['items'] ?? null;
if (!is_array($items) || count($items) < 1) json_fail('items requerido');
if (count($items) > 5) json_fail('Máximo 5 por alta individual');

$chkEq = $pdo->prepare("SELECT 1 FROM equipos WHERE eqId=? LIMIT 1");
$chkSede = $pdo->prepare("SELECT 1 FROM cliente_sede WHERE csId=? AND clId=? LIMIT 1");
$chkDup = $pdo->prepare("SELECT 1 FROM polizasequipo WHERE pcId=? AND peSN=? LIMIT 1");

$ins = $pdo->prepare("
  INSERT INTO polizasequipo (peDescripcion, peSN, pcId, csId, peSO, peEstatus, eqId)
  VALUES (?, ?, ?, ?, ?, ?, ?)
");

foreach ($items as $it) {
  $eqId = (int)($it['eqId'] ?? 0);
  $csId = (int)($it['csId'] ?? 0);
  $peSN = trim((string)($it['peSN'] ?? ''));
  $peSO = trim((string)($it['peSO'] ?? ''));
  $peDescripcion = trim((string)($it['peDescripcion'] ?? ''));
  $peEstatus = trim((string)($it['peEstatus'] ?? 'Activo'));

  if ($eqId <= 0) json_fail('eqId requerido');
  if ($csId <= 0) json_fail('csId requerido (obligatorio)');
  if ($peSN === '') json_fail('peSN requerido');

  $chkEq->execute([$eqId]);
  if (!$chkEq->fetchColumn()) json_fail('eqId no existe');

  $chkSede->execute([$csId, $clId]);
  if (!$chkSede->fetchColumn()) json_fail('csId no pertenece al cliente');

  $chkDup->execute([$pcId, $peSN]);
  if ($chkDup->fetchColumn()) json_fail("Duplicado: SN ya existe en la póliza ($peSN)");

  if ($peSO === '') $peSO = 'N/A';
  if ($peDescripcion === '') $peDescripcion = '—';
  $allowed = ['Activo','Inactivo','Error'];
  if (!in_array($peEstatus, $allowed, true)) $peEstatus = 'Activo';

  $ins->execute([$peDescripcion, $peSN, $pcId, $csId, $peSO, $peEstatus, $eqId]);
}

json_ok(['ok' => true]);