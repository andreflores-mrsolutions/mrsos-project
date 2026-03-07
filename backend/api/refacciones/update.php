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
    $maId = (int)($body['maId'] ?? 0);
    $refPartNumber = trim((string)($body['refPartNumber'] ?? ''));
    $refDescripcion = trim((string)($body['refDescripcion'] ?? ''));
    $refTipoRefaccion = trim((string)($body['refTipoRefaccion'] ?? ''));
    $refInterfaz = trim((string)($body['refInterfaz'] ?? ''));
    $refTipo = trim((string)($body['refTipo'] ?? ''));
    $refCapacidad = (string)($body['refCapacidad'] ?? '');
    $refTpCapacidad = trim((string)($body['refTpCapacidad'] ?? ''));
    $refVelocidad = (string)($body['refVelocidad'] ?? '');
    $refTpVelocidad = trim((string)($body['refTpVelocidad'] ?? ''));
    $refEstatus = trim((string)($body['refEstatus'] ?? 'Activo'));

    $permitidos = ['Activo','Inactivo','Cambios','Error'];

    if ($refId <= 0) json_fail('ID de refacción inválido.', 422);
    if ($maId <= 0) json_fail('La marca es obligatoria.', 422);
    if ($refPartNumber === '') json_fail('El Part Number es obligatorio.', 422);
    if ($refDescripcion === '') json_fail('La descripción es obligatoria.', 422);
    if ($refTipoRefaccion === '') json_fail('El tipo de refacción es obligatorio.', 422);
    if ($refInterfaz === '') json_fail('La interfaz es obligatoria.', 422);
    if ($refTipo === '') json_fail('El tipo es obligatorio.', 422);
    if (!in_array($refEstatus, $permitidos, true)) json_fail('Estatus inválido.', 422);

    $refCapacidadNum = ($refCapacidad === '') ? 0 : (float)$refCapacidad;
    $refVelocidadNum = ($refVelocidad === '') ? 0 : (float)$refVelocidad;

    $stCurrent = $pdo->prepare("
        SELECT
            refId, refPartNumber, refDescripcion, refTipoRefaccion, refInterfaz,
            refTipo, maId, refCapacidad, refTpCapacidad, refVelocidad,
            refTpVelocidad, refEstatus
        FROM refaccion
        WHERE refId = ?
        LIMIT 1
    ");
    $stCurrent->execute([$refId]);
    $actual = $stCurrent->fetch(PDO::FETCH_ASSOC);

    if (!$actual) {
        json_fail('La refacción no existe.', 404);
    }

    $stMarca = $pdo->prepare("SELECT maId, maNombre FROM marca WHERE maId = ? LIMIT 1");
    $stMarca->execute([$maId]);
    $marca = $stMarca->fetch(PDO::FETCH_ASSOC);
    if (!$marca) json_fail('La marca seleccionada no existe.', 404);

    $stDup = $pdo->prepare("
        SELECT refId
        FROM refaccion
        WHERE refPartNumber = ? AND refId <> ?
        LIMIT 1
    ");
    $stDup->execute([$refPartNumber, $refId]);
    if ($stDup->fetch()) {
        json_fail('Ya existe otra refacción con ese Part Number.', 409);
    }

    $st = $pdo->prepare("
        UPDATE refaccion
        SET
            refPartNumber = ?, refDescripcion = ?, refTipoRefaccion = ?, refInterfaz = ?,
            refTipo = ?, maId = ?, refCapacidad = ?, refTpCapacidad = ?,
            refVelocidad = ?, refTpVelocidad = ?, refEstatus = ?
        WHERE refId = ?
        LIMIT 1
    ");
    $st->execute([
        $refPartNumber, $refDescripcion, $refTipoRefaccion, $refInterfaz,
        $refTipo, $maId, $refCapacidadNum, $refTpCapacidad,
        $refVelocidadNum, $refTpVelocidad, $refEstatus, $refId
    ]);

    if (class_exists('Historial')) {
        $cambios = [];
        foreach ([
            'refPartNumber','refDescripcion','refTipoRefaccion','refInterfaz',
            'refTipo','maId','refTpCapacidad','refTpVelocidad','refEstatus'
        ] as $campo) {
            $nuevoValor = ($campo === 'maId') ? (string)$maId : (string)$$campo;
            $viejoValor = (string)($actual[$campo] ?? '');
            if ($viejoValor !== $nuevoValor) {
                $cambios[] = "{$campo}: '{$viejoValor}' -> '{$nuevoValor}'";
            }
        }
        if ((float)$actual['refCapacidad'] !== (float)$refCapacidadNum) {
            $cambios[] = "refCapacidad: '{$actual['refCapacidad']}' -> '{$refCapacidadNum}'";
        }
        if ((float)$actual['refVelocidad'] !== (float)$refVelocidadNum) {
            $cambios[] = "refVelocidad: '{$actual['refVelocidad']}' -> '{$refVelocidadNum}'";
        }

        Historial::log(
            $pdo,
            $usId,
            'refaccion',
            "UPDATE refaccion (refId={$refId}) - " . ($cambios ? implode(' | ', $cambios) : 'Sin cambios visibles.'),
            'Activo'
        );
    }

    json_ok([
        'message' => 'Refacción actualizada correctamente.',
        'refId' => $refId
    ]);
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
        json_fail('No se pudo actualizar la refacción porque ya existe un registro igual.', 409);
    }
    json_fail('Error al actualizar la refacción: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_fail('Error al actualizar la refacción: ' . $e->getMessage(), 500);
}