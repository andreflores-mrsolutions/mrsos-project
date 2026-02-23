<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);

$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;
if ($tiId <= 0) json_fail('tiId inválido', 400);

$pdo = db();

// Ticket + cliente + sede + equipo + póliza + marca + ing asignado
$st = $pdo->prepare("
  SELECT
    t.tiId, t.clId, t.csId, t.peId, t.eqId, t.usIdIng,
    t.tiNombreContacto, t.tiNumeroContacto, t.tiCorreoContacto,
    t.tiDescripcion, t.tiDiagnostico, t.tiProceso,
    t.tiVisitaFecha, t.tiVisitaHora,
    c.clNombre, c.clDireccion, c.clTelefono, c.clCorreo,
    cs.csNombre, cs.csDireccion,
    e.eqModelo, e.eqVersion,
    m.maNombre,
    pe.peSN, pe.peSO
  FROM ticket_soporte t
  LEFT JOIN clientes c ON c.clId=t.clId
  LEFT JOIN cliente_sede cs ON cs.csId=t.csId
  LEFT JOIN equipos e ON e.eqId=t.eqId
  LEFT JOIN marca m ON m.maId=e.maId
  LEFT JOIN polizasequipo pe ON pe.peId=t.peId
  WHERE t.tiId=? LIMIT 1
");
$st->execute([$tiId]);
$t = $st->fetch();
if (!$t) json_fail('Ticket no existe', 404);

// Última HS del ticket (si existe)
$st2 = $pdo->prepare("
  SELECT hsId, hsFolio, hsPath, hsCreatedAt
  FROM hojas_servicio
  WHERE tiId = ? AND hsActivo=1
  ORDER BY hsCreatedAt DESC
  LIMIT 1
");
$st2->execute([$tiId]);
$last = $st2->fetch();
$lastOut = null;
if ($last) {
  $lastOut = [
    'hsId' => (int)$last['hsId'],
    'hsFolio' => (string)$last['hsFolio'],
    'createdAt' => (string)$last['hsCreatedAt'],
    'downloadUrl' => 'api/hoja_servicio_download.php?hsId='.(int)$last['hsId'],
  ];
}

// Lista de ingenieros (DISTINCT por usId porque tu tabla ingenieros repite por tier)
$eng = $pdo->query("
  SELECT DISTINCT i.usId,
         CONCAT(u.usNombre,' ',u.usAPaterno,' ',u.usAMaterno) AS nombre
  FROM ingenieros i
  INNER JOIN usuarios u ON u.usId=i.usId
  WHERE i.ingEstatus='Activo'
  ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

json_ok([
  'ticket' => [
    'tiId' => (int)$t['tiId'],
    'clId' => (int)$t['clId'],
    'csId' => (int)($t['csId'] ?? 0),
    'peId' => (int)($t['peId'] ?? 0),
    'eqId' => (int)($t['eqId'] ?? 0),
    'usIdIng' => (int)($t['usIdIng'] ?? 0),

    'clNombre' => (string)($t['clNombre'] ?? ''),
    'clDireccion' => (string)($t['clDireccion'] ?? ''),
    'clTelefono' => (string)($t['clTelefono'] ?? ''),
    'clCorreo' => (string)($t['clCorreo'] ?? ''),

    'csNombre' => (string)($t['csNombre'] ?? ''),
    'csDireccion' => (string)($t['csDireccion'] ?? ''),

    'eqModelo' => (string)($t['eqModelo'] ?? ''),
    'eqVersion' => (string)($t['eqVersion'] ?? ''),
    'maNombre' => (string)($t['maNombre'] ?? ''),

    'peSN' => (string)($t['peSN'] ?? ''),
    'peSO' => (string)($t['peSO'] ?? ''),

    'contactoNombre' => (string)($t['tiNombreContacto'] ?? ''),
    'contactoTelefono' => (string)($t['tiNumeroContacto'] ?? ''),
    'contactoCorreo' => (string)($t['tiCorreoContacto'] ?? ''),

    'tiVisitaFecha' => (string)($t['tiVisitaFecha'] ?? ''),
    'tiVisitaHora' => (string)($t['tiVisitaHora'] ?? ''),
  ],
  'engineers' => array_map(fn($r) => [
    'usId' => (int)$r['usId'],
    'nombre' => (string)$r['nombre'],
  ], $eng),
  'last' => $lastOut
]);