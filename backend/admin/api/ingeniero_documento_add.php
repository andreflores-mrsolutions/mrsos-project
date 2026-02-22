<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);
csrf_verify_or_fail();

$usIdIng = (int)($_POST['usIdIng'] ?? 0);
$tipo    = trim((string)($_POST['tipo'] ?? ''));
$label   = trim((string)($_POST['label'] ?? ''));

$allowedTipos = ['credencial_trabajo','INE','NSS','OTRO'];

if ($usIdIng <= 0 || $tipo === '' || !in_array($tipo, $allowedTipos, true)) {
  json_fail('Datos inválidos (usIdIng/tipo).');
}
if ($label === '') {
  $label = match($tipo){
    'credencial_trabajo' => 'Credencial de trabajo',
    'INE' => 'INE',
    'NSS' => 'NSS',
    default => 'Documento'
  };
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  json_fail('Archivo inválido.');
}

$tmp  = $_FILES['file']['tmp_name'];
$name = (string)$_FILES['file']['name'];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmp) ?: 'application/octet-stream';

if ($mime !== 'application/pdf') json_fail('Solo se permite PDF.');

$pdo = db();

// valida que exista ingeniero
$st = $pdo->prepare("SELECT usId FROM ingenieros WHERE usId=? LIMIT 1");
$st->execute([$usIdIng]);
if (!$st->fetchColumn()) json_fail('Ingeniero no existe', 404);

// Ruta relativa (NO absoluta)
$relDir = "uploads/ingenieros/{$usIdIng}/docs";
$absDir = realpath(__DIR__ . '/../../../') . '/' . $relDir; // backend/ + relDir

if (!is_dir($absDir) && !mkdir($absDir, 0775, true)) {
  json_fail('No se pudo crear directorio de carga.');
}

$base = preg_replace('/[^a-zA-Z0-9_\-\.]+/', '_', pathinfo($name, PATHINFO_FILENAME));
$fn = $tipo . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $base . '.pdf';
$absPath = $absDir . '/' . $fn;
$relPath = $relDir . '/' . $fn;

if (!move_uploaded_file($tmp, $absPath)) {
  json_fail('No se pudo guardar el archivo.');
}

$pdo->prepare("
  INSERT INTO ingeniero_documentos (usIdIng, tipo, label, archivo, mime, verificado, activo)
  VALUES (?, ?, ?, ?, ?, 0, 1)
")->execute([$usIdIng, $tipo, $label, $relPath, $mime]);

$idocId = (int)$pdo->lastInsertId();

json_ok([
  'idocId' => $idocId,
  'doc' => [
    'usIdIng' => $usIdIng,
    'idocId' => $idocId,
    'tipo' => $tipo,
    'label' => $label,
    'archivo' => $relPath,
    'mime' => $mime,
    'verificado' => 0,
    'activo' => 1
  ]
]);