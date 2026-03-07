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

function slug_filename(string $text): string {
  $text = trim(mb_strtolower($text, 'UTF-8'));
  $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
  if ($tmp !== false) $text = $tmp;
  $text = preg_replace('/[^a-z0-9]+/', '-', $text);
  $text = trim((string)$text, '-');
  return $text !== '' ? $text : 'marca';
}

try {
  $pdo = db();
  $usId = (int)($_SESSION['usId'] ?? 0);

  $maId = (int)($_POST['maId'] ?? 0);
  $maNombre = trim((string)($_POST['maNombre'] ?? ''));
  $maEstatus = trim((string)($_POST['maEstatus'] ?? 'Activo'));
  $permitidosEstatus = ['Activo','Inactivo','Cambios','Error'];

  if ($maId <= 0) {
    json_fail('ID de marca inválido.', 422);
  }

  if ($maNombre === '') {
    json_fail('El nombre de la marca es obligatorio.', 422);
  }

  if (mb_strlen($maNombre) > 50) {
    json_fail('El nombre de la marca no puede exceder 50 caracteres.', 422);
  }

  if (!in_array($maEstatus, $permitidosEstatus, true)) {
    json_fail('Estatus de marca inválido.', 422);
  }

  $stCurrent = $pdo->prepare("
    SELECT maId, maNombre, maImgPath, maEstatus
    FROM marca
    WHERE maId = ?
    LIMIT 1
  ");
  $stCurrent->execute([$maId]);
  $actual = $stCurrent->fetch();

  if (!$actual) {
    json_fail('La marca no existe.', 404);
  }

  $stDup = $pdo->prepare("
    SELECT maId
    FROM marca
    WHERE maNombre = ? AND maId <> ?
    LIMIT 1
  ");
  $stDup->execute([$maNombre, $maId]);
  if ($stDup->fetch()) {
    json_fail('Ya existe otra marca con ese nombre.', 409);
  }

  $maImgPath = (string)($actual['maImgPath'] ?? '');
  $uploadDirFs = dirname(__DIR__, 2) . '/../img/Marcas/';
  $uploadDirDb = 'img/Marcas/';

  if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0775, true) && !is_dir($uploadDirFs)) {
    json_fail('No se pudo crear la carpeta de imágenes.', 500);
  }

  if (!empty($_FILES['maImagen']['name'])) {
    if (!isset($_FILES['maImagen']) || $_FILES['maImagen']['error'] !== UPLOAD_ERR_OK) {
      json_fail('Error al subir la imagen.', 422);
    }

    $tmp = $_FILES['maImagen']['tmp_name'];
    $size = (int)($_FILES['maImagen']['size'] ?? 0);
    $mime = mime_content_type($tmp);

    $permitidos = [
      'image/png' => 'png',
      'image/jpeg' => 'jpg',
      'image/webp' => 'webp',
      'image/svg+xml' => 'svg',
    ];

    if (!isset($permitidos[$mime])) {
      json_fail('Formato de imagen no permitido.', 422);
    }

    if ($size > 2 * 1024 * 1024) {
      json_fail('La imagen no debe exceder 2 MB.', 422);
    }

    $ext = $permitidos[$mime];
    $base = slug_filename($maNombre);
    $filename = $base . '.' . $ext;
    $destFs = $uploadDirFs . $filename;

    if (file_exists($destFs)) {
      $filename = $base . '_' . time() . '.' . $ext;
      $destFs = $uploadDirFs . $filename;
    }

    if (!move_uploaded_file($tmp, $destFs)) {
      json_fail('No se pudo guardar la imagen.', 500);
    }

    if (!empty($maImgPath) && $maImgPath !== 'img/Marcas/default.png') {
      $oldFs = dirname(__DIR__, 2) . '/' . $maImgPath;
      if (is_file($oldFs)) {
        @unlink($oldFs);
      }
    }

    $maImgPath = $uploadDirDb . $filename;
  }

  $st = $pdo->prepare("
    UPDATE marca
    SET maNombre = ?, maImgPath = ?, maEstatus = ?
    WHERE maId = ?
    LIMIT 1
  ");
  $st->execute([$maNombre, $maImgPath ?: null, $maEstatus, $maId]);

  if (class_exists('Historial')) {
    $cambios = [];

    if ((string)$actual['maNombre'] !== $maNombre) {
      $cambios[] = "maNombre: '{$actual['maNombre']}' -> '{$maNombre}'";
    }
    if ((string)($actual['maImgPath'] ?? '') !== (string)$maImgPath) {
      $cambios[] = "maImgPath: '" . ((string)$actual['maImgPath'] ?: 'NULL') . "' -> '" . ((string)$maImgPath ?: 'NULL') . "'";
    }
    if ((string)$actual['maEstatus'] !== $maEstatus) {
      $cambios[] = "maEstatus: '{$actual['maEstatus']}' -> '{$maEstatus}'";
    }

    Historial::log(
      $pdo,
      $usId,
      'marca',
      "UPDATE marca (maId={$maId}) - " . ($cambios ? implode(' | ', $cambios) : 'Sin cambios visibles.'),
      'Activo'
    );
  }

  json_ok([
    'message' => 'Marca actualizada correctamente.',
    'maId' => $maId,
    'maImgPath' => $maImgPath ?: null
  ]);
} catch (PDOException $e) {
  if ((string)$e->getCode() === '23000') {
    json_fail('No se pudo actualizar la marca porque ya existe un registro igual.', 409);
  }
  json_fail('Error al actualizar la marca: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
  json_fail('Error al actualizar la marca: ' . $e->getMessage(), 500);
}