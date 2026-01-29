<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require "conexion.php";

function jexit($a){ echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$adminId = $_SESSION['usId'] ?? 0;
if(!$adminId) jexit(['success'=>false,'error'=>'Sesión no válida']);

$usId = (int)($_POST['usId'] ?? 0);
if($usId<=0) jexit(['success'=>false,'error'=>'usId inválido']);

// 1) Traer correo del usuario
$stmt = $conectar->prepare("SELECT usCorreo, usNombre FROM usuarios WHERE usId=? LIMIT 1");
$stmt->bind_param("i",$usId);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$u) jexit(['success'=>false,'error'=>'Usuario no existe']);

$correo = trim($u['usCorreo'] ?? '');
$nombre = trim($u['usNombre'] ?? '');
if($correo==='') jexit(['success'=>false,'error'=>'Usuario sin correo']);

// 2) Generar contraseña aleatoria
function genPass($len=12){
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  $out = '';
  for($i=0;$i<$len;$i++){
    $out .= $chars[random_int(0, strlen($chars)-1)];
  }
  return $out;
}
$newPass = genPass(12);
$hash = password_hash($newPass, PASSWORD_DEFAULT);

// 3) Guardar + forzar cambio
$stmt = $conectar->prepare("UPDATE usuarios SET usPass=?, usEstatus='NewPass' WHERE usId=?");
$stmt->bind_param("si", $hash, $usId);
if(!$stmt->execute()){
  $err = $stmt->error;
  $stmt->close();
  jexit(['success'=>false,'error'=>"No se pudo actualizar pass: $err"]);
}
$stmt->close();


jexit(['success'=>true,'message'=>'Contraseña restablecida y enviada por correo']);
