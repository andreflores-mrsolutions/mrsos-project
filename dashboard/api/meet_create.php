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
$mpModo = trim((string)($raw['modo'] ?? 'remoto'));
$mpPlataforma = trim((string)($raw['plataforma'] ?? ''));
$mpLink = trim((string)($raw['enlace'] ?? ''));
$mpMotivo = trim((string)($raw['motivo'] ?? ''));
$slots = $raw['slots'] ?? null;

if ($tiId <= 0) json_fail('Falta tiId.', 400);
if ($usId <= 0) json_fail('Sesión inválida.', 401);
if (!is_array($slots) || count($slots) !== 3) {
    json_fail('Debes enviar exactamente 3 propuestas.', 400);
}

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

    $batchId = 'BATCH-' . $tiId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));

    $pdo->beginTransaction();

    $ins = $pdo->prepare("
        INSERT INTO ticket_meet_propuestas
        (
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
            mpCreadoPor
        )
        VALUES
        (
            ?, ?, 'cliente', ?, ?, ?, ?, ?, ?, 'pendiente', ?
        )
    ");

    $guardadas = 0;

    foreach ($slots as $slot) {
        $inicio = trim((string)($slot['inicio'] ?? ''));
        $fin    = trim((string)($slot['fin'] ?? ''));

        if ($inicio === '' || $fin === '') {
            continue;
        }

        $ins->execute([
            $tiId,
            $batchId,
            'propuesta',
            $mpPlataforma,
            $mpLink,
            $mpMotivo,
            $inicio,
            $fin,
            $usId
        ]);

        $guardadas++;
    }

    if ($guardadas !== 3) {
        $pdo->rollBack();
        json_fail('No se pudieron registrar las 3 propuestas correctamente.', 400);
    }

    $upd = $pdo->prepare("
        UPDATE ticket_soporte
        SET tiMeetEstado = 'pendiente'
        WHERE tiId = ?
        LIMIT 1
    ");
    $upd->execute([$tiId]);

    $pdo->commit();

    json_ok([
        'ok' => true,
        'batchId' => $batchId,
        'guardadas' => $guardadas
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail('Error meet_create. ' . $e->getMessage(), 500);
}