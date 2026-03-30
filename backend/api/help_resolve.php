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
$in  = read_json_body();

$taId = (int)($in['taId'] ?? 0);
$taEstado = trim((string)($in['taEstado'] ?? ''));

if ($taId <= 0) {
  json_fail('Solicitud inválida.');
}

$permitidos = ['pendiente', 'atendida', 'cerrada'];
if (!in_array($taEstado, $permitidos, true)) {
  json_fail('Estado inválido.');
}

$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = (string)($_SESSION['usRol'] ?? '');

$st = $pdo->prepare("
  SELECT a.taId, a.tiId, ti.clId
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

$atendidoEn = ($taEstado === 'atendida' || $taEstado === 'cerrada') ? 'NOW()' : 'NULL';
$atendidoPor = ($taEstado === 'atendida' || $taEstado === 'cerrada') ? (string)$usId : 'NULL';

$sql = "
  UPDATE ticket_ayuda
  SET
    taEstado = ?,
    taAtendidoEn = $atendidoEn,
    taAtendidoPor = $atendidoPor
  WHERE taId = ?
  LIMIT 1
";

$up = $pdo->prepare($sql);
$up->execute([$taEstado, $taId]);

try {
  $desc = sprintf(
    '[AYUDA ESTADO] taId=%d · tiId=%d · nuevoEstado=%s',
    $taId,
    (int)$ayuda['tiId'],
    $taEstado
  );

  $hist = $pdo->prepare("
    INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla, hEstatus)
    VALUES (?, ?, NOW(), 'ticket_ayuda', 'Activo')
  ");
  $hist->execute([$desc, $usId]);
} catch (Throwable $e) {}

json_ok([
  'message' => 'Estado actualizado correctamente.'
]);