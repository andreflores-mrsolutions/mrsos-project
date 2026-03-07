<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/historial.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

try {
  $pdo = db();
  $body = read_json_body();
  $usId = (int)($_SESSION['usId'] ?? 0);

  $maId = (int)($body['maId'] ?? 0);
  $eqModelo = trim((string)($body['eqModelo'] ?? ''));
  $eqVersion = trim((string)($body['eqVersion'] ?? ''));
  $eqTipoEquipo = trim((string)($body['eqTipoEquipo'] ?? ''));
  $eqTipo = trim((string)($body['eqTipo'] ?? ''));
  $eqCPU = trim((string)($body['eqCPU'] ?? ''));
  $eqSockets = trim((string)($body['eqSockets'] ?? ''));
  $eqMaxRAM = trim((string)($body['eqMaxRAM'] ?? ''));
  $eqNIC = trim((string)($body['eqNIC'] ?? ''));
  $eqDescripcion = trim((string)($body['eqDescripcion'] ?? ''));
  $eqEstatus = trim((string)($body['eqEstatus'] ?? 'Activo'));

  $permitidos = ['Activo','Inactivo','Cambios','Error'];

  if ($maId <= 0) json_fail('La marca es obligatoria.', 422);
  if ($eqModelo === '') json_fail('El modelo es obligatorio.', 422);
  if ($eqVersion === '') json_fail('La versión es obligatoria.', 422);
  if ($eqTipoEquipo === '') json_fail('El tipo de equipo es obligatorio.', 422);
  if ($eqTipo === '') json_fail('El tipo es obligatorio.', 422);
  if ($eqCPU === '') json_fail('La CPU es obligatoria.', 422);
  if ($eqSockets === '') json_fail('Los sockets son obligatorios.', 422);
  if ($eqMaxRAM === '') json_fail('La RAM máxima es obligatoria.', 422);
  if ($eqNIC === '') json_fail('La NIC es obligatoria.', 422);
  if ($eqDescripcion === '') json_fail('La descripción es obligatoria.', 422);
  if (!in_array($eqEstatus, $permitidos, true)) json_fail('Estatus inválido.', 422);

  if (mb_strlen($eqModelo) > 50) json_fail('El modelo no puede exceder 50 caracteres.', 422);
  if (mb_strlen($eqVersion) > 25) json_fail('La versión no puede exceder 25 caracteres.', 422);
  if (mb_strlen($eqTipoEquipo) > 50) json_fail('El tipo de equipo no puede exceder 50 caracteres.', 422);
  if (mb_strlen($eqTipo) > 50) json_fail('El tipo no puede exceder 50 caracteres.', 422);
  if (mb_strlen($eqCPU) > 50) json_fail('La CPU no puede exceder 50 caracteres.', 422);
  if (mb_strlen($eqSockets) > 50) json_fail('Sockets no puede exceder 50 caracteres.', 422);
  if (mb_strlen($eqMaxRAM) > 50) json_fail('RAM máxima no puede exceder 50 caracteres.', 422);
  if (mb_strlen($eqNIC) > 50) json_fail('NIC no puede exceder 50 caracteres.', 422);

  $stMarca = $pdo->prepare("SELECT maId, maNombre FROM marca WHERE maId = ? LIMIT 1");
  $stMarca->execute([$maId]);
  $marca = $stMarca->fetch();
  if (!$marca) {
    json_fail('La marca seleccionada no existe.', 404);
  }

  $stDup = $pdo->prepare("
    SELECT eqId
    FROM equipos
    WHERE maId = ? AND eqModelo = ? AND eqVersion = ?
    LIMIT 1
  ");
  $stDup->execute([$maId, $eqModelo, $eqVersion]);
  if ($stDup->fetch()) {
    json_fail('Ya existe un equipo con la misma marca, modelo y versión.', 409);
  }

  $st = $pdo->prepare("
    INSERT INTO equipos (
      eqModelo, eqVersion, eqTipoEquipo, maId,
      eqTipo, eqCPU, eqSockets, eqMaxRAM, eqNIC,
      eqDescripcion, eqEstatus
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $st->execute([
    $eqModelo, $eqVersion, $eqTipoEquipo, $maId,
    $eqTipo, $eqCPU, $eqSockets, $eqMaxRAM, $eqNIC,
    $eqDescripcion, $eqEstatus
  ]);

  $eqId = (int)$pdo->lastInsertId();

  if (class_exists('Historial')) {
    Historial::log(
      $pdo,
      $usId,
      'equipos',
      "CREATE equipo (eqId={$eqId}) - Alta de '{$eqModelo} {$eqVersion}' marca '{$marca['maNombre']}' con estatus '{$eqEstatus}'.",
      'Activo'
    );
  }

  json_ok([
    'message' => 'Equipo creado correctamente.',
    'eqId' => $eqId
  ]);
} catch (PDOException $e) {
  if ((string)$e->getCode() === '23000') {
    json_fail('No se pudo crear el equipo porque ya existe un registro igual.', 409);
  }
  json_fail('Error al crear el equipo: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
  json_fail('Error al crear el equipo: ' . $e->getMessage(), 500);
}