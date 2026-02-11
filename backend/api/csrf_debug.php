<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/csrf.php';

require_login();

// IMPORTANTÍSIMO: esto CREA el token si no existe
$token = csrf_token();

// Captura header como lo ve PHP
$hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

echo json_encode([
  'success' => true,
  'sid' => session_id(),
  'usId' => $_SESSION['usId'] ?? null,
  'csrf_session' => $token,
  'csrf_header' => $hdr,
], JSON_UNESCAPED_UNICODE);
