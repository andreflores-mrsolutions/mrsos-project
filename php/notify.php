<?php
require_once "conexion.php";
require_once "auth_guard.php";
require_once __DIR__ . "/services/NotificationService.php";

header('Content-Type: application/json; charset=utf-8');
require_login();

// ✅ CONFIG
$PROJECT_ID = 'mrsos-31341';
// Ruta segura al service account:
$SERVICE_ACCOUNT = __DIR__ . '/../secrets/mrsos-31341-66e405cfe145.json';

$action = $_POST['action'] ?? '';
if ($action === '') {
  echo json_encode(['success'=>false,'message'=>'action requerido']);
  exit;
}
$folio = $_POST['folio'] ?? '';
if ($folio === '') {
  echo json_encode(['success'=>false,'message'=>'folio requerido']);
  exit;
}

try {
  $fcm = new FcmService($PROJECT_ID, $SERVICE_ACCOUNT);
  $noti = new NotificationService($conectar, $fcm);

  // contexto: todo lo que venga por POST lo pasamos como ctx
  $ctx = $_POST;
  $ctx['byUsId'] = $_SESSION['usId'] ?? 0;

  $r = $noti->dispatch($action, $ctx, $folio);

  echo json_encode([
    'success' => ($r['ok'] ?? false),
    'sent'    => ($r['sent'] ?? 0),
    'errors'  => ($r['errors'] ?? []),
  ]);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
