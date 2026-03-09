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
$usId = (int)($_SESSION['usId'] ?? 0);

$raw = json_decode(file_get_contents('php://input'), true);
if (!is_array($raw)) {
    $raw = $_POST;
}

$tiId = isset($raw['tiId']) ? (int)$raw['tiId'] : 0;
$calificacion = isset($raw['calificacion']) ? (int)$raw['calificacion'] : 0;
$comentario = trim((string)($raw['comentario'] ?? ''));

if ($tiId <= 0) json_fail('Falta tiId.', 400);
if ($usId <= 0) json_fail('Sesión inválida.', 401);
if ($calificacion < 1 || $calificacion > 5) json_fail('La calificación debe estar entre 1 y 5.', 400);

$scope = ticket_scope_from_session('ts', 'cs');

try {
    $st = $pdo->prepare("
        SELECT ts.tiId
        FROM ticket_soporte ts
        LEFT JOIN cliente_sede cs ON cs.csId = ts.csId
        WHERE ts.tiId = ?
          AND ts.estatus = 'Activo'
          AND {$scope['sql']}
        LIMIT 1
    ");
    $params = array_merge([$tiId], $scope['params']);
    $st->execute($params);

    if (!$st->fetch(PDO::FETCH_ASSOC)) {
        json_fail('Ticket no encontrado o fuera de tu alcance.', 404);
    }

    $pdo->beginTransaction();

    $stExists = $pdo->prepare("
        SELECT tesId
        FROM ticket_encuesta_satisfaccion
        WHERE tiId = ?
        LIMIT 1
    ");
    $stExists->execute([$tiId]);
    $exists = $stExists->fetchColumn();

    if ($exists) {
        $pdo->prepare("
            UPDATE ticket_encuesta_satisfaccion
            SET
              calificacion = ?,
              comentario = ?,
              respondidoPor = ?,
              respondidoEn = NOW()
            WHERE tiId = ?
            LIMIT 1
        ")->execute([$calificacion, $comentario, $usId, $tiId]);
    } else {
        $pdo->prepare("
            INSERT INTO ticket_encuesta_satisfaccion
            (
              tiId,
              calificacion,
              comentario,
              respondidoPor,
              respondidoEn
            )
            VALUES
            (
              ?, ?, ?, ?, NOW()
            )
        ")->execute([$tiId, $calificacion, $comentario, $usId]);
    }

    // opcional: cerrar proceso si estaba en encuesta
    $pdo->prepare("
        UPDATE ticket_soporte
        SET tiProceso = 'finalizado'
        WHERE tiId = ?
          AND tiProceso = 'encuesta satisfaccion'
        LIMIT 1
    ")->execute([$tiId]);

    $pdo->commit();

    json_ok([
        'ok' => true,
        'calificacion' => $calificacion
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail('Error encuesta_save. ' . $e->getMessage(), 500);
}