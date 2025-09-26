<?php
// php/estadisticas_mes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once "conexion.php";
session_start();

/**
 * Sesión esperada:
 *  - $_SESSION['usRolente'] in ('AC','UC','EC')  // lado cliente
 *  - $_SESSION['usRol']        could be 'MRA'  // super admin MR
 *  - $_SESSION['usId'], $_SESSION['clId']
 */
$rolMr     = $_SESSION['usRol']      ?? null;   // 'MRA' (opcional)
$usRol    = $_SESSION['usRol']      ?? null;   // 'AC','UC','EC' (cuando es cliente)
$usId      = $_SESSION['usId']       ?? null;
$clIdSes   = $_SESSION['clId']       ?? null;

// Soporta override por MRA
$clId = ($rolMr === 'MRA' && isset($_GET['clId']))
  ? (int)$_GET['clId']
  : (int)$clIdSes;

if (!$clId && $rolMr !== 'MRA') {
  echo json_encode(['success'=>false,'error'=>'No autenticado / cliente no definido']);
  exit;
}

$csIdParam    = isset($_GET['csId']) ? (int)$_GET['csId'] : null; // sede opcional
$soloAbiertos = isset($_GET['soloAbiertos']) ? (int)$_GET['soloAbiertos'] : 0;
$ym           = $_GET['ym']       ?? null;   // 'YYYY-MM'
$lastDays     = $_GET['lastDays'] ?? null;   // 30, etc.

// 1) Ventana temporal
if ($ym) {
  $desde = date('Y-m-01', strtotime($ym . '-01'));
  $hasta = date('Y-m-01', strtotime($desde . ' +1 month'));
} elseif ($lastDays) {
  $desde = date('Y-m-d', strtotime('-'.intval($lastDays).' days'));
  $hasta = date('Y-m-d', strtotime('+1 day'));
} else {
  $desde = date('Y-m-01');
  $hasta = date('Y-m-01', strtotime('+1 month'));
}

// ---- SEDES PERMITIDAS SEGÚN ROL ----
$allowedCsIds = null; // null = sin restricción (MRA sin csId)

if ($rolMr === 'MRA') {
    // MRA: sin restricción, salvo que nos pasen ?csId=
    if ($csIdParam) $allowedCsIds = [$csIdParam];
} else {
    // Lado cliente
    $allowedCsIds = [];

    if ($usRol === 'AC') {
        // AC: TODAS las sedes del cliente
        $sql = "SELECT csId FROM cliente_sede WHERE clId = ?";
        $st  = $conectar->prepare($sql);
        if (!$st) { echo json_encode(['success'=>false,'error'=>'Prepare sedes(AC): '.$conectar->error]); exit; }
        $st->bind_param("i", $clId);
    } else {
        // UC / EC: solo sus sedes (ojo: aquí SÍ necesitamos 2 ?)
        $sql = "SELECT su.csId
                FROM sede_usuario su
                INNER JOIN cliente_sede cs ON cs.csId = su.csId
                WHERE su.usId = ? AND cs.clId = ?";
        $st  = $conectar->prepare($sql);
        if (!$st) { echo json_encode(['success'=>false,'error'=>'Prepare sedes(UC/EC): '.$conectar->error]); exit; }
        $st->bind_param("ii", $usId, $clId);
    }

    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) $allowedCsIds[] = (int)$r['csId'];
    $st->close();

    // Si desde el front pasaron ?csId=, limitamos a esa, pero solo si está permitida
    if ($csIdParam) {
        if (in_array($csIdParam, $allowedCsIds, true)) {
            $allowedCsIds = [$csIdParam];
        } else {
            // no autorizado → fuerza 0 resultados
            $allowedCsIds = [];
        }
    }
}


// 3) Joins comunes (para heredar sede por póliza)
$joins = "
  LEFT JOIN polizasequipo pe ON pe.peId = t.peId
  LEFT JOIN polizascliente pc ON pc.pcId = pe.pcId
";

// 4) WHERE base
$where = [];
$types = "";
$params = [];

// Cliente (MRA puede no forzarlo si no pasó clId)
if ($rolMr === 'MRA') {
  if ($clId) {
    $where[] = "(t.clId=? OR pc.clId=?)";
    $types  .= "ii";
    $params[] = $clId;
    $params[] = $clId;
  }
} else {
  $where[] = "(t.clId=? OR pc.clId=?)";
  $types  .= "ii";
  $params[] = $clId;
  $params[] = $clId;
}

// Sedes permitidas (si corresponde)
if (is_array($allowedCsIds)) {
    if (count($allowedCsIds)) {
        $in = implode(',', array_fill(0, count($allowedCsIds), '?'));
        $where[] = "COALESCE(t.csId, pc.csId) IN ($in)";
        $types  .= str_repeat('i', count($allowedCsIds));
        foreach ($allowedCsIds as $id) $params[] = $id;
    } else {
        // lista vacía: sin permisos / sin coincidencias
        $where[] = "1=0";
    }
}


// Solo abiertos (opcional)
if ($soloAbiertos) {
  $where[] = "t.tiEstatus='Abierto'";
}

// Rango temporal
$where[] = "t.tiFechaCreacion >= ? AND t.tiFechaCreacion < ?";
$types  .= "ss";
$params[] = $desde;
$params[] = $hasta;

$whereSQL = count($where) ? "WHERE ".implode(" AND ", $where) : "";

// Helper para ejecutar
function runQ(mysqli $db, string $sql, string $types, array $params) {
  $st = $db->prepare($sql);
  if (!$st) throw new Exception("prepare: ".$db->error);
  if ($types !== "") $st->bind_param($types, ...$params);
  $st->execute();
  return $st->get_result();
}

// 5) Salida base
$out = [
  'success'    => true,
  'clId'       => $clId ?: null,
  'csId'       => $csIdParam ?: null,
  'rango'      => [$desde, $hasta],
  'labels'     => [],
  'data'       => [],
  'porTipo'    => ['Servicio'=>0,'Preventivo'=>0,'Extra'=>0,'Otros'=>0],
  'porEstatus' => ['Abierto'=>0,'Cancelado'=>0,'Finalizado'=>0,'Otro'=>0],
  'ratio'      => ['finalizados'=>0,'total'=>0],
  'sedes'      => [],
];

// 6) Serie diaria
$sql1 = "
  SELECT DATE(t.tiFechaCreacion) f, COUNT(*) total
  FROM ticket_soporte t
  $joins
  $whereSQL
  GROUP BY DATE(t.tiFechaCreacion)
  ORDER BY f
";
$map = [];
$res1 = runQ($conectar, $sql1, $types, $params);
while ($r = $res1->fetch_assoc()) $map[$r['f']] = (int)$r['total'];

for ($cur=strtotime($desde), $end=strtotime($hasta); $cur<$end; $cur=strtotime('+1 day',$cur)) {
  $d = date('Y-m-d',$cur);
  $out['labels'][] = $d;
  $out['data'][]   = $map[$d] ?? 0;
}

// 7) Por tipo de ticket
$sql2 = "
  SELECT COALESCE(t.tiTipoTicket,'Otros') tipo, COUNT(*) total
  FROM ticket_soporte t
  $joins
  $whereSQL
  GROUP BY tipo
";
$res2 = runQ($conectar, $sql2, $types, $params);
while ($r = $res2->fetch_assoc()) {
  $k = $r['tipo'];
  if (!isset($out['porTipo'][$k])) $k = 'Otros';
  $out['porTipo'][$k] += (int)$r['total'];
}

// 8) Por estatus
$sqlE = "
  SELECT COALESCE(t.tiEstatus,'Otro') estatus, COUNT(*) total
  FROM ticket_soporte t
  $joins
  $whereSQL
  GROUP BY estatus
";
$resE = runQ($conectar, $sqlE, $types, $params);
while ($r = $resE->fetch_assoc()) {
  $k = $r['estatus'];
  if (!isset($out['porEstatus'][$k])) $k = 'Otro';
  $out['porEstatus'][$k] += (int)$r['total'];
}

// 9) Ratio finalizados/total
$sql3 = "
  SELECT
    SUM(CASE WHEN t.tiProceso='finalizado' THEN 1 ELSE 0 END) finalizados,
    COUNT(*) total
  FROM ticket_soporte t
  $joins
  $whereSQL
";
$r3 = runQ($conectar, $sql3, $types, $params)->fetch_assoc();
$out['ratio'] = [
  'finalizados' => (int)($r3['finalizados'] ?? 0),
  'total'       => (int)($r3['total'] ?? 0),
];

// 10) Devolver sedes accesibles para poblar combo en front
try {
  if ($rolMr === 'MRA') {
    // MRA: si filtró clId, listar sedes de ese cliente; si no, vacío (o todas)
    if ($clId) {
      $q = $conectar->prepare("SELECT csId, csNombre FROM cliente_sede WHERE clId=? ORDER BY csNombre");
      $q->bind_param("i", $clId);
      $q->execute();
      $rs = $q->get_result();
      while ($s = $rs->fetch_assoc()) $out['sedes'][] = $s;
      $q->close();
    }
  } else {
    // Lado cliente: sólo sedes a las que el usuario está vinculado
    $q = $conectar->prepare("SELECT csId, csNombre FROM cliente_sede WHERE clId=?");
    $q->bind_param("i", $clId);
    $q->execute();
    $rs = $q->get_result();
    $all = [];
    while ($s = $rs->fetch_assoc()) $all[(int)$s['csId']] = $s['csNombre'];
    $q->close();

    if (is_array($allowedCsIds)) {
      foreach ($allowedCsIds as $id) {
        if (isset($all[$id])) $out['sedes'][] = ['csId'=>$id,'csNombre'=>$all[$id]];
      }
    }
  }
} catch (Throwable $e) {
  // opcional: ignorar
}

echo json_encode($out);
