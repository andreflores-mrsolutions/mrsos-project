<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);
csrf_verify_or_fail();

$pdo = db();
$in  = read_json_body();

$clId = (int)($in['clId'] ?? 0);
$csId = (int)($in['csId'] ?? 0);

$czId = isset($in['czId']) && $in['czId'] !== '' ? (int)$in['czId'] : null; // opcional
$csNombre = trim((string)($in['csNombre'] ?? ''));
$csCodigo = isset($in['csCodigo']) ? trim((string)$in['csCodigo']) : null;
$csDireccion = isset($in['csDireccion']) ? trim((string)$in['csDireccion']) : null;
$csEstatus = isset($in['csEstatus']) ? (string)$in['csEstatus'] : null;
$csEsPrincipal = isset($in['csEsPrincipal']) ? (int)$in['csEsPrincipal'] : null;

if ($clId<=0) json_fail('clId requerido.');
if ($csId<=0) json_fail('csId requerido.');
if ($csNombre==='') json_fail('csNombre requerido.');
if ($csEstatus !== null && !in_array($csEstatus, ['Activo','Inactivo'], true)) json_fail('csEstatus inválido.');
if ($csEsPrincipal !== null && !in_array($csEsPrincipal, [0,1], true)) json_fail('csEsPrincipal inválido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);
  require_sede_of_cliente($pdo, $clId, $csId);

  // si viene czId, validar pertenencia; si no viene, permite NULL (sede sin zona)
  if ($czId !== null) require_zona_of_cliente($pdo, $clId, $czId);

  // duplicado de nombre por cliente (contra otras sedes)
  $st = $pdo->prepare("SELECT 1 FROM cliente_sede WHERE clId=? AND LOWER(csNombre)=LOWER(?) AND csId<>? LIMIT 1");
  $st->execute([$clId, $csNombre, $csId]);
  if ($st->fetchColumn()) json_fail('Ya existe otra sede con ese nombre en el cliente.', 409);

  $pdo->beginTransaction();

  // Si se marca principal, desmarca otras
  if ($csEsPrincipal === 1) {
    $st = $pdo->prepare("UPDATE cliente_sede SET csEsPrincipal=0 WHERE clId=?");
    $st->execute([$clId]);
  }

  $fields = ["czId=?","csNombre=?","csCodigo=?","csDireccion=?"];
  $args = [
    $czId,
    $csNombre,
    ($csCodigo === '' ? null : $csCodigo),
    ($csDireccion === '' ? null : $csDireccion),
  ];

  if ($csEstatus !== null) { $fields[] = "csEstatus=?"; $args[] = $csEstatus; }
  if ($csEsPrincipal !== null) { $fields[] = "csEsPrincipal=?"; $args[] = $csEsPrincipal; }

  $args[] = $csId;
  $args[] = $clId;

  $sql = "UPDATE cliente_sede SET ".implode(',', $fields)." WHERE csId=? AND clId=?";
  $st = $pdo->prepare($sql);
  $st->execute($args);

  $pdo->commit();
  json_ok(['updated'=>$st->rowCount()]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail('Error al actualizar sede.', 500);
}