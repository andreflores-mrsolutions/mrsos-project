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
    $refId = (int)($_GET['refId'] ?? 0);
    if ($refId <= 0) {
        json_fail('ID de refacción inválido.', 422);
    }

    $pdo = db();
    $st = $pdo->prepare("
        SELECT
            refId, refPartNumber, refDescripcion, refTipoRefaccion, refInterfaz,
            refTipo, maId, refCapacidad, refTpCapacidad, refVelocidad,
            refTpVelocidad, refEstatus
        FROM refaccion
        WHERE refId = ?
        LIMIT 1
    ");
    $st->execute([$refId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_fail('La refacción no existe.', 404);
    }

    json_ok(['row' => $row]);
} catch (Throwable $e) {
    json_fail('Error al obtener la refacción: ' . $e->getMessage(), 500);
}