<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
csrf_verify_or_fail();

$pdo = db();
$in = read_json_body();

$tiId = (int)($in['tiId'] ?? 0);
$taTipo = trim((string)($in['taTipo'] ?? 'general'));
$taMensaje = trim((string)($in['taMensaje'] ?? ''));
$taRequiereMeet = (int)($in['taRequiereMeet'] ?? 0) === 1 ? 1 : 0;
$taPlataformaPreferida = trim((string)($in['taPlataformaPreferida'] ?? ''));

if ($tiId <= 0) {
  json_fail('Ticket inválido.');
}

if ($taMensaje === '') {
  json_fail('Debes escribir el mensaje de ayuda.');
}

if (mb_strlen($taMensaje) > 2000) {
  json_fail('El mensaje es demasiado largo.');
}

$tiposPermitidos = ['general', 'logs', 'meet', 'visita', 'documentacion', 'otro'];
if (!in_array($taTipo, $tiposPermitidos, true)) {
  $taTipo = 'general';
}

$usId = (int)($_SESSION['usId'] ?? 0);
$usRol = (string)($_SESSION['usRol'] ?? '');
$clIdSesion = (int)($_SESSION['clId'] ?? 0);

/*
  Valida acceso al ticket:
  - Cliente: solo tickets de su clId
  - MR: queda abierto por compatibilidad
*/
$sqlTicket = "
  SELECT ti.tiId, ti.clId, ti.tiProceso
  FROM ticket_soporte ti
  WHERE ti.tiId = ?
  LIMIT 1
";
$st = $pdo->prepare($sqlTicket);
$st->execute([$tiId]);
$ticket = $st->fetch();

if (!$ticket) {
  json_fail('No se encontró el ticket.', 404);
}

if ($usRol === 'CLI' && (int)$ticket['clId'] !== $clIdSesion) {
  json_fail('Sin permisos para este ticket.', 403);
}

$pdo->beginTransaction();

try {
  $ins = $pdo->prepare("
    INSERT INTO ticket_ayuda
      (tiId, usId, taTipo, taMensaje, taRequiereMeet, taPlataformaPreferida, taEstado, taCreadoEn)
    VALUES
      (?, ?, ?, ?, ?, ?, 'pendiente', NOW())
  ");
  $ins->execute([
    $tiId,
    $usId,
    $taTipo,
    $taMensaje,
    $taRequiereMeet,
    $taPlataformaPreferida !== '' ? $taPlataformaPreferida : null
  ]);

  $taId = (int)$pdo->lastInsertId();

  // Historial global del sistema
  try {
    $desc = sprintf(
      '[AYUDA] Ticket %d · tipo=%s · requiereMeet=%d · %s',
      $tiId,
      $taTipo,
      $taRequiereMeet,
      mb_substr($taMensaje, 0, 180)
    );

    $hist = $pdo->prepare("
      INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla, hEstatus)
      VALUES (?, ?, NOW(), 'ticket_ayuda', 'Activo')
    ");
    $hist->execute([$desc, $usId]);
  } catch (Throwable $e) {
    // No tumbamos el flujo si historial falla
  }

  /*
    OPCIONAL:
    Si pidió Meet y el ticket no está en proceso meet,
    puedes dejarlo solo como solicitud o mover el flujo.
    Aquí lo dejamos solo como solicitud para no romper el proceso.
  */

  $pdo->commit();

  json_ok([
    'message' => 'Solicitud de ayuda creada correctamente.',
    'taId' => $taId
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail('No se pudo guardar la solicitud de ayuda.', 500, [
    'debug' => $e->getMessage()
  ]);
}