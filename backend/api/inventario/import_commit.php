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

    $mode = trim((string)($body['mode'] ?? 'upsert'));
    $rows = $body['rows'] ?? [];

    if (!in_array($mode, ['insert_only','update_only','upsert'], true)) json_fail('Modo de importación inválido.', 422);
    if (!is_array($rows) || !$rows) json_fail('No hay filas para importar.', 422);

    $inserted = 0;
    $updated = 0;
    $skipped = 0;

    $pdo->beginTransaction();

    $stInsert = $pdo->prepare("INSERT INTO inventario (invSerialNumber, refId, invUbicacion, invEstatus) VALUES (?, ?, ?, ?)");
    $stUpdate = $pdo->prepare("UPDATE inventario SET refId = ?, invUbicacion = ?, invEstatus = ? WHERE invId = ? LIMIT 1");

    foreach ($rows as $r) {
        if (!empty($r['errors'])) { $skipped++; continue; }

        $exists = !empty($r['exists']);
        $invId = (int)($r['invId'] ?? 0);

        if ($mode === 'insert_only' && $exists) { $skipped++; continue; }
        if ($mode === 'update_only' && !$exists) { $skipped++; continue; }

        if ($exists) {
            $stUpdate->execute([
                (int)$r['refId'],
                (string)$r['invUbicacion'],
                (string)$r['invEstatus'],
                $invId
            ]);
            $updated++;
        } else {
            $stInsert->execute([
                (string)$r['invSerialNumber'],
                (int)$r['refId'],
                (string)$r['invUbicacion'],
                (string)$r['invEstatus']
            ]);
            $inserted++;
        }
    }

    $pdo->commit();

    if (class_exists('Historial')) {
        Historial::log($pdo, $usId, 'inventario', "IMPORT inventario masivo - modo={$mode}; insertados={$inserted}; actualizados={$updated}; omitidos={$skipped}.", 'Activo');
    }

    json_ok([
        'message' => 'Importación completada.',
        'summary' => [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped
        ]
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    json_fail('Error al confirmar la importación: ' . $e->getMessage(), 500);
}