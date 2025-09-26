<?php
// php/logout.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Vaciar variables de sesión
$_SESSION = [];

// Borrar cookie de sesión si existe
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

// ¿AJAX?
$isAjax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// Si dejaste cookies “remember me”, bórralas aquí también.
// setcookie('remember_me', '', time() - 3600, '/');

if ($isAjax) {
  header('Content-Type: application/json');
  echo json_encode(['success' => true]);
  exit;
}

// Navegación normal
header('Location: ../login/login.php');
exit;
