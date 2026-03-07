<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';
require_once __DIR__ . '/../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);


$pdo = db();
try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
  }

  $tiId  = isset($_POST['tiId']) ? (int)$_POST['tiId'] : 0;
  $diag  = isset($_POST['tiDiagnostico']) ? trim((string)$_POST['tiDiagnostico']) : '';
  $next  = isset($_POST['nextProceso']) ? trim((string)$_POST['nextProceso']) : 'logs';

  if ($tiId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'tiId inválido']);
    exit;
  }
  if ($diag === '') $diag = 'Faltan datos';



  // Actualiza diagnóstico y avanza proceso
  $upd = $pdo->prepare("UPDATE ticket_soporte SET tiDiagnostico = :d, tiProceso = :p WHERE tiId = :id");
  $upd->execute([':d' => $diag, ':p' => $next, ':id' => $tiId]);

  // (Opcional) historial
  // $h = $pdo->prepare("INSERT INTO historial (hDescripcion, usId, hFecha_hora, hTabla, hEstatus)
  //                     VALUES (:desc, :usr, NOW(), 'ticket_soporte', 'Activo')");
  // $h->execute([':desc' => "Análisis guardado y proceso → $next (tiId $tiId)", ':usr' => $usuarioActualId]);

  echo json_encode(['success' => true]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
