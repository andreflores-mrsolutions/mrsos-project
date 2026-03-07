<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$pdo = db();
$pcId = (int)($_POST['pcId'] ?? 0);
if ($pcId <= 0) json_fail('pcId requerido');

$st = $pdo->prepare("SELECT pcId, clId, pcIdentificador FROM polizascliente WHERE pcId=? LIMIT 1");
$st->execute([$pcId]);
$pc = $st->fetch(PDO::FETCH_ASSOC);
if (!$pc) json_fail('Póliza no existe');

$clId = (int)$pc['clId'];
$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = current_usRol();

if (!mr_can_access_client($pdo, $usId, $usRol, $clId)) {
  json_fail('Sin acceso al cliente');
}

$pcIdentificador = (string)$pc['pcIdentificador'];
if (trim($pcIdentificador) === '') json_fail('pcIdentificador inválido');

// sanitizar para filesystem (sin cambiar el texto “lógico” en BD)
function sanitize_fs(string $s): string {
  $s = trim($s);
  $s = preg_replace('/[^\pL\pN\-_]/u', '', $s) ?? '';
  if ($s === '') $s = 'POLIZA';
  return $s;
}

function require_pdf(string $field): array {
  if (!isset($_FILES[$field])) json_fail("Archivo faltante: $field");
  $f = $_FILES[$field];
  if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_fail("Error subiendo archivo: $field");
  }
  $name = (string)($f['name'] ?? '');
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if ($ext !== 'pdf') json_fail("El archivo $field debe ser PDF");
  return $f;
}

function optional_pdf(string $field): ?array {
  if (!isset($_FILES[$field])) return null;
  $f = $_FILES[$field];
  if (!is_array($f)) return null;
  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) json_fail("Error subiendo archivo: $field");
  $name = (string)($f['name'] ?? '');
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if ($ext !== 'pdf') json_fail("El archivo $field debe ser PDF");
  return $f;
}

// factura obligatoria
$fileFactura = require_pdf('file_factura');
$filePoliza  = optional_pdf('file_poliza');
$fileWK      = optional_pdf('file_wk');

$base = sanitize_fs($pcIdentificador);

// destino
$relDir = "img/Polizas/$clId/$pcId";
$absDir = realpath(__DIR__ . '/../../../') . DIRECTORY_SEPARATOR . $relDir;

if ($absDir === false) json_fail('No se pudo resolver ruta base');
if (!is_dir($absDir) && !mkdir($absDir, 0775, true)) json_fail('No se pudo crear carpeta destino');

function move_pdf(array $f, string $absDir, string $filename): string {
  $dst = $absDir . DIRECTORY_SEPARATOR . $filename;
  if (!move_uploaded_file((string)$f['tmp_name'], $dst)) {
    json_fail("No se pudo guardar $filename");
  }
  return $filename;
}

$facturaName = $base . 'factura.pdf';
$polizaName  = $base . 'poliza.pdf';
$wkName      = $base . 'WK.pdf';

move_pdf($fileFactura, $absDir, $facturaName);
if ($filePoliza) move_pdf($filePoliza, $absDir, $polizaName);
if ($fileWK)     move_pdf($fileWK, $absDir, $wkName);

// rutas relativas a guardar
$facturaPath = "$relDir/$facturaName";
$polizaPath  = $filePoliza ? "$relDir/$polizaName" : null;
$wkPath      = $fileWK ? "$relDir/$wkName" : null;

// ⚠️ Requiere que existan columnas pcPdfPolizaPath y pcPdfWKPath.
// Si aún no las agregas, dime y lo dejamos en NULL sin fallar (o hacemos ALTER).
try {
  $st = $pdo->prepare("
    UPDATE polizascliente
    SET pcPdfPath = ?, pcPdfPolizaPath = COALESCE(?, pcPdfPolizaPath), pcPdfWKPath = COALESCE(?, pcPdfWKPath)
    WHERE pcId = ?
    LIMIT 1
  ");
  $st->execute([$facturaPath, $polizaPath, $wkPath, $pcId]);
} catch (Throwable $e) {
  json_fail('Error actualizando rutas PDF (¿faltan columnas pcPdfPolizaPath/pcPdfWKPath?)');
}

json_ok([
  'pcId' => $pcId,
  'pcPdfPath' => $facturaPath,
  'pcPdfPolizaPath' => $polizaPath,
  'pcPdfWKPath' => $wkPath
]);