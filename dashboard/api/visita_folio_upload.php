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

$pdo = db();
$usId = (int)($_SESSION['usId'] ?? 0);
$tiId = isset($_POST['tiId']) ? (int)$_POST['tiId'] : 0;
$folio = trim((string)($_POST['tiFolioEntrada'] ?? ''));
$comentario = trim((string)($_POST['comentario'] ?? ''));

if ($tiId <= 0) json_fail('Falta tiId.', 400);
if ($folio === '') json_fail('Falta folio.', 400);
if ($usId <= 0) json_fail('Sesión inválida.', 401);

$scope = ticket_scope_from_session('ts', 'cs');

$allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
$maxBytes = 10 * 1024 * 1024;

try {
    $st = $pdo->prepare("
        SELECT ts.tiId
        FROM ticket_soporte ts
        LEFT JOIN cliente_sede cs ON cs.csId = ts.csId
        WHERE ts.tiId = ?
          AND ts.estatus = 'Activo'
          AND {$scope['sql']}
        LIMIT 1
    ");
    $params = array_merge([$tiId], $scope['params']);
    $st->execute($params);

    if (!$st->fetch(PDO::FETCH_ASSOC)) {
        json_fail('Ticket no encontrado o fuera de tu alcance.', 404);
    }

    $rel = null;

    if (isset($_FILES['folioFile']) && (int)$_FILES['folioFile']['error'] === UPLOAD_ERR_OK) {
        $orig = (string)$_FILES['folioFile']['name'];
        $tmp  = (string)$_FILES['folioFile']['tmp_name'];
        $size = (int)$_FILES['folioFile']['size'];

        if (!is_uploaded_file($tmp)) {
            json_fail('Archivo inválido.', 400);
        }

        if ($size <= 0 || $size > $maxBytes) {
            json_fail('Archivo demasiado grande.', 400);
        }

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            json_fail('Formato de archivo no permitido.', 400);
        }

        $dir = __DIR__ . '/../../uploads/folios/' . $tiId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            json_fail('No se pudo crear carpeta de folios.', 500);
        }

        $name = 'folio_' . $tiId . '_' . date('YmdHis') . '.' . $ext;
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($tmp, $dest)) {
            json_fail('No se pudo guardar el archivo.', 500);
        }

        $rel = 'uploads/folios/' . $tiId . '/' . $name;
    }

    $pdo->beginTransaction();

    // 1) Actualizar cache en ticket_soporte
    $pdo->prepare("
        UPDATE ticket_soporte
        SET
          tiFolioEntrada = ?,
          tiFolioArchivo = ?,
          tiFolioCreadoEn = NOW(),
          tiFolioCreadoPor = ?
        WHERE tiId = ?
        LIMIT 1
    ")->execute([
        $folio,
        $rel,
        $usId,
        $tiId
    ]);

    // 2) Insertar histórico en tabla de folio entrada
    $pdo->prepare("
        INSERT INTO ticket_folio_entrada
        (
          tiId,
          folio,
          archivoRuta,
          comentario,
          creadoPor
        )
        VALUES
        (
          ?, ?, ?, ?, ?
        )
    ")->execute([
        $tiId,
        $folio,
        $rel,
        $comentario,
        $usId
    ]);

    $pdo->commit();

    json_ok([
        'ok' => true,
        'tiFolioEntrada' => $folio,
        'tiFolioArchivo' => $rel
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail('Error visita_folio_upload. ' . $e->getMessage(), 500);
}