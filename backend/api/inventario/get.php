<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);

try {
    $invId = (int)($_GET['invId'] ?? 0);
    if ($invId <= 0) json_fail('ID de inventario inválido.', 422);

    $pdo = db();
    $st = $pdo->prepare("
        SELECT
            i.invId, i.invSerialNumber, i.refId, i.invUbicacion, i.invEstatus,
            r.maId
        FROM inventario i
        INNER JOIN refaccion r ON r.refId = i.refId
        WHERE i.invId = ?
        LIMIT 1
    ");
    $st->execute([$invId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) json_fail('La pieza no existe.', 404);

    json_ok(['row' => $row]);
} catch (Throwable $e) {
    json_fail('Error al obtener la pieza: ' . $e->getMessage(), 500);
}