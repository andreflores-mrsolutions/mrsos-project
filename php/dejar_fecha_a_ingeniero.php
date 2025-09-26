<?php
// php/dejar_fecha_ingeniero.php
header('Content-Type: application/json');
require_once __DIR__ . '/conexion.php';
session_start();

try {
  $clId = $_SESSION['clId'] ?? null;
  if (!$clId) throw new Exception('No autenticado');

  // Lee payload JSON o POST
  $payload = [];
  $ct = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($ct, 'application/json') !== false) {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
  }
  $ticketId = (int)($payload['ticketId'] ?? $_POST['ticketId'] ?? 0);
  if ($ticketId <= 0) throw new Exception('Parámetros inválidos');

  // Actualiza: quitar fecha y poner proceso "asignacion fecha"
  $sql = "UPDATE ticket_soporte
SET tiVisita = NULL,
    tiCitaTipo = 'ingeniero',
    tiCitaEstado = 'en espera confirmacion cliente',
    tiCitaPropuesta = NULL,
    tiProceso = 'asignacion fecha ingeniero'
WHERE tiId = ? AND clId = ?;";
  $stmt = $conectar->prepare($sql);
  if (!$stmt) throw new Exception('Error de preparación');
  $stmt->bind_param('ii', $ticketId, $clId);
  $stmt->execute();

  if ($stmt->affected_rows < 1) {
    // No coincidió ticket del cliente, o ya estaba igual
    // Verifica existencia para dar mejor mensaje
    $chk = $conectar->prepare("SELECT tiId FROM ticket_soporte WHERE tiId=? AND clId=?");
    $chk->bind_param('ii', $ticketId, $clId);
    $chk->execute();
    $r = $chk->get_result();
    if (!$r->fetch_assoc()) throw new Exception('Ticket no pertenece a la cuenta');
  }

  // Devuelve el estado actualizado
  $sel = $conectar->prepare("SELECT tiId, tiProceso, tiVisita, tiEstatus FROM ticket_soporte WHERE tiId=? AND clId=?");
  $sel->bind_param('ii', $ticketId, $clId);
  $sel->execute();
  $row = $sel->get_result()->fetch_assoc();

  echo json_encode(['success' => true, 'ticket' => $row]);
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
