<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';
require_once __DIR__ . '/../../../php/historial.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$pdo = db();
$in = read_json_body();

$peId = (int)($in['peId'] ?? 0);
if ($peId <= 0) json_fail('peId requerido');

$csId = (int)($in['csId'] ?? 0);
if ($csId <= 0) json_fail('csId requerido');

$peEstatus = trim((string)($in['peEstatus'] ?? 'Activo'));
$peSO = trim((string)($in['peSO'] ?? ''));
$peDescripcion = trim((string)($in['peDescripcion'] ?? ''));

$peSN_new = isset($in['peSN']) ? trim((string)$in['peSN']) : null;
$sn_confirm = trim((string)($in['sn_confirm'] ?? ''));

$rol = current_usRol();
$usId = (int)($_SESSION['usId'] ?? 0);

/** Cargar pe + validar pertenencia a póliza/cliente */
$st = $pdo->prepare("
  SELECT pe.peId, pe.pcId, pe.eqId, pe.csId, pe.peSN,
         pc.clId
  FROM polizasequipo pe
  JOIN polizascliente pc ON pc.pcId = pe.pcId
  WHERE pe.peId = ?
  LIMIT 1
");
$st->execute([$peId]);
$cur = $st->fetch(PDO::FETCH_ASSOC);
if (!$cur) json_fail('Registro no existe', 404);

$clId = (int)$cur['clId'];

if (!mr_can_access_client($pdo, $usId, $rol, $clId)) {
  json_fail('Sin acceso al cliente', 403);
}

/** Validar que la sede pertenece al mismo cliente */
$st = $pdo->prepare("SELECT 1 FROM cliente_sede WHERE csId=? AND clId=? LIMIT 1");
$st->execute([$csId, $clId]);
if (!$st->fetchColumn()) json_fail('La sede no pertenece al cliente', 400);

$peSN_old = (string)($cur['peSN'] ?? '');
$changingSN = ($peSN_new !== null && $peSN_new !== '' && $peSN_new !== $peSN_old);

/** Regla SN */
if ($changingSN) {
  if ($rol !== 'MRSA') json_fail('Solo MRSA puede cambiar el Serial Number', 403);

  if ($sn_confirm === '' || $sn_confirm !== $peSN_new) {
    json_fail('Confirmación de SN inválida. Escribe exactamente el nuevo SN para confirmar.', 400);
  }

  // (Opcional recomendado) evitar duplicado de SN dentro de la misma póliza
  $st = $pdo->prepare("SELECT 1 FROM polizasequipo WHERE pcId=? AND peSN=? AND peId<>? LIMIT 1");
  $st->execute([(int)$cur['pcId'], $peSN_new, $peId]);
  if ($st->fetchColumn()) json_fail('SN duplicado dentro de la póliza', 400);
}

$fields = [
  'csId' => $csId,
  'peEstatus' => $peEstatus,
  'peSO' => $peSO,
  'peDescripcion' => $peDescripcion,
];

$sqlSet = "csId=:csId, peEstatus=:peEstatus, peSO=:peSO, peDescripcion=:peDescripcion";

if ($changingSN) {
  $fields['peSN'] = $peSN_new;
  $sqlSet .= ", peSN=:peSN";
}

$fields['peId'] = $peId;

$st = $pdo->prepare("UPDATE polizasequipo SET $sqlSet WHERE peId=:peId");
$st->execute($fields);

// --- historial ---
$who = (int)($_SESSION['usId'] ?? 0);

$desc = Historial::msg(
  'UPDATE',
  'polizasequipo',
  ['peId'=>$peId, 'pcId'=>(int)$cur['pcId'], 'clId'=>$clId],
  'Actualización de datos del equipo en póliza.'
);

// Si hubo cambio de SN, que quede explícito
if ($changingSN) {
  $desc = Historial::msg(
    'UPDATE',
    'polizasequipo',
    ['peId'=>$peId, 'pcId'=>(int)$cur['pcId'], 'clId'=>$clId],
    "Cambio de SN: '{$peSN_old}' -> '{$peSN_new}'"
  );
}

Historial::log($pdo, $who, 'polizasequipo', $desc, 'Activo');
json_ok(['updated' => true]);
