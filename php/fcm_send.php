<?php
require_once "conexion.php";
require_once "fcm_oauth.php";

// ✅ Cambia esta ruta a donde subiste el JSON
define('FCM_SA_PATH', __DIR__ . '/../secrets/firebase-service-account.json');

// ✅ Tu Project ID de Firebase
define('FCM_PROJECT_ID', 'mrsos-31341');

function fcm_send_to_tokens($tokens, $title, $body, $data = []) {
  if (!is_array($tokens) || count($tokens) === 0) return ['ok'=>true, 'sent'=>0];

  $accessToken = get_google_access_token(FCM_SA_PATH);
  $url = 'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send';

  $sent = 0;
  $errors = [];

  foreach ($tokens as $token) {
    $payload = [
      'message' => [
        'token' => $token,
        'notification' => [
          'title' => $title,
          'body'  => $body,
        ],
        'data' => array_map('strval', $data), // data debe ser string->string
        'android' => [
          'priority' => 'HIGH',
        ],
      ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
      ],
      CURLOPT_POSTFIELDS     => json_encode($payload),
      CURLOPT_TIMEOUT        => 20,
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) {
      $errors[] = 'cURL error: ' . curl_error($ch);
    } elseif ($code >= 200 && $code < 300) {
      $sent++;
    } else {
      $errors[] = "FCM error ($code): $resp";
    }
    curl_close($ch);
  }

  return ['ok'=>count($errors)===0, 'sent'=>$sent, 'errors'=>$errors];
}

function get_user_tokens($conectar, $usId, $platform = null) {
  if ($platform) {
    $stmt = $conectar->prepare("SELECT token FROM user_push_devices WHERE usId=? AND platform=?");
    $stmt->bind_param("is", $usId, $platform);
  } else {
    $stmt = $conectar->prepare("SELECT token FROM user_push_devices WHERE usId=?");
    $stmt->bind_param("i", $usId);
  }
  $stmt->execute();
  $res = $stmt->get_result();

  $tokens = [];
  while ($row = $res->fetch_assoc()) {
    $tokens[] = $row['token'];
  }
  return $tokens;
}

function notify_user($conectar, $usId, $title, $body, $data = []) {
  $tokens = get_user_tokens($conectar, $usId);
  return fcm_send_to_tokens($tokens, $title, $body, $data);
}
