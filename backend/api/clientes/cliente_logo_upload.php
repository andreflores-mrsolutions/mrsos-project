<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);
csrf_verify_or_fail();

$pdo = db();

$clId = (int)($_POST['clId'] ?? 0);
if ($clId <= 0) json_fail('clId requerido.');

try {
  require_cliente_exists($pdo, $clId);
  require_mr_access_client($pdo, $clId);

  if (!isset($_FILES['imagen']) || !is_array($_FILES['imagen'])) {
    json_fail('Archivo imagen requerido (campo: imagen).');
  }

  $f = $_FILES['imagen'];

  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_fail('Error al subir imagen (code ' . (int)$f['error'] . ').');
  }

  $maxBytes = 2 * 1024 * 1024; // 2MB
  if (($f['size'] ?? 0) <= 0 || (int)$f['size'] > $maxBytes) {
    json_fail('La imagen excede 2MB o está vacía.');
  }

  $tmp = (string)$f['tmp_name'];
  $imgInfo = @getimagesize($tmp);
  if (!$imgInfo) json_fail('Archivo no es una imagen válida.');

  $mime = $imgInfo['mime'] ?? '';
  $extMap = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
  ];
  if (!isset($extMap[$mime])) {
    json_fail('Formato no permitido. Usa JPG, PNG o WEBP.');
  }
  $ext = $extMap[$mime];

  // Tomar nombre del cliente desde BD (fuente de verdad)
  $st = $pdo->prepare("SELECT clNombre FROM clientes WHERE clId=? LIMIT 1");
  $st->execute([$clId]);
  $clNombre = (string)$st->fetchColumn();
  if ($clNombre === '') json_fail('Cliente sin nombre.');

  // Sanitización mínima para filesystem (manteniendo "tal cual" lo más posible)
  $base = trim($clNombre);

  // quitar caracteres inválidos Windows/Linux
  $base = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/u', '', $base) ?? $base;
  // quitar controles
  $base = preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $base) ?? $base;

  $base = trim($base);
  if ($base === '') $base = 'Cliente_' . $clId;

  $fileName = $base . '.' . $ext;

  // Ruta física
  $dir = realpath(__DIR__ . '/../../../img') ?: (__DIR__ . '/../../../img');
  $targetDir = $dir . DIRECTORY_SEPARATOR . 'Clientes';

  if (!is_dir($targetDir)) {
    if (!@mkdir($targetDir, 0775, true)) {
      json_fail('No se pudo crear carpeta img/Clientes.');
    }
  }

  // Borrar versiones anteriores con el mismo base (para evitar basura)
  foreach (['jpg','png','webp'] as $e) {
    $p = $targetDir . DIRECTORY_SEPARATOR . $base . '.' . $e;
    if (is_file($p)) @unlink($p);
  }

  $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

  if (!@move_uploaded_file($tmp, $targetPath)) {
    json_fail('No se pudo guardar la imagen en el servidor.');
  }

  // Guardar ruta relativa en BD
  $rel = 'Clientes/' . $fileName;

  $st = $pdo->prepare("UPDATE clientes SET clImagen=? WHERE clId=?");
  $st->execute([$rel, $clId]);

  json_ok([
    'clId' => $clId,
    'clImagen' => $rel
  ]);
} catch (Throwable $e) {
  json_fail('Error al subir imagen del cliente.', 500);
}