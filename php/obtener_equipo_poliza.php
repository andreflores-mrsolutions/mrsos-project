<?php
/**
 * obtener_equipo_poliza.php
 *
 * - SIN pe / peId  -> modo LISTA: devuelve todos los equipos visibles para el usuario
 * - CON pe / peId  -> modo DETALLE: devuelve solo ese equipo (para QR, etc.)
 *
 * Usa:
 *   - $_SESSION['usId']
 *   - $_SESSION['usRol']  ('AC','UC','EC','MRA','MRV','MRSA')
 *   - $_SESSION['clId']   (cliente activo)
 *
 * Y los permisos de usuario_cliente_rol:
 *   - ADMIN_GLOBAL, ADMIN_ZONA, ADMIN_SEDE, USUARIO, VISOR
 */

declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// 🔌 Conexión (ajusta la ruta si es distinta)
require_once __DIR__ . '/../php/conexion.php'; // debe definir $conectar (mysqli)

function json_response(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Calcula el estado de la póliza con base en la fecha fin.
 *
 * @param string|null        $fechaFin      Fecha fin de la póliza (Y-m-d) o null
 * @param DateTimeImmutable  $hoy           Fecha actual
 * @param int                $diasWarn      Días para considerar "próxima a vencer"
 * @return array{0:string,1:?int,2:bool}    [estado, diasRestantes, puedeCrearTicket]
 *                                         estado: vigente|proxima|vencida
 */
function calcularEstadoPoliza(?string $fechaFin, DateTimeImmutable $hoy, int $diasWarn): array
{
    if (empty($fechaFin) || $fechaFin === '0000-00-00') {
        // Sin fecha válida -> lo tratamos como vigente sin conteo de días
        return ['vigente', null, true];
    }

    try {
        $fin = new DateTimeImmutable($fechaFin);
    } catch (Throwable $e) {
        // Si la fecha es inválida, también lo tratamos como vigente
        return ['vigente', null, true];
    }

    // %r%a -> días con signo (negativo si ya venció)
    $dias = (int)$hoy->diff($fin)->format('%r%a');

    if ($dias < 0) {
        return ['vencida', $dias, false];
    }

    if ($dias <= $diasWarn) {
        return ['proxima', $dias, true];
    }

    return ['vigente', $dias, true];
}

// 1) Validar sesión base
$usId        = isset($_SESSION['usId'])  ? (int)$_SESSION['usId']  : 0;
$usRol       = $_SESSION['usRol']       ?? null;
$clIdSession = isset($_SESSION['clId']) ? (int)$_SESSION['clId']   : 0;

if ($usId <= 0 || !$usRol) {
    json_response([
        'success' => false,
        'error'   => 'Sesión no válida. Inicia sesión nuevamente.'
    ]);
}

// 2) Leer peId (para modo detalle)
$peId = 0;
if (isset($_REQUEST['peId'])) {
    $peId = (int)$_REQUEST['peId'];
} elseif (isset($_REQUEST['pe'])) {
    $peId = (int)$_REQUEST['pe'];
}

// Roles MR (acceso más global)
$isMR = in_array($usRol, ['MRA', 'MRSA', 'MRV'], true);

// Configuración de días de advertencia
$HOY = new DateTimeImmutable('today');
$DIAS_ADVERTENCIA = 30;

// 3) SELECT base que usaremos en ambos modos
$selectCommon = "
    SELECT 
        pe.peId,
        pe.pcId,
        pe.eqId,
        pe.peDescripcion,
        pe.peSN,
        pe.peSO,
        pe.peEstatus,

        pc.clId,
        pc.csId,
        pc.pcTipoPoliza,
        pc.pcEstatus,
        pc.pcFechaInicio,
        pc.pcFechaFin,

        e.eqModelo,
        e.eqVersion,
        e.eqTipoEquipo,

        m.maNombre,

        c.clNombre,
        cs.csNombre,
        cs.czId  AS csZonaId,

        -- Prefijo de ticket a partir del nombre del cliente, ej. ENE
        UPPER(LEFT(REPLACE(c.clNombre, ' ', ''), 3)) AS ticketPrefix,

        -- Número de tickets abiertos/pospuestos activos para esta póliza
        (
            SELECT COUNT(*)
            FROM ticket_soporte t
            WHERE 
                t.peId   = pe.peId
                AND t.clId = pc.clId
                AND t.estatus = 'Activo'
                AND t.tiEstatus IN ('Abierto','Pospuesto')
        ) AS ticketsAbiertosCount,

        -- IDs de tickets abiertos/pospuestos activos, separados por coma
        (
            SELECT GROUP_CONCAT(t2.tiId ORDER BY t2.tiId SEPARATOR ',')
            FROM ticket_soporte t2
            WHERE 
                t2.peId   = pe.peId
                AND t2.clId = pc.clId
                AND t2.estatus = 'Activo'
                AND t2.tiEstatus IN ('Abierto','Pospuesto')
        ) AS ticketsAbiertosIds
    FROM polizasequipo pe
    INNER JOIN polizascliente pc ON pc.pcId = pe.pcId
    INNER JOIN equipos        e  ON e.eqId  = pe.eqId
    INNER JOIN marca          m  ON m.maId  = e.maId
    INNER JOIN clientes       c  ON c.clId  = pc.clId
    LEFT  JOIN cliente_sede   cs ON cs.csId = pc.csId
    WHERE 
        pe.peEstatus = 'Activo'
        AND pc.pcEstatus = 'Activo'
";

// --------------------------------------------------------
// 4) MODO DETALLE: si viene peId (para QR, etc.)
// --------------------------------------------------------
if ($peId > 0) {
    if ($isMR) {
        // MR: acceso global por peId
        $sql  = $selectCommon . " AND pe.peId = ? ";
        $stmt = $conectar->prepare($sql);
        if (!$stmt) {
            json_response([
                'success' => false,
                'error'   => 'Error al preparar consulta (MR-detalle): ' . $conectar->error,
            ]);
        }
        $stmt->bind_param('i', $peId);
    } else {
        // Clientes: validar contra usuario_cliente_rol
        $sql = $selectCommon . "
            AND pe.peId = ?
            AND EXISTS (
                SELECT 1
                FROM usuario_cliente_rol ucr
                WHERE 
                    ucr.usId        = ?
                    AND ucr.clId    = pc.clId
                    AND ucr.ucrEstatus = 'Activo'
                    AND (
                        -- Admin global: todas las sedes del cliente
                        ucr.ucrRol = 'ADMIN_GLOBAL'

                        -- Admin de zona: la sede de la póliza debe estar en su czId
                        OR (ucr.ucrRol = 'ADMIN_ZONA'
                            AND cs.czId IS NOT NULL
                            AND cs.czId = ucr.czId)

                        -- Admin de sede / usuario / visor: misma sede
                        OR (ucr.ucrRol IN ('ADMIN_SEDE','USUARIO','VISOR')
                            AND pc.csId IS NOT NULL
                            AND pc.csId = ucr.csId)
                    )
            )
        ";
        $stmt = $conectar->prepare($sql);
        if (!$stmt) {
            json_response([
                'success' => false,
                'error'   => 'Error al preparar consulta (cliente-detalle): ' . $conectar->error,
            ]);
        }
        // Orden: peId, usId
        $stmt->bind_param('ii', $peId, $usId);
    }

    if (!$stmt->execute()) {
        json_response([
            'success' => false,
            'error'   => 'Error al ejecutar consulta (detalle): ' . $stmt->error,
        ]);
    }

    $res    = $stmt->get_result();
    $equipo = $res->fetch_assoc();
    $stmt->close();

    if (!$equipo) {
        json_response([
            'success' => false,
            'error'   => 'No se encontró el equipo o no tienes permisos para ver esta póliza.',
        ]);
    }

    // ➕ Añadimos información de vigencia de póliza
    [$estado, $diasRest, $puedeCrear] = calcularEstadoPoliza($equipo['pcFechaFin'] ?? null, $HOY, $DIAS_ADVERTENCIA);
    $equipo['polizaEstado']    = $estado;       // vigente | proxima | vencida
    $equipo['diasRestantes']   = $diasRest;     // puede ser negativo
    $equipo['puedeCrearTicket'] = $puedeCrear;  // bool

    json_response([
        'success'          => true,
        'mode'             => 'detalle',
        'equipo'           => $equipo,
        'hoy'              => $HOY->format('Y-m-d'),
        'diasAdvertencia'  => $DIAS_ADVERTENCIA
    ]);
}

// --------------------------------------------------------
// 5) MODO LISTA: sin peId, lista de equipos visibles
// --------------------------------------------------------

if (!$isMR && $clIdSession <= 0) {
    json_response([
        'success' => false,
        'error'   => 'No hay cliente activo en la sesión.',
    ]);
}

if ($isMR) {
    // MR: si hay cliente activo en sesión, filtramos por él; si no, lista global
    if ($clIdSession > 0) {
        $sql  = $selectCommon . " AND pc.clId = ? ORDER BY e.eqTipoEquipo, e.eqModelo ";
        $stmt = $conectar->prepare($sql);
        if (!$stmt) {
            json_response([
                'success' => false,
                'error'   => 'Error al preparar consulta lista (MR): ' . $conectar->error,
            ]);
        }
        $stmt->bind_param('i', $clIdSession);
    } else {
        $sql  = $selectCommon . " ORDER BY c.clNombre, e.eqTipoEquipo, e.eqModelo ";
        $stmt = $conectar->prepare($sql);
        if (!$stmt) {
            json_response([
                'success' => false,
                'error'   => 'Error al preparar consulta lista global (MR): ' . $conectar->error,
            ]);
        }
    }
} else {
    // Clientes: clId de sesión + permisos usuario_cliente_rol
    $sql = $selectCommon . "
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
        ORDER BY e.eqTipoEquipo, e.eqModelo
    ";
    $stmt = $conectar->prepare($sql);
    if (!$stmt) {
        json_response([
            'success' => false,
            'error'   => 'Error al preparar consulta lista (cliente): ' . $conectar->error,
        ]);
    }
    $stmt->bind_param('ii', $clIdSession, $usId);
}

if (!$stmt->execute()) {
    json_response([
        'success' => false,
        'error'   => 'Error al ejecutar consulta lista: ' . $stmt->error,
    ]);
}

$res     = $stmt->get_result();
$equipos = [];
while ($row = $res->fetch_assoc()) {
    // ➕ Añadimos información de vigencia de póliza por cada equipo
    [$estado, $diasRest, $puedeCrear] = calcularEstadoPoliza($row['pcFechaFin'] ?? null, $HOY, $DIAS_ADVERTENCIA);
    $row['polizaEstado']     = $estado;
    $row['diasRestantes']    = $diasRest;
    $row['puedeCrearTicket'] = $puedeCrear;

    $equipos[] = $row;
}
$stmt->close();

json_response([
    'success'          => true,
    'mode'             => 'lista',
    'equipos'          => $equipos,
    'hoy'              => $HOY->format('Y-m-d'),
    'diasAdvertencia'  => $DIAS_ADVERTENCIA
]);
