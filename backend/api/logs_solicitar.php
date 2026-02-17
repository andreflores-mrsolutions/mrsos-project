<?php
// admin/api/logs_solicitar.php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';
require_once __DIR__ . '/../../php/csrf.php';

no_store();
require_login();
$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA','MRSA','ADMIN'], true)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}
require_login();
csrf_verify_or_fail();

$pdo   = db();
$usId  = (int)($_SESSION['usId'] ?? 0);
$usRol = current_usRol();

$body = read_json_body();
$in = read_json_body();
$tiId  = (int)($in['tiId'] ?? 0);
if ($tiId <= 0) json_fail('Falta tiId', 400);


$motivo = trim((string)($in['motivo'] ?? ''));
if ($motivo === '') $motivo = 'Solicito nuevamente logs.';

try {
  $pdo = db();

  // Guardamos “motivo” en tiDiagnostico como JSON (no te rompe estructura)
  // Si tú ya usas tiDescripcion para análisis, puedes moverlo ahí.
  $stmt = $pdo->prepare("SELECT tiDiagnostico FROM ticket_soporte WHERE tiId=? LIMIT 1");
  $stmt->execute([$tiId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if(!$row) json_fail('Ticket no existe', 404);

  $extra = [];
  if (!empty($row['tiDiagnostico'])) {
    $tmp = json_decode((string)$row['tiDiagnostico'], true);
    if (is_array($tmp)) $extra = $tmp;
  }

  $extra['logs_request'] = [
    'motivo' => $motivo,
    'by' => (int)($_SESSION['usId'] ?? 0),
    'at' => date('c'),
  ];

  $upd = $pdo->prepare("
    UPDATE ticket_soporte
    SET tiProceso='logs', tiDiagnostico=?
    WHERE tiId=?
    LIMIT 1
  ");
  $upd->execute([json_encode($extra, JSON_UNESCAPED_UNICODE), $tiId]);

  // TODO: notificación correo/celular (placeholder)
  // notify_client_logs_request($tiId, $motivo);

  json_ok(['tiId'=>$tiId, 'tiProceso'=>'logs']);
} catch (Throwable $e) {
  json_fail('Error interno', 500);
}
