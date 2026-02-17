<?php
// admin/api/ticket_diagnostico.php
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

$in = read_json_body();
$tiId = (int)($in['tiId'] ?? 0);
$diag = trim((string)($in['diagnostico'] ?? ''));
$next = trim((string)($in['nextStep'] ?? ''));

if ($tiId <= 0) json_fail('Falta tiId', 400);
if ($diag === '') $diag = 'Faltan datos';
if ($next === '') json_fail('Falta nextStep', 400);

// whitelist de procesos permitidos (evita inyección por proceso)
$allowed = [
  'asignacion','revision inicial','logs','meet','revision especial','espera refaccion',
  'visita','fecha asignada','espera ventana','espera visita','en camino',
  'espera documentacion','encuesta satisfaccion','finalizado','cancelado',
  'fuera de alcance','servicio por evento'
];
if (!in_array($next, $allowed, true)) json_fail('Proceso no permitido', 400);

try {
  $pdo = db();

  $stmt = $pdo->prepare("SELECT tiDiagnostico FROM ticket_soporte WHERE tiId=? LIMIT 1");
  $stmt->execute([$tiId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if(!$row) json_fail('Ticket no existe', 404);

  $extra = [];
  if (!empty($row['tiDiagnostico'])) {
    $tmp = json_decode((string)$row['tiDiagnostico'], true);
    if (is_array($tmp)) $extra = $tmp;
  }

  $extra['diagnostico'] = [
    'texto' => $diag,
    'by' => (int)($_SESSION['usId'] ?? 0),
    'at' => date('c'),
  ];

  $upd = $pdo->prepare("
    UPDATE ticket_soporte
    SET tiProceso=?, tiDiagnostico=?
    WHERE tiId=?
    LIMIT 1
  ");
  $upd->execute([$next, json_encode($extra, JSON_UNESCAPED_UNICODE), $tiId]);

  json_ok(['tiId'=>$tiId, 'tiProceso'=>$next]);
} catch (Throwable $e) {
  json_fail('Error interno', 500);
}
