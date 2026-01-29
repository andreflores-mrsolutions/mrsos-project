<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require "conexion.php"; // tu mysqli $con

$out = ["success"=>false];

$usId = $_SESSION['usId'] ?? 0;
$clId = $_SESSION['clId'] ?? 0; // si manejas cliente en sesión
if (!$usId || !$clId) {
  echo json_encode(["success"=>false,"error"=>"Sesión inválida."]);
  exit;
}

$tab = $_GET['tab'] ?? 'HS_T';     // HS_T | HS_HC | POLIZAS
$q   = trim($_GET['q'] ?? '');
$csId = isset($_GET['csId']) ? (int)$_GET['csId'] : 0;

/**
 * 1) Resolver sedes permitidas por usuario
 *   - usuario: solo su sede
 *   - admin zona: sedes de su zona
 *   - admin global: todas
 * Ajusta a tu modelo real en usuario_cliente_rol
 */
$sedesPermitidas = [];
$sqlPerm = "
  SELECT ucr.csId, ucr.ucrRol
  FROM usuario_cliente_rol ucr
  WHERE ucr.usId = ? AND ucr.clId = ?
";
$stmt = $conectar->prepare($sqlPerm);
$stmt->bind_param("ii", $usId, $clId);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
  $sedesPermitidas[] = (int)$row['csId'];
  if($row['ucrRol'] === 'ADMIN_GLOBAL'){
    // obtener todas las sedes del cliente
    $sedesPermitidas = [];
    $sqlAll = "SELECT csId FROM cliente_sede WHERE clId = ?";
    $stmtAll = $conectar->prepare($sqlAll);
    $stmtAll->bind_param("i", $clId);
    $stmtAll->execute();
    $resAll = $stmtAll->get_result();
    while($rAll = $resAll->fetch_assoc()){
      $sedesPermitidas[] = (int)$rAll['csId'];
    }
    $stmtAll->close();
    break; // no seguir leyendo roles
  } else if($row['ucrRol'] === 'ADMIN_ZONA'){
    // obtener sedes de su zona
    $zonaId = $row['czId'] ?? null;
    $sqlAll = "SELECT csId FROM cliente_sede WHERE clId = ? AND czId = ?";
    $stmtAll = $conectar->prepare($sqlAll);
    $stmtAll->bind_param("is", $clId, $zonaId);
    $stmtAll->execute();
    $resAll = $stmtAll->get_result();
    while($rAll = $resAll->fetch_assoc()){
      $sedesPermitidas[] = (int)$rAll['csId'];
    }
  }
}
$stmt->close();

if (empty($sedesPermitidas)) {
  echo json_encode(["success"=>true,"sedes"=>[],"items"=>[],"polizas"=>[]]);
  exit;
}

// Si viene csId filtro, validar que esté permitido
if ($csId > 0 && !in_array($csId, $sedesPermitidas, true)) {
  echo json_encode(["success"=>false,"error"=>"No autorizado para esa sede."]);
  exit;
}

$baseUrl = "http://192.168.3.7"; // tu base

/**
 * 2) Listar según TAB
 */
if ($tab === 'POLIZAS') {
  // POLIZAS por sedes (si las quieres agrupadas) o planas.
  // Recomendación: ya tienes pcPdfPath y pcIdentificador en polizascliente/polizasequipos.
  $whereSede = $csId ? " AND pe.csId = ? " : "";
  $whereQ = $q ? " AND (pc.pcIdentificador LIKE ? OR pc.pcTipoPoliza LIKE ?) " : "";

  // Ajusta joins según tu esquema real:
  $sql = "
    SELECT DISTINCT
      pc.pcId, pc.pcIdentificador, pc.pcTipoPoliza, pc.pcPdfPath
    FROM polizascliente pc
    JOIN polizasequipo pe ON pe.pcId = pc.pcId
    WHERE pc.clId = ?
      AND pe.csId IN (" . implode(',', array_map('intval',$sedesPermitidas)) . ")
      $whereSede
      $whereQ
    ORDER BY pc.pcIdentificador ASC
  ";

  $stmt = $conectar->prepare($sql);

  $types = "i";
  $params = [$clId];

  if ($csId) { $types.="i"; $params[]=$csId; }
  if ($q) { $like="%$q%"; $types.="ss"; $params[]=$like; $params[]=$like; }

  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $r = $stmt->get_result();

  $polizas = [];
  while($row = $r->fetch_assoc()){
    $pdfPath = $row['pcPdfPath'] ?? '';
    $url = $pdfPath ? ($baseUrl . "/" . ltrim($pdfPath,'/') . "/". $row["pcIdentificador"] . ".pdf") : "";
    $polizas[] = [
      "pcId" => (int)$row["pcId"],
      "pcIdentificador" => $row["pcIdentificador"],
      "pcTipoPoliza" => $row["pcTipoPoliza"],
      "url" => $url,
    ];
  }
  $stmt->close();

  echo json_encode(["success"=>true,"tab"=>"POLIZAS","polizas"=>$polizas]);
  exit;
}

// HS_T o HS_HC
$tipo = ($tab === 'HS_HC') ? 'HC' : 'T';

$whereSede = $csId ? " AND hs.csId = ? " : "";
$whereQ = $q ? " AND (hs.hsFolio LIKE ? OR hs.hsNombreEquipo LIKE ?) " : "";

$sql = "
  SELECT
    hs.hsId, hs.csId, hs.hsFolio, hs.hsTipo, hs.hsNombreEquipo, hs.hsPath,
    cs.csNombre
  FROM hojas_servicio hs
  JOIN cliente_sede cs ON cs.csId = hs.csId
  WHERE hs.clId = ?
    AND hs.hsActivo = 1
    AND hs.hsTipo = ?
    AND hs.csId IN (" . implode(',', array_map('intval',$sedesPermitidas)) . ")
    $whereSede
    $whereQ
  ORDER BY cs.csNombre ASC, hs.hsNumero ASC
";

$stmt = $conectar->prepare($sql);

$types = "is";
$params = [$clId, $tipo];

if ($csId) { $types.="i"; $params[]=$csId; }
if ($q) { $like="%$q%"; $types.="ss"; $params[]=$like; $params[]=$like; }

$stmt->bind_param($types, ...$params);
$stmt->execute();
$r = $stmt->get_result();

$items = [];
while($row = $r->fetch_assoc()){
  $path = $row['hsPath'];
  $url = $baseUrl . "/uploads/" . ltrim($path,'/');
  $items[] = [
    "hsId" => (int)$row["hsId"],
    "csId" => (int)$row["csId"],
    "csNombre" => $row["csNombre"],
    "folio" => $row["hsFolio"],
    "tipo" => $row["hsTipo"],
    "equipo" => $row["hsNombreEquipo"] ?? "",
    "url" => $url
  ];
}
$stmt->close();

// agrupar por sede (como mock)
$sedes = [];
foreach ($items as $it) {
  $k = $it["csId"];
  if (!isset($sedes[$k])) {
    $sedes[$k] = ["csId"=>$it["csId"], "csNombre"=>$it["csNombre"], "items"=>[]];
  }
  $sedes[$k]["items"][] = $it;
}

echo json_encode([
  "success"=>true,
  "tab"=>$tab,
  "sedes"=>array_values($sedes),
  "count"=>count($items)
]);
