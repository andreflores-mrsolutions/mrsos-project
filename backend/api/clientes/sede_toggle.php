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
$csId = (int)($in['csId'] ?? 0);
$to   = (string)($in['to'] ?? '');

if ($clId<=0) json_fail('clId requerido.');
if ($csId<=0) json_fail('csId requerido.');
if (!in_array($to, ['Activo','Inactivo'], true)) json_fail('to inválido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);
  require_sede_of_cliente($pdo, $clId, $csId);

  $st = $pdo->prepare("UPDATE cliente_sede SET csEstatus=? WHERE csId=? AND clId=?");
  $st->execute([$to, $csId, $clId]);

  json_ok(['updated'=>$st->rowCount()]);
} catch (Throwable $e) {
  json_fail('Error al cambiar estatus de sede.', 500);
}