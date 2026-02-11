<?php
// admin/api/clientes_dashboard.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../php/conexion.php';

// --- Seguridad ---
if (empty($_SESSION['usId'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'No autenticado'], JSON_UNESCAPED_UNICODE);
  exit;
}

$US_ROL = (string)($_SESSION['usRol'] ?? 'CLI');
if (!in_array($US_ROL, ['MRA', 'MRSA'], true)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}

$pdo = db();

// --- Helpers ---
function daysDiff(string $dateYmd): int {
  $d = DateTime::createFromFormat('Y-m-d', $dateYmd);
  if (!$d) return 999999;
  $now = new DateTime('today');
  return (int)$now->diff($d)->format('%r%a');
}

function computeClientStatus(array $polizas): string {
  $hasExpired = false;
  $hasSoon = false;

  foreach ($polizas as $p) {
    $estatus = (string)($p['pcEstatus'] ?? '');
    $fin = (string)($p['pcFechaFin'] ?? '');

    $diff = daysDiff($fin);

    if ($estatus === 'Vencida' || $diff < 0) $hasExpired = true;
    else if ($diff <= 60) $hasSoon = true;
  }

  if ($hasExpired) return 'VENCIDO';
  if ($hasSoon) return 'POR_VENCER';
  return 'VIGENTE';
}

function groupByLetter(string $name): string {
  $name = trim($name);
  if ($name === '') return '#';
  $c = strtoupper(mb_substr($name, 0, 1, 'UTF-8'));

  if ($c >= 'A' && $c <= 'F') return 'A–F';
  if ($c >= 'G' && $c <= 'L') return 'G–L';
  if ($c >= 'M' && $c <= 'R') return 'M–R';
  if ($c >= 'S' && $c <= 'Z') return 'S–Z';
  return '#';
}

function findClientLogoUrl(int $clId, string $clNombre): string {
  // URL pública (desde /admin/api/ -> sube 2 niveles)
  $urlBase = "../../img/Clientes/";

  // FS real
  $fsBase = realpath(__DIR__ . "/../../img/Clientes");
  if (!$fsBase) return $urlBase . "cliente_default.png";

  $exts = ['png','jpg','jpeg','webp','svg'];

  foreach ($exts as $ext) {
    $fs = $fsBase . DIRECTORY_SEPARATOR . $clId . "." . $ext;
    if (file_exists($fs)) return $urlBase . $clId . "." . $ext;
  }

  $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $clNombre)));
  foreach ($exts as $ext) {
    $fs = $fsBase . DIRECTORY_SEPARATOR . $slug . "." . $ext;
    if (file_exists($fs)) return $urlBase . $slug . "." . $ext;
  }

  return $urlBase . "cliente_default.png";
}

// --- Permisos de alcance (anti Telcel) ---
// MRA: ve todo
// MRSA: solo ve clientes ligados en `cuentas` por usId
$usId = (int)($_SESSION['usId'] ?? 0);
$restrictByCuentas = ($US_ROL === 'MRV');

// --- Data ---
// 1) Clientes
if ($restrictByCuentas) {
  $stmt = $pdo->prepare("
    SELECT c.clId, c.clNombre, c.clEstatus
    FROM clientes c
    INNER JOIN cuentas cu ON cu.clId = c.clId
    WHERE c.clEstatus = 'Activo' AND cu.usId = ?
    GROUP BY c.clId, c.clNombre, c.clEstatus
    ORDER BY c.clNombre ASC
  ");
  $stmt->execute([$usId]);
} else {
  $stmt = $pdo->prepare("
    SELECT clId, clNombre, clEstatus
    FROM clientes
    WHERE clEstatus = 'Activo'
    ORDER BY clNombre ASC
  ");
  $stmt->execute();
}

$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si no hay clientes para este admin, responde vacío (sin error)
if (!$clientes) {
  echo json_encode([
    'success' => true,
    'kpi' => ['open'=>0,'risk'=>0,'critical'=>0,'clients'=>0],
    'cards' => [],
    'user' => [
      'name' => (string)($_SESSION['usUsername'] ?? 'Admin'),
      'rol'  => $US_ROL,
    ],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// lista de clIds (para filtrar los agregados)
$clIds = array_map(fn($c) => (int)$c['clId'], $clientes);
$placeholders = implode(',', array_fill(0, count($clIds), '?'));

// 2) Sedes por cliente
$sedesCount = [];
$stmt = $pdo->prepare("
  SELECT clId, COUNT(*) AS n
  FROM cliente_sede
  WHERE csEstatus = 'Activo'
    AND clId IN ($placeholders)
  GROUP BY clId
");
$stmt->execute($clIds);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $sedesCount[(int)$row['clId']] = (int)$row['n'];
}

// 3) Pólizas por cliente
$polizasByClient = [];
$polizasCount = [];

$stmt = $pdo->prepare("
  SELECT pcId, clId, pcTipoPoliza, pcFechaInicio, pcFechaFin, pcEstatus
  FROM polizascliente
  WHERE pcEstatus <> 'Error'
    AND clId IN ($placeholders)
");
$stmt->execute($clIds);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $cid = (int)$row['clId'];
  $polizasByClient[$cid][] = $row;
  $polizasCount[$cid] = ($polizasCount[$cid] ?? 0) + 1;
}

// 4) Tickets por cliente (abiertos/riesgo/críticos)
$ticketsOpen = [];
$ticketsRisk = [];
$ticketsCritical = [];

$stmt = $pdo->prepare("
  SELECT
    clId,
    SUM(CASE WHEN tiEstatus='Abierto' THEN 1 ELSE 0 END) AS abiertos,
    SUM(CASE WHEN tiEstatus='Abierto' AND tiNivelCriticidad IN ('1','2') THEN 1 ELSE 0 END) AS riesgo,
    SUM(CASE WHEN tiEstatus='Abierto' AND tiNivelCriticidad='1' THEN 1 ELSE 0 END) AS criticos
  FROM ticket_soporte
  WHERE estatus='Activo'
    AND clId IN ($placeholders)
  GROUP BY clId
");
$stmt->execute($clIds);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $cid = (int)$row['clId'];
  $ticketsOpen[$cid]     = (int)$row['abiertos'];
  $ticketsRisk[$cid]     = (int)$row['riesgo'];
  $ticketsCritical[$cid] = (int)$row['criticos'];
}

// 5) KPIs
$kpi = [
  'open'     => array_sum($ticketsOpen),
  'risk'     => array_sum($ticketsRisk),
  'critical' => array_sum($ticketsCritical),
  'clients'  => count($clientes),
];

// 6) Cards
$cards = [];
foreach ($clientes as $c) {
  $cid  = (int)$c['clId'];
  $name = (string)$c['clNombre'];
  $pols = $polizasByClient[$cid] ?? [];

  $status = computeClientStatus($pols);

  $cards[] = [
    'clId' => $cid,
    'name' => $name,
    'group' => groupByLetter($name),
    'logo' => findClientLogoUrl($cid, $name),
    'sedes' => (int)($sedesCount[$cid] ?? 0),
    'polizas' => (int)($polizasCount[$cid] ?? 0),
    'open' => (int)($ticketsOpen[$cid] ?? 0),
    'risk' => (int)($ticketsRisk[$cid] ?? 0),
    'critical' => (int)($ticketsCritical[$cid] ?? 0),
    'status' => $status,
  ];
}

// orden “urgencia”
$priorityOrder = ['VENCIDO' => 0, 'POR_VENCER' => 1, 'VIGENTE' => 2];
usort($cards, function($a, $b) use ($priorityOrder) {
  $pa = $priorityOrder[$a['status']] ?? 9;
  $pb = $priorityOrder[$b['status']] ?? 9;
  if ($pa !== $pb) return $pa <=> $pb;
  if ($a['open'] !== $b['open']) return $b['open'] <=> $a['open'];
  return strcmp($a['name'], $b['name']);
});

echo json_encode([
  'success' => true,
  'kpi' => $kpi,
  'cards' => $cards,
  'user' => [
    'name' => (string)($_SESSION['usUsername'] ?? 'Admin'),
    'rol'  => $US_ROL,
  ],
], JSON_UNESCAPED_UNICODE);
