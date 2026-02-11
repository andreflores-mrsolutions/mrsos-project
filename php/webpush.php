<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// Aquí sí cargas VAPID desde config.local.php idealmente
// define('VAPID_PUBLIC', ...), etc.

function send_webpush_to_user(int $usId, string $title, string $body, string $url = '/admin/'): array {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM webpush_subscriptions WHERE usId = :u");
  $stmt->execute([':u' => $usId]);
  $subs = $stmt->fetchAll();

  if (!$subs) return ['success' => false, 'message' => 'Sin suscripciones registradas'];

  $webPush = new WebPush([
    'VAPID' => [
      'subject'    => VAPID_SUBJECT,
      'publicKey'  => VAPID_PUBLIC,
      'privateKey' => VAPID_PRIVATE,
    ],
  ]);

  $payload = json_encode(['title'=>$title,'body'=>$body,'url'=>$url]);

  $ok = 0; $fail = 0;
  foreach ($subs as $row) {
    $subscription = Subscription::create([
      'endpoint' => $row['endpoint'],
      'keys'     => ['p256dh' => $row['p256dh'], 'auth' => $row['auth']],
    ]);
    $result = $webPush->sendOneNotification($subscription, $payload);
    if ($result->isSuccess()) $ok++; else $fail++;
  }

  return ['success' => true, 'sent' => $ok, 'failed' => $fail];
}
