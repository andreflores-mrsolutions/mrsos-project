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
$czId = isset($_GET['czId']) && $_GET['czId'] !== '' ? (int)$_GET['czId'] : null;

if ($clId<=0) json_fail('clId requerido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);

  if ($czId !== null) require_zona_of_cliente($pdo, $clId, $czId);

  $sql = "SELECT csId, clId, czId, csNombre, csCodigo, csDireccion, csEstatus, csEsPrincipal
          FROM cliente_sede
          WHERE clId=?";
  $args = [$clId];

  if ($czId !== null) { $sql .= " AND czId=?"; $args[] = $czId; }

  $sql .= " ORDER BY csEsPrincipal DESC, csNombre ASC";

  $st = $pdo->prepare($sql);
  $st->execute($args);

  json_ok(['sedes'=>$st->fetchAll()]);
} catch (Throwable $e) {
  json_fail('Error al listar sedes.', 500);
}