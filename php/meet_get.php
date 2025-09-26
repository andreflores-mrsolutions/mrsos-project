<?php
// php/meet_get.php
header('Content-Type: application/json');
session_start();
require_once 'conexion.php';

$tiId = isset($_GET['ticketId']) ? (int)$_GET['ticketId'] : 0;
$clId = $_SESSION['clId'] ?? null;
$isAdminMR = $_SESSION['is_admin_mr'] ?? 0;

if ($tiId<=0) { echo json_encode(['success'=>false,'error'=>'ticketId inválido']); exit; }

$where = "tiId=?";
$types = "i";
$params = [ $tiId ];

if ($clId && !$isAdminMR) {
  $where .= " AND clId=?";
  $types .= "i";
  $params[] = $clId;
}

$sql = "SELECT tiMeetActivo, tiMeetPlataforma, tiMeetLink, tiProceso, tiMeetFecha FROM ticket_soporte WHERE $where LIMIT 1";
$stmt = $conectar->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

echo json_encode(['success'=>true, 'data'=>$row ?: null]);
