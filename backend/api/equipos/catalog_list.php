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

$q      = trim((string)($_GET['q'] ?? ''));
$maId   = (int)($_GET['maId'] ?? 0);
$tipoEq = trim((string)($_GET['eqTipoEquipo'] ?? ''));
$estatus= trim((string)($_GET['eqEstaus'] ?? 'Activo'));

$where = [];
$args  = [];

if ($estatus !== '') { $where[] = "e.eqEstaus = ?"; $args[] = $estatus; }
if ($maId > 0)        { $where[] = "e.maId = ?"; $args[] = $maId; }
if ($tipoEq !== '')   { $where[] = "e.eqTipoEquipo = ?"; $args[] = $tipoEq; }

if ($q !== '') {
  $where[] = "(e.eqModelo LIKE ? OR e.eqVersion LIKE ? OR e.eqTipo LIKE ? OR m.maNombre LIKE ?)";
  $like = "%$q%";
  array_push($args, $like, $like, $like, $like);
}

$sqlWhere = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$st = $pdo->prepare("
  SELECT e.eqId, e.eqModelo, e.eqVersion, e.eqTipoEquipo, e.eqTipo, e.eqDescripcion, e.eqEstaus,
         m.maId, m.maNombre
  FROM equipos e
  JOIN marca m ON m.maId = e.maId
  $sqlWhere
  ORDER BY m.maNombre ASC, e.eqModelo ASC
  LIMIT 200
");
$st->execute($args);

json_ok(['equipos' => $st->fetchAll(PDO::FETCH_ASSOC)]);