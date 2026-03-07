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
    $refId = (int)($body['refId'] ?? 0);
    $invSerialNumber = trim((string)($body['invSerialNumber'] ?? ''));
    $invUbicacion = trim((string)($body['invUbicacion'] ?? ''));
    $invEstatus = trim((string)($body['invEstatus'] ?? 'Activo'));

    $permitidos = ['Activo','Inactivo','Cambios','Error'];

    if ($invId <= 0) json_fail('ID de inventario inválido.', 422);
    if ($refId <= 0) json_fail('La refacción es obligatoria.', 422);
    if ($invSerialNumber === '') json_fail('El Serial Number es obligatorio.', 422);
    if ($invUbicacion === '') json_fail('La ubicación es obligatoria.', 422);
    if (!in_array($invEstatus, $permitidos, true)) json_fail('Estatus inválido.', 422);

    $stCurrent = $pdo->prepare("SELECT invId, invSerialNumber, refId, invUbicacion, invEstatus FROM inventario WHERE invId = ? LIMIT 1");
    $stCurrent->execute([$invId]);
    $actual = $stCurrent->fetch(PDO::FETCH_ASSOC);
    if (!$actual) json_fail('La pieza no existe.', 404);

    $stRef = $pdo->prepare("SELECT refId, refPartNumber FROM refaccion WHERE refId = ? LIMIT 1");
    $stRef->execute([$refId]);
    if (!$stRef->fetch()) json_fail('La refacción seleccionada no existe.', 404);

    $stDup = $pdo->prepare("SELECT invId FROM inventario WHERE invSerialNumber = ? AND invId <> ? LIMIT 1");
    $stDup->execute([$invSerialNumber, $invId]);
    if ($stDup->fetch()) json_fail('Ya existe otra pieza con ese Serial Number.', 409);

    $st = $pdo->prepare("UPDATE inventario SET invSerialNumber = ?, refId = ?, invUbicacion = ?, invEstatus = ? WHERE invId = ? LIMIT 1");
    $st->execute([$invSerialNumber, $refId, $invUbicacion, $invEstatus, $invId]);

    if (class_exists('Historial')) {
        $cambios = [];
        foreach (['invSerialNumber','refId','invUbicacion','invEstatus'] as $campo) {
            $nuevoValor = ($campo === 'refId') ? (string)$refId : (string)$$campo;
            $viejoValor = (string)($actual[$campo] ?? '');
            if ($viejoValor !== $nuevoValor) $cambios[] = "{$campo}: '{$viejoValor}' -> '{$nuevoValor}'";
        }
        Historial::log($pdo, $usId, 'inventario', "UPDATE inventario (invId={$invId}) - " . ($cambios ? implode(' | ', $cambios) : 'Sin cambios visibles.'), 'Activo');
    }

    json_ok(['message' => 'Pieza actualizada correctamente.', 'invId' => $invId]);
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') json_fail('No se pudo actualizar la pieza porque ya existe un registro igual.', 409);
    json_fail('Error al actualizar la pieza: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_fail('Error al actualizar la pieza: ' . $e->getMessage(), 500);
}