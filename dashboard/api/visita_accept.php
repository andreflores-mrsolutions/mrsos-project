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
if (!is_array($raw)) $raw = $_POST;

$vpId = isset($raw['vpId']) ? (int)$raw['vpId'] : 0;
if ($vpId <= 0) json_fail('Falta vpId.', 400);

$scope = ticket_scope_from_session('ts', 'cs');

try {
    $st = $pdo->prepare("
        SELECT
            p.vpId, p.tiId, p.vpBatchId, p.vpInicio, p.vpFin, p.vpEstado
        FROM ticket_visita_propuestas p
        INNER JOIN ticket_soporte ts ON ts.tiId = p.tiId
        LEFT JOIN cliente_sede cs ON cs.csId = ts.csId
        WHERE p.vpId = ?
          AND ts.estatus = 'Activo'
          AND {$scope['sql']}
        LIMIT 1
    ");
    $params = array_merge([$vpId], $scope['params']);
    $st->execute($params);
    $p = $st->fetch(PDO::FETCH_ASSOC);

    if (!$p) json_fail('Propuesta no encontrada o fuera de tu alcance.', 404);

    $tiId = (int)$p['tiId'];
    $batchId = (string)$p['vpBatchId'];

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE ticket_visita_propuestas
        SET vpEstado = 'aceptada'
        WHERE vpId = ?
        LIMIT 1
    ")->execute([$vpId]);

    $pdo->prepare("
        UPDATE ticket_visita_propuestas
        SET vpEstado = 'rechazada'
        WHERE tiId = ?
          AND vpBatchId = ?
          AND vpId <> ?
    ")->execute([$tiId, $batchId, $vpId]);

    $pdo->prepare("
        INSERT INTO ticket_visita_estado
        (tiId, confirmada_inicio, confirmada_fin, lock_cancel, estado)
        VALUES (?, ?, ?, 1, 'confirmada')
        ON DUPLICATE KEY UPDATE
          confirmada_inicio = VALUES(confirmada_inicio),
          confirmada_fin = VALUES(confirmada_fin),
          lock_cancel = 1,
          estado = 'confirmada'
    ")->execute([$tiId, $p['vpInicio'], $p['vpFin']]);

    $dt = new DateTime((string)$p['vpInicio']);

    $pdo->prepare("
        UPDATE ticket_soporte
        SET
          tiVisitaConfirmada = 1,
          tiVisitaEstado = 'confirmada',
          tiVisitaFecha = ?,
          tiVisitaHora = ?
        WHERE tiId = ?
        LIMIT 1
    ")->execute([
        $dt->format('Y-m-d'),
        $dt->format('H:i:s'),
        $tiId
    ]);

    $pdo->commit();

    json_ok(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail('Error visita_accept. ' . $e->getMessage(), 500);
}