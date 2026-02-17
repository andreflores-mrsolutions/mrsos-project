<?php
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
$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;
if ($tiId <= 0) json_fail('Falta tiId', 400);

$pdo = db();

/**
 * AJUSTA AQUÍ los nombres reales de columnas:
 * - taId / id
 * - taNombreArchivo / archivo
 * - taRuta / ruta (si existe)
 * - taFecha / created_at
 * - taTipo / categoria
 *
 * Si no existe ruta, asumimos: uploads/logs/{tiId}/{archivo}
 */
try {
  // 👉 Cambia SELECT según tu tabla real:
  $st = $pdo->prepare("
    SELECT
      taId AS id,
      taNombreAlmacenado AS filename,
      fecha AS uploaded_at
    FROM ticket_archivos
    WHERE tiId = ?
    ORDER BY fecha DESC, taId DESC
  ");
  $st->execute([$tiId]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $files = [];
  foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    $filename = (string)($r['filename'] ?? '');
    if ($id <= 0 || $filename === '') continue;

    // Ruta física (tu regla)
    $rel = "../uploads/logs/{$tiId}/{$filename}";
    $abs = realpath(__DIR__ . "/../uploads/logs/{$tiId}/{$filename}");

    // seguridad: evitar path traversal
    if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
      continue;
    }

    // size (si existe en disco)
    $size = 0;
    $diskPath = __DIR__ . "/../uploads/logs/{$tiId}/{$filename}";
    if (is_file($diskPath)) {
      $size = (int)filesize($diskPath);
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $canPreview = in_array($ext, ['txt','log','out','cfg','ini','json','xml','csv'], true) && $size > 0 && $size <= 500000; // 500KB

    $files[] = [
      'id' => $id,
      'filename' => $filename,
      'size_bytes' => $size,
      'ext' => $ext,
      'uploaded_at' => $r['uploaded_at'] ?? null,
      'download_url' => $rel,
      'can_preview' => $canPreview,
      'tiId' => $tiId,
    ];
  }

  json_ok(['files' => $files]);
} catch (Throwable $e) {
  json_fail('Error listando logs', 500);
}
