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
$lock = (int)($in['lock'] ?? 0);
if ($tiId <= 0) json_fail('tiId inválido');
$lock = $lock ? 1 : 0;

$pdo = db();
$usId = (int)($_SESSION['usId'] ?? 0);

$pdo->prepare("
  INSERT INTO ticket_visita_estado (tiId, lock_cancel)
  VALUES (?, ?)
  ON DUPLICATE KEY UPDATE lock_cancel=VALUES(lock_cancel)
")->execute([$tiId, $lock]);

$desc = "[VISITA] tiId={$tiId} · lock_cancel={$lock}";
$pdo->prepare("INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla) VALUES (?, ?, ?, 'ticket_soporte')")
    ->execute([$desc, $usId, date('Y-m-d H:i:s')]);

json_ok(['lock'=>$lock]);