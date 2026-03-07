<?php
// admin/api/health_catalog_clientes.php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','ADMIN']);
csrf_verify_or_fail();

$clId = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
$csId = isset($_GET['csId']) ? (int)$_GET['csId'] : 0;

if ($clId <= 0) json_fail('Falta clId');
if ($csId <= 0) json_fail('Falta csId');

$pdo = db();
$usId = (int)($_SESSION['usId'] ?? 0);
$usRol = (string)($_SESSION['usRol'] ?? '');

$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA','MRSA','ADMIN'], true)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}

$st = $pdo->prepare("
  SELECT
    u.usId,
    u.usNombre, u.usAPaterno, u.usAMaterno,
    u.usCorreo,
    u.usTelefono,
    u.usUsername,
    ucr.ucrRol
  FROM usuarios u
  INNER JOIN usuario_cliente_rol ucr ON ucr.usId = u.usId
  WHERE
    u.usRol = 'CLI'
    AND u.usEstatus = 'Activo'
    AND ucr.ucrEstatus = 'Activo'
    AND ucr.clId = ?
    AND (ucr.csId = ? OR ucr.csId IS NULL)
  ORDER BY
    FIELD(ucr.ucrRol, 'ADMIN_SEDE','ADMIN_ZONA','ADMIN_GLOBAL','USUARIO','VISOR') ASC,
    u.usNombre ASC, u.usAPaterno ASC
");
$st->execute([$clId, $csId]);
$rows = $st->fetchAll();

$clientes = array_map(function($r){
  $nombre = trim(($r['usNombre'] ?? '') . ' ' . ($r['usAPaterno'] ?? '') . ' ' . ($r['usAMaterno'] ?? ''));
  return [
    'usId' => (int)$r['usId'],
    'nombre' => $nombre !== '' ? $nombre : (string)($r['usUsername'] ?? ('CLI-' . $r['usId'])),
    'correo' => (string)($r['usCorreo'] ?? ''),
    'telefono' => (string)($r['usTelefono'] ?? ''),
    'ucrRol' => (string)($r['ucrRol'] ?? ''),
  ];
}, $rows);

json_ok(['clientes' => $clientes]);