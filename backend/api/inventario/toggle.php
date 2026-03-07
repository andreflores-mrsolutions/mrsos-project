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

    $invId = (int)($body['invId'] ?? 0);
    if ($invId <= 0) json_fail('ID de inventario inválido.', 422);

    $st = $pdo->prepare("SELECT invId, invSerialNumber, invEstatus FROM inventario WHERE invId = ? LIMIT 1");
    $st->execute([$invId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) json_fail('La pieza no existe.', 404);

    $nuevo = ((string)$row['invEstatus'] === 'Activo') ? 'Inactivo' : 'Activo';

    $up = $pdo->prepare("UPDATE inventario SET invEstatus = ? WHERE invId = ? LIMIT 1");
    $up->execute([$nuevo, $invId]);

    if (class_exists('Historial')) {
        Historial::log($pdo, $usId, 'inventario', "TOGGLE inventario (invId={$invId}) - Serie '{$row['invSerialNumber']}' cambió de '{$row['invEstatus']}' a '{$nuevo}'.", 'Activo');
    }

    json_ok(['message' => "Pieza {$nuevo} correctamente.", 'invId' => $invId, 'invEstatus' => $nuevo]);
} catch (Throwable $e) {
    json_fail('Error al cambiar el estatus de la pieza: ' . $e->getMessage(), 500);
}