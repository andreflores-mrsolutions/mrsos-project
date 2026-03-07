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
$to   = (string)($in['to'] ?? '');

if ($clId<=0) json_fail('clId requerido.');
if ($usId<=0) json_fail('usId requerido.');
if (!in_array($to, ['Activo','Inactivo'], true)) json_fail('to inválido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);

  $st = $pdo->prepare("UPDATE usuarios SET usEstatus=? WHERE usId=? AND clId=? AND usRol='CLI' AND usEstatus<>'Eliminado'");
  $st->execute([$to, $usId, $clId]);

  json_ok(['updated'=>$st->rowCount()]);
} catch (Throwable $e) {
  json_fail('Error al cambiar estatus de usuario.', 500);
}