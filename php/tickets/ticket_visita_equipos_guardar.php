<?php
// ../php/ticket_visita_equipos_guardar.php
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

// Soportar JSON o form-data
$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) $payload = [];

$taiId = (int)($payload['taiId'] ?? ($_POST['taiId'] ?? 0));
$equipos = $payload['equipos'] ?? null;

// Si viene en form-data como string JSON
if ($equipos === null && isset($_POST['equipos'])) {
  $equipos = json_decode((string)$_POST['equipos'], true);
}

if ($taiId <= 0) jexit(['success'=>false,'error'=>'taiId inválido'], 400);
if (!is_array($equipos)) jexit(['success'=>false,'error'=>'equipos inválido'], 400);

// 1) Validar que el taiId exista y a quién pertenece
$st = $conectar->prepare("SELECT taiId, usId FROM ticket_acceso_ingeniero WHERE taiId=? LIMIT 1");
if (!$st) jexit(['success'=>false,'error'=>'DB prepare error'], 500);
$st->bind_param("i", $taiId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) jexit(['success'=>false,'error'=>'Folio (taiId) no encontrado'], 404);

// Solo el ingeniero dueño del folio o MR/admin puede editar
$ownerUsId = (int)($row['usId'] ?? 0);
if (!$mr && $ownerUsId !== $usId) jexit(['success'=>false,'error'=>'No autorizado'], 403);

// 2) Normalizar equipos: máximo 20, cantidades >=1
if (count($equipos) > 20) jexit(['success'=>false,'error'=>'Máximo 20 equipos'], 400);

$norm = [];
foreach ($equipos as $e) {
  if (!is_array($e)) continue;
  $ieId = (int)($e['ieId'] ?? 0);
  if ($ieId <= 0) continue;
  $cant = (int)($e['cantidad'] ?? 1);
  if ($cant <= 0) $cant = 1;
  $notas = trim((string)($e['notas'] ?? ''));
  $norm[] = ['ieId'=>$ieId, 'cantidad'=>$cant, 'notas'=>$notas];
}
if (count($norm) === 0) jexit(['success'=>false,'error'=>'No se recibieron equipos válidos'], 400);

// 3) Validar que todos los ieId pertenezcan al ingeniero dueño (ownerUsId)
$ids = array_map(fn($x) => (int)$x['ieId'], $norm);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids) + 1);

$sqlCheck = "SELECT ieId FROM ingeniero_equipos WHERE usId=? AND ieId IN ($placeholders) AND ieActivo=1";
$st = $conectar->prepare($sqlCheck);
if (!$st) jexit(['success'=>false,'error'=>'DB prepare error (check ieId)'], 500);

$params = array_merge([$ownerUsId], $ids);

// bind_param dinámico
$bindNames = [];
$bindNames[] = &$types;
$tmp = [];
$tmp[] = $ownerUsId;
foreach ($ids as $v) $tmp[] = $v;
for ($i=0; $i<count($tmp); $i++) $bindNames[] = &$tmp[$i];

call_user_func_array([$st, 'bind_param'], $bindNames);
$st->execute();
$rs = $st->get_result();

$allowed = [];
while ($r = $rs->fetch_assoc()) $allowed[(int)$r['ieId']] = true;
$st->close();

foreach ($ids as $ieId) {
  if (!isset($allowed[$ieId])) {
    jexit(['success'=>false,'error'=>"Equipo ieId=$ieId no pertenece al ingeniero o está inactivo"], 400);
  }
}

// 4) Transacción: borrar anteriores + insertar nuevas
$conectar->begin_transaction();

try {
  $st = $conectar->prepare("DELETE FROM ticket_visita_equipos WHERE taiId=?");
  if (!$st) throw new Exception('DB delete prepare error');
  $st->bind_param("i", $taiId);
  $st->execute();
  $st->close();

  $sqlIns = "INSERT INTO ticket_visita_equipos (taiId, ieId, tveCantidad, tveNotas)
             VALUES (?,?,?,?)";
  $ins = $conectar->prepare($sqlIns);
  if (!$ins) throw new Exception('DB insert prepare error');

  $inserted = 0;
  foreach ($norm as $e) {
    $ieId = (int)$e['ieId'];
    $cant = (int)$e['cantidad'];
    $notas = (string)$e['notas'];
    $ins->bind_param("iiis", $taiId, $ieId, $cant, $notas);
    if ($ins->execute()) $inserted++;
  }
  $ins->close();

  $conectar->commit();

  jexit([
    'success'=>true,
    'taiId'=>$taiId,
    'inserted'=>$inserted
  ]);

} catch (Throwable $e) {
  $conectar->rollback();
  jexit(['success'=>false,'error'=>'No se pudo guardar equipos', 'detail'=>$e->getMessage()], 500);
}
