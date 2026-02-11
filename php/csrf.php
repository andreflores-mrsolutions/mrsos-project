<?php
// php/csrf.php
declare(strict_types=1);

function csrf_token(): string
{
  if (session_status() === PHP_SESSION_NONE) session_start();

  if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_issued_at'] = time();
  }
  return $_SESSION['csrf_token'];
}

function csrf_verify_or_fail(): void
{
  if (session_status() === PHP_SESSION_NONE) session_start();

  $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

  // 1) Header directo (lo ideal)
  $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

  // 2) getallheaders() (por si el server no lo mapea a $_SERVER)
  if ($token === '' && function_exists('getallheaders')) {
    $hdrs = getallheaders();
    foreach ($hdrs as $k => $v) {
      if (strtolower((string)$k) === 'x-csrf-token') {
        $token = (string)$v;
        break;
      }
    }
  }

  // 3) POST normal (si algún endpoint usa form-data)
  if ($token === '' && isset($_POST['csrf_token'])) {
    $token = (string)$_POST['csrf_token'];
  }

  // 4) JSON body (fallback para fetch application/json)
  if ($token === '') {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '') {
      $data = json_decode($raw, true);
      if (is_array($data) && isset($data['csrf_token'])) {
        $token = (string)$data['csrf_token'];
      }
    }
  }


  if ($sessionToken === '' || $token === '' || !hash_equals($sessionToken, $token)) {
    json_fail(
      'CSRF inválido o expirado: sid=' . session_id()
        . ' | sess=' . ($sessionToken ?: 'NULL')
        . ' | hdr=' . ($token ?: 'NULL')
        . ' | post=' . (isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 'NO_POST'),
      419
    );
  }
}
