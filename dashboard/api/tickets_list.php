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

$pdo = db();
$scope = ticket_scope_from_session('ts', 'cs');

try {
    $sql = "
        SELECT
            ts.tiId,
            ts.clId,
            ts.csId,
            ts.usId,
            ts.usIdIng,
            ts.peId,
            ts.eqId,
            ts.tiDescripcion,
            ts.tiEstatus,
            ts.tiProceso,
            ts.tiTipoTicket,
            ts.tiNivelCriticidad,
            ts.tiFechaCreacion,
            ts.tiMeetEstado,
            ts.tiMeetFecha,
            ts.tiMeetHora,
            ts.tiMeetPlataforma,
            ts.tiMeetEnlace,
            ts.tiVisitaFecha,
            ts.tiVisitaHora,
            ts.tiVisitaEstado,
            ts.tiVisitaConfirmada,
            ts.tiFolioEntrada,
            ts.tiFolioArchivo,
            ts.tiUltimaRespuestaCliente,
            ts.tiUltimaRespuestaIng,
            ts.tiVenceRespuestaCliente,

            c.clNombre,

            cs.csNombre,
            cs.czId,

            cz.czNombre,

            eq.eqModelo,
            eq.eqVersion,
            eq.eqTipoEquipo,
            eq.eqImgPath,

            pe.peSN,
            pe.peSO,

            ma.maNombre
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
        WHERE ts.estatus = 'Activo'
          AND {$scope['sql']}
        ORDER BY
            c.clNombre ASC,
            cz.czNombre ASC,
            cs.csNombre ASC,
            CASE ts.tiEstatus
                WHEN 'Abierto' THEN 1
                WHEN 'Pospuesto' THEN 2
                WHEN 'Cerrado' THEN 3
                ELSE 4
            END,
            ts.tiId DESC
    ";

    $st = $pdo->prepare($sql);
    $st->execute($scope['params']);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $tickets = array_map(function (array $r): array {
        $proceso = trim((string)($r['tiProceso'] ?? ''));

        $requiereAccion = in_array($proceso, [
            'logs',
            'meet',
            'visita',
            'encuesta satisfaccion'
        ], true);

        if ($proceso === 'meet' && (string)($r['tiMeetEstado'] ?? '') === 'confirmado') {
            $requiereAccion = false;
        }

        if (
            $proceso === 'visita' &&
            (int)($r['tiVisitaConfirmada'] ?? 0) === 1 &&
            !empty($r['tiFolioEntrada'])
        ) {
            $requiereAccion = false;
        }

        $prefijo = cliente_folio_prefijo((string)($r['clNombre'] ?? ''));

        $marcaFolder = (string)$r['maNombre'];
        $modelo = (string)$r['eqModelo'];
        $imgPath = (string)$r['eqImgPath'];

        if ($imgPath || $imgPath !== '') {
            $img = $imgPath;
        } else {
            $img = "../img/Equipos/{$marcaFolder}/{$modelo}.png";
        }

        return [
            'tiId' => (int)$r['tiId'],
            'folio' => $prefijo . '-' . (int)$r['tiId'],

            'clNombre' => (string)($r['clNombre'] ?? ''),
            'csNombre' => (string)($r['csNombre'] ?? ''),
            'czNombre' => (string)($r['czNombre'] ?? ''),
            'czId' => isset($r['czId']) ? (int)$r['czId'] : null,

            'tiEstatus' => (string)($r['tiEstatus'] ?? ''),
            'tiProceso' => $proceso,
            'tiTipoTicket' => (string)($r['tiTipoTicket'] ?? ''),
            'tiNivelCriticidad' => (string)($r['tiNivelCriticidad'] ?? ''),
            'tiFechaCreacion' => (string)($r['tiFechaCreacion'] ?? ''),

            'eqModelo' => (string)($r['eqModelo'] ?? ''),
            'eqVersion' => (string)($r['eqVersion'] ?? ''),
            'eqTipoEquipo' => (string)($r['eqTipoEquipo'] ?? ''),
            'eqImgPath' => (string)($img ?? ''),

            'maNombre' => (string)($r['maNombre'] ?? ''),
            'peSN' => (string)($r['peSN'] ?? ''),
            'peSO' => (string)($r['peSO'] ?? ''),

            'tiMeetEstado' => (string)($r['tiMeetEstado'] ?? ''),
            'tiMeetFecha' => (string)($r['tiMeetFecha'] ?? ''),
            'tiMeetHora' => (string)($r['tiMeetHora'] ?? ''),
            'tiMeetPlataforma' => (string)($r['tiMeetPlataforma'] ?? ''),
            'tiMeetEnlace' => (string)($r['tiMeetEnlace'] ?? ''),

            'tiVisitaFecha' => (string)($r['tiVisitaFecha'] ?? ''),
            'tiVisitaHora' => (string)($r['tiVisitaHora'] ?? ''),
            'tiVisitaEstado' => (string)($r['tiVisitaEstado'] ?? ''),
            'tiVisitaConfirmada' => (int)($r['tiVisitaConfirmada'] ?? 0),
            'tiFolioEntrada' => (string)($r['tiFolioEntrada'] ?? ''),
            'tiFolioArchivo' => (string)($r['tiFolioArchivo'] ?? ''),

            'requiereAccionCliente' => $requiereAccion,
        ];
    }, $rows);

    json_ok([
        'tickets' => $tickets,
        'meta' => [
            'scope'    => $scope['scope'],
            'total'    => count($tickets),
            'abiertos' => count(array_filter($tickets, fn($t) => $t['tiEstatus'] === 'Abierto')),
            'accion'   => count(array_filter($tickets, fn($t) => !empty($t['requiereAccionCliente']))),
            'curso'    => count(array_filter(
                $tickets,
                fn($t) =>
                in_array($t['tiProceso'], [
                    'meet',
                    'visita',
                    'espera visita',
                    'en camino',
                    'espera documentacion'
                ], true)
            )),
        ]
    ]);
} catch (Throwable $e) {
    json_fail('Error al obtener tickets. ' . $e->getMessage(), 500);
}
