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

$clId = (int)($in['clId'] ?? 0);
$czId = (int)($in['czId'] ?? 0);

$czNombre = trim((string)($in['czNombre'] ?? ''));
$czCodigo = isset($in['czCodigo']) ? trim((string)$in['czCodigo']) : null;          // nullable
$czDescripcion = isset($in['czDescripcion']) ? trim((string)$in['czDescripcion']) : null; // nullable
$czEstatus = isset($in['czEstatus']) ? (string)$in['czEstatus'] : null;              // nullable

if ($clId <= 0) json_fail('clId requerido.');
if ($czId <= 0) json_fail('czId requerido.');
if ($czNombre === '') json_fail('czNombre requerido.');
if ($czEstatus !== null && !in_array($czEstatus, ['Activo','Inactivo'], true)) json_fail('czEstatus inválido.');

try {
  // Validar que exista y pertenezca al cliente
  $st = $pdo->prepare("SELECT czId FROM cliente_zona WHERE czId=? AND clId=? LIMIT 1");
  $st->execute([$czId, $clId]);
  if (!$st->fetchColumn()) json_fail('Zona no pertenece al cliente.', 409);

  // Duplicado por nombre dentro del cliente (solo contra otras zonas activas o todas; aquí: todas)
  $st = $pdo->prepare("SELECT 1 FROM cliente_zona WHERE clId=? AND LOWER(czNombre)=LOWER(?) AND czId<>? LIMIT 1");
  $st->execute([$clId, $czNombre, $czId]);
  if ($st->fetchColumn()) json_fail('Ya existe otra zona con ese nombre en el cliente.', 409);

  $fields = ["czNombre=?","czCodigo=?","czDescripcion=?"];
  $args = [$czNombre, ($czCodigo === '' ? null : $czCodigo), ($czDescripcion === '' ? null : $czDescripcion)];

  if ($czEstatus !== null) {
    $fields[] = "czEstatus=?";
    $args[] = $czEstatus;
  }

  $args[] = $czId;
  $args[] = $clId;

  $sql = "UPDATE cliente_zona SET " . implode(',', $fields) . " WHERE czId=? AND clId=?";
  $st = $pdo->prepare($sql);
  $st->execute($args);

  json_ok(['updated' => $st->rowCount()]);
} catch (Throwable $e) {
  json_fail('Error al actualizar zona.', 500);
}