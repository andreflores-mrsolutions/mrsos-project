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

/* ==========================
   Ticket base
========================== */
$st = $pdo->prepare("SELECT tiId, clId, tiProceso, tiEstatus FROM ticket_soporte WHERE tiId=? LIMIT 1");
$st->execute([$tiId]);
$tk = $st->fetch();
if (!$tk) json_fail('Ticket no existe', 404);

/* ==========================
   Estado visita
========================== */
$st = $pdo->prepare("SELECT estado, confirmada_inicio, confirmada_fin, lock_cancel FROM ticket_visita_estado WHERE tiId=? LIMIT 1");
$st->execute([$tiId]);
$vis = $st->fetch() ?: [
  'estado' => 'sin_propuestas',
  'confirmada_inicio' => null,
  'confirmada_fin' => null,
  'lock_cancel' => 0
];

/* ==========================
   Propuestas (3)
========================== */
$st = $pdo->prepare("
  SELECT vpId, vpOpcion AS opcion, vpInicio AS inicio, vpFin AS fin, vpEstado AS estado, vpAutorTipo
  FROM ticket_visita_propuestas
  WHERE tiId=?
  ORDER BY vpOpcion ASC, vpCreadoEn DESC
");
$st->execute([$tiId]);
$props = $st->fetchAll();

// última por opción
$lastByOpt = [];
foreach ($props as $p) {
  $opt = (int)$p['opcion'];
  if (!isset($lastByOpt[$opt])) $lastByOpt[$opt] = $p;
}
$propuestas = array_values($lastByOpt);

// confirmada
$confirmada = null;
foreach ($propuestas as $p) {
  if (($p['estado'] ?? '') === 'aceptada') {
    $confirmada = [
      'opcion' => (int)$p['opcion'],
      'inicio' => $p['inicio'],
      'fin'    => $p['fin'],
      'inicio_txt' => substr((string)$p['inicio'], 0, 16),
      'fin_txt'    => substr((string)$p['fin'], 0, 16),
    ];
    break;
  }
}

/* ==========================
   Folio (último) + URLs seguras
========================== */
$st = $pdo->prepare("
  SELECT tfeId, folio, archivoRuta, comentario, creadoEn
  FROM ticket_folio_entrada
  WHERE tiId=?
  ORDER BY creadoEn DESC
  LIMIT 1
");
$st->execute([$tiId]);
$fo = $st->fetch(PDO::FETCH_ASSOC);

$foFolio = (is_array($fo) && isset($fo['folio'])) ? (string)$fo['folio'] : '';
$foRuta  = (is_array($fo) && isset($fo['archivoRuta'])) ? (string)$fo['archivoRuta'] : '';
$foFecha = (is_array($fo) && isset($fo['creadoEn'])) ? $fo['creadoEn'] : null;

$hasFolio = ($foRuta !== '');

$folio = [
  'hasFile' => $hasFolio,
  'folio'   => $foFolio,
  'nombre'  => ($foFolio !== '') ? ('Folio: ' . $foFolio) : 'Folio',
  'fecha'   => $foFecha,
  // NO rutas absolutas: solo endpoint
  'view_url'     => $hasFolio ? ("api/visita_folio_file.php?tiId={$tiId}&mode=view") : null,
  'download_url' => $hasFolio ? ("api/visita_folio_file.php?tiId={$tiId}&mode=download") : null,
];

/* ==========================
   SNAPSHOT: Ingenieros del ticket
========================== */
$st = $pdo->prepare("
  SELECT tvi.usIdIng, tvi.rol,
         u.usNombre, u.usAPaterno, u.usAMaterno, u.usTelefono, u.usCorreo,
         i.ingTier, i.ingExperto, i.ingDescripcion
  FROM ticket_visita_ingenieros tvi
  INNER JOIN usuarios u ON u.usId = tvi.usIdIng
  LEFT JOIN ingenieros i ON i.usId = tvi.usIdIng
  WHERE tvi.tiId=?
  ORDER BY FIELD(tvi.rol,'principal','apoyo'), tvi.usIdIng ASC
");
$st->execute([$tiId]);
$ingenieros = $st->fetchAll();

/* ==========================
   SNAPSHOT: Docs del ticket
========================== */
$st = $pdo->prepare("
  SELECT tvd.usIdIng, tvd.tipo, tvd.label, tvd.archivo_snapshot, tvd.idocId, tvd.creadoEn
  FROM ticket_visita_docs tvd
  WHERE tvd.tiId=?
  ORDER BY tvd.creadoEn DESC
");
$st->execute([$tiId]);
$docs = $st->fetchAll();

// Flags importantes
$hasCredencial = false;
foreach ($docs as $d) {
  if (($d['tipo'] ?? '') === 'credencial_trabajo') { $hasCredencial = true; break; }
}

/* ==========================
   SNAPSHOT: Vehículos del ticket (opcional)
========================== */
$st = $pdo->prepare("
  SELECT tvv.usIdIng, tvv.placas, tvv.marca, tvv.modelo, tvv.color, tvv.viId, tvv.creadoEn
  FROM ticket_visita_vehiculos tvv
  WHERE tvv.tiId=?
  ORDER BY tvv.creadoEn DESC
");
$st->execute([$tiId]);
$vehiculos = $st->fetchAll();

/* ==========================
   SNAPSHOT: Equipos seleccionados (laptop/celular/etc.)
========================== */
$st = $pdo->prepare("
  SELECT sel.usIdIng, sel.ieId, sel.cantidad,
         ie.ieTipo, ie.ieMarca, ie.ieModelo, ie.ieSerie
  FROM ticket_visita_equipos_sel sel
  LEFT JOIN ingeniero_equipos ie ON ie.ieId = sel.ieId
  WHERE sel.tiId=?
  ORDER BY sel.usIdIng ASC, ie.ieTipo ASC
");
$st->execute([$tiId]);
$equipos_sel = $st->fetchAll();

/* ==========================
   SNAPSHOT: Herramientas (opcional)
========================== */
$st = $pdo->prepare("
  SELECT tvh.usIdIng, tvh.ihtId, tvh.nombre, tvh.creadoEn
  FROM ticket_visita_herramientas_sel tvh
  WHERE tvh.tiId=?
  ORDER BY tvh.creadoEn DESC
");
$st->execute([$tiId]);
$herramientas = $st->fetchAll();

/* ==========================
   SNAPSHOT: Piezas (inventario o nota)
========================== */
$st = $pdo->prepare("
  SELECT tvp.tipo_pieza, tvp.partNumber, tvp.serialNumber, tvp.invId, tvp.notas, tvp.creadoEn
  FROM ticket_visita_piezas tvp
  WHERE tvp.tiId=?
  ORDER BY tvp.creadoEn DESC
");
$st->execute([$tiId]);
$piezas = $st->fetchAll();

/* ==========================
   Consolidado para UI actual (objeto "acceso")
   (para que tu Offcanvas no se rompa)
========================== */
$acceso = [
  'ing_nombre' => '',
  'ing_tel'    => '',
  'ing_email'  => '',
  'expertis'   => '',
  'vehiculo'   => '',
  'placas'     => '',
  'piezas_txt' => '',
];

// Ingeniero principal como “cara” del ticket
if (!empty($ingenieros)) {
  $p = $ingenieros[0];
  $acceso['ing_nombre'] = trim(($p['usNombre'] ?? '').' '.($p['usApellidos'] ?? ''));
  $acceso['ing_tel']    = $p['usTelefono'] ?? '';
  $acceso['ing_email']  = $p['usEmail'] ?? '';
  $acceso['expertis']   = trim(($p['ingTier'] ?? '').' · '.($p['ingExperto'] ?? ''));
}

// Vehículo principal (si existe)
if (!empty($vehiculos)) {
  $v = $vehiculos[0];
  $acceso['placas']   = $v['placas'] ?? '';
  $acceso['vehiculo'] = trim(($v['marca'] ?? '').' '.($v['modelo'] ?? '').' '.($v['color'] ?? ''));
}

// Piezas en texto (si existen)
if (!empty($piezas)) {
  $parts = [];
  foreach ($piezas as $x) {
    $t  = trim((string)($x['tipo_pieza'] ?? ''));
    $pn = trim((string)($x['partNumber'] ?? ''));
    $sn = trim((string)($x['serialNumber'] ?? ''));
    $nt = trim((string)($x['notas'] ?? ''));
    $s = $t ?: 'Pieza';
    if ($pn) $s .= " PN:$pn";
    if ($sn) $s .= " SN:$sn";
    if ($nt) $s .= " · $nt";
    $parts[] = $s;
  }
  $acceso['piezas_txt'] = implode(' · ', $parts);
}

/* ==========================
   Acceso ready (gating)
   Regla mínima: al menos 1 ingeniero + credencial_trabajo
========================== */
$acceso_ready = (!empty($ingenieros) && $hasCredencial) ? 1 : 0;

/* ==========================
   Historial (últimos 3)
========================== */
$st = $pdo->prepare("
  SELECT hDescripcion, hFecha_hora
  FROM historial
  WHERE hTabla='ticket_soporte' AND hDescripcion LIKE ?
  ORDER BY hId DESC
  LIMIT 3
");
$st->execute(['%tiId=' . $tiId . '%']);
$hh = $st->fetchAll();

$historial = [];
foreach ($hh as $e) {
  $historial[] = [
    'titulo' => 'Evento',
    'descripcion' => (string)$e['hDescripcion'],
    'fecha' => (string)$e['hFecha_hora'],
  ];
}

/* ==========================
   Response
========================== */
json_ok([
  'propuestas' => $propuestas,
  'confirmada' => $confirmada,
  'lock_cancel' => (int)($vis['lock_cancel'] ?? 0),
  'visita' => $vis,

  // Folio
  'folio' => $folio,

  // NUEVO: gating
  'acceso_ready' => $acceso_ready,

  // Compatibilidad UI
  'acceso' => $acceso,

  // NUEVO: detalle para futura pantalla/Offcanvas avanzado
  'ingenieros' => $ingenieros,
  'docs' => $docs,
  'vehiculos' => $vehiculos,
  'equipos' => $equipos_sel,
  'herramientas' => $herramientas,
  'piezas' => $piezas,

  'historial' => $historial,
]);