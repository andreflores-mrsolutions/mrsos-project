<?php
// admin/api/meet_accept.php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';
require_once __DIR__ . '/../../php/csrf.php';

no_store();
require_login();
csrf_verify_or_fail();

$pdo   = db();
$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = current_usRol();

$body = read_json_body();
$mpId = isset($body['mpId']) ? (int)$body['mpId'] : 0;
if ($mpId <= 0) json_fail('Falta mpId', 400);

try {
  // 1) Leer propuesta seleccionada
  $st = $pdo->prepare("
    SELECT mpId, tiId, mpAutorTipo, mpModo, mpInicio, mpFin, mpPlataforma, mpLink, mpEstado, mpCreadoEn
    FROM ticket_meet_propuestas
    WHERE mpId=? LIMIT 1
  ");
  $st->execute([$mpId]);
  $p = $st->fetch();
  if (!$p) json_fail('Propuesta no encontrada.', 404);
  if (($p['mpModo'] ?? '') !== 'propuesta') json_fail('Propuesta inválida.', 409);
  if (($p['mpEstado'] ?? '') !== 'pendiente') json_fail('Esta propuesta ya fue procesada.', 409);

  $tiId = (int)$p['tiId'];

  // 2) Validar permisos contra ticket
  $stT = $pdo->prepare("SELECT tiId, clId, estatus FROM ticket_soporte WHERE tiId=? LIMIT 1");
  $stT->execute([$tiId]);
  $t = $stT->fetch();
  if (!$t) json_fail('Ticket no encontrado.', 404);

  $clIdTicket = (int)$t['clId'];

  // Reglas de quién puede aceptar:
  // - Si autorTipo=cliente => acepta MR (admin/ing)
  // - Si autorTipo=ingeniero => acepta cliente
  $autorTipo = (string)$p['mpAutorTipo'];

  if ($autorTipo === 'cliente') {
    // debe ser MR
    if (!is_mr()) json_fail('Solo MR puede confirmar un meet propuesto por cliente.', 403);
    $ROL = $_SESSION['usRol'] ?? null;

        $ROLES_PERMITIDOS = ['CLI', 'MRV', 'MRA', 'MRSA'];

        if (!$ROL || !in_array($ROL, $ROLES_PERMITIDOS, true)) {
            http_response_code(403);
            exit('Acceso no autorizado');
        }
  } else if ($autorTipo === 'ingeniero') {
    // idealmente cliente (CLI). Permitimos override por MRSA/MRA si lo necesitas:
    if (is_cli()) {
      $clIdSes = (int)($_SESSION['clId'] ?? 0);
      if ($clIdSes <= 0 || $clIdSes !== $clIdTicket) json_fail('Sin permisos.', 403);
    } else {
      // override solo MRSA/MRA (puedes ajustar)
      if (!in_array($usRol, ['MRSA','MRA'], true)) {
        json_fail('Este meet fue propuesto por ingeniero. Confírmalo desde cliente.', 403);
      }
      $ROL = $_SESSION['usRol'] ?? null;

        $ROLES_PERMITIDOS = ['CLI', 'MRV', 'MRA', 'MRSA'];

        if (!$ROL || !in_array($ROL, $ROLES_PERMITIDOS, true)) {
            http_response_code(403);
            exit('Acceso no autorizado');
        }
    }
  } else {
    json_fail('Autor inválido.', 409);
  }

  $batchCreadoEn = (string)$p['mpCreadoEn'];

  $pdo->beginTransaction();

  // 3) Aceptar elegida
  $up1 = $pdo->prepare("
    UPDATE ticket_meet_propuestas
    SET mpEstado='aceptada'
    WHERE mpId=? AND mpEstado='pendiente'
    LIMIT 1
  ");
  $up1->execute([$mpId]);

  if ($up1->rowCount() !== 1) {
    $pdo->rollBack();
    json_fail('No se pudo aceptar (posible carrera).', 409);
  }

  // 4) Rechazar las otras del mismo batch
  $up2 = $pdo->prepare("
    UPDATE ticket_meet_propuestas
    SET mpEstado='rechazada'
    WHERE tiId=? AND mpModo='propuesta'
      AND mpAutorTipo=? AND mpCreadoEn=?
      AND mpId<>?
      AND mpEstado='pendiente'
  ");
  $up2->execute([$tiId, $autorTipo, $batchCreadoEn, $mpId]);

  // 5) Cache en ticket_soporte (confirmado + fecha/hora)
  $inicio = (string)$p['mpInicio']; // "YYYY-MM-DD HH:MM:SS"
  $fecha = substr($inicio, 0, 10);
  $hora  = substr($inicio, 11, 8);

  $modo = ($autorTipo === 'cliente') ? 'asignado_cliente' : 'asignado_ingeniero';

  $upT = $pdo->prepare("
    UPDATE ticket_soporte
    SET tiMeetEstado='confirmado',
        tiMeetModo=?,
        tiMeetFecha=?,
        tiMeetHora=?,
        tiMeetPlataforma=?,
        tiMeetEnlace=?,
        tiMeetActivo=?
    WHERE tiId=? LIMIT 1
  ");
  $activo = ($autorTipo === 'cliente') ? 'meet cliente' : 'meet ingeniero';
  $upT->execute([
    $modo,
    $fecha,
    $hora,
    $p['mpPlataforma'] ?? null,
    $p['mpLink'] ?? null,
    $activo,
    $tiId
  ]);

  $pdo->commit();

  json_ok([
    'tiId' => $tiId,
    'accepted' => [
      'mpId' => (int)$mpId,
      'inicio' => $p['mpInicio'],
      'fin' => $p['mpFin'],
      'plataforma' => $p['mpPlataforma'],
      'link' => $p['mpLink'],
    ]
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail('Error al aceptar meet.', 500);
}
