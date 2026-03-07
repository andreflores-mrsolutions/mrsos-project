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

$pdo = db();
$clId = (int)($_GET['clId'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$estatus = trim((string)($_GET['estatus'] ?? ''));

if ($clId <= 0) json_fail('clId requerido');

$usId = (int)($_SESSION['usId'] ?? 0);
$rol  = current_usRol();
if (!mr_can_access_client($pdo, $usId, $rol, $clId)) json_fail('Sin acceso al cliente');

$where = ["clId=?"];
$args = [$clId];

if ($estatus !== '') { $where[] = "pcEstatus=?"; $args[] = $estatus; }
if ($q !== '') {
  $where[] = "(pcIdentificador LIKE ? OR pcTipoPoliza LIKE ? OR pcEstatus LIKE ?)";
  $like = "%$q%";
  array_push($args, $like, $like, $like);
}

$sql = "SELECT pcId, pcIdentificador, pcTipoPoliza, pcFechaInicio, pcFechaFin, pcEstatus
        FROM polizascliente
        WHERE ".implode(" AND ", $where)."
        ORDER BY pcId DESC
        LIMIT 300";
$st = $pdo->prepare($sql);
$st->execute($args);

json_ok(['polizas' => $st->fetchAll(PDO::FETCH_ASSOC)]);