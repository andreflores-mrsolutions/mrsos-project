<?php
// admin/api/ticket_set_proceso.php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';
require_once __DIR__ . '/../../php/csrf.php';

no_store();
require_login();
$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA','MRSA','ADMIN'], true)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}
csrf_verify_or_fail();

$pdo = db();
$in = read_json_body();

$tiId    = (int)($in['tiId'] ?? 0);
$proceso = trim((string)($in['proceso'] ?? ''));

if ($tiId <= 0) json_fail('Falta tiId', 400);
if ($proceso === '') json_fail('Falta proceso', 400);

// Lista oficial (misma que ya usas en JS)
$STEPS = [
  'asignacion','revision inicial','logs','meet','revision especial','espera refaccion',
  'visita','fecha asignada','espera ventana','espera visita','en camino',
  'espera documentacion','encuesta satisfaccion','finalizado','cancelado',
  'fuera de alcance','servicio por evento'
];

$procesoN = mb_strtolower($proceso);
if (!in_array($procesoN, $STEPS, true)) json_fail('Proceso inválido', 422);

try {
  // valida ticket activo
  $st = $pdo->prepare("SELECT tiId, estatus, tiProceso FROM ticket_soporte WHERE tiId=? LIMIT 1");
  $st->execute([$tiId]);
  $t = $st->fetch(PDO::FETCH_ASSOC);
  if (!$t) json_fail('Ticket no encontrado', 404);
  if (($t['estatus'] ?? '') !== 'Activo') json_fail('Ticket no activo', 409);

  // ✅ Regla mínima (por ahora): permitir avanzar desde MEET a REVISION ESPECIAL
  // (Si quieres luego metemos matriz de transiciones)
  $actual = mb_strtolower((string)($t['tiProceso'] ?? ''));
  if ($procesoN === 'revision especial' && $actual !== 'meet') {
    // ojo: si luego quieres permitir logs -> revision especial, aquí lo abres
    json_fail("Transición no permitida: {$actual} -> {$procesoN}", 409);
  }

  $up = $pdo->prepare("UPDATE ticket_soporte SET tiProceso=? WHERE tiId=? LIMIT 1");
  $up->execute([$procesoN, $tiId]);

  json_ok([
    'tiId' => $tiId,
    'tiProceso' => $procesoN
  ]);
} catch (Throwable $e) {
  json_fail('Error al actualizar proceso', 500);
}
