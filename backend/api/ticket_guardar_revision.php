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
$tiId = (int)($raw['tiId'] ?? 0);
$diagnostico = trim((string)($raw['diagnostico'] ?? ''));
$nextStep = trim((string)($raw['nextStep'] ?? ''));
$nuevoEstatus = trim((string)($raw['nuevoEstatus'] ?? ''));

if ($tiId <= 0) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Falta tiId'], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($diagnostico === '') $diagnostico = 'Faltan datos';

// VALIDAR nextStep contra tu lista real (hardening)
$validSteps = [
  'asignacion','revision inicial','logs','meet','revision especial','espera refaccion','visita',
  'fecha asignada','espera ventana','espera visita','en camino','espera documentacion',
  'encuesta satisfaccion','finalizado','cancelado','fuera de alcance','servicio por evento'
];
if ($nextStep !== '' && !in_array($nextStep, $validSteps, true)) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Paso inválido'], JSON_UNESCAPED_UNICODE);
  exit;
}

$allowedStatus = ['','Abierto','Pospuesto','Cerrado'];
if (!in_array($nuevoEstatus, $allowedStatus, true)) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Estatus inválido'], JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * IMPORTANTE:
 * Ajusta aquí el campo donde guardas “descripción de falla / diagnóstico”.
 * Yo uso `tiDescripcion` porque es el más típico.
 * Si tú ya tienes `tiDiagnostico` o `tiAnalisis`, cámbialo aquí.
 */
if ($nextStep !== '' && $nuevoEstatus !== '') {
  $sql = "UPDATE ticket_soporte SET tiDescripcion=?, tiProceso=?, tiEstatus=? WHERE tiId=? LIMIT 1";
  $stmt = $conectar->prepare($sql);
  $stmt->bind_param("sssi", $diagnostico, $nextStep, $nuevoEstatus, $tiId);
} elseif ($nextStep !== '') {
  $sql = "UPDATE ticket_soporte SET tiDescripcion=?, tiProceso=? WHERE tiId=? LIMIT 1";
  $stmt = $conectar->prepare($sql);
  $stmt->bind_param("ssi", $diagnostico, $nextStep, $tiId);
} elseif ($nuevoEstatus !== '') {
  $sql = "UPDATE ticket_soporte SET tiDescripcion=?, tiEstatus=? WHERE tiId=? LIMIT 1";
  $stmt = $conectar->prepare($sql);
  $stmt->bind_param("ssi", $diagnostico, $nuevoEstatus, $tiId);
} else {
  $sql = "UPDATE ticket_soporte SET tiDescripcion=? WHERE tiId=? LIMIT 1";
  $stmt = $conectar->prepare($sql);
  $stmt->bind_param("si", $diagnostico, $tiId);
}

$stmt->execute();

echo json_encode([
  'success' => true,
  'tiId' => $tiId,
  'nextStep' => $nextStep,
  'nuevoEstatus' => $nuevoEstatus
], JSON_UNESCAPED_UNICODE);
