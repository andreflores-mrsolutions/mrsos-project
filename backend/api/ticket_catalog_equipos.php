<?php
// admin/api/ticket_catalog_equipos.php
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
    e.eqImgPath AS imgPath,
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

if (!$rows) {
  json_ok(['equipos' => []]);
}

$peIds = array_map(fn($r) => (int)$r['peId'], $rows);
$in = implode(',', array_fill(0, count($peIds), '?'));

// tickets abiertos por peId
$st2 = $pdo->prepare("
  SELECT peId, COUNT(*) cnt
  FROM ticket_soporte
  WHERE estatus='Activo' AND tiEstatus IN ('Abierto','Pospuesto')
    AND peId IN ($in)
  GROUP BY peId
");
$st2->execute($peIds);
$mapCnt = [];
while ($r = $st2->fetch()) {
  $mapCnt[(int)$r['peId']] = (int)$r['cnt'];
}

// 3 tickets recientes (ids) por peId
$st3 = $pdo->prepare("
  SELECT ti.peId, ti.tiId, cl.clNombre, cl.clId
  FROM ticket_soporte ti
  INNER JOIN clientes cl ON cl.clId = ti.clId
  WHERE ti.estatus='Activo' AND ti.tiEstatus IN ('Abierto','Pospuesto')
    AND ti.peId IN ($in)
  ORDER BY ti.tiId DESC
");
$st3->execute($peIds);
$mapList = [];
while ($r = $st3->fetch()) {
  $pid = (int)$r['peId'];
  $clid = strtoupper(substr((string)trim($r['clNombre']), 0, 3));

  if (!isset($mapList[$pid])) $mapList[$pid] = [];
  if (count($mapList[$pid]) < 3) $mapList[$pid][] = $clid .'-'. (int)$r['tiId'];
}

$equipos = [];
foreach ($rows as $r) {
  $marcaFolder = (string)$r['marca'];
  $modelo = (string)$r['modelo'];
  $imgPath = (string)$r['imgPath'];

  // Imagen por convención (si no existe, el <img onerror> pone default.png)
  if($imgPath || $imgPath !== '') {
    $img = $imgPath;
  } else {
    $img = "../img/Equipos/{$marcaFolder}/{$modelo}.png";
  }

  $peId = (int)$r['peId'];
  $equipos[] = [
    'peId' => $peId,
    'eqId' => (int)$r['eqId'],
    'sn' => (string)$r['sn'],
    'modelo' => $modelo,
    'tipoEquipo' => (string)$r['tipoEquipo'],
    'marca' => (string)$r['marca'],
    'polizaTipo' => (string)($r['polizaTipo'] ?? ''),
    'img' => $img,
    'ticketsActivos' => $mapCnt[$peId] ?? 0,
    'ticketsList' => $mapList[$peId] ?? [],
  ];
}

json_ok(['equipos' => $equipos]);