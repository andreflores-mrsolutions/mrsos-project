

<?php
// admin/api/clientes_dashboard.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../php/conexion.php';

// --- Seguridad ---
if (empty($_SESSION['usId'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'No autenticado']);
  exit;
}

$US_ROL = (string)($_SESSION['usRol'] ?? 'CLI');
if (!in_array($US_ROL, ['MRA', 'MRSA'], true)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Sin permisos']);
  exit;
}

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
  // URL pública
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

// --- Data ---
$clientes = [];
$sqlClientes = "SELECT clId, clNombre, clEstatus
                FROM clientes
                WHERE clEstatus='Activo'
                ORDER BY clNombre ASC";
$res = $conectar->query($sqlClientes);
if ($res) {
  while ($row = $res->fetch_assoc()) $clientes[] = $row;
  $res->free();
}

// sedes
$sedesCount = [];
$res = $conectar->query("SELECT clId, COUNT(*) AS n
                         FROM cliente_sede
                         WHERE csEstatus='Activo'
                         GROUP BY clId");
if ($res) {
  while ($row = $res->fetch_assoc()) $sedesCount[(int)$row['clId']] = (int)$row['n'];
  $res->free();
}

// pólizas
$polizasByClient = [];
$polizasCount = [];
$res = $conectar->query("SELECT pcId, clId, pcTipoPoliza, pcFechaInicio, pcFechaFin, pcEstatus
                         FROM polizascliente
                         WHERE pcEstatus <> 'Error'");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $clId = (int)$row['clId'];
    $polizasByClient[$clId][] = $row;
    $polizasCount[$clId] = ($polizasCount[$clId] ?? 0) + 1;
  }
  $res->free();
}

// tickets por cliente
$ticketsOpen = [];
$ticketsRisk = [];
$ticketsCritical = [];

$res = $conectar->query("SELECT clId,
                SUM(tiEstatus='Abierto') AS abiertos,
                SUM(tiEstatus='Abierto' AND tiNivelCriticidad IN ('1','2')) AS riesgo,
                SUM(tiEstatus='Abierto' AND tiNivelCriticidad='1') AS criticos
         FROM ticket_soporte
         WHERE estatus='Activo'
         GROUP BY clId");
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $clId = (int)$row['clId'];
    $ticketsOpen[$clId]     = (int)$row['abiertos'];
    $ticketsRisk[$clId]     = (int)$row['riesgo'];
    $ticketsCritical[$clId] = (int)$row['criticos'];
  }
  $res->free();
}

// KPIs
$kpi = [
  'open'    => array_sum($ticketsOpen),
  'risk'    => array_sum($ticketsRisk),
  'critical'=> array_sum($ticketsCritical),
  'clients' => count($clientes),
];

// cards
$cards = [];
foreach ($clientes as $c) {
  $clId = (int)$c['clId'];
  $name = (string)$c['clNombre'];
  $pols = $polizasByClient[$clId] ?? [];

  $status = computeClientStatus($pols);

  $cards[] = [
    'clId' => $clId,
    'name' => $name,
    'group' => groupByLetter($name),
    'logo' => findClientLogoUrl($clId, $name),
    'sedes' => (int)($sedesCount[$clId] ?? 0),
    'polizas' => (int)($polizasCount[$clId] ?? 0),
    'open' => (int)($ticketsOpen[$clId] ?? 0),
    'risk' => (int)($ticketsRisk[$clId] ?? 0),
    'critical' => (int)($ticketsCritical[$clId] ?? 0),
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
]);
