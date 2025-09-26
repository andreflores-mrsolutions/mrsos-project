<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json');
include 'conexion.php'; session_start();
if (($_SESSION['usRol'] ?? '') !== 'AC') { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$usId = (int)($in['usId'] ?? 0);
$pwd  = trim($in['password'] ?? '');

if ($usId<=0 || $pwd==='') { echo json_encode(['success'=>false,'error'=>'Datos inválidos']); exit; }

$hash = password_hash($pwd, PASSWORD_DEFAULT);
$st = $conectar->prepare("UPDATE usuarios SET usPass=? WHERE usId=?");
$st->bind_param("si", $hash, $usId);
$ok = $st->execute();
$st->close();

echo json_encode(['success'=>$ok]);
