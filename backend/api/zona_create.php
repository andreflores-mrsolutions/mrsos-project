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
$czNombre = trim((string)($in['czNombre'] ?? ''));
if ($clId<=0) json_fail('clId requerido.');
if ($czNombre==='') json_fail('czNombre requerido.');

try {
  $st = $pdo->prepare("SELECT 1 FROM clientes WHERE clId=? LIMIT 1");
  $st->execute([$clId]);
  if (!$st->fetchColumn()) json_fail('Cliente no existe.', 404);

  $st = $pdo->prepare("SELECT 1 FROM cliente_zona WHERE clId=? AND LOWER(czNombre)=LOWER(?) AND czEstatus='Activo' LIMIT 1");
  $st->execute([$clId, $czNombre]);
  if ($st->fetchColumn()) json_fail('Ya existe una zona activa con ese nombre.', 409);

  $st = $pdo->prepare("INSERT INTO cliente_zona (clId, czNombre, czEstatus) VALUES (?, ?, 'Activo')");
  $st->execute([$clId, $czNombre]);

  $czId = (int)$pdo->lastInsertId();

//   if (function_exists('historial_add')) {
//     @historial_add($pdo, (int)($_SESSION['usId'] ?? 0), 'zona', $czId, 'create', "Zona creada: {$czNombre}");
//   }

  json_ok(['czId'=>$czId]);
} catch (Throwable $e) {
  json_fail('Error al crear zona.', 500);
}