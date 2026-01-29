<?php
require "conexion.php";
require "helpers/sedes_permitidas.php";
session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($msg){
  echo json_encode(['success'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

function clPrefix(string $name): string {
  // quitar acentos/raros de manera simple + solo letras
  $name = strtoupper($name);
  $name = preg_replace('/[^A-Z]/', '', $name ?? '');
  $p = substr($name, 0, 3);
  return $p !== '' ? $p : 'CLI';
}

$clId = (int)($_SESSION['clId'] ?? 0);
$usId = (int)($_SESSION['usId'] ?? 0);
if (!$clId || !$usId) fail("No autenticado");

$pcId = (int)($_GET['pcId'] ?? 0);
if (!$pcId) fail("Falta pcId");

$csId = (int)($_GET['csId'] ?? 0);
$tipo = trim($_GET['tipo'] ?? '');
$q    = trim($_GET['q'] ?? '');

$allowed = getAllowedSedes($conectar, $clId, $usId);
if (!$allowed) fail("Sin sedes asignadas");

if ($csId && !in_array($csId, $allowed, true)) fail("Sede no permitida");

$filterSedes = $csId ? [$csId] : $allowed;

$in = implode(',', array_fill(0, count($filterSedes), '?'));
$types = str_repeat('i', count($filterSedes));

$where = "pe.pcId = ? AND pe.csId IN ($in)";
$bindTypes = "i" . $types;
$params = array_merge([$pcId], $filterSedes);

if ($tipo !== '') {
  $where .= " AND e.eqTipoEquipo = ?";
  $bindTypes .= "s";
  $params[] = $tipo;
}

if ($q !== '') {
  $where .= " AND (
      e.eqModelo LIKE ? OR e.eqVersion LIKE ? OR pe.peSN LIKE ?
      OR m.maNombre LIKE ? OR cs.csNombre LIKE ? OR pc.pcIdentificador LIKE ?
  )";
  $like = "%$q%";
  $bindTypes .= "ssssss";
  array_push($params, $like,$like,$like,$like,$like,$like);
}

$sql = "
SELECT
  pe.peId, pe.peSN, pe.csId,
  e.eqId, e.eqModelo, e.eqVersion, e.eqTipoEquipo,
  m.maNombre,
  cs.csNombre,
  cl.clNombre,
  pc.pcTipoPoliza, pc.pcIdentificador, pc.pcFechaInicio, pc.pcFechaFin
FROM polizasequipo pe
JOIN equipos e ON e.eqId = pe.eqId
JOIN marca m ON m.maId = e.maId
JOIN polizascliente pc ON pc.pcId = pe.pcId
LEFT JOIN cliente_sede cs ON cs.csId = pe.csId
LEFT JOIN clientes cl ON cl.clId = cs.clId
WHERE $where
ORDER BY cs.csNombre ASC, e.eqTipoEquipo ASC, e.eqModelo ASC
";

$stmt = $conectar->prepare($sql);
$stmt->bind_param($bindTypes, ...$params);
$stmt->execute();
$r = $stmt->get_result();

$equipos = [];
while ($row = $r->fetch_assoc()) {
  $equipos[] = $row;
}

$total_equipos = count($equipos);

/**
 * ✅ REGLA NUEVA:
 * Si el total de equipos visibles para esta póliza es 0,
 * regresamos un flag para que el frontend NO la muestre en opciones.
 *
 * (No marcamos "success=false" para no romper tu flujo actual;
 * solo indicamos explícitamente que debe ocultarse.)
 */
if ($total_equipos === 0) {
  echo json_encode([
    'success' => true,
    'pcId' => $pcId,
    'ocultar_poliza' => true,
    'total_equipos' => 0,
    'sedes' => [],
    'equipos' => [],
    'prefix' => 'CLI',
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// agrupación por sede (para tu UI)
$bySede = [];
foreach ($equipos as $e) {
  $key = (string)($e['csId'] ?? '0');
  if (!isset($bySede[$key])) {
    $bySede[$key] = [
      'csId' => (int)($e['csId'] ?? 0),
      'csNombre' => $e['csNombre'] ?? '',
      'equipos' => []
    ];
  }
  $bySede[$key]['equipos'][] = $e;
}

$clienteNombre = $equipos[0]['clNombre'] ?? '';
$prefix = clPrefix($clienteNombre);

echo json_encode([
  'success' => true,
  'pcId' => $pcId,
  'ocultar_poliza' => false,
  'total_equipos' => $total_equipos,
  'sedes' => array_values($bySede),
  'equipos' => $equipos,
  'prefix' => $prefix,
], JSON_UNESCAPED_UNICODE);
