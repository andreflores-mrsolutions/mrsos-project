<?php
// ../php/adm_tickets_cliente_tickets.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/conexion.php';

function json_response(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1) Validar sesión & rol
$usId  = isset($_SESSION['usId']) ? (int)$_SESSION['usId'] : 0;
$usRol = $_SESSION['usRol'] ?? null; // CLI | MRA | MRV | MRSA

if ($usId <= 0 || !$usRol) {
    json_response([
        'success' => false,
        'error'   => 'Sesión no válida.'
    ]);
}

$isMR = in_array($usRol, ['MRA','MRV','MRSA'], true);
if (!$isMR) {
    json_response([
        'success' => false,
        'error'   => 'No tienes permisos para ver los tickets de clientes.'
    ]);
}

// 2) Entrada
$clId  = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
$vista = $_POST['vista'] ?? 'abiertos'; 
// valores esperados: 'abiertos', 'todos', 'cerrados'

if ($clId <= 0) {
    json_response([
        'success' => false,
        'error'   => 'Parámetros inválidos (clId requerido).'
    ]);
}

// 3) Filtro por vista
$extraWhere = '';
if ($vista === 'abiertos') {
    $extraWhere = " AND t.tiEstatus IN ('Abierto','Pospuesto') ";
} elseif ($vista === 'cerrados') {
    $extraWhere = " AND t.tiEstatus IN ('Cerrado','Cancelado') ";
} else {
    // 'todos' -> sin filtro adicional
    $extraWhere = "";
}

// 4) Consulta principal
$sql = "
    SELECT
        t.tiId,
        t.clId,
        t.peId,
        t.tiEstatus,
        t.tiProceso,
        t.tiTipoTicket,
        t.tiFechaCreacion,
        t.tiExtra,
        t.tiNivelCriticidad,
        t.tiVisitaFecha,
        t.tiVisitaHora,
        t.tiVisitaDuracionMins,

        c.clNombre,

        pe.peSN,
        pe.peDescripcion,
        pe.peSO,

        pc.pcId,
        pc.pcTipoPoliza,
        pc.pcFechaInicio,
        pc.pcFechaFin,

        e.eqId,
        e.eqModelo,
        e.eqVersion,
        e.eqTipoEquipo,

        m.maNombre,

        cs.csId,
        cs.csNombre,
        cz.czId,
        cz.czNombre AS czNombre,

        UPPER(LEFT(REPLACE(c.clNombre, ' ', ''), 3)) AS ticketPrefix
    FROM ticket_soporte t
    INNER JOIN clientes c
        ON c.clId = t.clId
    LEFT JOIN polizasequipo pe
        ON pe.peId = t.peId
    LEFT JOIN polizascliente pc
        ON pc.pcId = pe.pcId
    LEFT JOIN equipos e
        ON e.eqId = pe.eqId
    LEFT JOIN marca m
        ON m.maId = e.maId
    LEFT JOIN cliente_sede cs
        ON cs.csId = pc.csId
    LEFT JOIN cliente_zona cz
        ON cz.czId = cs.czId
    WHERE 
        t.clId = ?
        AND t.estatus = 'Activo'
        {$extraWhere}
    ORDER BY 
        t.tiEstatus DESC,
        t.tiId DESC
";

$stmt = $conectar->prepare($sql);
if (!$stmt) {
    json_response([
        'success' => false,
        'error'   => 'Error al preparar consulta: ' . ($conectar instanceof mysqli ? $conectar->error : 'Unknown error')
    ]);
}
$stmt->bind_param('i', $clId);

if (!$stmt->execute()) {
    json_response([
        'success' => false,
        'error'   => 'Error al ejecutar consulta: ' . $stmt->error
    ]);
}

$res = $stmt->get_result();
$tickets = [];
while ($row = $res->fetch_assoc()) {
    $tickets[] = [
        'tiId'               => (int)$row['tiId'],
        'clId'               => (int)$row['clId'],
        'peId'               => $row['peId'] !== null ? (int)$row['peId'] : null,
        'tiEstatus'          => $row['tiEstatus'] ?? '',
        'tiProceso'          => $row['tiProceso'] ?? '',
        'tiTipoTicket'       => $row['tiTipoTicket'] ?? '',
        'tiFechaCreacion'    => $row['tiFechaCreacion'] ?? '',
        'tiExtra'            => $row['tiExtra'] ?? '',
        'tiNivelCriticidad'    => $row['tiNivelCriticidad'] ?? '',
        'tiVisitaFecha'      => $row['tiVisitaFecha'] ?? '',
        'tiVisitaHora'       => $row['tiVisitaHora'] ?? '',
        'tiVisitaDuracionMins'=> $row['tiVisitaDuracionMins'] !== null ? (int)$row['tiVisitaDuracionMins'] : null,

        'clNombre'           => $row['clNombre'] ?? '',

        'peSN'               => $row['peSN'] ?? '',
        'peDescripcion'      => $row['peDescripcion'] ?? '',
        'peSO'               => $row['peSO'] ?? '',

        'pcId'               => $row['pcId'] !== null ? (int)$row['pcId'] : null,
        'pcTipoPoliza'       => $row['pcTipoPoliza'] ?? '',
        'pcFechaInicio'      => $row['pcFechaInicio'] ?? '',
        'pcFechaFin'         => $row['pcFechaFin'] ?? '',

        'eqId'               => $row['eqId'] !== null ? (int)$row['eqId'] : null,
        'eqModelo'           => $row['eqModelo'] ?? '',
        'eqVersion'          => $row['eqVersion'] ?? '',
        'eqTipoEquipo'       => $row['eqTipoEquipo'] ?? '',

        'maNombre'           => $row['maNombre'] ?? '',

        'csId'               => $row['csId'] !== null ? (int)$row['csId'] : null,
        'csNombre'           => $row['csNombre'] ?? '',
        'czId'               => $row['czId'] !== null ? (int)$row['czId'] : null,
        'czNombre'           => $row['czNombre'] ?? '',

        'ticketPrefix'       => $row['ticketPrefix'] ?? 'TIC'
    ];
}
$stmt->close();

json_response([
    'success' => true,
    'tickets' => $tickets
]);
