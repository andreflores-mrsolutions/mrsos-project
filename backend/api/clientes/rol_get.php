<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$pdo = db();
$clId = (int)($_GET['clId'] ?? 0);
$usId = (int)($_GET['usId'] ?? 0);

if ($clId<=0) json_fail('clId requerido.');
if ($usId<=0) json_fail('usId requerido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);

  $st = $pdo->prepare("
    SELECT ucrId, usId, clId, czId, csId, ucrRol, ucrEstatus
    FROM usuario_cliente_rol
    WHERE clId=? AND usId=? AND ucrEstatus='Activo'
    LIMIT 1
  ");
  $st->execute([$clId, $usId]);
  $rol = $st->fetch();

  json_ok(['rol'=>$rol ?: null]);
} catch (Throwable $e) {
  json_fail('Error al obtener rol.', 500);
}