<?php
require "conexion.php";
require "helpers/sedes_permitidas.php";
session_start();
header('Content-Type: application/json; charset=utf-8');

function fail($msg){ echo json_encode(['success'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function clPrefix(string $name): string {
  // quitar acentos/raros de manera simple + solo letras
  $name = strtoupper($name);
  $name = preg_replace('/[^A-Z]/', '', $name ?? '');
  $p = substr($name, 0, 3);
  return $p !== '' ? $p : 'CLI';
}

$clId = (int)($_SESSION['clId'] ?? 0);
$usId = (int)($_SESSION['usId'] ?? 0);
if (!$clId || !$usId) fail("No autenticado");

$peId = (int)($_GET['peId'] ?? 0);
if (!$peId) fail("Falta peId");

$allowed = getAllowedSedes($conectar, $clId, $usId);
if (!$allowed) fail("Sin sedes asignadas");

$in = implode(',', array_fill(0, count($allowed), '?'));
$types = str_repeat('i', count($allowed));
$bindTypes = "i";
$params = array_merge([$peId], $allowed);

$sql = "
SELECT
  pe.peId, pe.peSN, pe.peDescripcion, pe.peSO, pe.csId, pe.pcId,
  e.eqId, e.eqModelo, e.eqVersion, e.eqTipoEquipo, e.eqCPU, e.eqMaxRAM, e.eqNIC, e.eqDescripcion,
  m.maNombre,
  cs.csNombre,
  cl.clNombre,
  pc.pcTipoPoliza, pc.pcFechaInicio, pc.pcFechaFin, pc.pcIdentificador, pc.pcPdfPath
FROM polizasequipo pe
JOIN equipos e ON e.eqId = pe.eqId
JOIN marca m ON m.maId = e.maId
JOIN clientes cl ON cl.clId = cl.clId
JOIN polizascliente pc ON pc.pcId = pe.pcId
LEFT JOIN cliente_sede cs ON cs.csId = pe.csId
WHERE pe.peId = ?
LIMIT 1
";


$stmt = $conectar->prepare($sql);
$stmt->bind_param("i", $peId);
$stmt->execute();
$r = $stmt->get_result();
if ($r->num_rows === 0) fail("Equipo no encontrado o no permitido");

$equipo = $r->fetch_assoc();

// Tickets abiertos del equipo
$sqlT = "
SELECT tiId, tiProceso, tiTipoTicket, tiNivelCriticidad, tiEstatus
FROM ticket_soporte
WHERE peId = ? AND tiEstatus = 'Abierto'
ORDER BY tiId DESC
LIMIT 20
";
$stmtT = $conectar->prepare($sqlT);
$stmtT->bind_param("i", $peId);
$stmtT->execute();
$rt = $stmtT->get_result();
$tickets = [];
while ($t = $rt->fetch_assoc()) $tickets[] = $t;

// Texto de póliza (UI)
$tipo = strtolower(trim($equipo['pcTipoPoliza'] ?? ''));
$clienteNombre = $equipo['clNombre'] ?? '';
  $prefix = clPrefix($clienteNombre);
$polizaDesc = '';
if ($tipo === 'platinum') {
  $polizaDesc = "7 días x 24 horas x 365 días.\nIncluye dos (2) mantenimientos preventivos.\nMantenimientos correctivos necesarios (según aplique).\nSoporte telefónico/remoto 7x24x365.\nSoporte a hardware: cobertura para los equipos listados en el alcance (según contrato).";
} elseif ($tipo === 'gold') {
  $polizaDesc = "5 días x 8 horas (NBD).\nHorario: Lunes a Viernes de 09:00 a 18:00.\nIncluye un (1) mantenimiento preventivo (según contrato).\nCorrectivos necesarios (si aplica).\nSoporte telefónico/remoto 5x8 (NBD).";
} else {
  $polizaDesc = "Consulta tu contrato o documento de póliza para los alcances específicos.";
}

$disclaimer = "Nota: La información mostrada es referencial y puede variar según el contrato, alcance y anexos vigentes. Para confirmar condiciones exactas (cobertura, tiempos, exclusiones y equipos incluidos), consulta tu documento oficial o contacta a tu ejecutivo/BDM.";

echo json_encode([
  'success' => true,
  'equipo' => $equipo,
  'ticketsAbiertos' => $tickets,
  'prefix' => $prefix,
  'polizaDescripcion' => $polizaDesc,
  'disclaimer' => $disclaimer,
], JSON_UNESCAPED_UNICODE);
