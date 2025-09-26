<?php
// php/obtener_tickets_sedes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once 'conexion.php';
session_start();

/**
 * Sesión
 */
$rolSesion  = $_SESSION['usRol']  ?? null;   // 'MRA' | 'AC' | 'UC' | 'EC'
$clIdSesion = $_SESSION['clId'] ?? null;
$usIdSesion = $_SESSION['usId'] ?? null;

// MRA puede ver otro cliente con ?clId=
$clId = ($rolSesion === 'MRA' && isset($_GET['clId']))
  ? (int)$_GET['clId']
  : (int)$clIdSesion;

if (!$clId) {
  echo json_encode(['success'=>false,'error'=>'No autenticado / cliente no definido']);
  exit;
}

$csIdFiltro = isset($_GET['csId']) ? (int)$_GET['csId'] : null;

/**
 * Sedes permitidas según rol
 */
/**
 * Sedes permitidas según rol
 * - MRA: ve todo; si pasa ?csId filtra esa sede.
 * - AC: ve TODAS las sedes del cliente (ignora usuario_sede). Si pasa ?csId filtra esa sede.
 * - UC/EC: solo sus sedes de usuario_sede. Si el cliente no maneja sedes, no filtra por sede.
 */
$allowedCs   = [];
$filterBySede = false;

if ($rolSesion === 'MRA') {
  if ($csIdFiltro) {
    $allowedCs = [$csIdFiltro];
    $filterBySede = true;
  } else {
    $filterBySede = false; // todas
  }

} elseif ($rolSesion === 'AC') {
  if ($csIdFiltro) {
    $allowedCs = [$csIdFiltro];
    $filterBySede = true;  // AC puede filtrar a una sede concreta
  } else {
    $filterBySede = false; // AC ve TODAS las sedes del cliente (no limitar por sede)
  }

} else { // UC / EC
  // sedes asignadas al usuario
  $sql = "SELECT csId FROM sede_usuario WHERE usId=? AND suEstatus = 'Activo'";
  $st  = $conectar->prepare($sql);
  $st->bind_param("i", $usIdSesion);
  $st->execute();
  $res = $st->get_result();
  while ($r = $res->fetch_assoc()) $allowedCs[] = (int)$r['csId'];
  $st->close();

  // ¿El cliente maneja sedes?
  $st = $conectar->prepare("SELECT 1 FROM cliente_sede WHERE clId=? LIMIT 1");
  $st->bind_param("i", $clId);
  $st->execute();
  $st->store_result();
  $tieneSedes = $st->num_rows > 0;
  $st->close();

  // Para UC/EC solo filtras por sede si el cliente maneja sedes y hay sedes asignadas
  $filterBySede = $tieneSedes && !empty($allowedCs);

  // Si además llegó ?csId, intersección rápida (por seguridad)
  if ($filterBySede && $csIdFiltro) {
    if (in_array($csIdFiltro, $allowedCs, true)) {
      $allowedCs = [$csIdFiltro];
    } else {
      // no tiene permiso para esa sede -> vacía (no devolverá nada)
      $allowedCs = [-1];
    }
  }
}

/**
 * Armado de filtros
 */
$where = [];
$types = "";
$params = [];

// cliente (en ticket o en poliza del equipo)
$where[] = "(t.clId=? OR pc.clId=?)";
$types  .= "ii";
$params[] = $clId;
$params[] = $clId;

// sede (cuando corresponde)
// Usamos COALESCE(t.csId, pc.csId) como sede efectiva
if ($filterBySede) {
  $placeholders = implode(',', array_fill(0, count($allowedCs), '?'));
  $where[] = "COALESCE(t.csId, pc.csId) IN ($placeholders)";
  $types   .= str_repeat('i', count($allowedCs));
  array_push($params, ...$allowedCs);
}

// Si además MRA pasó ?csId=, ya está cubierto arriba

$whereSQL = count($where) ? "WHERE ".implode(' AND ', $where) : "";

/**
 * Consulta:
 *  - Trae hasta 3 tickets por sede (ROW_NUMBER() en MySQL 8)
 *  - El nombre de la sede
 *  - Datos suficientes para pintar la tabla
 */
$sql = "
WITH t_base AS (
  SELECT
    t.tiId, t.tiEstatus, t.tiProceso, t.tiTipoTicket, t.tiExtra, t.tiFechaCreacion,
    e.eqModelo, e.eqVersion, m.maNombre,
    COALESCE(pe.peSN, pe_eq.peSN) AS peSN,
    COALESCE(t.csId, pc.csId) AS csId
  FROM ticket_soporte t
  LEFT JOIN polizasequipo pe    ON pe.peId = t.peId
  LEFT JOIN polizasequipo pe_eq ON (t.peId IS NULL AND pe_eq.eqId = t.eqId)
  LEFT JOIN equipos e           ON e.eqId  = COALESCE(pe.eqId, pe_eq.eqId, t.eqId)
  LEFT JOIN marca m             ON m.maId  = e.maId
  LEFT JOIN polizascliente pc   ON pc.pcId = COALESCE(pe.pcId, pe_eq.pcId)
  $whereSQL
),
t_rank AS (
  SELECT
    tb.*,
    ROW_NUMBER() OVER (PARTITION BY tb.csId ORDER BY tb.tiFechaCreacion DESC, tb.tiId DESC) AS rn
  FROM t_base tb
)
SELECT
  c.csId, c.csNombre,
  tr.tiId, tr.tiEstatus, tr.tiProceso, tr.tiTipoTicket, tr.tiExtra, tr.tiFechaCreacion,
  tr.eqModelo, tr.eqVersion, tr.maNombre, tr.peSN
FROM t_rank tr
LEFT JOIN cliente_sede c ON c.csId = tr.csId
WHERE tr.rn <= 3
ORDER BY c.csNombre ASC, tr.tiFechaCreacion DESC, tr.tiId DESC
";

$stmt = $conectar->prepare($sql);
if (!$stmt) {
  echo json_encode(['success'=>false,'error'=>'Prepare error: '.$conectar->error]);
  exit;
}
if ($types !== "") {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

// Reagrupar por sede
$bySede = [];
while ($row = $res->fetch_assoc()) {
  $sid = (int)($row['csId'] ?? 0);
  $sname = $row['csNombre'] ?? 'Sin sede';
  if (!isset($bySede[$sid])) {
    $bySede[$sid] = ['csId'=>$sid, 'csNombre'=>$sname, 'tickets'=>[]];
  }
  $bySede[$sid]['tickets'][] = [
    'tiId' => (int)$row['tiId'],
    'tiEstatus' => $row['tiEstatus'],
    'tiProceso' => $row['tiProceso'],
    'tiTipoTicket' => $row['tiTipoTicket'],
    'tiExtra' => $row['tiExtra'],
    'tiFechaCreacion' => $row['tiFechaCreacion'],
    'eqModelo' => $row['eqModelo'],
    'eqVersion' => $row['eqVersion'],
    'maNombre' => $row['maNombre'],
    'peSN' => $row['peSN'],
  ];
}
$stmt->close();

// Si no hay sedes (cliente sin sedes) podemos devolver una "falsa sede"
$sedes = array_values($bySede);
if (empty($sedes) && $rolSesion !== 'MRA') {
  // intenta devolver 0:Sin sede con top 3 general
  $sql2 = "
    SELECT
      t.tiId, t.tiEstatus, t.tiProceso, t.tiTipoTicket, t.tiExtra, t.tiFechaCreacion,
      e.eqModelo, e.eqVersion, m.maNombre, COALESCE(pe.peSN, pe_eq.peSN) AS peSN
    FROM ticket_soporte t
    LEFT JOIN polizasequipo pe    ON pe.peId = t.peId
    LEFT JOIN polizasequipo pe_eq ON (t.peId IS NULL AND pe_eq.eqId = t.eqId)
    LEFT JOIN equipos e           ON e.eqId  = COALESCE(pe.eqId, pe_eq.eqId, t.eqId)
    LEFT JOIN marca m             ON m.maId  = e.maId
    LEFT JOIN polizascliente pc   ON pc.pcId = COALESCE(pe.pcId, pe_eq.pcId)
    WHERE (t.clId=? OR pc.clId=?)
    ORDER BY t.tiFechaCreacion DESC, t.tiId DESC
    LIMIT 3
  ";
  $st2 = $conectar->prepare($sql2);
  $st2->bind_param('ii', $clId, $clId);
  $st2->execute();
  $r2 = $st2->get_result();
  $tmp = [];
  while ($w = $r2->fetch_assoc()) {
    $tmp[] = [
      'tiId'=>(int)$w['tiId'],
      'tiEstatus'=>$w['tiEstatus'],
      'tiProceso'=>$w['tiProceso'],
      'tiTipoTicket'=>$w['tiTipoTicket'],
      'tiExtra'=>$w['tiExtra'],
      'tiFechaCreacion'=>$w['tiFechaCreacion'],
      'eqModelo'=>$w['eqModelo'],
      'eqVersion'=>$w['eqVersion'],
      'maNombre'=>$w['maNombre'],
      'peSN'=>$w['peSN'],
    ];
  }
  $st2->close();
  if ($tmp) {
    $sedes = [[ 'csId'=>0, 'csNombre'=>'Sin sede', 'tickets'=>$tmp ]];
  }
}

echo json_encode([
  'success'=>true,
  'clId'=>$clId,
  'rol'=>$rolSesion,
  'sedes'=>$sedes
]);
