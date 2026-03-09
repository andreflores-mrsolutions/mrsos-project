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
        SELECT ts.tiId, ts.tiProceso, ts.tiEstatus
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

    $st2 = $pdo->prepare("
        SELECT
            tesId,
            tiId,
            calificacion,
            comentario,
            respondidoPor,
            respondidoEn
        FROM ticket_encuesta_satisfaccion
        WHERE tiId = ?
        ORDER BY tesId DESC
        LIMIT 1
    ");
    $st2->execute([$tiId]);
    $encuesta = $st2->fetch(PDO::FETCH_ASSOC) ?: null;

    json_ok([
        'ticket' => [
            'tiId' => (int)$ticket['tiId'],
            'tiProceso' => (string)($ticket['tiProceso'] ?? ''),
            'tiEstatus' => (string)($ticket['tiEstatus'] ?? ''),
        ],
        'encuesta' => $encuesta
    ]);
} catch (Throwable $e) {
    json_fail('Error encuesta_get. ' . $e->getMessage(), 500);
}