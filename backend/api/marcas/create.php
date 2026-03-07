<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/historial.php';

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

  $maNombre = trim((string)($_POST['maNombre'] ?? ''));
  $maEstatus = trim((string)($_POST['maEstatus'] ?? 'Activo'));
  $permitidosEstatus = ['Activo','Inactivo','Cambios','Error'];

  if ($maNombre === '') {
    json_fail('El nombre de la marca es obligatorio.', 422);
  }

  if (mb_strlen($maNombre) > 50) {
    json_fail('El nombre de la marca no puede exceder 50 caracteres.', 422);
  }

  if (!in_array($maEstatus, $permitidosEstatus, true)) {
    json_fail('Estatus de marca inválido.', 422);
  }

  $stDup = $pdo->prepare("SELECT maId FROM marca WHERE maNombre = ? LIMIT 1");
  $stDup->execute([$maNombre]);
  if ($stDup->fetch()) {
    json_fail('Ya existe una marca con ese nombre.', 409);
  }

  $maImgPath = null;
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

    $maImgPath = $uploadDirDb . $filename;
  }

  $st = $pdo->prepare("
    INSERT INTO marca (maNombre, maImgPath, maEstatus)
    VALUES (?, ?, ?)
  ");
  $st->execute([$maNombre, $maImgPath, $maEstatus]);

  $maId = (int)$pdo->lastInsertId();

  if (class_exists('Historial')) {
    Historial::log(
      $pdo,
      $usId,
      'marca',
      "CREATE marca (maId={$maId}) - Alta de marca '{$maNombre}' con estatus '{$maEstatus}'" . ($maImgPath ? " e imagen '{$maImgPath}'." : '.'),
      'Activo'
    );
  }

  json_ok([
    'message' => 'Marca creada correctamente.',
    'maId' => $maId,
    'maImgPath' => $maImgPath
  ]);
} catch (PDOException $e) {
  if ((string)$e->getCode() === '23000') {
    json_fail('No se pudo crear la marca porque ya existe un registro igual.', 409);
  }
  json_fail('Error al crear la marca: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
  json_fail('Error al crear la marca: ' . $e->getMessage(), 500);
}