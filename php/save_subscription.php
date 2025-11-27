<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
  $input = json_decode(file_get_contents('php://input'), true);
  $usId = isset($input['usId']) ? (int)$input['usId'] : 0;
  $sub  = $input['subscription'] ?? null;

  if ($usId <= 0 || !$sub || empty($sub['endpoint']) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Datos inválidos']); exit;
  }

  $pdo = new PDO('mysql:host=127.0.0.1;dbname=mrsos;charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);

  // UPSERT por endpoint
  $sql = "INSERT INTO webpush_subscriptions (usId, endpoint, p256dh, auth)
          VALUES (:u, :e, :p, :a)
          ON DUPLICATE KEY UPDATE usId=:u2, p256dh=:p2, auth=:a2, updated_at=NOW()";
  $st = $pdo->prepare($sql);
  $st->execute([
    ':u'=>$usId, ':e'=>$sub['endpoint'], ':p'=>$sub['keys']['p256dh'], ':a'=>$sub['keys']['auth'],
    ':u2'=>$usId, ':p2'=>$sub['keys']['p256dh'], ':a2'=>$sub['keys']['auth']
  ]);

  echo json_encode(['success'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
