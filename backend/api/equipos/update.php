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
  return $text !== '' ? $text : 'equipo';
}

try {
  $pdo = db();
  $usId = (int)($_SESSION['usId'] ?? 0);

  $eqId = (int)($_POST['eqId'] ?? 0);
  $maId = (int)($_POST['maId'] ?? 0);
  $eqModelo = trim((string)($_POST['eqModelo'] ?? ''));
  $eqVersion = trim((string)($_POST['eqVersion'] ?? ''));
  $eqTipoEquipo = trim((string)($_POST['eqTipoEquipo'] ?? ''));
  $eqTipo = trim((string)($_POST['eqTipo'] ?? ''));
  $eqCPU = trim((string)($_POST['eqCPU'] ?? ''));
  $eqSockets = trim((string)($_POST['eqSockets'] ?? ''));
  $eqMaxRAM = trim((string)($_POST['eqMaxRAM'] ?? ''));
  $eqNIC = trim((string)($_POST['eqNIC'] ?? ''));
  $eqDescripcion = trim((string)($_POST['eqDescripcion'] ?? ''));
  $eqEstatus = trim((string)($_POST['eqEstatus'] ?? 'Activo'));

  $permitidos = ['Activo','Inactivo','Cambios','Error'];

  if ($eqId <= 0) json_fail('ID de equipo inválido.', 422);
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

  $stActual = $pdo->prepare("
    SELECT
      eqId, eqModelo, eqVersion, eqImgPath, eqTipoEquipo, maId,
      eqTipo, eqCPU, eqSockets, eqMaxRAM, eqNIC,
      eqDescripcion, eqEstatus
    FROM equipos
    WHERE eqId = ?
    LIMIT 1
  ");
  $stActual->execute([$eqId]);
  $actual = $stActual->fetch();

  if (!$actual) json_fail('El equipo no existe.', 404);

  $stMarca = $pdo->prepare("SELECT maId, maNombre FROM marca WHERE maId = ? LIMIT 1");
  $stMarca->execute([$maId]);
  $marca = $stMarca->fetch();
  if (!$marca) json_fail('La marca seleccionada no existe.', 404);

  $stDup = $pdo->prepare("
    SELECT eqId
    FROM equipos
    WHERE maId = ? AND eqModelo = ? AND eqVersion = ? AND eqId <> ?
    LIMIT 1
  ");
  $stDup->execute([$maId, $eqModelo, $eqVersion, $eqId]);
  if ($stDup->fetch()) {
    json_fail('Ya existe otro equipo con la misma marca, modelo y versión.', 409);
  }

  $eqImgPath = (string)($actual['eqImgPath'] ?? '');
  $uploadDirFs = dirname(__DIR__, 2) . '/../img/Equipos/';
  $uploadDirDb = '../img/Equipos/';

  if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0775, true) && !is_dir($uploadDirFs)) {
    json_fail('No se pudo crear la carpeta de imágenes.', 500);
  }

  if (!empty($_FILES['eqImagen']['name'])) {
    if (!isset($_FILES['eqImagen']) || $_FILES['eqImagen']['error'] !== UPLOAD_ERR_OK) {
      json_fail('Error al subir la imagen del equipo.', 422);
    }

    $tmp = $_FILES['eqImagen']['tmp_name'];
    $size = (int)($_FILES['eqImagen']['size'] ?? 0);
    $mime = mime_content_type($tmp);

    $permitidosMime = [
      'image/png' => 'png',
      'image/jpeg' => 'jpg',
      'image/webp' => 'webp',
      'image/svg+xml' => 'svg',
    ];

    if (!isset($permitidosMime[$mime])) {
      json_fail('Formato de imagen no permitido.', 422);
    }

    if ($size > 2 * 1024 * 1024) {
      json_fail('La imagen no debe exceder 2 MB.', 422);
    }

    $ext = $permitidosMime[$mime];
    $base = slug_filename($marca['maNombre'] . '-' . $eqModelo . '-' . $eqVersion);
    $filename = $base . '.' . $ext;
    $destFs = $uploadDirFs . $filename;

    if (file_exists($destFs)) {
      $filename = $base . '_' . time() . '.' . $ext;
      $destFs = $uploadDirFs . $filename;
    }

    if (!move_uploaded_file($tmp, $destFs)) {
      json_fail('No se pudo guardar la imagen del equipo.', 500);
    }

    if (!empty($eqImgPath) && $eqImgPath !== '../img/Equipos/default.png') {
      $oldFs = dirname(__DIR__, 2) . '/../' . $eqImgPath;
      if (is_file($oldFs)) {
        @unlink($oldFs);
      }
    }

    $eqImgPath = $uploadDirDb . $filename;
  }

  $st = $pdo->prepare("
    UPDATE equipos
    SET
      eqModelo = ?, eqVersion = ?, eqImgPath = ?, eqTipoEquipo = ?, maId = ?,
      eqTipo = ?, eqCPU = ?, eqSockets = ?, eqMaxRAM = ?, eqNIC = ?,
      eqDescripcion = ?, eqEstatus = ?
    WHERE eqId = ?
    LIMIT 1
  ");
  $st->execute([
    $eqModelo, $eqVersion, $eqImgPath ?: null, $eqTipoEquipo, $maId,
    $eqTipo, $eqCPU, $eqSockets, $eqMaxRAM, $eqNIC,
    $eqDescripcion, $eqEstatus, $eqId
  ]);

  if (class_exists('Historial')) {
    $cambios = [];

    foreach ([
      'maId','eqModelo','eqVersion','eqTipoEquipo','eqTipo','eqCPU',
      'eqSockets','eqMaxRAM','eqNIC','eqDescripcion','eqEstatus'
    ] as $campo) {
      $nuevoValor = ($campo === 'maId') ? (string)$maId : (string)$$campo;
      $viejoValor = (string)($actual[$campo] ?? '');
      if ($viejoValor !== $nuevoValor) {
        $cambios[] = "{$campo}: '{$viejoValor}' -> '{$nuevoValor}'";
      }
    }

    if ((string)($actual['eqImgPath'] ?? '') !== (string)$eqImgPath) {
      $cambios[] = "eqImgPath: '" . ((string)$actual['eqImgPath'] ?: 'NULL') . "' -> '" . ((string)$eqImgPath ?: 'NULL') . "'";
    }

    Historial::log(
      $pdo,
      $usId,
      'equipos',
      "UPDATE equipo (eqId={$eqId}) - " . ($cambios ? implode(' | ', $cambios) : 'Sin cambios visibles.'),
      'Activo'
    );
  }

  json_ok([
    'message' => 'Equipo actualizado correctamente.',
    'eqId' => $eqId,
    'eqImgPath' => $eqImgPath ?: null
  ]);
} catch (PDOException $e) {
  if ((string)$e->getCode() === '23000') {
    json_fail('No se pudo actualizar el equipo porque ya existe un registro igual.', 409);
  }
  json_fail('Error al actualizar el equipo: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
  json_fail('Error al actualizar el equipo: ' . $e->getMessage(), 500);
}