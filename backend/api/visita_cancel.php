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
$motivo = trim((string)($in['motivo'] ?? ''));
if ($tiId <= 0) json_fail('tiId inválido');
if ($motivo === '') json_fail('Motivo requerido');

$pdo = db();
$usId = (int)($_SESSION['usId'] ?? 0);

$pdo->beginTransaction();

// marca estado visita
$pdo->prepare("
  INSERT INTO ticket_visita_estado (tiId, estado, cancel_motivo, cancel_by, cancel_en)
  VALUES (?, 'cancelada', ?, ?, NOW())
  ON DUPLICATE KEY UPDATE
    estado='cancelada', cancel_motivo=VALUES(cancel_motivo), cancel_by=VALUES(cancel_by), cancel_en=VALUES(cancel_en)
")->execute([$tiId, $motivo, $usId]);

// proceso ticket -> cancelado
$pdo->prepare("UPDATE ticket_soporte SET tiProceso='cancelado' WHERE tiId=?")->execute([$tiId]);

// propuestas -> canceladas (solo pendientes)
$pdo->prepare("UPDATE ticket_visita_propuestas SET vpEstado='cancelada' WHERE tiId=? AND vpEstado='pendiente'")
    ->execute([$tiId]);

// historial
$desc = "[VISITA] tiId={$tiId} · CANCELADO (admin) · Motivo: {$motivo}";
$pdo->prepare("INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla) VALUES (?, ?, ?, 'ticket_soporte')")
    ->execute([$desc, $usId, date('Y-m-d H:i:s')]);

$pdo->commit();

json_ok();