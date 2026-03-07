<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));
$estatus = trim((string)($_GET['estatus'] ?? ''));

$where = "1=1";
$args = [];

if ($q !== '') {
  $where .= " AND clNombre LIKE ?";
  $args[] = "%{$q}%";
}
if ($estatus !== '') {
  if (!in_array($estatus, ['Activo','Inactivo','NewPass','Error'], true)) json_fail('estatus inválido.');
  $where .= " AND clEstatus=?";
  $args[] = $estatus;
}

try {
  if (is_mrv()) {
    $usId = (int)($_SESSION['usId'] ?? 0);
    $sql = "
      SELECT DISTINCT c.clId, c.clNombre, c.clEstatus
      FROM clientes c
      INNER JOIN cuentas cu ON cu.clId = c.clId
      WHERE cu.usId=? AND {$where}
      ORDER BY c.clNombre ASC
      LIMIT 200
    ";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge([$usId], $args));
  } else {
    $sql = "SELECT clId, clNombre, clEstatus FROM clientes WHERE {$where} ORDER BY clNombre ASC LIMIT 200";
    $st = $pdo->prepare($sql);
    $st->execute($args);
  }

  json_ok(['clientes'=>$st->fetchAll()]);
} catch (Throwable $e) {
  json_fail('Error al listar clientes.', 500);
}