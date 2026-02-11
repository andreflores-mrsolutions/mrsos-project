<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../php/auth_guard.php';
require_login();

$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA','MRSA','ADMIN'], true)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . '/../../php/csrf.php';
csrf_verify_or_fail();

require_once __DIR__ . '/../../php/conexion.php';

$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$tiId = isset($raw['tiId']) ? (int)$raw['tiId'] : 0;

if ($tiId <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Falta tiId'], JSON_UNESCAPED_UNICODE);
  exit;
}

// regresar a logs
$sql = "UPDATE ticket_soporte SET tiProceso='logs' WHERE tiId=? LIMIT 1";
$stmt = $conectar->prepare($sql);
$stmt->bind_param("i", $tiId);
$stmt->execute();

echo json_encode([
  'success' => true,
  'tiId' => $tiId,
  'note' => 'Notificación (correo/celular) pendiente de integrar'
], JSON_UNESCAPED_UNICODE);
