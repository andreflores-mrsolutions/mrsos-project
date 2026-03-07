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
  $usId = (int)($_SESSION['usId'] ?? 0);
  $maId = (int)($_POST['maId'] ?? 0);

  if ($maId <= 0) {
    json_fail('ID de marca inválido.', 422);
  }

  $st = $pdo->prepare("
    SELECT maId, maNombre, maEstatus
    FROM marca
    WHERE maId = ?
    LIMIT 1
  ");
  $st->execute([$maId]);
  $row = $st->fetch();

  if (!$row) {
    json_fail('La marca no existe.', 404);
  }

  $nuevo = ((string)$row['maEstatus'] === 'Activo') ? 'Inactivo' : 'Activo';

  $up = $pdo->prepare("
    UPDATE marca
    SET maEstatus = ?
    WHERE maId = ?
    LIMIT 1
  ");
  $up->execute([$nuevo, $maId]);

  if (class_exists('Historial')) {
    Historial::log(
      $pdo,
      $usId,
      'marca',
      "TOGGLE marca (maId={$maId}) - '{$row['maNombre']}' cambió de '{$row['maEstatus']}' a '{$nuevo}'.",
      'Activo'
    );
  }

  json_ok([
    'message' => "Marca {$nuevo} correctamente.",
    'maId' => $maId,
    'maEstatus' => $nuevo
  ]);
} catch (Throwable $e) {
  json_fail('Error al cambiar el estatus de la marca: ' . $e->getMessage(), 500);
}