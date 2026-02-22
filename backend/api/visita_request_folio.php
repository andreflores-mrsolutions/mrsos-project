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
if ($motivo === '') $motivo = 'Solicitud de folio de entrada/autorización.';

$pdo = db();
$usId = (int)($_SESSION['usId'] ?? 0);

$desc = "[VISITA] tiId={$tiId} · Solicitud de folio: {$motivo}";
$pdo->prepare("INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla) VALUES (?, ?, ?, 'ticket_soporte')")
    ->execute([$desc, $usId, date('Y-m-d H:i:s')]);

json_ok();