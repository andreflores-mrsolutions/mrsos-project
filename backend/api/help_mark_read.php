<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$pdo = db();
$in = read_json_body();

$tiId = (int)($in['tiId'] ?? 0);
if ($tiId <= 0) json_fail('Ticket inválido.');

$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = (string)($_SESSION['usRol'] ?? '');

$st = $pdo->prepare("
  SELECT clId
  FROM ticket_soporte
  WHERE tiId = ?
  LIMIT 1
");
$st->execute([$tiId]);
$ticket = $st->fetch(PDO::FETCH_ASSOC);

if (!$ticket) json_fail('No se encontró el ticket.', 404);
if (!mr_can_access_client($pdo, $usId, $usRol, (int)$ticket['clId'])) {
  json_fail('Sin permisos.', 403);
}

$up = $pdo->prepare("
  UPDATE ticket_ayuda
  SET taAdminLeidoEn = NOW()
  WHERE tiId = ?
");
$up->execute([$tiId]);

json_ok(['message' => 'Marcado como leído.']);