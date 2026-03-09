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

$pdo  = db();
$tiId = isset($_POST['tiId']) ? (int)$_POST['tiId'] : 0;
$usId = (int)($_SESSION['usId'] ?? 0);

if ($tiId <= 0) json_fail('Falta tiId.', 400);
if ($usId <= 0) json_fail('Sesión inválida.', 401);

$scope = ticket_scope_from_session('ts', 'cs');

$allowedExt = ['txt', 'log', 'zip', '7z', 'rar', 'gz', 'tar', 'json', 'csv'];
$maxFileBytes = 25 * 1024 * 1024; // 25MB por archivo (ajústalo)
$maxFiles = 10;

function safe_ext(string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return preg_replace('/[^a-z0-9]/', '', $ext);
}

try {
    // Validar que ticket existe y está en alcance
    $stTicket = $pdo->prepare("
        SELECT ts.tiId
        FROM ticket_soporte ts
        LEFT JOIN cliente_sede cs ON cs.csId = ts.csId
        WHERE ts.tiId = ?
          AND ts.estatus = 'Activo'
          AND {$scope['sql']}
        LIMIT 1
    ");
    $params = array_merge([$tiId], $scope['params']);
    $stTicket->execute($params);
    if (!$stTicket->fetch(PDO::FETCH_ASSOC)) {
        json_fail('Ticket no encontrado o fuera de tu alcance.', 404);
    }

    if (!isset($_FILES['files'])) {
        json_fail('No se recibieron archivos.', 400);
    }

    $files = $_FILES['files'];
    $count = is_array($files['name']) ? count($files['name']) : 0;
    if ($count <= 0) json_fail('No se recibieron archivos.', 400);
    if ($count > $maxFiles) json_fail("Máximo {$maxFiles} archivos por carga.", 400);

    $baseDir = __DIR__ . '/../../uploads/logs/' . $tiId;
    if (!is_dir($baseDir)) {
        if (!mkdir($baseDir, 0775, true)) {
            json_fail('No se pudo crear el directorio de logs.', 500);
        }
    }

    $ins = $pdo->prepare("
        INSERT INTO ticket_archivos
            (tiId, taTipo, taNombreOriginal, taNombreAlmacenado, taMime, taTamano, taRuta, usId)
        VALUES
            (?, 'log', ?, ?, ?, ?, ?, ?)
    ");

    $saved = [];

    for ($i = 0; $i < $count; $i++) {
        $origName = (string)($files['name'][$i] ?? '');
        $tmpName  = (string)($files['tmp_name'][$i] ?? '');
        $error    = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        $size     = (int)($files['size'][$i] ?? 0);
        $mime     = (string)($files['type'][$i] ?? '');

        if ($error !== UPLOAD_ERR_OK) continue;
        if ($origName === '' || $tmpName === '' || !is_uploaded_file($tmpName)) continue;
        if ($size <= 0) continue;
        if ($size > $maxFileBytes) continue;

        $ext = safe_ext($origName);
        if ($ext === '' || !in_array($ext, $allowedExt, true)) continue;

        $stored = 'log_' . $tiId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $baseDir . '/' . $stored;

        if (!move_uploaded_file($tmpName, $destPath)) continue;

        $rel = 'uploads/logs/' . $tiId . '/' . $stored;

        $ins->execute([$tiId, $origName, $stored, $mime, $size, $rel, $usId]);

        $saved[] = [
            'taId' => (int)$pdo->lastInsertId(),
            'taNombreOriginal' => $origName,
            'taNombreAlmacenado' => $stored,
            'taMime' => $mime,
            'taTamano' => $size,
            'taRuta' => $rel
        ];
    }
    // si se subió al menos un archivo: mover proceso a "revision especial"
    $upd = $pdo->prepare("
        UPDATE ticket_soporte
        SET tiProceso = 'revision especial'
        WHERE tiId = ?
        LIMIT 1
        ");
    $upd->execute([$tiId]);

    if (!$saved) {
        json_fail('No se guardó ningún archivo. Revisa tipo/tamaño.', 400);
    }

    json_ok([
        'saved' => $saved,
        'newProceso' => 'revision especial'
    ]);
} catch (Throwable $e) {
    json_fail('Error al subir logs. ' . $e->getMessage(), 500);
}
