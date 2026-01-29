<?php
// ../php/ticket_visita_equipos_list.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/conexion.php';

function jexit(array $out, int $code = 200): void {
  http_response_code($code);
  if (ob_get_length()) { @ob_clean(); }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}

function isMr(): bool {
  $rol = strtoupper(trim((string)($_SESSION['usRol'] ?? '')));
  return in_array($rol, ['MR','MRA','ADMIN_GLOBAL','ADMIN_ZONA','ADMIN_SEDE'], true);
}

$usId = (int)($_SESSION['usId'] ?? 0);
if ($usId <= 0) jexit(['success'=>false,'error'=>'No autenticado'], 401);

$mr = isMr();

$taiId = (int)($_GET['taiId'] ?? 0);
if ($taiId <= 0) jexit(['success'=>false,'error'=>'taiId inválido'], 400);

// Validar taiId + owner
$st = $conectar->prepare("SELECT taiId, usId, tiId FROM ticket_acceso_ingeniero WHERE taiId=? LIMIT 1");
if (!$st) jexit(['success'=>false,'error'=>'DB prepare error'], 500);
$st->bind_param("i", $taiId);
$st->execute();
$hdr = $st->get_result()->fetch_assoc();
$st->close();

if (!$hdr) jexit(['success'=>false,'error'=>'Folio (taiId) no encontrado'], 404);

$ownerUsId = (int)($hdr['usId'] ?? 0);
if (!$mr && $ownerUsId !== $usId) jexit(['success'=>false,'error'=>'No autorizado'], 403);

$sql = "
SELECT
  tve.tveId,
  tve.taiId,
  tve.ieId,
  tve.tveCantidad,
  tve.tveNotas,
  ie.ieTipo,
  ie.ieMarca,
  ie.ieModelo,
  ie.ieSerie,
  ie.ieDescripcion
FROM ticket_visita_equipos tve
JOIN ingeniero_equipos ie ON ie.ieId = tve.ieId
WHERE tve.taiId = ?
ORDER BY ie.ieTipo ASC, ie.ieMarca ASC, ie.ieModelo ASC, tve.tveId ASC
";

$st = $conectar->prepare($sql);
if (!$st) jexit(['success'=>false,'error'=>'DB prepare error'], 500);
$st->bind_param("i", $taiId);
$st->execute();

$rs = $st->get_result();
$data = [];
while ($r = $rs->fetch_assoc()) $data[] = $r;

$st->close();

jexit([
  'success'=>true,
  'taiId'=>$taiId,
  'tiId'=>$hdr['tiId'] ?? null,
  'data'=>$data
]);
