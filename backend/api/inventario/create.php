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
    $invSerialNumber = trim((string)($body['invSerialNumber'] ?? ''));
    $invUbicacion = trim((string)($body['invUbicacion'] ?? ''));
    $invEstatus = trim((string)($body['invEstatus'] ?? 'Activo'));

    $permitidos = ['Activo','Inactivo','Cambios','Error'];

    if ($refId <= 0) json_fail('La refacción es obligatoria.', 422);
    if ($invSerialNumber === '') json_fail('El Serial Number es obligatorio.', 422);
    if ($invUbicacion === '') json_fail('La ubicación es obligatoria.', 422);
    if (!in_array($invEstatus, $permitidos, true)) json_fail('Estatus inválido.', 422);
    if (strlen($invSerialNumber) > 30) json_fail('El Serial Number no puede exceder 30 caracteres.', 422);
    if (mb_strlen($invUbicacion) > 15) json_fail('La ubicación no puede exceder 15 caracteres.', 422);

    $stRef = $pdo->prepare("SELECT r.refId, r.refPartNumber, m.maNombre FROM refaccion r INNER JOIN marca m ON m.maId = r.maId WHERE r.refId = ? LIMIT 1");
    $stRef->execute([$refId]);
    $ref = $stRef->fetch(PDO::FETCH_ASSOC);
    if (!$ref) json_fail('La refacción seleccionada no existe.', 404);

    $stDup = $pdo->prepare("SELECT invId FROM inventario WHERE invSerialNumber = ? LIMIT 1");
    $stDup->execute([$invSerialNumber]);
    if ($stDup->fetch()) json_fail('Ya existe una pieza con ese Serial Number.', 409);

    $st = $pdo->prepare("INSERT INTO inventario (invSerialNumber, refId, invUbicacion, invEstatus) VALUES (?, ?, ?, ?)");
    $st->execute([$invSerialNumber, $refId, $invUbicacion, $invEstatus]);

    $invId = (int)$pdo->lastInsertId();

    if (class_exists('Historial')) {
        Historial::log($pdo, $usId, 'inventario', "CREATE inventario (invId={$invId}) - Serie '{$invSerialNumber}', PN '{$ref['refPartNumber']}', marca '{$ref['maNombre']}', ubicación '{$invUbicacion}', estatus '{$invEstatus}'.", 'Activo');
    }

    json_ok(['message' => 'Pieza creada correctamente.', 'invId' => $invId]);
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') json_fail('No se pudo crear la pieza porque ya existe un registro igual.', 409);
    json_fail('Error al crear la pieza: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_fail('Error al crear la pieza: ' . $e->getMessage(), 500);
}