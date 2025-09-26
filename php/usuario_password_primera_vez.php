<?php
// php/usuario_password_primera_vez.php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json');
include 'conexion.php'; session_start();

$usId = (int)($_SESSION['usId'] ?? 0);
if (!$usId || empty($_SESSION['forzarCambioPass'])) {
  echo json_encode(['success'=>false,'error'=>'No autorizado']); exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$pass1 = trim($in['password'] ?? '');
$pass2 = trim($in['password2'] ?? '');
if ($pass1==='' || $pass2==='' || $pass1!==$pass2) {
  echo json_encode(['success'=>false,'error'=>'Contraseñas inválidas']); exit;
}

$hash = password_hash($pass1, PASSWORD_BCRYPT);
$st = $conectar->prepare("UPDATE usuarios SET usPass=?, usEstatus='Activo' WHERE usId=?");
$st->bind_param('si', $hash, $usId);
$ok = $st->execute(); $st->close();

if ($ok) {
  unset($_SESSION['forzarCambioPass']);
  echo json_encode(['success'=>true]);
} else {
  echo json_encode(['success'=>false,'error'=>'No se pudo actualizar']);
}
