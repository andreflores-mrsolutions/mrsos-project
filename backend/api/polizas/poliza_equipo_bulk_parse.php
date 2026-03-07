<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../php/conexion.php';
require_once __DIR__ . '/../../../php/auth_guard.php';
require_once __DIR__ . '/../../../php/csrf.php';
require_once __DIR__ . '/../../../php/json.php';
require_once __DIR__ . '/../../../php/cliente_guard.php';

no_store();
require_login();
require_usRol(['MRSA','MRA','MRV']);
csrf_verify_or_fail();

$pdo = db();
$pcId = (int)($_POST['pcId'] ?? 0);
if ($pcId <= 0) json_fail('pcId requerido');

$st = $pdo->prepare("SELECT pcId, clId FROM polizascliente WHERE pcId=? LIMIT 1");
$st->execute([$pcId]);
$pc = $st->fetch(PDO::FETCH_ASSOC);
if (!$pc) json_fail('Póliza no existe');

$clId = (int)$pc['clId'];
$usId = (int)($_SESSION['usId'] ?? 0);
$rol  = current_usRol();
if (!mr_can_access_client($pdo, $usId, $rol, $clId)) json_fail('Sin acceso al cliente');

if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  json_fail('Archivo requerido (file)');
}

$tmp  = (string)$_FILES['file']['tmp_name'];
$name = (string)($_FILES['file']['name'] ?? 'upload');
$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

/** ---------- helpers ---------- */

function normh(string $s): string {
  $s = trim($s);
  $s = mb_strtolower($s, 'UTF-8');
  $s = str_replace([' ', '-', '_', '.', '/', '\\', "\t"], '', $s);
  return $s;
}

function normalize_text(string $s): string {
  $s = trim($s);
  $s = mb_strtolower($s, 'UTF-8');
  // quitar acentos (sin intl)
  $map = [
    'á'=>'a','à'=>'a','ä'=>'a','â'=>'a',
    'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
    'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
    'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o',
    'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
    'ñ'=>'n'
  ];
  $s = strtr($s, $map);
  $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
  return $s;
}

function read_csv_rows(string $path): array {
  $fh = fopen($path, 'r');
  if (!$fh) json_fail('No se pudo leer CSV');
  $header = null;
  $out = [];
  while (($data = fgetcsv($fh)) !== false) {
    if ($header === null) {
      $header = array_map(fn($h) => normh((string)$h), $data);
      continue;
    }
    if (count(array_filter($data, fn($x) => trim((string)$x) !== '')) === 0) continue;

    $row = [];
    foreach ($header as $i => $h) $row[$h] = $data[$i] ?? '';
    $out[] = $row;
  }
  fclose($fh);
  return $out;
}

function require_composer_autoload(): void {
  $candidates = [
    __DIR__ . '/../../../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
  ];
  foreach ($candidates as $p) {
    if (file_exists($p)) { require_once $p; return; }
  }
  json_fail('XLSX requiere composer vendor/autoload.php (PhpSpreadsheet). Usa CSV o instala dependencia.');
}

function read_xlsx_rows(string $path): array {
  require_composer_autoload();
  if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
    json_fail('PhpSpreadsheet no está disponible. Usa CSV o instala phpoffice/phpspreadsheet.');
  }
  $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
  $sheet = $ss->getActiveSheet();
  $data = $sheet->toArray(null, true, true, true);
  if (!$data || count($data) < 2) return [];

  $headerRow = array_shift($data);
  $headers = [];
  foreach ($headerRow as $cell) $headers[] = normh((string)$cell);

  $out = [];
  foreach ($data as $r) {
    $vals = array_values($r);
    if (count(array_filter($vals, fn($x) => trim((string)$x) !== '')) === 0) continue;

    $row = [];
    foreach ($headers as $i => $h) $row[$h] = $vals[$i] ?? '';
    $out[] = $row;
  }
  return $out;
}

/**
 * Acepta headers flexibles:
 * eqId: eqid
 * SN: pesn / serial / sn
 * sede: sede / csnombre / sedename
 * so: peso / so
 * descripcion: pedescripcion / descripcion
 * estatus: peestatus / estatus
 */
function map_row(array $r): array {
  $eqId = (int)($r['eqid'] ?? 0);
  $sn   = trim((string)($r['pesn'] ?? $r['serial'] ?? $r['sn'] ?? ''));
  $sede = trim((string)($r['sede'] ?? $r['csnombre'] ?? $r['sedename'] ?? ''));
  $so   = trim((string)($r['peso'] ?? $r['so'] ?? ''));
  $desc = trim((string)($r['pedescripcion'] ?? $r['descripcion'] ?? ''));
  $est  = trim((string)($r['peestatus'] ?? $r['estatus'] ?? ''));
  return [
    'eqId' => $eqId,
    'peSN' => $sn,
    'sede' => $sede,
    'peSO' => $so,
    'peDescripcion' => $desc,
    'peEstatus' => $est,
  ];
}

/** ---------- leer archivo ---------- */
$rawRows = [];
if ($ext === 'csv') $rawRows = read_csv_rows($tmp);
elseif ($ext === 'xlsx') $rawRows = read_xlsx_rows($tmp);
else json_fail('Formato no soportado. Usa CSV o XLSX');

if (count($rawRows) === 0) json_fail('El archivo no tiene filas');

/** ---------- sedes del cliente ---------- */
$st = $pdo->prepare("SELECT csId, csNombre FROM cliente_sede WHERE clId=? AND csEstatus='Activo' ORDER BY csNombre");
$st->execute([$clId]);
$sedes = $st->fetchAll(PDO::FETCH_ASSOC);

$sedesNormMap = []; // normName => [ {csId, csNombre}, ... ]
foreach ($sedes as $s) {
  $n = normalize_text((string)$s['csNombre']);
  if (!isset($sedesNormMap[$n])) $sedesNormMap[$n] = [];
  $sedesNormMap[$n][] = ['csId' => (int)$s['csId'], 'csNombre' => (string)$s['csNombre']];
}

/** ---------- validar eqIds ---------- */
$eqIds = [];
foreach ($rawRows as $r) {
  $m = map_row($r);
  if ($m['eqId'] > 0) $eqIds[$m['eqId']] = true;
}
$eqIds = array_keys($eqIds);

$eqExists = [];
if (count($eqIds)) {
  $inQ = implode(',', array_fill(0, count($eqIds), '?'));
  $st = $pdo->prepare("SELECT eqId FROM equipos WHERE eqId IN ($inQ)");
  $st->execute($eqIds);
  foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) $eqExists[(int)$id] = true;
}

/** ---------- sugerencias de sede (fuzzy) ---------- */
$sedesNormList = array_keys($sedesNormMap);

function sede_candidates(string $sedeRaw, array $sedesNormMap, array $sedesNormList): array {
  $sedeRaw = trim($sedeRaw);
  if ($sedeRaw === '') return [];

  $n = normalize_text($sedeRaw);

  // match exacto
  if (isset($sedesNormMap[$n])) {
    return array_map(fn($x) => ['csId'=>$x['csId'], 'csNombre'=>$x['csNombre'], 'score'=>100], $sedesNormMap[$n]);
  }

  // fuzzy: top 5 por levenshtein
  $scores = [];
  foreach ($sedesNormList as $cand) {
    $dist = levenshtein($n, $cand);
    $scores[] = ['cand'=>$cand, 'dist'=>$dist];
  }
  usort($scores, fn($a,$b) => $a['dist'] <=> $b['dist']);
  $top = array_slice($scores, 0, 5);

  $out = [];
  foreach ($top as $t) {
    $cand = $t['cand'];
    foreach ($sedesNormMap[$cand] as $x) {
      $score = max(1, 100 - (int)$t['dist'] * 10); // aproximado
      $out[] = ['csId'=>$x['csId'], 'csNombre'=>$x['csNombre'], 'score'=>$score];
    }
  }
  return $out;
}

/** ---------- construir preview ---------- */
$rowsOut = [];
foreach ($rawRows as $i => $r) {
  $line = $i + 2;
  $m = map_row($r);

  $errors = [];
  if ($m['eqId'] <= 0) $errors[] = 'eqId requerido';
  elseif (!isset($eqExists[$m['eqId']])) $errors[] = 'eqId no existe';

  if ($m['peSN'] === '') $errors[] = 'peSN (Serial) requerido';
  if ($m['sede'] === '') $errors[] = 'Sede requerida (texto)';

  $cands = sede_candidates($m['sede'], $sedesNormMap, $sedesNormList);

  $status = 'ok';
  if (count($errors)) $status = 'error';
  else {
    // si no hay match exacto, marcamos como "needs_map"
    $n = normalize_text($m['sede']);
    if (!isset($sedesNormMap[$n])) $status = 'needs_map';
    else if (count($sedesNormMap[$n]) > 1) $status = 'ambiguous';
  }

  $rowsOut[] = [
    'line' => $line,
    'status' => $status,
    'errors' => $errors,
    'data' => $m,
    'sedeCandidates' => $cands,
    'csIdResolved' => null,
  ];
}

json_ok([
  'pcId' => $pcId,
  'clId' => $clId,
  'meta' => [
    'fileName' => $name,
    'rows' => count($rowsOut),
    'note' => 'Este paso no inserta nada. Selecciona sede por fila (csId) y luego haz commit.'
  ],
  'sedes' => $sedes, // para dropdown general si quieres
  'rows' => $rowsOut
]);