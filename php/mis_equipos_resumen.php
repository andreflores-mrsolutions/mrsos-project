<?php
include  "conexion.php";
require_once "helpers/sedes_permitidas.php";
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

$allowed = getAllowedSedes($conectar, $clId, $usId);
if (!$allowed) fail("Sin sedes asignadas");

$in = implode(',', array_fill(0, count($allowed), '?'));
$types = str_repeat('i', count($allowed));

// Pólizas vigentes (ajusta campos si tus nombres difieren)
$sql = "
SELECT pc.pcId, pc.pcTipoPoliza, pc.pcFechaInicio, pc.pcFechaFin, pc.pcIdentificador, pc.pcPdfPath, cl.clNombre
FROM polizascliente pc
LEFT JOIN clientes cl ON cl.clId = pc.clId
WHERE pc.clId = ?
ORDER BY pc.pcFechaFin DESC
";

$stmt = $conectar->prepare($sql);
$stmt->bind_param("i", $clId);
$stmt->execute();
$res = $stmt->get_result();

$polizas = [];
while ($pc = $res->fetch_assoc()) {
  $pcId = (int)$pc['pcId'];

  // Equipos visibles dentro de esa póliza (filtrados por sede permitida)
  $sqlE = "
    SELECT pe.peId, pe.peSN, pe.csId,
           e.eqModelo, e.eqVersion, e.eqTipoEquipo,
           m.maNombre,
           cs.csNombre,
           cl.clNombre
    FROM polizasequipo pe
    JOIN equipos e ON e.eqId = pe.eqId
    JOIN marca m ON m.maId = e.maId
    LEFT JOIN cliente_sede cs ON cs.csId = pe.csId
    LEFT JOIN clientes cl ON cl.clId = cs.clId
    WHERE pe.pcId = ? AND pe.csId IN ($in)
    ORDER BY pe.peId DESC
    LIMIT 6
  ";
  
  $stmtE = $conectar->prepare($sqlE);
  // bind dinámico (pcId + allowed sedes)
  $params = array_merge([$pcId], $allowed);
  $bindTypes = "i" . $types;
  $stmtE->bind_param($bindTypes, ...$params);
  $stmtE->execute();
  $rE = $stmtE->get_result();

  $equipos = [];
  while ($row = $rE->fetch_assoc()) $equipos[] = $row;

  // Tickets abiertos por póliza (opcional): suma tickets de los equipos visibles
  $sqlT = "
    SELECT ts.tiId, ts.tiEstatus, ts.peId
    FROM ticket_soporte ts
    WHERE ts.csId IN ($in) AND ts.tiEstatus = 'Abierto'
    ORDER BY ts.tiId DESC
    LIMIT 10
  ";
  $stmtT = $conectar->prepare($sqlT);
  $paramsT = $allowed;
  $stmtT->bind_param($types, ...$paramsT);
  $stmtT->execute();
  $rT = $stmtT->get_result();

  $tickets = [];
  while ($t = $rT->fetch_assoc()) $tickets[] = $t;
  $clienteNombre = $pc['clNombre'] ?? '';
  $prefix = clPrefix($clienteNombre);
  
  $polizas[] = [
    'pcId' => $pcId,
    'pcTipoPoliza' => $pc['pcTipoPoliza'] ?? '',
    'pcFechaInicio' => $pc['pcFechaInicio'] ?? '',
    'pcFechaFin' => $pc['pcFechaFin'] ?? '',
    'pcIdentificador' => $pc['pcIdentificador'] ?? '',
    'pcPdfPath' => $pc['pcPdfPath'] ?? '',
    'equipos' => $equipos,
    'ticketsAbiertos' => $tickets,
    'clienteNombre' => $prefix,
  ];
}

echo json_encode(['success'=>true, 'prefix'=>$prefix, 'polizas'=>$polizas], JSON_UNESCAPED_UNICODE);
