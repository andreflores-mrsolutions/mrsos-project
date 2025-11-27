<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

header('Content-Type: application/json; charset=utf-8');

try {
  $usId = isset($_GET['usId']) ? (int)$_GET['usId'] : 1001;
  if ($usId <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'usId requerido']); exit; }

  $pdo = new PDO('mysql:host=127.0.0.1;dbname=mrsos;charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);

  $stmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM webpush_subscriptions WHERE usId = :u");
  $stmt->execute([':u'=>$usId]);
  $subs = $stmt->fetchAll();
  if (!$subs) { echo json_encode(['success'=>false,'error'=>'Sin suscripciones']); exit; }

  $public  = getenv('VAPID_PUBLIC')  ?: 'TU_PUBLIC_KEY';
  $private = getenv('VAPID_PRIVATE') ?: 'TU_PRIVATE_KEY';
  $subject = getenv('VAPID_SUBJECT') ?: 'mailto:soporte@mrsos.mx';

  $webPush = new WebPush([
    'VAPID' => [
      'subject' => $subject,
      'publicKey' => $public,
      'privateKey' => $private,
    ],
  ]);

  $payload = json_encode([
    'title' => 'Prueba MR SOS',
    'body'  => 'Notificación de prueba enviada correctamente.',
    'url'   => '/admin/'
  ]);

  $ok = 0; $fail = 0;
  foreach ($subs as $row) {
    $subscription = Subscription::create([
      'endpoint' => $row['endpoint'],
      'keys'     => ['p256dh' => $row['p256dh'], 'auth' => $row['auth']],
    ]);
    $result = $webPush->sendOneNotification($subscription, $payload);
    if ($result->isSuccess()) $ok++; else $fail++;
  }

  echo json_encode(['success'=>true,'sent'=>$ok,'failed'=>$fail]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
