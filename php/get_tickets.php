<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/conexion.php'; // db(): PDO

function jfail(string $msg, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

$usId  = $_SESSION['usId']  ?? null;
$usRol = $_SESSION['usRol'] ?? null;
$clId  = $_SESSION['clId']  ?? null;

if (!$usId || !$usRol) jfail('No autenticado', 401);

$usId = (int)$usId;
$usRol = (string)$usRol;
$clId = (int)($clId ?? 0);
if ($clId <= 0) jfail('Cliente no definido', 400);

$pdo = db();

// --------- Scope por rol (MRV restringido) ----------
if ($usRol === 'MRV') {
  // vendedor solo puede ver sus clientes/pólizas
  $st = $pdo->prepare("SELECT 1 FROM cuentas WHERE usId = ? AND clId = ? LIMIT 1");
  $st->execute([$usId, $clId]);
  if (!$st->fetchColumn()) jfail('Sin permisos para este cliente', 403);
}

// Si es CLI, solo lo de su sesión (ya está)
// Si es MRA/MRSA, permitido (este endpoint usa clId sesión)

// ----------------------------------------------------
// Query corregida:
// - Relaciona pe con el ticket (por peId) si existe, o por eqId si tu modelo lo requiere.
// - Asegura que la póliza pertenece al cliente (polizascliente.clId = :clId)
// ----------------------------------------------------
$sql = "
  SELECT
    t.tiId,
    t.tiDescripcion,
    t.tiEstatus,
    t.tiNivelCriticidad,
    t.tiFechaCreacion,
    t.tiProceso,
    t.eqId,
    e.eqModelo,
    e.eqVersion,
    e.maId,
    m.maNombre,
    pe.peSN
  FROM ticket_soporte t
  INNER JOIN equipos e ON t.eqId = e.eqId
  INNER JOIN marca m ON e.maId = m.maId
  LEFT JOIN polizasequipo pe ON pe.peId = t.peId
  LEFT JOIN polizascliente pc ON pc.pcId = pe.pcId
  WHERE t.usId = ?
    AND t.tiEstatus = 'Abierto'
    AND t.estatus = 'Activo'
    AND (
      pc.clId = ?  -- ticket ligado a póliza del cliente
      OR t.clId = ? -- fallback si tu ticket_soporte ya trae clId directo
    )
  ORDER BY t.tiFechaCreacion DESC, t.tiId DESC
  LIMIT 3
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usId, $clId, $clId]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'tickets' => $tickets], JSON_UNESCAPED_UNICODE);
