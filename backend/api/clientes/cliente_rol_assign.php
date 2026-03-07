<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);
csrf_verify_or_fail();

$pdo = db();
$in  = read_json_body();

$usId = (int)($in['usId'] ?? 0);
$clId = (int)($in['clId'] ?? 0);
$ucrRol = (string)($in['ucrRol'] ?? '');
$czId = isset($in['czId']) && $in['czId'] !== '' ? (int)$in['czId'] : null;
$csId = isset($in['csId']) && $in['csId'] !== '' ? (int)$in['csId'] : null;

$validRoles = ['ADMIN_GLOBAL','ADMIN_ZONA','ADMIN_SEDE','USUARIO','VISOR'];
if ($usId<=0 || $clId<=0) json_fail('usId y clId requeridos.');
if (!in_array($ucrRol, $validRoles, true)) json_fail('Rol inválido.');

try {
  // Validar usuario CLI del mismo cliente
  $st = $pdo->prepare("SELECT usRol, clId FROM usuarios WHERE usId=? LIMIT 1");
  $st->execute([$usId]);
  $u = $st->fetch();
  if (!$u) json_fail('Usuario no existe.', 404);
  if ($u['usRol'] !== 'CLI') json_fail('Solo se permite asignar roles de cliente a usuarios CLI.', 409);
  if ((int)$u['clId'] !== $clId) json_fail('El usuario no pertenece a ese cliente (usuarios.clId).', 409);

  // Validar jerarquía según rol
  if ($ucrRol === 'ADMIN_ZONA') {
    if ($czId === null) json_fail('ADMIN_ZONA requiere czId.');
    $st = $pdo->prepare("SELECT 1 FROM cliente_zona WHERE czId=? AND clId=? AND czEstatus='Activo' LIMIT 1");
    $st->execute([$czId, $clId]);
    if (!$st->fetchColumn()) json_fail('La zona no pertenece al cliente.', 409);
    // csId opcional (si lo usas como default UX). Si lo usas, valida pertenencia.
    if ($csId !== null) {
      $st = $pdo->prepare("SELECT 1 FROM cliente_sede WHERE csId=? AND clId=? LIMIT 1");
      $st->execute([$csId, $clId]);
      if (!$st->fetchColumn()) json_fail('csId no pertenece al cliente.', 409);
    }
  }

  if (in_array($ucrRol, ['ADMIN_SEDE','USUARIO','VISOR'], true)) {
    if ($csId === null) json_fail("$ucrRol requiere csId.");
    $st = $pdo->prepare("SELECT czId FROM cliente_sede WHERE csId=? AND clId=? AND csEstatus='Activo' LIMIT 1");
    $st->execute([$csId, $clId]);
    $czFromSede = $st->fetchColumn();
    if ($czFromSede === false) json_fail('La sede no pertenece al cliente o está inactiva.', 409);
    // Para consistencia: si viene czId, debe coincidir (y puede ser null si la sede no tiene zona)
    if ($czId !== null && (int)$czId !== (int)$czFromSede) json_fail('czId no coincide con la sede.', 409);
    // si no viene czId, lo “normalizas” con el de la sede (puede ser NULL)
    $czId = $czFromSede !== null ? (int)$czFromSede : null;
  }

  if ($ucrRol === 'ADMIN_GLOBAL') {
    // permisos no dependen de czId/csId; si quieres, puedes guardar csId como default UX
    $czId = null;
    // $csId puede permanecer como "default"
  }

  $pdo->beginTransaction();

  // Inactivar cualquier rol activo previo (para cumplir "1 rol por usuario por cliente")
  $st = $pdo->prepare("
    UPDATE usuario_cliente_rol
    SET ucrEstatus='Inactivo'
    WHERE usId=? AND clId=? AND ucrEstatus='Activo'
  ");
  $st->execute([$usId, $clId]);

  // Insertar nuevo rol activo
  $st = $pdo->prepare("
    INSERT INTO usuario_cliente_rol (usId, clId, czId, csId, ucrRol, ucrEstatus)
    VALUES (?, ?, ?, ?, ?, 'Activo')
  ");
  $st->execute([$usId, $clId, $czId, $csId, $ucrRol]);

  $ucrId = (int)$pdo->lastInsertId();

  // Historial + Notify: usa tus funciones actuales
  // historial_add($pdo, ...);
  // notify(...);

  $pdo->commit();
  json_ok(['ucrId' => $ucrId]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail('Error al asignar rol.', 500);
}