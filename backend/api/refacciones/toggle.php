<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/historial.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

try {
    $pdo = db();
    $body = read_json_body();
    $usId = (int)($_SESSION['usId'] ?? 0);

    $refId = (int)($body['refId'] ?? 0);
    if ($refId <= 0) {
        json_fail('ID de refacción inválido.', 422);
    }

    $st = $pdo->prepare("
        SELECT refId, refPartNumber, refEstatus
        FROM refaccion
        WHERE refId = ?
        LIMIT 1
    ");
    $st->execute([$refId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_fail('La refacción no existe.', 404);
    }

    $nuevo = ((string)$row['refEstatus'] === 'Activo') ? 'Inactivo' : 'Activo';

    $up = $pdo->prepare("
        UPDATE refaccion
        SET refEstatus = ?
        WHERE refId = ?
        LIMIT 1
    ");
    $up->execute([$nuevo, $refId]);

    if (class_exists('Historial')) {
        Historial::log(
            $pdo,
            $usId,
            'refaccion',
            "TOGGLE refaccion (refId={$refId}) - '{$row['refPartNumber']}' cambió de '{$row['refEstatus']}' a '{$nuevo}'.",
            'Activo'
        );
    }

    json_ok([
        'message' => "Refacción {$nuevo} correctamente.",
        'refId' => $refId,
        'refEstatus' => $nuevo
    ]);
} catch (Throwable $e) {
    json_fail('Error al cambiar el estatus de la refacción: ' . $e->getMessage(), 500);
}