<?php
require_once "conexion.php";
require_once "auth_guard.php";
require_once 'json.php';
require_once __DIR__ . "/services/NotificationService.php";

header('Content-Type: application/json; charset=utf-8');
require_login();
$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA','MRSA','ADMIN'], true)) {
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'Sin permisos'], JSON_UNESCAPED_UNICODE);
  exit;
}


// ✅ CONFIG
$PROJECT_ID = 'mrsos-31341';
// Ruta segura al service account:
$SERVICE_ACCOUNT = __DIR__ . '/../secrets/mrsos-31341-66e405cfe145.json';
$action   = (string)($_POST['action'] ?? '');
$tiId     = (int)($_POST['tiId'] ?? 0);
$audience = (string)($_POST['audience'] ?? 'ambos'); // cliente|ingeniero|ambos
$proceso  = (string)($_POST['proceso'] ?? '');
$tiFolio  = (string)($_POST['folio'] ?? '');

// echo $action, $tiId, $audience, $proceso, $tiFolio;

if ($action === '') {
  echo json_encode(['success'=>false,'message'=>'action requerido']);
  exit;
}

if ($tiFolio === '') {
  echo json_encode(['success'=>false,'message'=>'folio requerido']);
  exit;
}

try {
  $fcm = new FcmService($PROJECT_ID, $SERVICE_ACCOUNT);
  $noti = new NotificationService($conectar, $fcm);

  // contexto: todo lo que venga por POST lo pasamos como ctx
  $ctx = $_POST;
  $ctx['byUsId'] = $_SESSION['usId'] ?? 0;

  $r = $noti->dispatch($action, $ctx, $tiFolio);

  echo json_encode([
    'success' => ($r['ok'] ?? false),
    'sent'    => ($r['sent'] ?? 0),
    'errors'  => ($r['errors'] ?? []),
  ]);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
