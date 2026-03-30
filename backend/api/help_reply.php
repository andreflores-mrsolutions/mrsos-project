<?php

declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA', 'MRA', 'MRV']);
csrf_verify_or_fail();

$pdo = db();
$in  = read_json_body();

$taId = (int)($in['taId'] ?? 0);
$tarMensaje = trim((string)($in['tarMensaje'] ?? ''));
$tarEsInterno = (int)($in['tarEsInterno'] ?? 0) === 1 ? 1 : 0;

if ($taId <= 0) {
  json_fail('Solicitud inválida.');
}
if ($tarMensaje === '') {
  json_fail('Debes escribir una respuesta.');
}
if (mb_strlen($tarMensaje) > 2000) {
  json_fail('La respuesta es demasiado larga.');
}

$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = (string)($_SESSION['usRol'] ?? '');

$st = $pdo->prepare("
  SELECT a.taId, a.tiId, ti.clId, a.taEstado
  FROM ticket_ayuda a
  INNER JOIN ticket_soporte ti ON ti.tiId = a.tiId
  WHERE a.taId = ?
  LIMIT 1
");
$st->execute([$taId]);
$ayuda = $st->fetch(PDO::FETCH_ASSOC);

if (!$ayuda) {
  json_fail('No se encontró la solicitud.', 404);
}

if (!mr_can_access_client($pdo, $usId, $usRol, (int)$ayuda['clId'])) {
  json_fail('Sin permisos para esta solicitud.', 403);
}

$pdo->beginTransaction();

try {
  $ins = $pdo->prepare("
    INSERT INTO ticket_ayuda_respuesta
      (taId, usId, tarMensaje, tarEsInterno, tarCreadoEn)
    VALUES
      (?, ?, ?, ?, NOW())
  ");
  $ins->execute([$taId, $usId, $tarMensaje, $tarEsInterno]);

  $up = $pdo->prepare("
    UPDATE ticket_ayuda
    SET
      taEstado = CASE WHEN taEstado = 'cerrada' THEN taEstado ELSE 'atendida' END,
      taAtendidoEn = CASE WHEN taEstado = 'cerrada' THEN taAtendidoEn ELSE NOW() END,
      taAtendidoPor = CASE WHEN taEstado = 'cerrada' THEN taAtendidoPor ELSE ? END
    WHERE taId = ?
    LIMIT 1
  ");
  $up->execute([$usId, $taId]);

  try {
    $desc = sprintf(
      '[AYUDA RESPUESTA] taId=%d · tiId=%d · %s',
      $taId,
      (int)$ayuda['tiId'],
      mb_substr($tarMensaje, 0, 180)
    );

    $hist = $pdo->prepare("
      INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla, hEstatus)
      VALUES (?, ?, NOW(), 'ticket_ayuda_respuesta', 'Activo')
    ");
    $hist->execute([$desc, $usId]);

    $upMeta = $pdo->prepare("
      UPDATE ticket_ayuda
      SET
        taEstado = CASE WHEN taEstado = 'cerrada' THEN taEstado ELSE 'atendida' END,
        taAtendidoEn = CASE WHEN taEstado = 'cerrada' THEN taAtendidoEn ELSE NOW() END,
        taAtendidoPor = CASE WHEN taEstado = 'cerrada' THEN taAtendidoPor ELSE ? END,
        taUltimoMensajeAt = NOW(),
        taUltimoMensajeUsId = ?,
        taAdminLeidoEn = NOW()
      WHERE taId = ?
      LIMIT 1
    ");
    $upMeta->execute([$usId, $usId, $taId]);
  } catch (Throwable $e) {
  }

  $pdo->commit();

  json_ok([
    'message' => 'Respuesta enviada correctamente.'
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail('No se pudo guardar la respuesta.', 500, [
    'debug' => $e->getMessage()
  ]);
}
