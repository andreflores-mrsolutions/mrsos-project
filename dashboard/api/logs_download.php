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

try {
    $st = $pdo->prepare("
        SELECT a.taId, a.tiId, a.taNombreOriginal, a.taNombreAlmacenado, a.taMime, a.taRuta
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

    $rel = (string)$row['taRuta'];
    // Seguridad: solo permitir dentro de uploads/logs/<tiId>/
    $tiId = (int)$row['tiId'];
    $expectedPrefix = "uploads/logs/{$tiId}/";
    if (strpos($rel, $expectedPrefix) !== 0) {
        json_fail('Ruta inválida.', 400);
    }

    $abs = __DIR__ . '/../../' . $rel;
    if (!is_file($abs)) json_fail('Archivo no existe en disco.', 404);

    $mime = (string)($row['taMime'] ?? 'application/octet-stream');
    $name = (string)($row['taNombreOriginal'] ?? basename($abs));

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . str_replace('"','', $name) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($abs));

    readfile($abs);
    exit;
} catch (Throwable $e) {
    json_fail('Error de descarga. ' . $e->getMessage(), 500);
}