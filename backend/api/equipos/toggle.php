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

  $eqId = (int)($body['eqId'] ?? 0);
  if ($eqId <= 0) {
    json_fail('ID de equipo inválido.', 422);
  }

  $st = $pdo->prepare("
    SELECT eqId, eqModelo, eqVersion, eqEstatus
    FROM equipos
    WHERE eqId = ?
    LIMIT 1
  ");
  $st->execute([$eqId]);
  $row = $st->fetch();

  if (!$row) {
    json_fail('El equipo no existe.', 404);
  }

  $nuevo = ((string)$row['eqEstatus'] === 'Activo') ? 'Inactivo' : 'Activo';

  $up = $pdo->prepare("
    UPDATE equipos
    SET eqEstatus = ?
    WHERE eqId = ?
    LIMIT 1
  ");
  $up->execute([$nuevo, $eqId]);

  if (class_exists('Historial')) {
    Historial::log(
      $pdo,
      $usId,
      'equipos',
      "TOGGLE equipo (eqId={$eqId}) - '{$row['eqModelo']} {$row['eqVersion']}' cambió de '{$row['eqEstatus']}' a '{$nuevo}'.",
      'Activo'
    );
  }

  json_ok([
    'message' => "Equipo {$nuevo} correctamente.",
    'eqId' => $eqId,
    'eqEstatus' => $nuevo
  ]);
} catch (Throwable $e) {
  json_fail('Error al cambiar el estatus del equipo: ' . $e->getMessage(), 500);
}