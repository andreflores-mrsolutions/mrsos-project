<?php
// php/obtener_hojas_servicio.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include("conexion.php");
session_start();

$rolSesion  = $_SESSION['usRol']  ?? null;   // 'MRA', 'AC', 'UC', 'EC'
$clIdSesion = $_SESSION['clId'] ?? null;

// Si eres MRA y quieres ver de un cliente concreto, permite ?clId= (opcional)
$clId = ($rolSesion === 'MRA' && isset($_GET['clId']))
  ? (int)$_GET['clId']
  : $clIdSesion;

// Usuarios NO admin deben tener cliente
if (!$clId && $rolSesion !== 'MRA') {
  echo json_encode(['success'=>false, 'error'=>'No autenticado']);
  exit;
}

// Lee filtros (POST JSON)
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$desde = $input['desde']      ?? null; // YYYY-MM-DD
$hasta = $input['hasta']      ?? null; // YYYY-MM-DD
$tipo  = $input['tipoEquipo'] ?? null; // 'Servidor', 'Storage', etc.

// --- Helper para carpetas seguras ---
function slug_segment($s) {
  if ($s === null) return 'general';
  // quita acentos / caracteres especiales si iconv disponible
  if (function_exists('iconv')) {
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE', $s);
  }
  $s = strtolower(trim($s));
  $s = preg_replace('/[^a-z0-9]+/','-', $s);
  $s = trim($s, '-');
  return $s ?: 'general';
}

// Joins robustos: t.peId primero; fallback eqId
$joins = "
  LEFT JOIN polizasequipo pe       ON pe.peId = t.peId
  LEFT JOIN polizasequipo pe_eq    ON (t.peId IS NULL AND pe_eq.eqId = t.eqId)
  LEFT JOIN equipos e              ON e.eqId = COALESCE(pe.eqId, pe_eq.eqId, t.eqId)
  LEFT JOIN marca m                ON m.maId = e.maId
  LEFT JOIN polizascliente pc      ON pc.pcId = COALESCE(pe.pcId, pe_eq.pcId)
  LEFT JOIN clientes cl            ON cl.clId = COALESCE(t.clId, pc.clId)
  LEFT JOIN cliente_sede cs        ON cs.csId = COALESCE(t.csId, pc.csId)
";

$where   = [];
$types   = "";
$params  = [];

// Filtro por cliente:
// - MRA ve TODO si no pasa clId
// - Usuarios normales ven sólo su cliente
if ($rolSesion !== 'MRA' || $clId) {
  $where[] = "(t.clId = ? OR pc.clId = ?)";
  $types  .= "ii";
  $params[] = (int)$clId;
  $params[] = (int)$clId;
}

// Fechas (si vienen) — sobre fecha de creación del ticket
if (!empty($desde)) {
  $where[] = "DATE(t.tiFechaCreacion) >= ?";
  $types  .= "s";
  $params[] = $desde;
}
if (!empty($hasta)) {
  $where[] = "DATE(t.tiFechaCreacion) <= ?";
  $types  .= "s";
  $params[] = $hasta;
}

// Tipo de equipo (opcional)
if (!empty($tipo)) {
  $where[] = "e.eqTipoEquipo = ?";
  $types  .= "s";
  $params[] = $tipo;
}

$whereSQL = count($where) ? "WHERE ".implode(" AND ", $where) : "";

// Selecciona lo necesario para formar ruta y pintar card
$sql = "
  SELECT
    t.tiId,
    t.tiEstatus,
    t.tiProceso,
    DATE(t.tiFechaCreacion) AS fecha,
    e.eqModelo,
    e.eqVersion,
    e.eqTipoEquipo,
    m.maNombre,
    COALESCE(pe.peSN, pe_eq.peSN) AS peSN,
    cl.clNombre,
    cs.csNombre
  FROM ticket_soporte t
  $joins
  $whereSQL
  ORDER BY t.tiFechaCreacion DESC, t.tiId DESC
";

$stmt = $conectar->prepare($sql);
if (!$stmt) {
  echo json_encode(['success'=>false, 'error'=>'Error prepare: '.$conectar->error]);
  exit;
}
if ($types !== "") {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

// Rutas base (filesystem y URL) — uploads está un nivel arriba de /php
$baseFs  = realpath(__DIR__ . "/../uploads");
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')."/../uploads";

// Si realpath falla (carpeta aún no existe), igual construimos ruta relativa
if (!$baseFs) {
  $baseFs = __DIR__ . "/../uploads";
}

$rows = [];
while ($r = $res->fetch_assoc()) {
  $clNombre = $r['clNombre'] ?? 'cliente';
  $csNombre = $r['csNombre'] ?? 'general';

  $clSlug = slug_segment($clNombre);
  $csSlug = slug_segment($csNombre);

  // Carpeta: uploads/<cliente>/<sede>/
  $dir = rtrim($baseFs, DIRECTORY_SEPARATOR)
       . DIRECTORY_SEPARATOR . $clSlug
       . DIRECTORY_SEPARATOR . $csSlug
       . DIRECTORY_SEPARATOR;

  $disponible = false;
  $archivo    = null;
  $archivoUrl = null;

  if (is_dir($dir)) {
    // Convención: HS_<ticketId>*.pdf (toma el más reciente si hay varios)
    $pattern = $dir . "HS_" . $r['tiId'] . "*.pdf";
    $files = glob($pattern);
    if ($files && count($files) > 0) {
      usort($files, function($a,$b){ return filemtime($b) <=> filemtime($a); });
      $archivo    = basename($files[0]);
      $disponible = true;

      // URL pública (siempre con /, no con backslashes)
      $archivoUrl = $baseUrl . "/"
                  . rawurlencode($clSlug) . "/"
                  . rawurlencode($csSlug) . "/"
                  . rawurlencode($archivo);
    }
  }

  // Mantén formato previo: 'archivo_url' y 'hsArchivo'
  $r['hsArchivo']   = $archivo;      // basename del archivo si existe
  $r['archivo_url'] = $archivoUrl;   // URL completa o null
  $r['disponible']  = $disponible;   // boolean para tu lógica de UI

  $rows[] = $r;
}
$stmt->close();

echo json_encode(['success'=>true, 'hojas'=>$rows]);
