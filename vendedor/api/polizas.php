<?php
// vendedor/api/polizas.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../php/conexion.php';

if (empty($_SESSION['usId'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'No autenticado'], JSON_UNESCAPED_UNICODE);
  exit;
}

$rol = (string)($_SESSION['usRol'] ?? '');
if (!in_array($rol, ['MRV'], true)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}

$usId = (int)$_SESSION['usId'];
$clId = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
if ($clId <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Falta clId'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Seguridad: validar que el vendedor tenga ese cliente en cuentas
$chk = $conectar->prepare("SELECT 1 FROM cuentas WHERE usId=? AND clId=? LIMIT 1");
$chk->bind_param("ii", $usId, $clId);
$chk->execute();
$ok = $chk->get_result()->fetch_row();
if (!$ok) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Cliente no asignado a este vendedor'], JSON_UNESCAPED_UNICODE);
  exit;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$where = " WHERE cu.usId=? AND cu.clId=? ";
$types = "ii";
$params = [$usId, $clId];

if ($q !== '') {
  $where .= " AND (pc.pcIdentificador LIKE ? OR pc.pcTipoPoliza LIKE ?) ";
  $types .= "ss";
  $like = "%{$q}%";
  $params[] = $like;
  $params[] = $like;
}

$sql = "
  SELECT
    c.clNombre,
    pc.pcId,
    pc.pcIdentificador,
    pc.pcTipoPoliza,
    pc.pcFechaInicio,
    pc.pcFechaFin,
    pc.pcEstatus,
    COUNT(DISTINCT pe.peId) AS totalEquipos
  FROM cuentas cu
    INNER JOIN clientes c ON c.clId = cu.clId
    INNER JOIN polizascliente pc ON pc.pcId = cu.pcId
    LEFT JOIN polizasequipo pe ON pe.pcId = pc.pcId
  {$where}
  GROUP BY c.clNombre, pc.pcId, pc.pcIdentificador, pc.pcTipoPoliza, pc.pcFechaInicio, pc.pcFechaFin, pc.pcEstatus
  ORDER BY pc.pcFechaFin DESC, pc.pcId DESC
";

$stmt = $conectar->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Error preparando query'], JSON_UNESCAPED_UNICODE);
  exit;
}

// bind dinámico
$bind = [];
$bind[] = $types;
for ($i=0; $i<count($params); $i++) $bind[] = &$params[$i];
call_user_func_array([$stmt, 'bind_param'], $bind);

$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$clNombre = '';
$polizas = [];

foreach ($rows as $r) {
  $clNombre = (string)($r['clNombre'] ?? '');

  // Estado UX
  $fin = (string)($r['pcFechaFin'] ?? '');
  $badge = 'vigente';
  if ($fin && strtotime($fin) < strtotime(date('Y-m-d'))) $badge = 'vencida';
  else if ($fin && strtotime($fin) <= strtotime(date('Y-m-d', strtotime('+60 days')))) $badge = 'por_vencer';

  $polizas[] = [
    'pcId' => (int)$r['pcId'],
    'pcIdentificador' => (string)($r['pcIdentificador'] ?? ('Póliza #' . $r['pcId'])),
    'pcTipoPoliza' => (string)($r['pcTipoPoliza'] ?? ''),
    'pcFechaInicio' => (string)($r['pcFechaInicio'] ?? ''),
    'pcFechaFin' => (string)($r['pcFechaFin'] ?? ''),
    'pcEstatus' => (string)($r['pcEstatus'] ?? ''),
    'totalEquipos' => (int)($r['totalEquipos'] ?? 0),
    'badge' => $badge,
  ];
}

echo json_encode([
  'success' => true,
  'clId' => $clId,
  'clNombre' => $clNombre,
  'count' => count($polizas),
  'polizas' => $polizas
], JSON_UNESCAPED_UNICODE);
