<?php
// php/asignar_fecha.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/conexion.php';

try {
    // Verifica método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido');
    }

    // Autenticación básica por sesión
    $clId = $_SESSION['clId'] ?? null;
    if (!$clId || !is_numeric($clId)) {
        throw new RuntimeException('No autenticado');
    }
    $clId = (int)$clId;

    // Lee parámetros desde JSON o POST clásico
    $payload = [];
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    }

    $ticketId    = (int)($payload['ticketId'] ?? $_POST['ticketId'] ?? 0);
    $tiVisitaRaw = trim((string)($payload['tiVisita'] ?? $_POST['tiVisita'] ?? ''));

    // Soporta también fecha/hora por separado (por si usas 2 inputs)
    if ($tiVisitaRaw === '') {
        $fecha = trim((string)($payload['fecha'] ?? $_POST['fecha'] ?? ''));
        $hora  = trim((string)($payload['hora']  ?? $_POST['hora']  ?? ''));
        if ($fecha !== '' && $hora !== '') {
            $tiVisitaRaw = "$fecha $hora";
        }
    }

    // Normaliza "YYYY-MM-DD HH:MM" o "YYYY-MM-DDTHH:MM"
    $tiVisitaRaw = str_replace('T', ' ', $tiVisitaRaw);
    $dt = DateTime::createFromFormat('Y-m-d H:i', $tiVisitaRaw) ?: DateTime::createFromFormat('Y-m-d H:i:s', $tiVisitaRaw);
    if (!$dt) {
        throw new InvalidArgumentException('Formato de fecha/hora inválido (usa YYYY-MM-DD HH:MM).');
    }
    $tiVisita = $dt->format('Y-m-d H:i:s');

    // (Opcional) Validar que no sea pasado
    // if (new DateTime($tiVisita) <= new DateTime()) {
    //     throw new InvalidArgumentException('La fecha/hora debe ser en el futuro');
    // }

    // 1) Asegura que el ticket pertenezca al cliente
    $sqlCheck = "SELECT tiId, tiEstatus, tiProceso 
                 FROM ticket_soporte 
                 WHERE tiId = ? AND clId = ? 
                 LIMIT 1";
    $stmt = $conectar->prepare($sqlCheck);
    if (!$stmt) throw new RuntimeException('Error al preparar consulta de verificación');
    $stmt->bind_param('ii', $ticketId, $clId);
    $stmt->execute();
    $res = $stmt->get_result();
    $ticket = $res->fetch_assoc();
    $stmt->close();

    if (!$ticket) {
        throw new RuntimeException('Ticket no encontrado para este cliente');
    }

    // Si está cerrado, no permitir cambio
    if (isset($ticket['tiEstatus']) && $ticket['tiEstatus'] === 'Cerrado') {
        throw new RuntimeException('El ticket está cerrado y no puede modificarse');
    }

    // 2) Actualiza tiVisita y, salvo que esté finalizado/cancelado, cambia a "fecha asignada"
    $sqlUpd = "UPDATE ticket_soporte t
               SET t.tiVisita = ?,
                   t.tiProceso = CASE 
                       WHEN t.tiProceso NOT IN ('finalizado','cancelado') THEN 'fecha asignada'
                       ELSE t.tiProceso
                   END
               WHERE t.tiId = ? AND t.clId = ?";
    $stmt = $conectar->prepare($sqlUpd);
    if (!$stmt) throw new RuntimeException('Error al preparar actualización');
    $stmt->bind_param('sii', $tiVisita, $ticketId, $clId);
    $ok = $stmt->execute();
    if (!$ok) throw new RuntimeException('No se pudo actualizar el ticket');
    $stmt->close();

    // 3) Devuelve los datos actualizados (útil para refrescar UI)
    $sqlGet = "SELECT tiId, tiEstatus, tiProceso, tiVisita 
               FROM ticket_soporte 
               WHERE tiId = ? AND clId = ? 
               LIMIT 1";
    $stmt = $conectar->prepare($sqlGet);
    $stmt->bind_param('ii', $ticketId, $clId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'ticket'  => $row ?? [
            'tiId' => $ticketId,
            'tiVisita' => $tiVisita,
            'tiProceso' => 'fecha asignada'
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
