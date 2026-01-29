<?php
// php/estadisticas_mes.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once "conexion.php";
session_start();

/**
 * Sesión esperada:
 *  - usuarios.usRol ∈ ('CLI','MRA','MRV','MRSA')
 *  - $_SESSION['usId'], $_SESSION['clId'] (para clientes)
 */

$usId   = $_SESSION['usId']   ?? null;
$clIdSes= $_SESSION['clId']   ?? null;
$usRol  = $_SESSION['usRol']  ?? null; // CLI, MRA, MRV, MRSA

if (!$usId || !$usRol) {
    echo json_encode(['success'=>false, 'error'=>'No autenticado']);
    exit;
}

// ¿Es usuario MR (lado MRSolutions)?
$isMr = in_array($usRol, ['MRA','MRV','MRSA'], true);

// Cliente objetivo (para MR puede venir por GET)
if ($isMr) {
    $clId = isset($_GET['clId']) ? (int)$_GET['clId'] : null; // MR puede ver varios clientes
} else {
    $clId = (int)$clIdSes;
}

if (!$isMr && !$clId) {
    echo json_encode(['success'=>false,'error'=>'Cliente no definido']);
    exit;
}

// Parámetros de filtro
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
    // Por defecto: mes actual
    $desde = date('Y-m-01');
    $hasta = date('Y-m-01', strtotime('+1 month'));
}

/**
 * 2) SEDES PERMITIDAS SEGÚN ROL
 *
 *  - MR (MRA/MRV/MRSA): sin restricción de sede, salvo que venga ?csId=
 *  - CLI:
 *      usamos usuario_cliente_rol:
 *         ADMIN_GLOBAL → todas las sedes del cliente
 *         ADMIN_ZONA   → sedes de esa zona
 *         ADMIN_SEDE   → esa sede
 *         USUARIO/VISOR→ esa sede
 */

$allowedCsIds = null; // null = sin restricción

if ($isMr) {
    // MR: solo filtramos por sede si la pasan explícitamente
    if ($csIdParam) {
        $allowedCsIds = [$csIdParam];
    }
} else {
    $allowedCsIds = [];

    // 2.1 Leemos roles del usuario para ese cliente
    $sqlRoles = "
        SELECT ucrRol, czId, csId
        FROM usuario_cliente_rol
        WHERE usId = ? AND clId = ? AND ucrEstatus = 'Activo'
    ";
    $stR = $conectar->prepare($sqlRoles);
    if (!$stR) {
        echo json_encode(['success'=>false,'error'=>'Error al preparar roles: '.$conectar->error]);
        exit;
    }
    $stR->bind_param('ii', $usId, $clId);
    $stR->execute();
    $rsR = $stR->get_result();
    $roles = [];
    while ($r = $rsR->fetch_assoc()) {
        $roles[] = $r;
    }
    $stR->close();

    // 2.2 Si no tiene filas en usuario_cliente_rol,
    //     por compatibilidad dejamos que vea TODAS las sedes del cliente.
    //     (Si prefieres bloquear, aquí podrías dejar $allowedCsIds = [];)
    if (!count($roles)) {
        $sqlAllSedes = "SELECT csId FROM cliente_sede WHERE clId=? AND csEstatus='Activo'";
        $stS = $conectar->prepare($sqlAllSedes);
        $stS->bind_param('i', $clId);
        $stS->execute();
        $rsS = $stS->get_result();
        while ($s = $rsS->fetch_assoc()) {
            $allowedCsIds[] = (int)$s['csId'];
        }
        $stS->close();
    } else {
        // 2.3 Procesamos filas
        $adminGlobal = false;
        $zonasIds = [];
        $sedesIds = [];

        foreach ($roles as $r) {
            $rol = $r['ucrRol'];
            $cz  = $r['czId'] ? (int)$r['czId'] : null;
            $cs  = $r['csId'] ? (int)$r['csId'] : null;

            if ($rol === 'ADMIN_GLOBAL') {
                $adminGlobal = true;
            } elseif ($rol === 'ADMIN_ZONA' && $cz) {
                $zonasIds[] = $cz;
            } elseif (in_array($rol, ['ADMIN_SEDE','USUARIO','VISOR'], true) && $cs) {
                $sedesIds[] = $cs;
            }
        }

        if ($adminGlobal) {
            // Todas las sedes del cliente
            $sqlAllSedes = "SELECT csId FROM cliente_sede WHERE clId=? AND csEstatus='Activo'";
            $stS = $conectar->prepare($sqlAllSedes);
            $stS->bind_param('i', $clId);
            $stS->execute();
            $rsS = $stS->get_result();
            while ($s = $rsS->fetch_assoc()) {
                $allowedCsIds[] = (int)$s['csId'];
            }
            $stS->close();
        } else {
            // Sedes por zona
            if (count($zonasIds)) {
                $inZ = implode(',', array_fill(0, count($zonasIds), '?'));
                $sqlZ = "SELECT csId FROM cliente_sede WHERE clId=? AND czId IN ($inZ) AND csEstatus='Activo'";
                $stZ = $conectar->prepare($sqlZ);
                if (!$stZ) {
                    echo json_encode(['success'=>false,'error'=>'Error preparar sedes por zona: '.$conectar->error]);
                    exit;
                }
                // bind: clId + cada czId
                $typesZ = 'i' . str_repeat('i', count($zonasIds));
                $paramsZ = array_merge([$clId], $zonasIds);
                $stZ->bind_param($typesZ, ...$paramsZ);
                $stZ->execute();
                $rsZ = $stZ->get_result();
                while ($s = $rsZ->fetch_assoc()) {
                    $allowedCsIds[] = (int)$s['csId'];
                }
                $stZ->close();
            }

            // Sedes directas
            foreach ($sedesIds as $sid) {
                $allowedCsIds[] = $sid;
            }
        }

        // Quitamos duplicados
        $allowedCsIds = array_values(array_unique($allowedCsIds));
    }

    // 2.4 Si desde el front pasaron ?csId=, filtramos a esa sede sólo si está permitida
    if ($csIdParam) {
        if (in_array($csIdParam, $allowedCsIds, true)) {
            $allowedCsIds = [$csIdParam];
        } else {
            // No autorizado → sin resultados
            $allowedCsIds = [];
        }
    }
}

// 3) Joins comunes (heredamos sede por póliza si el ticket no trae csId)
$joins = "
  LEFT JOIN polizasequipo pe ON pe.peId = t.peId
  LEFT JOIN polizascliente pc ON pc.pcId = pe.pcId
";

// 4) WHERE base
$where  = [];
$types  = "";
$params = [];

// Cliente (para MR es opcional: si NO hay clId, ve todos; si hay, filtra)
if ($isMr) {
    if ($clId) {
        $where[]  = "(t.clId = ? OR pc.clId = ?)";
        $types   .= "ii";
        $params[] = $clId;
        $params[] = $clId;
    }
} else {
    $where[]  = "(t.clId = ? OR pc.clId = ?)";
    $types   .= "ii";
    $params[] = $clId;
    $params[] = $clId;
}

// Sedes permitidas (clientes)
if (!$isMr && is_array($allowedCsIds)) {
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
    $where[] = "t.tiEstatus = 'Abierto'";
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

// 10) Sedes para el combo del front
try {
    if ($isMr) {
        // MR: si filtró clId, listar sedes de ese cliente; si no, vacío
        if ($clId) {
            $q = $conectar->prepare("SELECT csId, csNombre FROM cliente_sede WHERE clId=? AND csEstatus='Activo' ORDER BY csNombre");
            $q->bind_param("i", $clId);
            $q->execute();
            $rs = $q->get_result();
            while ($s = $rs->fetch_assoc()) $out['sedes'][] = $s;
            $q->close();
        }
    } else {
        // Cliente: solo sedes a las que el usuario tiene acceso
        if (is_array($allowedCsIds) && count($allowedCsIds)) {
            $in = implode(',', array_fill(0, count($allowedCsIds), '?'));
            $sqlS = "SELECT csId, csNombre FROM cliente_sede WHERE csId IN ($in) AND csEstatus='Activo' ORDER BY csNombre";
            $stS = $conectar->prepare($sqlS);
            if ($stS) {
                $typesS = str_repeat('i', count($allowedCsIds));
                $stS->bind_param($typesS, ...$allowedCsIds);
                $stS->execute();
                $rs = $stS->get_result();
                while ($s = $rs->fetch_assoc()) $out['sedes'][] = $s;
                $stS->close();
            }
        }
    }
} catch (Throwable $e) {
    // opcional: log
}

echo json_encode($out);
