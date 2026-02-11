<?php
// vendedor/api/clientes.php
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

// Filtro opcional (server) por búsqueda
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$where = " WHERE cu.usId = ? ";
$types = "i";
$params = [$usId];

if ($q !== '') {
  $where .= " AND (c.clNombre LIKE ?) ";
  $types .= "s";
  $params[] = "%{$q}%";
}

$sql = "
  SELECT
    c.clId,
    c.clNombre,
    COUNT(DISTINCT cu.pcId) AS polizas,
    COUNT(DISTINCT cs.csId) AS sedes,
    SUM(CASE WHEN pc.pcFechaFin < CURDATE() THEN 1 ELSE 0 END) AS vencidas,
    SUM(CASE WHEN pc.pcFechaFin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 1 ELSE 0 END) AS porVencer,
    SUM(CASE WHEN pc.pcFechaFin > DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 1 ELSE 0 END) AS vigentes,
    MIN(pc.pcFechaFin) AS minFin,
    MAX(pc.pcFechaFin) AS maxFin
  FROM cuentas cu
    INNER JOIN clientes c ON c.clId = cu.clId
    LEFT JOIN polizascliente pc ON pc.pcId = cu.pcId
    LEFT JOIN cliente_sede cs ON cs.clId = c.clId AND cs.csEstatus='Activo'
  {$where}
  GROUP BY c.clId, c.clNombre
  ORDER BY c.clNombre ASC
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

$out = [];
foreach ($rows as $r) {
  $vig = (int)($r['vigentes'] ?? 0);
  $pv  = (int)($r['porVencer'] ?? 0);
  $ven = (int)($r['vencidas'] ?? 0);

  // Regla simple de badge: lo “normal” NO se marca; lo excepcional sí.
  // Aquí para vendedor lo dejamos útil: mostramos badge solo si hay algo por vencer o vencido.
  $badge = '';
  if ($ven > 0) $badge = 'vencido';
  else if ($pv > 0) $badge = 'por_vencer';

  $out[] = [
    'clId' => (int)$r['clId'],
    'clNombre' => (string)$r['clNombre'],
    'polizas' => (int)$r['polizas'],
    'sedes' => (int)$r['sedes'],
    'badge' => $badge,
    'minFin' => $r['minFin'],
    'maxFin' => $r['maxFin'],
  ];
}

echo json_encode([
  'success' => true,
  'count' => count($out),
  'clientes' => $out
], JSON_UNESCAPED_UNICODE);
