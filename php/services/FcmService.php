<?php
// php/services/FcmService.php
class FcmService {
  private string $projectId;
  private string $serviceAccountPath;

  public function __construct(string $projectId, string $serviceAccountPath) {
    $this->projectId = $projectId;
    $this->serviceAccountPath = $serviceAccountPath;
  }

  private function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  private function getAccessToken(): string {
    $json = json_decode(file_get_contents($this->serviceAccountPath), true);
    if (!$json) throw new Exception("No se pudo leer service account JSON");

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
      'iss'   => $json['client_email'],
      'sub'   => $json['client_email'],
      'aud'   => 'https://oauth2.googleapis.com/token',
      'iat'   => $now,
      'exp'   => $now + 3600,
      'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    ];

    $jwtHeader = $this->base64url_encode(json_encode($header));
    $jwtClaims = $this->base64url_encode(json_encode($claims));
    $jwtUnsigned = $jwtHeader . "." . $jwtClaims;

    $privateKey = openssl_pkey_get_private($json['private_key']);
    if (!$privateKey) throw new Exception("No se pudo cargar la llave privada");

    $signature = '';
    openssl_sign($jwtUnsigned, $signature, $privateKey, 'sha256');
    $jwt = $jwtUnsigned . "." . $this->base64url_encode($signature);

    $postFields = http_build_query([
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion'  => $jwt,
    ]);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
      CURLOPT_POSTFIELDS     => $postFields,
      CURLOPT_TIMEOUT        => 20,
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($resp === false) throw new Exception("cURL error: " . curl_error($ch));
    curl_close($ch);

    $data = json_decode($resp, true);
    if ($code < 200 || $code >= 300 || empty($data['access_token'])) {
      throw new Exception("OAuth token error ($code): $resp");
    }
    return $data['access_token'];
  }

  public function sendToToken(string $token, string $title, string $body, array $data = []): array {
    $accessToken = $this->getAccessToken();
    $url = 'https://fcm.googleapis.com/v1/projects/' . $this->projectId . '/messages:send';

    // FCM "data" debe ser string->string
    $dataStr = [];
foreach ($data as $k => $v) {
  if (is_array($v) || is_object($v)) {
    $dataStr[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
  } else {
    $dataStr[$k] = strval($v);
  }
}


    $payload = [
      'message' => [
        'token' => $token,
        'notification' => [
          'title' => $title,
          'body'  => $body,
        ],
        'data' => $dataStr,
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
      $err = curl_error($ch);
      curl_close($ch);
      return ['ok'=>false, 'code'=>0, 'error'=>$err];
    }
    curl_close($ch);

    return ['ok'=>($code >= 200 && $code < 300), 'code'=>$code, 'resp'=>$resp];
  }

  public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array {
    $sent = 0; $errors = [];
    foreach ($tokens as $t) {
      $r = $this->sendToToken($t, $title, $body, $data);
      if ($r['ok']) $sent++;
      else $errors[] = $r;
    }
    return ['ok'=>count($errors)===0, 'sent'=>$sent, 'errors'=>$errors];
  }
}
