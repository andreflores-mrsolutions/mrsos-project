<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'conexion.php';
session_start();
$rol = strtoupper(trim($_SESSION['usRol'] ?? ''));

if (!in_array($rol, ['AC', 'MRA', 'MRSA'], true)) {
  echo json_encode(['success' => false, 'error' => 'No autorizado']);
  exit;
}


$usId = (int)($_GET['usId'] ?? 0);
if ($usId <= 0) {
  echo json_encode(['success' => false, 'error' => 'Parámetro usId']);
  exit;
}

$sql = "SELECT u.usId,u.usNombre,u.usAPaterno,u.usAMaterno,u.usCorreo,u.usTelefono,u.usUsername,u.usRol,
               cs.csNombre
        FROM usuarios u
        LEFT JOIN sede_usuario su ON su.usId=u.usId
        LEFT JOIN cliente_sede cs ON cs.csId=su.csId
        WHERE u.usId=? LIMIT 1";
$st = $conectar->prepare($sql);
$st->bind_param("i", $usId);
$st->execute();
$u = $st->get_result()->fetch_assoc();
$st->close();

if (!$u) {
  echo json_encode(['success' => false, 'error' => 'No encontrado']);
  exit;
}

// imagen
$exts = ['webp', 'jpg', 'jpeg', 'png', 'gif'];
$img = null;
foreach ($exts as $e) {
  if (file_exists(__DIR__ . "/../img/Usuario/{$u['usUsername']}.$e")) {
    $img = "../img/Usuario/{$u['usUsername']}.$e";
    break;
  }
}
$u['imgUrl'] = $img ?: "../img/Usuario/user.webp";

echo json_encode(['success' => true, 'user' => $u]);
