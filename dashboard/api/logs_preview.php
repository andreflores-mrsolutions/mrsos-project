<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';
require_once __DIR__ . '/../../php/ticket_scope.php';

no_store();
require_login();
require_usRol(['CLI','MRSA','MRA','MRV']);

$pdo = db();
$taId = isset($_GET['taId']) ? (int)$_GET['taId'] : 0;

if ($taId <= 0) json_fail('Falta taId.', 400);

$scope = ticket_scope_from_session('ts','cs');

$maxPreviewBytes = 200 * 1024; // 200KB

try {
    $st = $pdo->prepare("
        SELECT a.taId, a.tiId, a.taNombreOriginal, a.taMime, a.taRuta, a.taTamano
        FROM ticket_archivos a
        INNER JOIN ticket_soporte ts ON ts.tiId = a.tiId
        LEFT JOIN cliente_sede cs ON cs.csId = ts.csId
        WHERE a.taId = ?
          AND a.taTipo = 'log'
          AND ts.estatus = 'Activo'
          AND {$scope['sql']}
        LIMIT 1
    ");
    $params = array_merge([$taId], $scope['params']);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) json_fail('Archivo no encontrado o fuera de tu alcance.', 404);

    $tiId = (int)$row['tiId'];
    $rel = (string)$row['taRuta'];
    $expectedPrefix = "uploads/logs/{$tiId}/";
    if (strpos($rel, $expectedPrefix) !== 0) json_fail('Ruta inválida.', 400);

    $abs = __DIR__ . '/../../' . $rel;
    if (!is_file($abs)) json_fail('Archivo no existe en disco.', 404);

    $name = (string)($row['taNombreOriginal'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['txt','log','json','csv'];

    if (!in_array($ext, $allowed, true)) {
        json_fail('Este archivo no soporta previsualización.', 400);
    }

    $size = (int)($row['taTamano'] ?? 0);
    if ($size <= 0 || $size > $maxPreviewBytes) {
        json_fail('Archivo demasiado grande para previsualizar.', 400);
    }

    $content = file_get_contents($abs);
    if ($content === false) json_fail('No se pudo leer el archivo.', 500);

    // sanitizar mínimo (evitar binarios)
    $content = preg_replace('/[^\P{C}\n\r\t]/u', '', (string)$content);

    json_ok([
        'taId' => (int)$row['taId'],
        'name' => $name,
        'size' => $size,
        'content' => $content
    ]);
} catch (Throwable $e) {
    json_fail('Error de previsualización. ' . $e->getMessage(), 500);
}