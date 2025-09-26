<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json');
include 'conexion.php'; session_start();
if (($_SESSION['usRol'] ?? '') !== 'AC') { echo json_encode(['success'=>false,'error'=>'No autorizado']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$usId = (int)($in['usId'] ?? 0);
$rol  = $in['usRol'] ?? '';
if ($usId<=0 || !in_array($rol, ['AC','UC','EC'], true)) {
  echo json_encode(['success'=>false,'error'=>'Datos inválidos']); exit;
}
$st = $conectar->prepare("UPDATE usuarios SET usRol=? WHERE usId=?");
$st->bind_param("si", $rol, $usId);
$ok = $st->execute();
$st->close();

echo json_encode(['success'=>$ok]);
