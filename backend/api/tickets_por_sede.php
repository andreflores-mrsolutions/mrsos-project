<?php
// admin/api/tickets_por_sede.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../php/conexion.php'; // debe exponer db(): PDO

// --- Seguridad básica ---
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

$clId = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
if ($clId <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Falta clId'], JSON_UNESCAPED_UNICODE);
  exit;
}

$pdo = db();

// ✅ Ownership real con tu tabla `cuentas`
// MRSA solo puede ver clientes que tenga ligados en cuentas.
// (MRA = global => no restringimos)
if ($US_ROL === 'MRV') {
  $usId = (int)$_SESSION['usId'];

  $stmtPerm = $pdo->prepare("SELECT 1 FROM cuentas WHERE usId = ? AND clId = ? LIMIT 1");
  $stmtPerm->execute([$usId, $clId]);

  if (!$stmtPerm->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permisos para este cliente'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// --- Filtros opcionales ---
$estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : ''; // Abierto|Pospuesto|Cerrado|all
$q      = isset($_GET['q']) ? trim((string)$_GET['q']) : '';          // búsqueda simple

$where  = " WHERE t.estatus='Activo' AND t.clId=? ";
$params = [$clId];

if ($estado && strtolower($estado) !== 'all') {
  $where .= " AND t.tiEstatus=? ";
  $params[] = $estado;
}

if ($q !== '') {
  $q = mb_substr($q, 0, 80);
  $like = "%{$q}%";
  $where .= " AND (
      t.tiDescripcion LIKE ?
      OR e.eqModelo LIKE ?
      OR e.eqVersion LIKE ?
      OR pe.peSN LIKE ?
      OR m.maNombre LIKE ?
    ) ";
  array_push($params, $like, $like, $like, $like, $like);
}

$sql = "
  SELECT
    c.clId, c.clNombre,
    cs.csId, cs.csNombre,
    t.tiId, t.tiEstatus, t.tiProceso, t.tiTipoTicket, t.tiExtra,
    t.tiNivelCriticidad, t.tiFechaCreacion, t.tiVisita,
    t.tiNombreContacto, t.tiNumeroContacto, t.tiCorreoContacto,
    t.usIdIng,
    e.eqModelo, e.eqVersion,
    m.maNombre,
    pe.peSN
  FROM ticket_soporte t
    INNER JOIN clientes c ON c.clId = t.clId
    LEFT JOIN cliente_sede cs ON cs.csId = t.csId
    LEFT JOIN polizasequipo pe ON pe.peId = t.peId
    LEFT JOIN equipos e ON e.eqId = t.eqId
    LEFT JOIN marca m ON m.maId = e.maId
  {$where}
  ORDER BY cs.csNombre ASC, t.tiFechaCreacion DESC, t.tiId DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sedesMap = []; // csId => {csId, csNombre, clId, clNombre, tickets:[]}
$clNombre = '';

foreach ($rows as $r) {
  $clNombre = (string)($r['clNombre'] ?? '');
  $csId = ($r['csId'] !== null) ? (int)$r['csId'] : 0;

  if (!isset($sedesMap[$csId])) {
    $sedesMap[$csId] = [
      'csId' => $csId,
      'csNombre' => $r['csNombre'] ?? 'Sin sede',
      'clId' => (int)$r['clId'],
      'clNombre' => $r['clNombre'] ?? '',
      'tickets' => [],
    ];
  }

  $tiVisita = $r['tiVisita'];
  if ($tiVisita === '0000-00-00 00:00:00' || $tiVisita === '0000-00-00') $tiVisita = null;

  $sedesMap[$csId]['tickets'][] = [
    'tiId' => (int)$r['tiId'],
    'tiEstatus' => $r['tiEstatus'],
    'tiProceso' => $r['tiProceso'],
    'tiTipoTicket' => $r['tiTipoTicket'],
    'tiExtra' => $r['tiExtra'],
    'tiNivelCriticidad' => $r['tiNivelCriticidad'],
    'tiFechaCreacion' => $r['tiFechaCreacion'],
    'tiVisita' => $tiVisita,
    'eqModelo' => $r['eqModelo'],
    'eqVersion' => $r['eqVersion'],
    'maNombre' => $r['maNombre'],
    'peSN' => $r['peSN'],
    'persona' => $r['tiNombreContacto'],
    'contacto' => $r['tiNumeroContacto'],
    'correo' => $r['tiCorreoContacto'],
    'usIdIng' => (int)($r['usIdIng'] ?? 0),
    'clId' => (int)$r['clId'],
    'csId' => $csId,
    'csNombre' => $r['csNombre'] ?? 'Sin sede',
    'clNombre' => $r['clNombre'] ?? '',
  ];
}

echo json_encode([
  'success' => true,
  'clId' => $clId,
  'clNombre' => $clNombre,
  'sedes' => array_values($sedesMap),
  'count' => count($rows)
], JSON_UNESCAPED_UNICODE);
