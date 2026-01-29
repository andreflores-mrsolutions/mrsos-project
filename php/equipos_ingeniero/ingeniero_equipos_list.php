<?php
// ../php/ingeniero_equipos_list.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/conexion.php';

function jexit(array $out, int $code = 200): void {
  http_response_code($code);
  if (ob_get_length()) { @ob_clean(); }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}

$usId = (int)($_SESSION['usId'] ?? 0);
if ($usId <= 0) jexit(['success'=>false,'error'=>'No autenticado'], 401);

$soloActivos = isset($_GET['activos']) ? (int)$_GET['activos'] : 1;

$sql = "SELECT ieId, usId, ieTipo, ieMarca, ieModelo, ieSerie, ieDescripcion, ieActivo, created_at
        FROM ingeniero_equipos
        WHERE usId = ? ".($soloActivos ? "AND ieActivo=1" : "")."
        ORDER BY ieTipo ASC, ieMarca ASC, ieModelo ASC, ieId DESC";

$st = $conectar->prepare($sql);
if (!$st) jexit(['success'=>false,'error'=>'DB prepare error'], 500);

$st->bind_param("i", $usId);
$st->execute();
$rs = $st->get_result();

$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$st->close();

jexit(['success'=>true,'data'=>$rows]);
