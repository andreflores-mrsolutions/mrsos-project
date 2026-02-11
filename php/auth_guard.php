<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/** Detecta si es llamada API (AJAX / fetch) */
function is_api_request(): bool {
  return (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
  ) || (
    str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
  );
}

/** Headers anti-cache */
function no_store(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}

/** Requiere login */
function require_login(): void {
  if (empty($_SESSION['usId'])) {
    if (is_api_request()) {
      http_response_code(401);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['success' => false, 'error' => 'No autenticado.'], JSON_UNESCAPED_UNICODE);
    } else {
      header('Location: ../login/login.php');
    }
    exit;
  }
}

/** Rol actual */
function current_usRol(): string {
  return (string)($_SESSION['usRol'] ?? '');
}

/** Helpers de rol */
function is_mrsa(): bool { return current_usRol() === 'MRSA'; }
function is_mra(): bool  { return current_usRol() === 'MRA'; }
function is_mrv(): bool  { return current_usRol() === 'MRV'; }
function is_cli(): bool  { return current_usRol() === 'CLI'; }
function is_mr(): bool   { return in_array(current_usRol(), ['MRSA','MRA','MRV'], true); }

/**
 * Requiere rol permitido
 */
function require_usRol(array $allowed): void {
  $r = current_usRol();

  if (!in_array($r, $allowed, true)) {
    if (is_api_request()) {
      http_response_code(403);
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(['success' => false, 'error' => 'Sin permisos.'], JSON_UNESCAPED_UNICODE);
    } else {
      header('Location: ../login/login.php?forbidden=1');
    }
    exit;
  }
}
