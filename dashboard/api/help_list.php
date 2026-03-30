<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();

$pdo = db();

$tiId = (int)($_GET['tiId'] ?? 0);
if ($tiId <= 0) {
  json_fail('Ticket inválido.');
}

$usRol = (string)($_SESSION['usRol'] ?? '');
$clIdSesion = (int)($_SESSION['clId'] ?? 0);

$stTicket = $pdo->prepare("
  SELECT tiId, clId
  FROM ticket_soporte
  WHERE tiId = ?
  LIMIT 1
");
$stTicket->execute([$tiId]);
$ticket = $stTicket->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
  json_fail('No se encontró el ticket.', 404);
}

if ($usRol === 'CLI' && (int)$ticket['clId'] !== $clIdSesion) {
  json_fail('Sin permisos para este ticket.', 403);
}

$st = $pdo->prepare("
  SELECT
    a.taId,
    a.tiId,
    a.usId,
    a.taTipo,
    a.taMensaje,
    a.taRequiereMeet,
    a.taPlataformaPreferida,
    a.taEstado,
    a.taCreadoEn,
    a.taAtendidoEn,
    a.taAtendidoPor
  FROM ticket_ayuda a
  WHERE a.tiId = ?
  ORDER BY a.taCreadoEn DESC, a.taId DESC
  LIMIT 20
");
$st->execute([$tiId]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

$taIds = array_map(static fn($x) => (int)$x['taId'], $items);
$respuestasPorTa = [];

if ($taIds) {
  $placeholders = implode(',', array_fill(0, count($taIds), '?'));

  $stR = $pdo->prepare("
    SELECT
      r.tarId,
      r.taId,
      r.usId,
      r.tarMensaje,
      r.tarEsInterno,
      r.tarCreadoEn,
      TRIM(CONCAT(
        COALESCE(u.usNombre, ''),
        CASE WHEN COALESCE(u.usAPaterno, '') <> '' THEN CONCAT(' ', u.usAPaterno) ELSE '' END,
        CASE WHEN COALESCE(u.usAMaterno, '') <> '' THEN CONCAT(' ', u.usAMaterno) ELSE '' END
      )) AS usuarioNombre
    FROM ticket_ayuda_respuesta r
    LEFT JOIN usuarios u ON u.usId = r.usId
    WHERE r.taId IN ($placeholders)
      AND r.tarEsInterno = 0
    ORDER BY r.tarCreadoEn ASC, r.tarId ASC
  ");
  $stR->execute($taIds);

  foreach ($stR->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $id = (int)$row['taId'];
    if (!isset($respuestasPorTa[$id])) $respuestasPorTa[$id] = [];
    $respuestasPorTa[$id][] = $row;
  }
}

foreach ($items as &$item) {
  $item['respuestas'] = $respuestasPorTa[(int)$item['taId']] ?? [];
}
unset($item);

json_ok([
  'items' => $items
]);