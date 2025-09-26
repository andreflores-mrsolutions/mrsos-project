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


$res = $conectar->query("SELECT csId, csNombre FROM cliente_sede ORDER BY csNombre");
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
echo json_encode(['success' => true, 'sedes' => $rows]);
