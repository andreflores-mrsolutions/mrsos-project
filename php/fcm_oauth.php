<?php
// fcm_oauth.php
// Obtiene access_token de Google OAuth usando JWT (Service Account) sin composer.

function base64url_encode($data) {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_google_access_token($serviceAccountPath, $scope = 'https://www.googleapis.com/auth/firebase.messaging') {
  $json = json_decode(file_get_contents($serviceAccountPath), true);
  if (!$json) throw new Exception("No se pudo leer el service account JSON");

  $now = time();
  $header = ['alg' => 'RS256', 'typ' => 'JWT'];
  $claims = [
    'iss'   => $json['client_email'],
    'sub'   => $json['client_email'],
    'aud'   => 'https://oauth2.googleapis.com/token',
    'iat'   => $now,
    'exp'   => $now + 3600,
    'scope' => $scope,
  ];

  $jwtHeader = base64url_encode(json_encode($header));
  $jwtClaims = base64url_encode(json_encode($claims));
  $jwtUnsigned = $jwtHeader . "." . $jwtClaims;

  $privateKey = openssl_pkey_get_private($json['private_key']);
  if (!$privateKey) throw new Exception("No se pudo cargar la llave privada del service account");

  $signature = '';
  openssl_sign($jwtUnsigned, $signature, $privateKey, 'sha256');
  $jwt = $jwtUnsigned . "." . base64url_encode($signature);

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
    throw new Exception("OAuth token error ($code): " . $resp);
  }
  return $data['access_token'];
}
