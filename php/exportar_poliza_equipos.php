<?php
// ../php/exportar_poliza_equipos.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/conexion.php';

$usId        = isset($_SESSION['usId'])  ? (int)$_SESSION['usId']  : 0;
$usRol       = $_SESSION['usRol']       ?? null;
$clIdSession = isset($_SESSION['clId']) ? (int)$_SESSION['clId']   : 0;

if ($usId <= 0 || !$usRol) {
    http_response_code(401);
    echo "Sesión no válida.";
    exit;
}

$pcId = isset($_GET['pcId']) ? (int)$_GET['pcId'] : 0;
if ($pcId <= 0) {
    http_response_code(400);
    echo "pcId inválido.";
    exit;
}

$isMR = in_array($usRol, ['MRA','MRSA','MRV'], true);

// SELECT base (muy similar a obtener_equipo_poliza.php)
$selectCommon = "
    SELECT 
        pe.peId,
        pe.peDescripcion,
        pe.peSN,
        pe.peSO,
        pe.peEstatus,

        pc.pcId,
        pc.clId,
        pc.csId,
        pc.pcTipoPoliza,
        pc.pcEstatus,
        pc.pcFechaInicio,
        pc.pcFechaFin,

        e.eqId,
        e.eqModelo,
        e.eqVersion,
        e.eqTipoEquipo,

        m.maNombre,

        c.clNombre,
        cs.csNombre,
        cs.czId AS csZonaId
    FROM polizasequipo pe
    INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
    INNER JOIN equipos        e  ON e.eqId  = pe.eqId
    INNER JOIN marca          m  ON m.maId  = e.maId
    INNER JOIN clientes       c  ON c.clId  = pc.clId
    LEFT  JOIN cliente_sede   cs ON cs.csId = pc.csId
    WHERE 
        pe.peEstatus   = 'Activo'
        AND pc.pcEstatus = 'Activo'
        AND pc.pcId    = ?
";

// MR puede ver todo (si hay clId en sesión podrías restringirlo, igual que antes)
if ($isMR) {
    $sql  = $selectCommon;
    $stmt = $conectar->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo "Error al preparar consulta (MR).";
        exit;
    }
    $stmt->bind_param('i', $pcId);
} else {
    // Clientes: respetar usuario_cliente_rol (zonas / sedes)
    $sql  = $selectCommon . "
        AND pc.clId = ?
        AND EXISTS (
            SELECT 1
            FROM usuario_cliente_rol ucr
            WHERE 
                ucr.usId        = ?
                AND ucr.clId    = pc.clId
                AND ucr.ucrEstatus = 'Activo'
                AND (
                    ucr.ucrRol = 'ADMIN_GLOBAL'
                    OR (ucr.ucrRol = 'ADMIN_ZONA'
                        AND cs.czId IS NOT NULL
                        AND cs.czId = ucr.czId)
                    OR (ucr.ucrRol IN ('ADMIN_SEDE','USUARIO','VISOR')
                        AND pc.csId IS NOT NULL
                        AND pc.csId = ucr.csId)
                )
        )
    ";
    $stmt = $conectar->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo "Error al preparar consulta (cliente).";
        exit;
    }
    $stmt->bind_param('iii', $pcId, $clIdSession, $usId);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo "Error al ejecutar consulta.";
    exit;
}

$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

if (!$rows) {
    http_response_code(404);
    echo "No se encontraron equipos para esta póliza o no tienes permisos.";
    exit;
}

// ───────────── CABECERAS CSV ─────────────
$nombreArchivo = "poliza_{$pcId}_equipos.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir salida
$out = fopen('php://output', 'w');

// Encabezados
fputcsv($out, [
    'Póliza ID',
    'Cliente',
    'Sede',
    'Zona',
    'Equipo ID',
    'Modelo',
    'Versión',
    'Tipo de equipo',
    'Marca',
    'SN',
    'SO',
    'Tipo de póliza',
    'Inicio póliza',
    'Fin póliza',
    'Estatus póliza',
    'Estatus equipo póliza'
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['pcId'],
        $r['clNombre'],
        $r['csNombre'],
        $r['csZonaId'],
        $r['eqId'],
        $r['eqModelo'],
        $r['eqVersion'],
        $r['eqTipoEquipo'],
        $r['maNombre'],
        $r['peSN'],
        $r['peSO'],
        $r['pcTipoPoliza'],
        $r['pcFechaInicio'],
        $r['pcFechaFin'],
        $r['pcEstatus'],
        $r['peEstatus'],
    ]);
}

fclose($out);
exit;
