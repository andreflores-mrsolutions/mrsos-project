<?php
// php/guardar_preferencias.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/conexion.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$usId = $_SESSION['usId'] ?? null;
if (!$usId) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Leer JSON crudo
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

// Helpers
function boolToInt(mixed $v, int $default = 1): int {
    if ($v === null) return $default;
    return filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
}

$theme = $data['theme'] ?? 'light';
if (!in_array($theme, ['light','dark'], true)) {
    $theme = 'light';
}

$notifInApp        = boolToInt($data['notifInApp']        ?? true);
$notifMail         = boolToInt($data['notifMail']         ?? true);
$notifTicketCambio = boolToInt($data['notifTicketCambio'] ?? true);
$notifMeet         = boolToInt($data['notifMeet']         ?? true);
$notifVisita       = boolToInt($data['notifVisita']       ?? true);
$notifFolio        = boolToInt($data['notifFolio']        ?? true);

// Actualizar en BD
$sql = "UPDATE usuarios SET
          usTheme              = ?,
          usNotifInApp         = ?,
          usNotifMail          = ?,
          usNotifTicketCambio  = ?,
          usNotifMeet          = ?,
          usNotifVisita        = ?,
          usNotifFolio         = ?
        WHERE usId = ?";

if (!$stmt = $conectar->prepare($sql)) {
    echo json_encode(['success' => false, 'error' => 'Error en prepare()']);
    exit;
}

$stmt->bind_param(
    'siiiiiii',
    $theme,
    $notifInApp,
    $notifMail,
    $notifTicketCambio,
    $notifMeet,
    $notifVisita,
    $notifFolio,
    $usId
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Preferencias actualizadas']);
} else {
    echo json_encode(['success' => false, 'error' => 'No se pudieron actualizar las preferencias']);
}

$stmt->close();
