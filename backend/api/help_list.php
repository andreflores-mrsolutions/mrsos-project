<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);

$pdo = db();

$tiId = (int)($_GET['tiId'] ?? 0);
if ($tiId <= 0) {
  json_fail('Ticket inválido.');
}

$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = (string)($_SESSION['usRol'] ?? '');

$stTicket = $pdo->prepare("
  SELECT
    ti.tiId,
    ti.clId,
    ti.tiProceso,
    ti.tiEstatus,
    c.clNombre,
    cs.csNombre,
    pe.peSN,
    e.eqModelo,
    e.eqVersion,
    m.maNombre
  FROM ticket_soporte ti
  INNER JOIN clientes c ON c.clId = ti.clId
  LEFT JOIN cliente_sede cs ON cs.csId = ti.csId
  LEFT JOIN polizasequipo pe ON pe.peId = ti.peId
  LEFT JOIN equipos e ON e.eqId = pe.eqId
  LEFT JOIN marca m ON m.maId = e.maId
  WHERE ti.tiId = ?
  LIMIT 1
");
$stTicket->execute([$tiId]);
$ticket = $stTicket->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
  json_fail('No se encontró el ticket.', 404);
}

if (!mr_can_access_client($pdo, $usId, $usRol, (int)$ticket['clId'])) {
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
    a.taAtendidoPor,
    TRIM(CONCAT(
      COALESCE(u.usNombre, ''),
      CASE WHEN COALESCE(u.usAPaterno, '') <> '' THEN CONCAT(' ', u.usAPaterno) ELSE '' END,
      CASE WHEN COALESCE(u.usAMaterno, '') <> '' THEN CONCAT(' ', u.usAMaterno) ELSE '' END
    )) AS usuarioNombre
  FROM ticket_ayuda a
  LEFT JOIN usuarios u ON u.usId = a.usId
  WHERE a.tiId = ?
  ORDER BY a.taCreadoEn DESC, a.taId DESC
");
$st->execute([$tiId]);
$ayudas = $st->fetchAll(PDO::FETCH_ASSOC);

$taIds = array_map(static fn($x) => (int)$x['taId'], $ayudas);

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
    ORDER BY r.tarCreadoEn ASC, r.tarId ASC
  ");
  $stR->execute($taIds);

  foreach ($stR->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $id = (int)$row['taId'];
    if (!isset($respuestasPorTa[$id])) $respuestasPorTa[$id] = [];
    $respuestasPorTa[$id][] = $row;
  }
}

foreach ($ayudas as &$a) {
  $a['respuestas'] = $respuestasPorTa[(int)$a['taId']] ?? [];
}
unset($a);

$pendientes = 0;
$atendidas  = 0;
$cerradas   = 0;

foreach ($ayudas as $a) {
  $estado = strtolower((string)($a['taEstado'] ?? 'pendiente'));
  if ($estado === 'pendiente') $pendientes++;
  elseif ($estado === 'atendida') $atendidas++;
  elseif ($estado === 'cerrada') $cerradas++;
}

json_ok([
  'ticket' => $ticket,
  'items' => $ayudas,
  'resume' => [
    'total' => count($ayudas),
    'pendientes' => $pendientes,
    'atendidas' => $atendidas,
    'cerradas' => $cerradas,
  ],
]);