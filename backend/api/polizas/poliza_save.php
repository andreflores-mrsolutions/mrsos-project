<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$in = read_json_body();

$pdo = db();
$pcId = (int)($in['pcId'] ?? 0);
$clId = (int)($in['clId'] ?? 0);

$pcIdentificador = trim((string)($in['pcIdentificador'] ?? '')); // número de factura
$pcTipoPoliza = trim((string)($in['pcTipoPoliza'] ?? ''));
$pcFechaInicio = (string)($in['pcFechaInicio'] ?? '');
$pcFechaFin = (string)($in['pcFechaFin'] ?? '');
$pcEstatus = trim((string)($in['pcEstatus'] ?? 'Activo'));

// Este usId es el que tu tabla polizascliente pide NOT NULL (auditoría/creador, etc.)
$usId = (int)($in['usId'] ?? 0);

if ($clId <= 0) json_fail('clId requerido');
$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = current_usRol();

if (!mr_can_access_client($pdo, $usId, $usRol, $clId)) {
  json_fail('Sin acceso al cliente');
}

if ($pcIdentificador === '') json_fail('pcIdentificador (No. factura) requerido');
if ($pcTipoPoliza === '') json_fail('pcTipoPoliza requerido');
if ($pcFechaInicio === '' || $pcFechaFin === '') json_fail('Fechas requeridas');
if ($pcFechaFin < $pcFechaInicio) json_fail('pcFechaFin no puede ser menor a pcFechaInicio');
if ($usId <= 0) json_fail('usId requerido');

$allowedPc = ['Activo','Inactivo','Cambios','Error','Vencida'];
if (!in_array($pcEstatus, $allowedPc, true)) json_fail('pcEstatus inválido');

// helper: obtener póliza
function db_get_poliza(PDO $pdo, int $pcId): array {
  $st = $pdo->prepare("SELECT * FROM polizascliente WHERE pcId=? LIMIT 1");
  $st->execute([$pcId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) json_fail('Póliza no existe');
  return $row;
}

try {
  if ($pcId > 0) {
    $pc = db_get_poliza($pdo, $pcId);
    if ((int)$pc['clId'] !== $clId) json_fail('La póliza no pertenece a este cliente');

    $st = $pdo->prepare("
      UPDATE polizascliente
      SET pcIdentificador=?, pcTipoPoliza=?, clId=?, pcFechaInicio=?, pcFechaFin=?, usId=?, pcEstatus=?
      WHERE pcId=?
      LIMIT 1
    ");
    $st->execute([$pcIdentificador, $pcTipoPoliza, $clId, $pcFechaInicio, $pcFechaFin, $usId, $pcEstatus, $pcId]);

    json_ok(['pcId' => $pcId]);
  } else {
    $st = $pdo->prepare("
      INSERT INTO polizascliente
        (pcIdentificador, pcPdfPath, pcTipoPoliza, clId, csId, pcFechaInicio, pcFechaFin, usId, pcEstatus)
      VALUES
        (?, NULL, ?, ?, NULL, ?, ?, ?, ?)
    ");
    $st->execute([$pcIdentificador, $pcTipoPoliza, $clId, $pcFechaInicio, $pcFechaFin, $usId, $pcEstatus]);

    json_ok(['pcId' => (int)$pdo->lastInsertId()]);
  }
} catch (Throwable $e) {
  // UNIQUE pcIdentificador
  if (str_contains((string)$e->getMessage(), 'uq_pc_identificador') || str_contains((string)$e->getMessage(), 'Duplicate')) {
    json_fail('pcIdentificador duplicado (No. factura ya existe)');
  }
  json_fail('Error al guardar póliza');
}