<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';
require_once __DIR__ . '/../../php/ticket_scope.php';

no_store();
require_login();
require_usRol(['CLI', 'MRSA', 'MRA', 'MRV']);

function cliente_folio_prefijo(string $nombreCliente): string
{
    $nombreCliente = trim($nombreCliente);
    if ($nombreCliente === '') return 'TCK';

    $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $nombreCliente);
    $parts = preg_split('/\s+/', strtoupper((string)$clean)) ?: [];

    if (count($parts) >= 2) {
        return substr($parts[0], 0, 2) . substr($parts[1], 0, 1);
    }
    return substr(strtoupper($clean), 0, 3);
}

$pdo  = db();
$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;

if ($tiId <= 0) {
    json_fail('Falta tiId.', 400);
}

$scope = ticket_scope_from_session('ts', 'cs');

try {
    $sql = "
        SELECT
            ts.*,

            c.clNombre,

            cs.csNombre,
            cs.czId,

            cz.czNombre,

            eq.eqModelo,
            eq.eqVersion,
            eq.eqTipoEquipo,
            eq.eqDescripcion,
            eq.eqImgPath,

            pe.peSN,
            pe.peSO,

            ma.maNombre,

            CONCAT_WS(' ', u.usNombre, u.usAPaterno, u.usAMaterno) AS usuarioResponsable,
            CONCAT_WS(' ', ing.usNombre, ing.usAPaterno, ing.usAMaterno) AS ingenieroNombre

        FROM ticket_soporte ts
        LEFT JOIN clientes c
            ON c.clId = ts.clId
        LEFT JOIN cliente_sede cs
            ON cs.csId = ts.csId
        LEFT JOIN cliente_zona cz
            ON cz.czId = cs.czId
        LEFT JOIN polizasequipo pe
            ON pe.peId = ts.peId
        LEFT JOIN equipos eq
            ON eq.eqId = ts.eqId
        LEFT JOIN marca ma
            ON ma.maId = eq.maId
        LEFT JOIN usuarios u
            ON u.usId = ts.usId
        LEFT JOIN usuarios ing
            ON ing.usId = ts.usIdIng

        WHERE ts.estatus = 'Activo'
          AND ts.tiId = ?
          AND {$scope['sql']}
        LIMIT 1
    ";

    // OJO: primero va tiId, luego params del scope
    $params = array_merge([$tiId], $scope['params']);

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $r = $st->fetch(PDO::FETCH_ASSOC);

    if (!$r) {
        json_fail('Ticket no encontrado o fuera de tu alcance.', 404);
    }

    $proceso = trim((string)($r['tiProceso'] ?? ''));

    // Acción actual (lado cliente)
    $accion = [
        'key' => null,
        'title' => 'Sin acción inmediata',
        'required' => false,
        'description' => 'MR Solutions continúa trabajando en tu ticket.',
    ];

    switch ($proceso) {
        case 'logs':
            $accion = [
                'key' => 'logs',
                'title' => 'Subir logs',
                'required' => true,
                'description' => 'Necesitamos archivos de diagnóstico para continuar con la revisión.',
            ];
            break;

        case 'meet':
            $accion = [
                'key' => 'meet',
                'title' => 'Revisar o proponer Meet',
                'required' => true,
                'description' => 'Puedes confirmar una propuesta o sugerir 3 horarios para revisión remota.',
            ];
            break;

        case 'visita':
            $accion = [
                'key' => 'visita',
                'title' => 'Coordinar visita',
                'required' => true,
                'description' => 'Hace falta confirmar ventana o cargar folio/autorización para el acceso.',
            ];
            break;

        case 'encuesta satisfaccion':
            $accion = [
                'key' => 'encuesta',
                'title' => 'Responder encuesta',
                'required' => false,
                'description' => 'Tu opinión nos ayuda a mejorar el servicio.',
            ];
            break;
    }

    $prefijo = cliente_folio_prefijo((string)($r['clNombre'] ?? ''));

    $ticket = [
        'tiId' => (int)$r['tiId'],
        'folio' => $prefijo . '-' . (int)$r['tiId'],

        'clNombre' => (string)($r['clNombre'] ?? ''),
        'czNombre' => (string)($r['czNombre'] ?? ''),
        'czId' => isset($r['czId']) ? (int)$r['czId'] : null,
        'csNombre' => (string)($r['csNombre'] ?? ''),

        'tiEstatus' => (string)($r['tiEstatus'] ?? ''),
        'tiProceso' => $proceso,
        'tiTipoTicket' => (string)($r['tiTipoTicket'] ?? ''),
        'tiNivelCriticidad' => (string)($r['tiNivelCriticidad'] ?? ''),
        'tiFechaCreacion' => (string)($r['tiFechaCreacion'] ?? ''),
        'tiDescripcion' => (string)($r['tiDescripcion'] ?? ''),
        'tiDiagnostico' => (string)($r['tiDiagnostico'] ?? ''),

        'eqModelo' => (string)($r['eqModelo'] ?? ''),
        'eqVersion' => (string)($r['eqVersion'] ?? ''),
        'eqTipoEquipo' => (string)($r['eqTipoEquipo'] ?? ''),
        'eqDescripcion' => (string)($r['eqDescripcion'] ?? ''),
        'eqImgPath' => (string)($r['eqImgPath'] ?? ''),

        'maNombre' => (string)($r['maNombre'] ?? ''),
        'peSN' => (string)($r['peSN'] ?? ''),
        'peSO' => (string)($r['peSO'] ?? ''),

        'usuarioResponsable' => (string)($r['usuarioResponsable'] ?? ''),
        'ingenieroNombre' => (string)($r['ingenieroNombre'] ?? ''),

        // MEET (cache)
        'tiMeetEstado' => (string)($r['tiMeetEstado'] ?? ''),
        'tiMeetFecha' => (string)($r['tiMeetFecha'] ?? ''),
        'tiMeetHora' => (string)($r['tiMeetHora'] ?? ''),
        'tiMeetPlataforma' => (string)($r['tiMeetPlataforma'] ?? ''),
        'tiMeetEnlace' => (string)($r['tiMeetEnlace'] ?? ''),

        // VISITA (cache)
        'tiVisitaFecha' => (string)($r['tiVisitaFecha'] ?? ''),
        'tiVisitaHora' => (string)($r['tiVisitaHora'] ?? ''),
        'tiVisitaEstado' => (string)($r['tiVisitaEstado'] ?? ''),
        'tiVisitaConfirmada' => (int)($r['tiVisitaConfirmada'] ?? 0),
        'tiFolioEntrada' => (string)($r['tiFolioEntrada'] ?? ''),
        'tiFolioArchivo' => (string)($r['tiFolioArchivo'] ?? ''),

        // Acceso (si existen en tu ticket_soporte)
        'tiAccesoRequiereDatos' => (int)($r['tiAccesoRequiereDatos'] ?? 0),
        'tiAccesoExtraTexto' => (string)($r['tiAccesoExtraTexto'] ?? ''),

        'accionActual' => $accion,
    ];

    json_ok(['ticket' => $ticket, 'meta' => ['scope' => $scope['scope']]]);
} catch (Throwable $e) {
    // Deja el error detallado mientras estás corrigiendo.
    json_fail('Error al obtener detalle. ' . $e->getMessage(), 500);
}