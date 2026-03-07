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

$clNombre    = trim((string)($in['clNombre'] ?? ''));
$clDireccion = trim((string)($in['clDireccion'] ?? ''));
$clTelefono  = (string)($in['clTelefono'] ?? '');
$clCorreo    = trim((string)($in['clCorreo'] ?? ''));

$crearSedePrincipal = (bool)($in['crearSedePrincipal'] ?? true);
$csNombrePrincipal  = trim((string)($in['csNombrePrincipal'] ?? 'Principal'));
$csCodigoPrincipal  = isset($in['csCodigoPrincipal']) ? trim((string)$in['csCodigoPrincipal']) : null;
$csDireccionPrincipal = isset($in['csDireccionPrincipal']) ? trim((string)$in['csDireccionPrincipal']) : null;

if ($clNombre === '') json_fail('clNombre requerido.');
if ($clDireccion === '') json_fail('clDireccion requerida.');
if ($clCorreo === '') json_fail('clCorreo requerido.');
if ($clTelefono === '' || !ctype_digit((string)$clTelefono)) json_fail('clTelefono inválido.');

try {
  // Duplicado por nombre (case-insensitive)
  $st = $pdo->prepare("SELECT 1 FROM clientes WHERE LOWER(clNombre)=LOWER(?) LIMIT 1");
  $st->execute([$clNombre]);
  if ($st->fetchColumn()) json_fail('Ya existe un cliente con ese nombre.', 409);

  $pdo->beginTransaction();

  $st = $pdo->prepare("
    INSERT INTO clientes (clNombre, clDireccion, clTelefono, clCorreo, clEstatus)
    VALUES (?, ?, ?, ?, 'Activo')
  ");
  $st->execute([$clNombre, $clDireccion, (int)$clTelefono, $clCorreo]);
  $clId = (int)$pdo->lastInsertId();

  $csId = null;
  if ($crearSedePrincipal) {
    if ($csNombrePrincipal === '') $csNombrePrincipal = 'Principal';
    $st = $pdo->prepare("
      INSERT INTO cliente_sede (clId, czId, csNombre, csCodigo, csDireccion, csEstatus, csEsPrincipal)
      VALUES (?, NULL, ?, ?, ?, 'Activo', 1)
    ");
    $st->execute([
      $clId,
      $csNombrePrincipal,
      ($csCodigoPrincipal === '' ? null : $csCodigoPrincipal),
      ($csDireccionPrincipal === '' ? null : $csDireccionPrincipal),
    ]);
    $csId = (int)$pdo->lastInsertId();
  }

  $pdo->commit();
  json_ok(['clId'=>$clId, 'csIdPrincipal'=>$csId]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail('Error al crear cliente.', 500);
}