<?php
// admin/api/meet_get.php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();

$pdo   = db();
$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = current_usRol();

$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;
if ($tiId <= 0) json_fail('Falta tiId', 400);

try {
  // ticket base + permisos
  $st = $pdo->prepare("SELECT tiId, clId, estatus FROM ticket_soporte WHERE tiId=? LIMIT 1");
  $st->execute([$tiId]);
  $t = $st->fetch();
  if (!$t) json_fail('Ticket no encontrado.', 404);
  if (($t['estatus'] ?? '') !== 'Activo') json_fail('Ticket no activo.', 409);

  $clIdTicket = (int)$t['clId'];

  if (is_cli()) {
    $clIdSes = (int)($_SESSION['clId'] ?? 0);
    if ($clIdSes <= 0 || $clIdSes !== $clIdTicket) json_fail('Sin permisos.', 403);
  } else {
    $ROL = $_SESSION['usRol'] ?? null;

        $ROLES_PERMITIDOS = ['CLI', 'MRV', 'MRA', 'MRSA'];

        if (!$ROL || !in_array($ROL, $ROLES_PERMITIDOS, true)) {
            http_response_code(403);
            exit('Acceso no autorizado');
        }
  }

  // último batch (mpCreadoEn) del ticket
  $st2 = $pdo->prepare("
    SELECT mpCreadoEn
    FROM ticket_meet_propuestas
    WHERE tiId = ? AND mpModo='propuesta'
    ORDER BY mpCreadoEn DESC, mpId DESC
    LIMIT 1
  ");
  $st2->execute([$tiId]);
  $last = $st2->fetchColumn();

  if (!$last) {
    json_ok(['meet' => null]);
  }

  $st3 = $pdo->prepare("
    SELECT mpId, tiId, mpAutorTipo, mpModo, mpPlataforma, mpLink, mpInicio, mpFin, mpEstado, mpCreadoEn
    FROM ticket_meet_propuestas
    WHERE tiId = ? AND mpModo='propuesta' AND mpCreadoEn = ?
    ORDER BY mpInicio ASC, mpId ASC
  ");
  $st3->execute([$tiId, $last]);
  $rows = $st3->fetchAll();

  // estado del batch
  $status = 'propuesto';
  foreach ($rows as $r) {
    if (($r['mpEstado'] ?? '') === 'aceptada') {
      $status = 'confirmado';
      break;
    }
  }

  json_ok([
    'meet' => [
      'tiId' => $tiId,
      'batchCreadoEn' => $last,
      'status' => $status,             // propuesto | confirmado
      'autorTipo' => $rows[0]['mpAutorTipo'] ?? null,
      'plataforma' => $rows[0]['mpPlataforma'] ?? null,
      'link' => $rows[0]['mpLink'] ?? null,
      'opciones' => array_map(fn($r) => [
        'mpId' => (int)$r['mpId'],
        'inicio' => $r['mpInicio'],
        'fin' => $r['mpFin'],
        'estado' => $r['mpEstado'],
      ], $rows),
    ]
  ]);
} catch (Throwable $e) {
  json_fail('Error al obtener meet.', 500);
}
