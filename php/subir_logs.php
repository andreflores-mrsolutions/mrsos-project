<?php
// ../php/subir_logs.php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/conexion.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'Método no permitido']); exit;
}

$usId = $_SESSION['usId'] ?? null;
$clId = $_SESSION['clId'] ?? null;
if (!$usId || !$clId) {
  echo json_encode(['success' => false, 'error' => 'No autenticado']); exit;
}

$tiId = isset($_POST['tiId']) ? (int)$_POST['tiId'] : 0;
if ($tiId <= 0) {
  echo json_encode(['success' => false, 'error' => 'tiId inválido']); exit;
}

if (!isset($_FILES['logs'])) {
  echo json_encode(['success' => false, 'error' => 'No se recibió archivo']); exit;
}

$file = $_FILES['logs'];
if ($file['error'] !== UPLOAD_ERR_OK) {
  $errMap = [
    UPLOAD_ERR_INI_SIZE => 'Archivo supera el límite del servidor.',
    UPLOAD_ERR_FORM_SIZE => 'Archivo supera el límite del formulario.',
    UPLOAD_ERR_PARTIAL => 'Archivo subido parcialmente.',
    UPLOAD_ERR_NO_FILE => 'No se seleccionó archivo.',
    UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal.',
    UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo.',
    UPLOAD_ERR_EXTENSION => 'Extensión bloqueada por el servidor.'
  ];
  $msg = $errMap[$file['error']] ?? 'Error al subir el archivo.';
  echo json_encode(['success'=>false,'error'=>$msg]); exit;
}

// Autoría del ticket para este cliente
$authOk = false;
if ($stmt = $conectar->prepare("SELECT tiId FROM ticket_soporte WHERE tiId=? AND clId=?")) {
  $stmt->bind_param("ii", $tiId, $clId);
  $stmt->execute();
  $rs = $stmt->get_result();
  $authOk = (bool)$rs->fetch_assoc();
  $stmt->close();
}
if (!$authOk) {
  echo json_encode(['success'=>false,'error'=>'No autorizado para subir logs a este ticket']); exit;
}

// Validaciones de archivo
$maxBytes = 100 * 1024 * 1024; // 100 MB
if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
  echo json_encode(['success'=>false,'error'=>'Archivo vacío o excede 100MB']); exit;
}

$origName = $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowedExt = ['log','txt','zip','gz','tar','7z','rar'];
if (!in_array($ext, $allowedExt, true)) {
  echo json_encode(['success'=>false,'error'=>'Extensión no permitida']); exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowedMime = [
  'text/plain',
  'application/zip',
  'application/x-7z-compressed',
  'application/x-rar', 'application/x-rar-compressed',
  'application/gzip', 'application/x-gzip', 'application/x-tar'
];
if (!in_array($mime, $allowedMime, true) && !in_array($ext, ['log','txt'], true)) {
  echo json_encode(['success'=>false,'error'=>'Tipo de archivo no permitido']); exit;
}

// Ruta de destino
$baseDir  = realpath(__DIR__ . '/..'); // raíz del proyecto
$relDir   = "uploads/logs/" . $tiId;
$destDir  = $baseDir . DIRECTORY_SEPARATOR . $relDir;
if (!is_dir($destDir)) {
  if (!@mkdir($destDir, 0755, true)) {
    echo json_encode(['success'=>false,'error'=>'No se pudo crear directorio de destino']); exit;
  }
}

$slug   = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($origName, PATHINFO_FILENAME));
$stored = sprintf('log_%s_%s.%s', $tiId, uniqid('', true), $ext);
$destAbs = $destDir . DIRECTORY_SEPARATOR . $stored;
$destRel = $relDir . '/' . $stored;

if (!@move_uploaded_file($file['tmp_name'], $destAbs)) {
  echo json_encode(['success'=>false,'error'=>'No se pudo mover el archivo al destino']); exit;
}

// Variables **solo variables** para bind_param
$tipo         = 'log';
$origNameSafe = (string)$origName;
$storedSafe   = (string)$stored;
$mimeSafe     = (string)($mime ?? '');
$tamInt       = (int)$file['size'];
$rutaSafe     = (string)$destRel;
$tiIdInt      = (int)$tiId;
$usIdInt      = (int)($usId ?? 0);

// Registrar en ticket_archivos si existe
$insertOk = false;
if ($stmt = $conectar->prepare("
  INSERT INTO ticket_archivos
    (tiId, taTipo, taNombreOriginal, taNombreAlmacenado, taMime, taTamano, taRuta, usId)
  VALUES (?,?,?,?,?,?,?,?)
")) {
  // i s s s s i s i
  $stmt->bind_param(
    "issssisi",
    $tiIdInt,
    $tipo,
    $origNameSafe,
    $storedSafe,
    $mimeSafe,
    $tamInt,
    $rutaSafe,
    $usIdInt
  );
  $insertOk = $stmt->execute();
  $stmt->close();
}

// Fallback: anotar en tiExtra si no existe ticket_archivos o falló insert
if (!$insertOk) {
  if ($stmt = $conectar->prepare("
      UPDATE ticket_soporte
      SET tiExtra = CONCAT(COALESCE(tiExtra,''), ?)
      WHERE tiId=?
  ")) {
    $nota = " LOGS: " . $destRel;
    $stmt->bind_param("si", $nota, $tiIdInt);
    $stmt->execute();
    $stmt->close();
  }
}
$procesoActualizado = false;
if ($stmt = $conectar->prepare("
    UPDATE ticket_soporte
    SET tiProceso='revision especial'
    WHERE tiId=? AND LOWER(tiProceso)='logs'
")) {
    $stmt->bind_param("i", $tiIdInt);
    $stmt->execute();
    $procesoActualizado = $stmt->affected_rows > 0;
    $stmt->close();
}

echo json_encode([
  'success'            => true,
  'fileUrl'            => $destRel,
  'fileName'           => $origName,
  'procesoActualizado' => $procesoActualizado,
  'nuevoProceso'       => $procesoActualizado ? 'revision especial' : null
]);

