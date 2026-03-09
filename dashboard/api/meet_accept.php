<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';
require_once __DIR__ . '/../../php/ticket_scope.php';

no_store();
require_login();
require_usRol(['CLI', 'MRSA', 'MRA', 'MRV']);
csrf_verify_or_fail();

$pdo = db();

$raw = json_decode(file_get_contents('php://input'), true);
if (!is_array($raw)) {
    $raw = $_POST;
}

$mpId = isset($raw['mpId']) ? (int)$raw['mpId'] : 0;
if ($mpId <= 0) {
    json_fail('Falta mpId.', 400);
}

$scope = ticket_scope_from_session('ts', 'cs');

try {
    $st = $pdo->prepare("
        SELECT
            p.mpId,
            p.tiId,
            p.mpBatchId,
            p.mpPlataforma,
            p.mpLink,
            p.mpInicio,
            p.mpFin,
            p.mpEstado
        FROM ticket_meet_propuestas p
        INNER JOIN ticket_soporte ts ON ts.tiId = p.tiId
        LEFT JOIN cliente_sede cs ON cs.csId = ts.csId
        WHERE p.mpId = ?
          AND ts.estatus = 'Activo'
          AND {$scope['sql']}
        LIMIT 1
    ");
    $params = array_merge([$mpId], $scope['params']);
    $st->execute($params);
    $p = $st->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        json_fail('Propuesta no encontrada o fuera de tu alcance.', 404);
    }

    $tiId = (int)$p['tiId'];
    $batchId = (string)$p['mpBatchId'];
    $inicio = (string)$p['mpInicio'];

    $fecha = '';
    $hora = '';
    if ($inicio !== '') {
        $dt = new DateTime($inicio);
        $fecha = $dt->format('Y-m-d');
        $hora = $dt->format('H:i:s');
    }

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE ticket_meet_propuestas
        SET mpEstado = 'aceptada'
        WHERE mpId = ?
        LIMIT 1
    ")->execute([$mpId]);

    $pdo->prepare("
        UPDATE ticket_meet_propuestas
        SET mpEstado = 'rechazada'
        WHERE tiId = ?
          AND mpBatchId = ?
          AND mpId <> ?
    ")->execute([$tiId, $batchId, $mpId]);

    $pdo->prepare("
        UPDATE ticket_soporte
        SET
            tiMeetEstado = 'confirmado',
            tiMeetFecha = ?,
            tiMeetHora = ?,
            tiMeetPlataforma = ?,
            tiMeetEnlace = ?
        WHERE tiId = ?
        LIMIT 1
    ")->execute([
        $fecha,
        $hora,
        (string)($p['mpPlataforma'] ?? ''),
        (string)($p['mpLink'] ?? ''),
        $tiId
    ]);

    $pdo->commit();

    json_ok([
        'ok' => true,
        'tiId' => $tiId,
        'fecha' => $fecha,
        'hora' => $hora
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail('Error meet_accept. ' . $e->getMessage(), 500);
}