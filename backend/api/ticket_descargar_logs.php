<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../php/auth_guard.php';
require_login();

$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA','MRSA','ADMIN'], true)) {
  http_response_code(403);
  exit('Sin permisos');
}

require_once __DIR__ . '/../../php/conexion.php';

$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;
if ($tiId <= 0) { http_response_code(400); exit('Falta tiId'); }

// AJUSTA nombres de columnas si difieren:
// - tabla: ticket_archivo
// - columnas esperadas: taRuta/taPath/archivoRuta etc.
$sql = "SELECT * FROM ticket_archivo WHERE tiId = ? ORDER BY taId DESC";
$stmt = $conectar->prepare($sql);
$stmt->bind_param("i", $tiId);
$stmt->execute();
$res = $stmt->get_result();
$files = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

if (!$files) { http_response_code(404); exit('No hay archivos'); }

$zipName = "logs_ticket_{$tiId}.zip";
$tmp = tempnam(sys_get_temp_dir(), 'mrs_logs_');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);

// intenta detectar la ruta en cualquier key típica
$pathKeys = ['taRuta','taPath','archivoRuta','ruta','path'];
$nameKeys = ['taNombre','nombre','filename','archivoNombre'];

foreach ($files as $f) {
  $path = '';
  foreach ($pathKeys as $k) { if (!empty($f[$k])) { $path = (string)$f[$k]; break; } }
  if (!$path) continue;

  // si guardas rutas relativas, ajusta base:
  $full = $path;
  if ($path[0] !== '/' && strpos($path, ':\\') === false) {
    $full = realpath(__DIR__ . '/../../' . ltrim($path,'/'));
  }

  if (!$full || !is_file($full)) continue;

  $name = basename($full);
  foreach ($nameKeys as $k) { if (!empty($f[$k])) { $name = (string)$f[$k]; break; } }

  $zip->addFile($full, $name);
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$zipName.'"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
