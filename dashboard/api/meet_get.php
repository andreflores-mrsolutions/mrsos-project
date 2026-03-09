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

if ($tiId <= 0) {
    json_fail('Falta tiId.', 400);
}

$scope = ticket_scope_from_session('ts', 'cs');

try {
    $st = $pdo->prepare("
        SELECT
            ts.tiId,
            ts.tiMeetEstado,
            ts.tiMeetFecha,
            ts.tiMeetHora,
            ts.tiMeetPlataforma,
            ts.tiMeetEnlace
        FROM ticket_soporte ts
        LEFT JOIN cliente_sede cs ON cs.csId = ts.csId
        WHERE ts.tiId = ?
          AND ts.estatus = 'Activo'
          AND {$scope['sql']}
        LIMIT 1
    ");
    $params = array_merge([$tiId], $scope['params']);
    $st->execute($params);
    $ticket = $st->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        json_fail('Ticket no encontrado o fuera de tu alcance.', 404);
    }

    $stBatch = $pdo->prepare("
        SELECT mpBatchId
        FROM ticket_meet_propuestas
        WHERE tiId = ?
          AND mpBatchId IS NOT NULL
          AND mpBatchId <> ''
        ORDER BY mpCreadoEn DESC, mpId DESC
        LIMIT 1
    ");
    $stBatch->execute([$tiId]);
    $batchId = (string)($stBatch->fetchColumn() ?: '');

    $propuestas = [];
    $autorTipo = '';

    if ($batchId !== '') {
        $st2 = $pdo->prepare("
            SELECT
                mpId,
                tiId,
                mpBatchId,
                mpAutorTipo,
                mpModo,
                mpPlataforma,
                mpLink,
                mpMotivo,
                mpInicio,
                mpFin,
                mpEstado,
                mpCreadoPor,
                mpCreadoEn
            FROM ticket_meet_propuestas
            WHERE tiId = ?
              AND mpBatchId = ?
            ORDER BY mpInicio ASC, mpId ASC
        ");
        $st2->execute([$tiId, $batchId]);
        $propuestas = $st2->fetchAll(PDO::FETCH_ASSOC);

        if ($propuestas) {
            $autorTipo = (string)($propuestas[0]['mpAutorTipo'] ?? '');
        }
    }

    // ✅ propuesta aceptada/confirmada
    $stAccepted = $pdo->prepare("
        SELECT
            mpId,
            mpAutorTipo,
            mpModo,
            mpPlataforma,
            mpLink,
            mpMotivo,
            mpInicio,
            mpFin,
            mpEstado
        FROM ticket_meet_propuestas
        WHERE tiId = ?
          AND mpEstado = 'aceptada'
        ORDER BY mpId DESC
        LIMIT 1
    ");
    $stAccepted->execute([$tiId]);
    $accepted = $stAccepted->fetch(PDO::FETCH_ASSOC);

    json_ok([
        'meet' => [
            'estado' => (string)($ticket['tiMeetEstado'] ?? ''),
            'fecha' => (string)($ticket['tiMeetFecha'] ?? ''),
            'hora' => (string)($ticket['tiMeetHora'] ?? ''),
            'plataforma' => (string)($ticket['tiMeetPlataforma'] ?? ''),
            'enlace' => (string)($ticket['tiMeetEnlace'] ?? ''),
        ],
        'batchId' => $batchId !== '' ? $batchId : null,
        'autorTipo' => $autorTipo,
        'propuestas' => $propuestas,
        'accepted' => $accepted ?: null
    ]);
} catch (Throwable $e) {
    json_fail('Error meet_get. ' . $e->getMessage(), 500);
}