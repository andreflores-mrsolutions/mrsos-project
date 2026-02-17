<?php
// admin/api/logs_preview.php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA','MRSA','ADMIN'], true)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) json_fail('Falta id', 400);

$MAX = 500000; // 500 KB preview

$pdo = db();

try {
  $st = $pdo->prepare("
    SELECT taId, tiId, taNombreOriginal, taNombreAlmacenado, taTamano, taMime, taRuta, fecha
    FROM ticket_archivos
    WHERE taId = ?
    LIMIT 1
  ");
  $st->execute([$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) json_fail('Archivo no encontrado', 404);

  $ruta = (string)($r['taRuta'] ?? '');
  if ($ruta === '' || !str_starts_with($ruta, 'uploads/')) json_fail('Ruta inválida', 400);

  $download = '../' . $ruta;
  $size = (int)($r['taTamano'] ?? 0);

  // si es muy grande, no mandamos contenido
  if ($size > $MAX) {
    json_ok([
      'filename' => ($r['taNombreOriginal'] ?: $r['taNombreAlmacenado']),
      'size_bytes' => $size,
      'too_large' => true,
      'download_url' => $download
    ]);
  }

  // leer contenido desde disco (usa la ruta real del proyecto)
  $abs = realpath(__DIR__ . '/../../' . $ruta);
  if (!$abs || !is_file($abs)) {
    json_ok([
      'filename' => ($r['taNombreOriginal'] ?: $r['taNombreAlmacenado']),
      'size_bytes' => $size,
      'too_large' => true,
      'download_url' => $download
    ]);
  }

  $content = file_get_contents($abs);
  if ($content === false) json_fail('No se pudo leer el archivo', 500);

  // recorte final por si acaso
  if (strlen($content) > $MAX) $content = substr($content, 0, $MAX);

  json_ok([
    'filename' => ($r['taNombreOriginal'] ?: $r['taNombreAlmacenado']),
    'size_bytes' => $size,
    'too_large' => false,
    'download_url' => $download,
    'content' => $content
  ]);
} catch (Throwable $e) {
  json_fail('Error preview logs', 500);
}
