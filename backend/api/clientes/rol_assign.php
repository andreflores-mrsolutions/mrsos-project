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
$usId = (int)($in['usId'] ?? 0);
$ucrRol = (string)($in['ucrRol'] ?? '');

$czId = isset($in['czId']) && $in['czId'] !== '' ? (int)$in['czId'] : null;
$csId = isset($in['csId']) && $in['csId'] !== '' ? (int)$in['csId'] : null;

$valid = ['ADMIN_GLOBAL','ADMIN_ZONA','ADMIN_SEDE','USUARIO','VISOR'];

if ($clId<=0) json_fail('clId requerido.');
if ($usId<=0) json_fail('usId requerido.');
if (!in_array($ucrRol, $valid, true)) json_fail('ucrRol inválido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);

  // Usuario debe ser CLI del mismo cliente
  $st = $pdo->prepare("SELECT 1 FROM usuarios WHERE usId=? AND clId=? AND usRol='CLI' AND usEstatus<>'Eliminado' LIMIT 1");
  $st->execute([$usId, $clId]);
  if (!$st->fetchColumn()) json_fail('Usuario no es CLI del cliente.', 409);

  // Validación por rol
  if ($ucrRol === 'ADMIN_GLOBAL') {
    // czId/csId pueden venir por UX, pero permisos no dependen: dejamos czId NULL
    $czId = null;
  }

  if ($ucrRol === 'ADMIN_ZONA') {
    if ($czId === null) json_fail('ADMIN_ZONA requiere czId.');
    require_zona_of_cliente($pdo, $clId, $czId);
    // csId opcional (default UX). Si viene, validar pertenencia al cliente.
    if ($csId !== null) require_sede_of_cliente($pdo, $clId, $csId);
  }

  if (in_array($ucrRol, ['ADMIN_SEDE','USUARIO','VISOR'], true)) {
    if ($csId === null) json_fail($ucrRol . ' requiere csId.');
    $sede = require_sede_of_cliente($pdo, $clId, $csId);
    // normalizar czId a la zona real de la sede (puede ser NULL)
    $czId = ($sede['czId'] !== null) ? (int)$sede['czId'] : null;
  }

  $pdo->beginTransaction();

  // inactivar rol previo activo del mismo usuario-cliente
  $st = $pdo->prepare("UPDATE usuario_cliente_rol SET ucrEstatus='Inactivo' WHERE usId=? AND clId=? AND ucrEstatus='Activo'");
  $st->execute([$usId, $clId]);

  // insertar el nuevo activo
  $st = $pdo->prepare("
    INSERT INTO usuario_cliente_rol (usId, clId, czId, csId, ucrRol, ucrEstatus)
    VALUES (?, ?, ?, ?, ?, 'Activo')
  ");
  $st->execute([$usId, $clId, $czId, $csId, $ucrRol]);

  $ucrId = (int)$pdo->lastInsertId();

  $pdo->commit();
  json_ok(['ucrId'=>$ucrId]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail('Error al asignar rol.', 500);
}