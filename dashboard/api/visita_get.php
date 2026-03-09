<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';
require_once __DIR__ . '/../../php/ticket_scope.php';

no_store();
require_login();
require_usRol(['CLI', 'MRSA', 'MRA', 'MRV']);

$pdo = db();
$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;
if ($tiId <= 0) json_fail('Falta tiId.', 400);

$scope = ticket_scope_from_session('ts', 'cs');

try {
    $st = $pdo->prepare("
        SELECT
            ts.tiId,
            ts.tiVisitaFecha,
            ts.tiVisitaHora,
            ts.tiVisitaEstado,
            ts.tiVisitaConfirmada,
            ts.tiFolioEntrada,
            ts.tiFolioArchivo,
            ts.tiFolioCreadoEn,
            ts.tiAccesoRequiereDatos,
            ts.tiAccesoExtraTexto,
            tve.confirmada_inicio,
            tve.confirmada_fin,
            tve.lock_cancel,
            tve.estado
        FROM ticket_soporte ts
        LEFT JOIN cliente_sede cs ON cs.csId = ts.csId
        LEFT JOIN ticket_visita_estado tve ON tve.tiId = ts.tiId
        WHERE ts.tiId = ?
          AND ts.estatus = 'Activo'
          AND {$scope['sql']}
        LIMIT 1
    ");
    $params = array_merge([$tiId], $scope['params']);
    $st->execute($params);
    $ticket = $st->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) json_fail('Ticket no encontrado o fuera de tu alcance.', 404);

    $stBatch = $pdo->prepare("
        SELECT vpBatchId
        FROM ticket_visita_propuestas
        WHERE tiId = ?
          AND vpBatchId IS NOT NULL
          AND vpBatchId <> ''
        ORDER BY vpCreadoEn DESC, vpId DESC
        LIMIT 1
    ");
    $stBatch->execute([$tiId]);
    $batchId = (string)($stBatch->fetchColumn() ?: '');

    $propuestas = [];
    $autorTipo = '';

    if ($batchId !== '') {
        $st2 = $pdo->prepare("
            SELECT
                vpId,
                tiId,
                vpBatchId,
                vpAutorTipo,
                vpOpcion,
                vpInicio,
                vpFin,
                vpEstado,
                vpCreadoPor,
                vpCreadoEn
            FROM ticket_visita_propuestas
            WHERE tiId = ?
              AND vpBatchId = ?
            ORDER BY vpOpcion ASC, vpInicio ASC
        ");
        $st2->execute([$tiId, $batchId]);
        $propuestas = $st2->fetchAll(PDO::FETCH_ASSOC);

        if ($propuestas) {
            $autorTipo = (string)($propuestas[0]['vpAutorTipo'] ?? '');
        }
    }

    $stAccepted = $pdo->prepare("
        SELECT
            vpId,
            vpBatchId,
            vpAutorTipo,
            vpOpcion,
            vpInicio,
            vpFin,
            vpEstado
        FROM ticket_visita_propuestas
        WHERE tiId = ?
          AND vpEstado = 'aceptada'
        ORDER BY vpId DESC
        LIMIT 1
    ");
    $stAccepted->execute([$tiId]);
    $accepted = $stAccepted->fetch(PDO::FETCH_ASSOC) ?: null;

    $stIng = $pdo->prepare("
        SELECT
            tvi.tviId,
            tvi.usIdIng,
            tvi.rol,
            CONCAT_WS(' ', u.usNombre, u.usAPaterno, u.usAMaterno) AS nombre,
            u.usTelefono,
            u.usCorreo
        FROM ticket_visita_ingenieros tvi
        INNER JOIN usuarios u ON u.usId = tvi.usIdIng
        WHERE tvi.tiId = ?
        ORDER BY FIELD(tvi.rol,'principal','apoyo'), tvi.tviId ASC
    ");
    $stIng->execute([$tiId]);
    $ingenieros = $stIng->fetchAll(PDO::FETCH_ASSOC);

    $stVeh = $pdo->prepare("
        SELECT
            tvvId, usIdIng, placas, marca, modelo, color
        FROM ticket_visita_vehiculos
        WHERE tiId = ?
        ORDER BY tvvId ASC
    ");
    $stVeh->execute([$tiId]);
    $vehiculos = $stVeh->fetchAll(PDO::FETCH_ASSOC);

    $stPiezas = $pdo->prepare("
        SELECT
            tvpId, tipo_pieza, partNumber, serialNumber, notas
        FROM ticket_visita_piezas
        WHERE tiId = ?
        ORDER BY tvpId ASC
    ");
    $stPiezas->execute([$tiId]);
    $piezas = $stPiezas->fetchAll(PDO::FETCH_ASSOC);

    json_ok([
        'visita' => [
            'tiVisitaFecha' => (string)($ticket['tiVisitaFecha'] ?? ''),
            'tiVisitaHora' => (string)($ticket['tiVisitaHora'] ?? ''),
            'tiVisitaEstado' => (string)($ticket['tiVisitaEstado'] ?? ''),
            'tiVisitaConfirmada' => (int)($ticket['tiVisitaConfirmada'] ?? 0),
            'tiFolioEntrada' => (string)($ticket['tiFolioEntrada'] ?? ''),
            'tiFolioArchivo' => (string)($ticket['tiFolioArchivo'] ?? ''),
            'tiFolioCreadoEn' => (string)($ticket['tiFolioCreadoEn'] ?? ''),
            'tiAccesoRequiereDatos' => (int)($ticket['tiAccesoRequiereDatos'] ?? 0),
            'tiAccesoExtraTexto' => (string)($ticket['tiAccesoExtraTexto'] ?? ''),
            'confirmada_inicio' => (string)($ticket['confirmada_inicio'] ?? ''),
            'confirmada_fin' => (string)($ticket['confirmada_fin'] ?? ''),
            'lock_cancel' => (int)($ticket['lock_cancel'] ?? 0),
            'estado' => (string)($ticket['estado'] ?? ''),
        ],
        'batchId' => $batchId !== '' ? $batchId : null,
        'autorTipo' => $autorTipo,
        'accepted' => $accepted,
        'propuestas' => $propuestas,
        'ingenieros' => $ingenieros,
        'vehiculos' => $vehiculos,
        'piezas' => $piezas
    ]);
} catch (Throwable $e) {
    json_fail('Error visita_get. ' . $e->getMessage(), 500);
}