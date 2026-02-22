<?php
declare(strict_types=1);

require_once __DIR__ . '/../../php/conexion.php';
require_once __DIR__ . '/../../php/auth_guard.php';
require_once __DIR__ . '/../../php/json.php';

no_store();
require_login();
require_usRol(['MRSA','MRA']);

$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;
if ($tiId <= 0) json_fail('tiId inválido');

$pdo = db();

/* =========================
   Ticket base + header info
   - equipos NO tiene eqSerie
   - SN real: polizasequipo.peSN (ticket_soporte.peId)
========================= */
$st = $pdo->prepare("
  SELECT
    t.tiId, t.clId, t.csId, t.usId,
    t.eqId, t.peId,
    t.tiProceso, t.tiEstatus,
    t.tiNivelCriticidad, t.tiDescripcion,
    e.eqModelo,
    pe.peSN
  FROM ticket_soporte t
  LEFT JOIN equipos e       ON e.eqId  = t.eqId
  LEFT JOIN polizasequipo pe ON pe.peId = t.peId
  WHERE t.tiId = ?
  LIMIT 1
");
$st->execute([$tiId]);
$ticket = $st->fetch(PDO::FETCH_ASSOC);
if (!$ticket) json_fail('Ticket no existe', 404);

/* =========================
   Snapshot actual (si existe)
========================= */

// ingenieros del ticket
$st = $pdo->prepare("
  SELECT
    tvi.usIdIng, tvi.rol,
    u.usNombre, u.usAPaterno, u.usAMaterno,
    u.usTelefono, u.usCorreo, u.usUsername, u.usImagen,
    i.ingTier, i.ingExperto, i.ingDescripcion
  FROM ticket_visita_ingenieros tvi
  INNER JOIN usuarios u ON u.usId = tvi.usIdIng
  LEFT JOIN ingenieros i ON i.usId = tvi.usIdIng
  WHERE tvi.tiId=?
  ORDER BY FIELD(tvi.rol,'principal','apoyo'), tvi.usIdIng ASC
");
$st->execute([$tiId]);
$snap_ingenieros = $st->fetchAll(PDO::FETCH_ASSOC);

// docs snapshot
$st = $pdo->prepare("
  SELECT usIdIng, tipo, label, idocId, archivo_snapshot, creadoEn
  FROM ticket_visita_docs
  WHERE tiId=?
  ORDER BY creadoEn DESC
");
$st->execute([$tiId]);
$snap_docs = $st->fetchAll(PDO::FETCH_ASSOC);

// vehículos snapshot
$st = $pdo->prepare("
  SELECT usIdIng, viId, placas, marca, modelo, color, creadoEn
  FROM ticket_visita_vehiculos
  WHERE tiId=?
  ORDER BY creadoEn DESC
");
$st->execute([$tiId]);
$snap_vehiculos = $st->fetchAll(PDO::FETCH_ASSOC);

// equipos snapshot (selección)
$st = $pdo->prepare("
  SELECT sel.usIdIng, sel.ieId, sel.cantidad,
         ie.ieTipo, ie.ieMarca, ie.ieModelo, ie.ieSerie
  FROM ticket_visita_equipos_sel sel
  LEFT JOIN ingeniero_equipos ie ON ie.ieId = sel.ieId
  WHERE sel.tiId=?
  ORDER BY sel.usIdIng ASC, ie.ieTipo ASC
");
$st->execute([$tiId]);
$snap_equipos = $st->fetchAll(PDO::FETCH_ASSOC);

// herramientas snapshot
$st = $pdo->prepare("
  SELECT usIdIng, ihtId, nombre, creadoEn
  FROM ticket_visita_herramientas_sel
  WHERE tiId=?
  ORDER BY creadoEn DESC
");
$st->execute([$tiId]);
$snap_herramientas = $st->fetchAll(PDO::FETCH_ASSOC);

// piezas snapshot
$st = $pdo->prepare("
  SELECT tipo_pieza, partNumber, serialNumber, invId, notas, creadoEn
  FROM ticket_visita_piezas
  WHERE tiId=?
  ORDER BY creadoEn DESC
");
$st->execute([$tiId]);
$snap_piezas = $st->fetchAll(PDO::FETCH_ASSOC);

// gating snapshot (solo para UI)
$hasCred = false;
foreach ($snap_docs as $d) {
  if (($d['tipo'] ?? '') === 'credencial_trabajo') { $hasCred = true; break; }
}
$acceso_ready = (!empty($snap_ingenieros) && $hasCred) ? 1 : 0;

/* =========================
   Ingenieros disponibles (para seleccionar)
   - usuarios.usEstatus / ingenieros.ingEstatus
========================= */
$st = $pdo->prepare("
  SELECT
    u.usId AS usIdIng,
    u.usNombre, u.usAPaterno, u.usAMaterno,
    u.usTelefono, u.usCorreo, u.usUsername, u.usImagen,
    i.ingTier, i.ingExperto, i.ingDescripcion
  FROM ingenieros i
  INNER JOIN usuarios u ON u.usId = i.usId
  WHERE u.usEstatus='Activo' AND i.ingEstatus='Activo'
  ORDER BY i.ingTier ASC, u.usNombre ASC, u.usAPaterno ASC
");
$st->execute();
$ingenieros_disponibles = $st->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   Catálogos por ingeniero (solo seleccionados)
========================= */
$selectedIds = [];
foreach ($snap_ingenieros as $x) $selectedIds[] = (int)$x['usIdIng'];

// también aceptar seleccionados desde UI: ?sel=1,2,3
$sel = trim((string)($_GET['sel'] ?? ''));
if ($sel !== '') {
  foreach (explode(',', $sel) as $raw) {
    $id = (int)trim($raw);
    if ($id > 0) $selectedIds[] = $id;
  }
}

$selectedIds = array_values(array_unique(array_filter($selectedIds)));

$catalogos = [
  'docs' => [],
  'vehiculos' => [],
  'equipos' => [],
  'herramientas' => [],
  'epp' => [],
  'vestimenta' => [],
];

function in_params(array $ids): string {
  return implode(',', array_fill(0, count($ids), '?'));
}

if (count($selectedIds) > 0) {
  $in = in_params($selectedIds);

  // docs catálogo
  $st = $pdo->prepare("
    SELECT usIdIng, idocId, tipo, label, archivo, mime, verificado, vigente_desde, vigente_hasta, activo
    FROM ingeniero_documentos
    WHERE usIdIng IN ($in) AND activo=1
    ORDER BY usIdIng ASC, FIELD(tipo,'credencial_trabajo','INE','NSS','OTRO'), idocId DESC
  ");
  $st->execute($selectedIds);
  $catalogos['docs'] = $st->fetchAll(PDO::FETCH_ASSOC);

  // vehículos catálogo
  $st = $pdo->prepare("
    SELECT usIdIng, viId, placas, marca, modelo, color, anio, activo
    FROM vehiculos_ingenieros
    WHERE usIdIng IN ($in) AND activo=1
    ORDER BY usIdIng ASC, viId DESC
  ");
  $st->execute($selectedIds);
  $catalogos['vehiculos'] = $st->fetchAll(PDO::FETCH_ASSOC);

  // equipos catálogo (OJO: ingeniero_equipos usa usId)
  $st = $pdo->prepare("
    SELECT
      ie.usId AS usIdIng,
      ie.ieId, ie.ieTipo, ie.ieMarca, ie.ieModelo, ie.ieSerie, ie.ieDescripcion, ie.ieActivo
    FROM ingeniero_equipos ie
    WHERE ie.usId IN ($in) AND (ie.ieActivo=1 OR ie.ieActivo IS NULL)
    ORDER BY ie.usId ASC, ie.ieTipo ASC, ie.ieId DESC
  ");
  $st->execute($selectedIds);
  $catalogos['equipos'] = $st->fetchAll(PDO::FETCH_ASSOC);

  // herramientas catálogo
  $st = $pdo->prepare("
    SELECT usIdIng, ihtId, nombre, detalle, activo
    FROM ingeniero_herramientas
    WHERE usIdIng IN ($in) AND activo=1
    ORDER BY usIdIng ASC, ihtId DESC
  ");
  $st->execute($selectedIds);
  $catalogos['herramientas'] = $st->fetchAll(PDO::FETCH_ASSOC);

  // epp
  $st = $pdo->prepare("
    SELECT usIdIng, casco, chaleco, botas, notas
    FROM ingeniero_epp
    WHERE usIdIng IN ($in)
  ");
  $st->execute($selectedIds);
  $catalogos['epp'] = $st->fetchAll(PDO::FETCH_ASSOC);

  // vestimenta
  $st = $pdo->prepare("
    SELECT usIdIng, pantalon, camisa, calzado, notas
    FROM ingeniero_vestimenta
    WHERE usIdIng IN ($in)
  ");
  $st->execute($selectedIds);
  $catalogos['vestimenta'] = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================
   Flags UX: si no hay catálogo, el front debe mostrar "Agregar..."
========================= */
$flags = [
  'can_add_docs' => true,
  'can_add_vehiculos' => true,
  'can_add_equipos' => true,
  'can_add_herramientas' => true,
  'can_add_epp' => true,
  'can_add_vestimenta' => true,
];

/* =========================
   Respuesta final
========================= */
json_ok([
  'ticket' => $ticket,
  'acceso_ready' => $acceso_ready,
  'flags' => $flags,

  'snapshot' => [
    'ingenieros' => $snap_ingenieros,
    'docs' => $snap_docs,
    'vehiculos' => $snap_vehiculos,
    'equipos' => $snap_equipos,
    'herramientas' => $snap_herramientas,
    'piezas' => $snap_piezas,
  ],

  'ingenieros_disponibles' => $ingenieros_disponibles,
  'catalogos' => $catalogos,
]);