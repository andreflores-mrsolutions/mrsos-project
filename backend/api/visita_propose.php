<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);
csrf_verify_or_fail();

$in = read_json_body();
$tiId = (int)($in['tiId'] ?? 0);
$opciones = $in['opciones'] ?? null;

if ($tiId <= 0) json_fail('tiId inválido');
if (!is_array($opciones) || count($opciones) !== 3) json_fail('Se requieren 3 opciones');

$pdo = db();

// ticket existe
$st = $pdo->prepare("SELECT tiId FROM ticket_soporte WHERE tiId=? LIMIT 1");
$st->execute([$tiId]);
if (!$st->fetchColumn()) json_fail('Ticket no existe', 404);

$batch = $in['batchId'] ?? bin2hex(random_bytes(16));
$usId = (int)($_SESSION['usId'] ?? 0);

$pdo->beginTransaction();

$ins = $pdo->prepare("
  INSERT INTO ticket_visita_propuestas (tiId, vpBatchId, vpAutorTipo, vpOpcion, vpInicio, vpFin, vpEstado, vpCreadoPor)
  VALUES (?, ?, 'ingeniero', ?, ?, ?, 'pendiente', ?)
");

for ($i=0; $i<3; $i++) {
  $o = $opciones[$i];
  $op = (int)($o['opcion'] ?? ($i+1));
  $ini = (string)($o['inicio'] ?? '');
  $fin = (string)($o['fin'] ?? '');
  if ($op < 1 || $op > 3) json_fail('Opción inválida');
  if (!$ini || !$fin) json_fail('inicio/fin requeridos');

  $ins->execute([$tiId, $batch, $op, $ini, $fin, $usId]);
}

// estado visita
$pdo->prepare("
  INSERT INTO ticket_visita_estado (tiId, estado, lock_cancel)
  VALUES (?, 'pendiente', 0)
  ON DUPLICATE KEY UPDATE estado='pendiente'
")->execute([$tiId]);

// historial
$desc = "[VISITA] tiId={$tiId} · Propuestas registradas (batch {$batch})";
$pdo->prepare("INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla) VALUES (?, ?, ?, 'ticket_soporte')")
    ->execute([$desc, $usId, date('Y-m-d H:i:s')]);

$pdo->commit();

json_ok(['batchId'=>$batch]);