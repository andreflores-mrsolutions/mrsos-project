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
  $maId = (int)($_GET['maId'] ?? 0);
  if ($maId <= 0) {
    json_fail('ID de marca inválido.', 422);
  }

  $pdo = db();
  $st = $pdo->prepare("
    SELECT maId, maNombre, maImgPath, maEstatus
    FROM marca
    WHERE maId = ?
    LIMIT 1
  ");
  $st->execute([$maId]);
  $row = $st->fetch();

  if (!$row) {
    json_fail('La marca no existe.', 404);
  }

  json_ok(['row' => $row]);
} catch (Throwable $e) {
  json_fail('Error al obtener la marca: ' . $e->getMessage(), 500);
}