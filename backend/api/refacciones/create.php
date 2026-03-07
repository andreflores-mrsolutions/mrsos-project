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

    if ($maId <= 0) json_fail('La marca es obligatoria.', 422);
    if ($refPartNumber === '') json_fail('El Part Number es obligatorio.', 422);
    if ($refDescripcion === '') json_fail('La descripción es obligatoria.', 422);
    if ($refTipoRefaccion === '') json_fail('El tipo de refacción es obligatorio.', 422);
    if ($refInterfaz === '') json_fail('La interfaz es obligatoria.', 422);
    if ($refTipo === '') json_fail('El tipo es obligatorio.', 422);
    if (!in_array($refEstatus, $permitidos, true)) json_fail('Estatus inválido.', 422);

    if (mb_strlen($refPartNumber) > 50) json_fail('El Part Number no puede exceder 50 caracteres.', 422);
    if (mb_strlen($refInterfaz) > 25) json_fail('La interfaz no puede exceder 25 caracteres.', 422);
    if (mb_strlen($refTipo) > 15) json_fail('El tipo no puede exceder 15 caracteres.', 422);
    if (mb_strlen($refTpCapacidad) > 15) json_fail('La unidad de capacidad no puede exceder 15 caracteres.', 422);
    if (mb_strlen($refTpVelocidad) > 15) json_fail('La unidad de velocidad no puede exceder 15 caracteres.', 422);

    $refCapacidadNum = ($refCapacidad === '') ? 0 : (float)$refCapacidad;
    $refVelocidadNum = ($refVelocidad === '') ? 0 : (float)$refVelocidad;

    $stMarca = $pdo->prepare("SELECT maId, maNombre FROM marca WHERE maId = ? LIMIT 1");
    $stMarca->execute([$maId]);
    $marca = $stMarca->fetch(PDO::FETCH_ASSOC);
    if (!$marca) json_fail('La marca seleccionada no existe.', 404);

    $stDup = $pdo->prepare("SELECT refId FROM refaccion WHERE refPartNumber = ? LIMIT 1");
    $stDup->execute([$refPartNumber]);
    if ($stDup->fetch()) {
        json_fail('Ya existe una refacción con ese Part Number.', 409);
    }

    $st = $pdo->prepare("
        INSERT INTO refaccion (
            refPartNumber, refDescripcion, refTipoRefaccion, refInterfaz, refTipo,
            maId, refCapacidad, refTpCapacidad, refVelocidad, refTpVelocidad, refEstatus
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([
        $refPartNumber, $refDescripcion, $refTipoRefaccion, $refInterfaz, $refTipo,
        $maId, $refCapacidadNum, $refTpCapacidad, $refVelocidadNum, $refTpVelocidad, $refEstatus
    ]);

    $refId = (int)$pdo->lastInsertId();

    if (class_exists('Historial')) {
        Historial::log(
            $pdo,
            $usId,
            'refaccion',
            "CREATE refaccion (refId={$refId}) - Alta de PN '{$refPartNumber}' marca '{$marca['maNombre']}' con estatus '{$refEstatus}'.",
            'Activo'
        );
    }

    json_ok([
        'message' => 'Refacción creada correctamente.',
        'refId' => $refId
    ]);
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
        json_fail('No se pudo crear la refacción porque ya existe un registro igual.', 409);
    }
    json_fail('Error al crear la refacción: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_fail('Error al crear la refacción: ' . $e->getMessage(), 500);
}