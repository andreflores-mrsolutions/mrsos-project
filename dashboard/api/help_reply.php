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

$taId = (int)($in['taId'] ?? 0);
$tarMensaje = trim((string)($in['tarMensaje'] ?? ''));

if ($taId <= 0) {
    json_fail('Solicitud inválida.');
}
if ($tarMensaje === '') {
    json_fail('Debes escribir un mensaje.');
}
if (mb_strlen($tarMensaje) > 2000) {
    json_fail('El mensaje es demasiado largo.');
}

$usId = (int)($_SESSION['usId'] ?? 0);
$usRol = (string)($_SESSION['usRol'] ?? '');
$clIdSesion = (int)($_SESSION['clId'] ?? 0);

$st = $pdo->prepare("
  SELECT
    a.taId,
    a.tiId,
    a.taEstado,
    ti.clId
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

if ($usRol === 'CLI' && (int)$ayuda['clId'] !== $clIdSesion) {
    json_fail('Sin permisos para esta solicitud.', 403);
}

if (strtolower((string)$ayuda['taEstado']) === 'cerrada') {
    json_fail('La solicitud ya está cerrada.');
}

$pdo->beginTransaction();

try {
    $ins = $pdo->prepare("
    INSERT INTO ticket_ayuda_respuesta
      (taId, usId, tarMensaje, tarEsInterno, tarCreadoEn)
    VALUES
      (?, ?, ?, 0, NOW())
  ");
    $ins->execute([$taId, $usId, $tarMensaje]);

    // Si estaba atendida y el cliente vuelve a escribir, opcionalmente la regresamos a pendiente
    $up = $pdo->prepare("
    UPDATE ticket_ayuda
    SET taEstado = 'pendiente'
    WHERE taId = ?
      AND taEstado <> 'cerrada'
  ");
    $up->execute([$taId]);

    try {
        $desc = sprintf(
            '[AYUDA CLIENTE RESPUESTA] taId=%d · tiId=%d · %s',
            (int)$ayuda['taId'],
            (int)$ayuda['tiId'],
            mb_substr($tarMensaje, 0, 180)
        );

        $hist = $pdo->prepare("
      INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla, hEstatus)
      VALUES (?, ?, NOW(), 'ticket_ayuda_respuesta', 'Activo')
    ");
        $hist->execute([$desc, $usId]);
        $up = $pdo->prepare("
  UPDATE ticket_ayuda
  SET
    taEstado = 'pendiente',
    taUltimoMensajeAt = NOW(),
    taUltimoMensajeUsId = ?,
    taClienteLeidoEn = NOW()
  WHERE taId = ?
    AND taEstado <> 'cerrada'
  LIMIT 1
");
        $up->execute([$usId, $taId]);
    } catch (Throwable $e) {
    }

    $pdo->commit();

    json_ok([
        'message' => 'Mensaje enviado correctamente.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail('No se pudo enviar el mensaje.', 500, [
        'debug' => $e->getMessage()
    ]);
}
