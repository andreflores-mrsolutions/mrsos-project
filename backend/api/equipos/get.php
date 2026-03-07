<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);

try {
  $eqId = (int)($_GET['eqId'] ?? 0);
  if ($eqId <= 0) {
    json_fail('ID de equipo inválido.', 422);
  }

  $pdo = db();
  $st = $pdo->prepare("
    SELECT
      eqId, eqModelo, eqVersion, eqImgPath, eqTipoEquipo, maId,
      eqTipo, eqCPU, eqSockets, eqMaxRAM, eqNIC,
      eqDescripcion, eqEstatus
    FROM equipos
    WHERE eqId = ?
    LIMIT 1
  ");
  $st->execute([$eqId]);
  $row = $st->fetch();

  if (!$row) {
    json_fail('El equipo no existe.', 404);
  }

  json_ok(['row' => $row]);
} catch (Throwable $e) {
  json_fail('Error al obtener el equipo: ' . $e->getMessage(), 500);
}