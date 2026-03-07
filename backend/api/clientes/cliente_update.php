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

$clNombre    = trim((string)($in['clNombre'] ?? ''));
$clDireccion = trim((string)($in['clDireccion'] ?? ''));
$clTelefono  = (string)($in['clTelefono'] ?? '');
$clCorreo    = trim((string)($in['clCorreo'] ?? ''));
$clEstatus   = isset($in['clEstatus']) ? (string)$in['clEstatus'] : null;

if ($clId<=0) json_fail('clId requerido.');
if ($clNombre === '') json_fail('clNombre requerido.');
if ($clDireccion === '') json_fail('clDireccion requerida.');
if ($clCorreo === '') json_fail('clCorreo requerido.');
if ($clTelefono === '' || !ctype_digit((string)$clTelefono)) json_fail('clTelefono inválido.');

if ($clEstatus !== null && !in_array($clEstatus, ['Activo','Inactivo'], true)) {
  json_fail('clEstatus inválido (solo Activo/Inactivo desde admin).');
}

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);

  // Duplicado por nombre contra otros clientes
  $st = $pdo->prepare("SELECT 1 FROM clientes WHERE LOWER(clNombre)=LOWER(?) AND clId<>? LIMIT 1");
  $st->execute([$clNombre, $clId]);
  if ($st->fetchColumn()) json_fail('Ya existe otro cliente con ese nombre.', 409);

  $sql = "UPDATE clientes SET clNombre=?, clDireccion=?, clTelefono=?, clCorreo=?";
  $args = [$clNombre, $clDireccion, (int)$clTelefono, $clCorreo];

  if ($clEstatus !== null) {
    $sql .= ", clEstatus=?";
    $args[] = $clEstatus;
  }
  $sql .= " WHERE clId=?";
  $args[] = $clId;

  $st = $pdo->prepare($sql);
  $st->execute($args);

  json_ok(['updated'=>$st->rowCount()]);
} catch (Throwable $e) {
  json_fail('Error al actualizar cliente.', 500);
}