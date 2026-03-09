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

if ($tiId <= 0) {
    json_fail('Falta tiId.', 400);
}

$scope = ticket_scope_from_session('ts', 'cs');

try {
    // 1) Validar que el ticket existe y está dentro del alcance del usuario
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

    // 2) Traer últimos 3 movimientos
    // Ajusta esta consulta si tu historial se relaciona diferente.
    // Aquí usamos el patrón que ya venías manejando: hTabla + hDescripcion contiene tiId=...
    $st = $pdo->prepare("
        SELECT hDescripcion, hFecha_hora
        FROM historial
        WHERE hTabla = 'ticket_soporte'
          AND hDescripcion LIKE ?
        ORDER BY hFecha_hora DESC, hId DESC
        LIMIT 3
    ");
    $st->execute(['%tiId=' . $tiId . '%']);

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map(static function (array $r): array {
        return [
            'fecha' => (string)($r['hFecha_hora'] ?? ''),
            'descripcion' => (string)($r['hDescripcion'] ?? ''),
        ];
    }, $rows);

    json_ok([
        'items' => $items,
        'meta' => [
            'scope' => $scope['scope'],
            'count' => count($items),
        ]
    ]);
} catch (Throwable $e) {
    json_fail('Error al obtener historial. ' . $e->getMessage(), 500);
}