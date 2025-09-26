<?php
// php/meet_actualizar.php
error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json');
session_start();
require_once 'conexion.php';

$ticketId = isset($_POST['ticketId']) ? (int)$_POST['ticketId'] : 0;
$modo     = $_POST['modo'] ?? ''; // 'solicitar' | 'establecer' | 'cancelar'
$plat     = trim($_POST['plataforma'] ?? '');
$link     = trim($_POST['link'] ?? '');
$fecha    = trim($_POST['fecha'] ?? ''); // YYYY-MM-DD
$hora     = trim($_POST['hora'] ?? '');  // HH:MM

if ($ticketId <= 0 || !$modo) {
  echo json_encode(['success'=>false,'error'=>'Parámetros inválidos']); exit;
}

// compón datetime si vienen ambos
$meetDT = null;
if ($fecha && $hora) {
  $meetDT = $fecha . ' ' . $hora . ':00';
}

if ($modo === 'cancelar') {
  $sql = "UPDATE ticket_soporte
          SET tiMeetActivo=NULL, tiMeetPlataforma=NULL, tiMeetLink=NULL, tiMeetFecha=NULL
          WHERE tiId=?";
  $st = $conectar->prepare($sql);
  $st->bind_param("i", $ticketId);
  $ok = $st->execute();
  $st->close();
  echo json_encode(['success'=>$ok]); exit;
}

if ($modo === 'solicitar') {
  // cliente solicita
  $act = 'meet solicitado cliente';
  if ($meetDT) {
    $sql = "UPDATE ticket_soporte
            SET tiMeetActivo=?, tiMeetPlataforma=?, tiMeetLink=?, tiMeetFecha=?, tiProceso='meet'
            WHERE tiId=?";
    $st = $conectar->prepare($sql);
    $st->bind_param("ssssi", $act, $plat, $link, $meetDT, $ticketId);
  } else {
    $sql = "UPDATE ticket_soporte
            SET tiMeetActivo=?, tiMeetPlataforma=?, tiMeetLink=?, tiMeetFecha=?, tiProceso='meet'
            WHERE tiId=?";
    $st = $conectar->prepare($sql);
    $st->bind_param("sssi", $act, $plat, $link, $ticketId);
  }
  $ok = $st->execute(); $st->close();
  echo json_encode(['success'=>$ok]); exit;
}

if ($modo === 'establecer') {
  // ingeniero establece
  $act = 'meet ingeniero';
  if ($meetDT) {
    $sql = "UPDATE ticket_soporte
            SET tiMeetActivo=?, tiMeetPlataforma=?, tiMeetLink=?, tiMeetFecha=?, tiProceso='meet'
            WHERE tiId=?";
    $st = $conectar->prepare($sql);
    $st->bind_param("ssssi", $act, $plat, $link, $meetDT, $ticketId);
  } else {
    $sql = "UPDATE ticket_soporte
            SET tiMeetActivo=?, tiMeetPlataforma=?, tiMeetLink=?, tiMeetFecha=?, tiProceso='meet'
            WHERE tiId=?";
    $st = $conectar->prepare($sql);
    $st->bind_param("sssi", $act, $plat, $link, $ticketId);
  }
  $ok = $st->execute(); $st->close();
  echo json_encode(['success'=>$ok]); exit;
}

echo json_encode(['success'=>false,'error'=>'Modo no soportado']);
