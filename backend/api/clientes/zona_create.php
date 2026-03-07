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
$czNombre = trim((string)($in['czNombre'] ?? ''));
$czCodigo = isset($in['czCodigo']) ? trim((string)$in['czCodigo']) : null;
$czDescripcion = isset($in['czDescripcion']) ? trim((string)$in['czDescripcion']) : null;

if ($clId<=0) json_fail('clId requerido.');
if ($czNombre==='') json_fail('czNombre requerido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);

  // duplicado por nombre dentro del cliente
  $st = $pdo->prepare("SELECT 1 FROM cliente_zona WHERE clId=? AND LOWER(czNombre)=LOWER(?) AND czEstatus='Activo' LIMIT 1");
  $st->execute([$clId, $czNombre]);
  if ($st->fetchColumn()) json_fail('Ya existe una zona activa con ese nombre.', 409);

  $st = $pdo->prepare("
    INSERT INTO cliente_zona (clId, czNombre, czCodigo, czDescripcion, czEstatus)
    VALUES (?, ?, ?, ?, 'Activo')
  ");
  $st->execute([
    $clId,
    $czNombre,
    ($czCodigo === '' ? null : $czCodigo),
    ($czDescripcion === '' ? null : $czDescripcion),
  ]);

  json_ok(['czId'=>(int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
  json_fail('Error al crear zona.', 500);
}