<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';
require_once __DIR__ . '/../../php/ticket_scope.php';

no_store();
require_login();
require_usRol(['CLI', 'MRSA', 'MRA', 'MRV']);

$pdo  = db();
$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;

if ($tiId <= 0) json_fail('Falta tiId.', 400);

$scope = ticket_scope_from_session('ts', 'cs');

try {
    // Validar alcance + traer meta (motivo logs)
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
    $ticket = $st->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) json_fail('Ticket no encontrado o fuera de tu alcance.', 404);

    $metaRaw = (string)($ticket['tiMeta'] ?? '');
    $meta = [];
    if ($metaRaw !== '') {
        $decoded = json_decode($metaRaw, true);
        if (is_array($decoded)) $meta = $decoded;
    }

    $logsRequest = $meta['logs_request'] ?? null;

    $st2 = $pdo->prepare("
        SELECT
            taId, tiId, taTipo, taNombreOriginal, taNombreAlmacenado,
            taMime, taTamano, taRuta, usId, fecha
        FROM ticket_archivos
        WHERE tiId = ?
          AND taTipo = 'log'
        ORDER BY taId DESC
    ");
    $st2->execute([$tiId]);
    $rows = $st2->fetchAll(PDO::FETCH_ASSOC);

    $logs = array_map(static function(array $r): array {
        return [
            'taId' => (int)$r['taId'],
            'tiId' => (int)$r['tiId'],
            'taNombreOriginal' => (string)$r['taNombreOriginal'],
            'taNombreAlmacenado' => (string)$r['taNombreAlmacenado'],
            'taMime' => (string)($r['taMime'] ?? ''),
            'taTamano' => (int)($r['taTamano'] ?? 0),
            'taRuta' => (string)$r['taRuta'],
            'usId' => (int)($r['usId'] ?? 0),
            'fecha' => (string)($r['fecha'] ?? ''),
        ];
    }, $rows);

    json_ok([
        'logs_request' => $logsRequest,
        'logs' => $logs,
        'meta' => ['scope' => $scope['scope']]
    ]);
} catch (Throwable $e) {
    json_fail('Error al obtener logs. ' . $e->getMessage(), 500);
}