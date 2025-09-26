<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json');
include 'conexion.php'; session_start();
if (($_SESSION['usRol'] ?? '') !== 'AC') { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$usId = (int)($in['usId'] ?? 0);
if ($usId<=0) { echo json_encode(['success'=>false,'error'=>'Parámetro usId']); exit; }

$st = $conectar->prepare("UPDATE usuarios SET usEstatus='Eliminado' WHERE usId=?");
$st->bind_param("i", $usId);
$ok = $st->execute();
$st->close();

echo json_encode(['success'=>$ok]);
