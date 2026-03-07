<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);
csrf_verify_or_fail();

$pdo = db();
$in  = read_json_body();

$clId = (int)($in['clId'] ?? 0);
$czId = isset($in['czId']) && $in['czId'] !== '' ? (int)$in['czId'] : null;
$csNombre = trim((string)($in['csNombre'] ?? ''));
$csEsPrincipal = (int)($in['csEsPrincipal'] ?? 0);

if ($clId <= 0) json_fail('clId requerido.');
if ($csNombre === '') json_fail('Nombre de sede requerido.');

try {
  // Cliente existe
  $st = $pdo->prepare("SELECT 1 FROM clientes WHERE clId=? LIMIT 1");
  $st->execute([$clId]);
  if (!$st->fetchColumn()) json_fail('Cliente no existe.', 404);

  // Si viene czId, validar que pertenece al cliente
  if ($czId !== null) {
    $st = $pdo->prepare("SELECT 1 FROM cliente_zona WHERE czId=? AND clId=? AND czEstatus='Activo' LIMIT 1");
    $st->execute([$czId, $clId]);
    if (!$st->fetchColumn()) json_fail('La zona no pertenece al cliente o está inactiva.', 409);
  }

  // Duplicado de sede (por cliente)
  $st = $pdo->prepare("SELECT 1 FROM cliente_sede WHERE clId=? AND LOWER(csNombre)=LOWER(?) AND csEstatus='Activo' LIMIT 1");
  $st->execute([$clId, $csNombre]);
  if ($st->fetchColumn()) json_fail('Ya existe una sede activa con ese nombre.', 409);

  $pdo->beginTransaction();

  // Si csEsPrincipal=1, baja otras principales del cliente (opcional, recomendado)
  if ($csEsPrincipal === 1) {
    $st = $pdo->prepare("UPDATE cliente_sede SET csEsPrincipal=0 WHERE clId=?");
    $st->execute([$clId]);
  }

  $st = $pdo->prepare("
    INSERT INTO cliente_sede (clId, czId, csNombre, csEstatus, csEsPrincipal)
    VALUES (?, ?, ?, 'Activo', ?)
  ");
  $st->execute([$clId, $czId, $csNombre, $csEsPrincipal]);

  $csId = (int)$pdo->lastInsertId();

  $pdo->commit();
  json_ok(['csId' => $csId]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail('Error al crear sede.', 500);
}