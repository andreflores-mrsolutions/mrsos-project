<?php
// admin/api/health_catalog_equipos.php
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

function slug_folder(string $s): string {
  $s = trim(mb_strtolower($s));
  $s = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $s);
  $s = preg_replace('/[^a-z0-9]+/u', '', $s) ?? '';
  return $s ?: 'default';
}

$st = $pdo->prepare("
  SELECT
    pe.peId,
    pe.eqId,
    pe.peSN AS sn,
    e.eqModelo AS modelo,
    e.eqTipoEquipo AS tipoEquipo,
    m.maNombre AS marca,
    pc.pcTipoPoliza AS polizaTipo
  FROM polizasequipo pe
  INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
  INNER JOIN equipos e ON e.eqId = pe.eqId
  INNER JOIN marca m ON m.maId = e.maId
  WHERE pc.clId = ? AND pe.csId = ? AND pe.peEstatus = 'Activo'
  ORDER BY e.eqModelo ASC, pe.peId DESC
");
$st->execute([$clId, $csId]);
$rows = $st->fetchAll();

$equipos = [];
foreach ($rows as $r) {
  $marcaFolder = slug_folder((string)$r['marca']);
  $modelo = (string)$r['modelo'];
  $img = "../img/Equipos/{$marcaFolder}/{$modelo}.png";

  $equipos[] = [
    'peId' => (int)$r['peId'],
    'eqId' => (int)$r['eqId'],
    'sn' => (string)($r['sn'] ?? ''),
    'modelo' => $modelo,
    'tipoEquipo' => (string)($r['tipoEquipo'] ?? ''),
    'marca' => (string)($r['marca'] ?? ''),
    'polizaTipo' => (string)($r['polizaTipo'] ?? ''),
    'img' => $img,
  ];
}

json_ok(['equipos' => $equipos]);