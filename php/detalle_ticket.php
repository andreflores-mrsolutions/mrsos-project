<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/conexion.php';
session_start();

$clId = $_SESSION['clId'] ?? null;
if (!$clId) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'No autenticado']);
  exit;
}

$tiId = filter_input(INPUT_GET, 'tiId', FILTER_VALIDATE_INT);
if (!$tiId) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Parámetro tiId inválido']);
  exit;
}

/*
  IMPORTANTE:
  Esta consulta asume que ticket_soporte **ya tiene** peId y (opcionalmente) csId.
  Si aún no migraste, mira el bloque "FALLBACK" más abajo.
*/
$sql = "
SELECT
  t.tiId, t.clId, t.peId, t.csId,
  t.tiDescripcion, t.tiEstatus, t.tiProceso, t.tiTipoTicket, t.tiExtra,
  t.tiNivelCriticidad, t.tiFechaCreacion, t.tiVisita,
  t.tiNombreContacto, t.tiNumeroContacto, t.tiCorreoContacto,

  pe.peSN, pe.pcId,

  e.eqId, e.eqModelo, e.eqVersion, e.eqTipoEquipo,
  m.maNombre,

  pc.pcTipoPoliza, pc.pcFechaInicio, pc.pcFechaFin,

  cs.csNombre

FROM ticket_soporte t
JOIN polizasequipo pe ON pe.peId = t.peId
JOIN equipos e        ON e.eqId = pe.eqId
JOIN marca m          ON m.maId = e.maId
LEFT JOIN polizascliente pc ON pc.pcId = pe.pcId
LEFT JOIN cliente_sede  cs ON cs.csId = t.csId

WHERE t.tiId = ? AND t.clId = ?
LIMIT 1
";

$stmt = $conectar->prepare($sql);
$stmt->bind_param('ii', $tiId, $clId);
$stmt->execute();
$res = $stmt->get_result();
$ticket = $res->fetch_assoc();
$stmt->close();

if (!$ticket) {
  echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
  exit;
}

echo json_encode(['success' => true, 'ticket' => $ticket]);
exit;

/* ======= FALLBACK (si aún NO tienes t.peId en ticket_soporte) =======
   Reemplaza la SQL de arriba por esta unión más estricta:
   - Une por eqId PERO filtrando además por cliente y uniendo con pc.clId.
   - Preferible además usar csId si lo tienes.
$sql = "
SELECT ...
FROM ticket_soporte t
JOIN equipos e        ON e.eqId = t.eqId
JOIN polizasequipo pe ON pe.eqId = e.eqId
JOIN polizascliente pc ON pc.pcId = pe.pcId AND pc.clId = t.clId
JOIN marca m          ON m.maId = e.maId
LEFT JOIN cliente_sede cs ON cs.csId = t.csId
WHERE t.tiId = ? AND t.clId = ?
LIMIT 1
";
===================================================================== */
