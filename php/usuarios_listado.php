<?php
// usuarios_listado.php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require "conexion.php"; // ajusta

// ====== Helpers ======
function jexit($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function is_admin_global($rol){ return in_array(($rol), ['ADMIN_GLOBAL','adminglobal','global','superadmin'], true); }
function is_admin_zona($rol){ return in_array(($rol), ['ADMIN_ZONA','admin zona','adminzona','zona'], true); }
function is_admin_sede($rol){ return in_array(($rol), ['ADMIN_SEDE','admin sede','adminsede','sede'], true); }

// ====== Session required ======
$usId = $_SESSION['usId'] ?? 0;
$clId = $_SESSION['clId'] ?? 0; // si tu app maneja cliente por sesión
if (!$usId) jexit(['success'=>false,'error'=>'Sesión no válida (usId).']);

// ====== Inputs ======
$q     = trim($_GET['q'] ?? '');
$rolF  = trim($_GET['rol'] ?? '');     // puede ser texto
$czIdF = (int)($_GET['czId'] ?? 0);
$csIdF = (int)($_GET['csId'] ?? 0);
$notif = trim($_GET['notif'] ?? '');   // "" | "0" | "1"

// ====== Obtener rol y scope del usuario actual ======
// Ajusta nombres de campos en usuario_cliente_rol:
// - ucrRol (texto) o rolId join roles
// - czId, csId (pueden ser null/0)
$sqlMe = "
  SELECT 
    ucr.usId,
    ucr.clId,
    COALESCE(ucr.csId,0) as csId,
    COALESCE(ucr.czId,0) as czId,
    COALESCE(ucr.ucrRol, '') as rol
  FROM usuario_cliente_rol ucr
  WHERE ucr.usId = ? " . ($clId ? " AND ucr.clId = ? " : "") . "
  LIMIT 1
";
$stmt = $conectar->prepare($sqlMe);
if ($clId) $stmt->bind_param("ii", $usId, $clId);
else $stmt->bind_param("i", $usId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$me) jexit(['success'=>false,'error'=>'No existe relación usuario_cliente_rol para este usuario.']);

$myRol = $me['rol'];
$myCsId = (int)$me['csId'];
$myCzId = (int)$me['czId'];
$myClId = (int)$me['clId']; // si venía en la tabla

// ====== Construir restricciones por rol ======
$where = [];
$params = [];
$types = "";

// Cliente scope (siempre filtra por clId si existe)
$effectiveClId = $clId ?: $myClId;
if ($effectiveClId) {
  $where[] = "ucr.clId = ?";
  $types .= "i";
  $params[] = $effectiveClId;
}

// Scope:
if (is_admin_global($myRol)) {
  // no restringimos sede/zona
} elseif (is_admin_zona($myRol)) {
  // restringimos a su zona (si no trae czId, no restringimos - pero ideal que sí exista)
  if ($myCzId > 0) {
    $where[] = "cz.czId = ?";
    $types .= "i";
    $params[] = $myCzId;
  }
} else {
  // admin sede o usuario -> sede obligatoria
  if ($myCsId > 0) {
    $where[] = "cs.csId = ?";
    $types .= "i";
    $params[] = $myCsId;
  } else {
    // fallback seguro: no dejar ver nada si es usuario/sede sin csId
    $where[] = "1=0";
  }
}

// ====== Filtros UI ======
if ($rolF !== '') {
  $where[] = "LOWER(ucr.ucrRol) = LOWER(?)";
  $types .= "s";
  $params[] = $rolF;
}
if ($czIdF > 0) {
  $where[] = "cz.czId = ?";
  $types .= "i";
  $params[] = $czIdF;
}
if ($csIdF > 0) {
  $where[] = "cs.csId = ?";
  $types .= "i";
  $params[] = $csIdF;
}
if ($q !== '') {
  $where[] = "(u.usNombre LIKE ? OR u.usAPaterno LIKE ? OR u.usAMaterno LIKE ? OR u.usCorreo LIKE ? OR u.usUsername LIKE ?)";
  $like = "%$q%";
  $types .= "sssss";
  array_push($params, $like,$like,$like,$like,$like);
}
if ($notif === "1" || $notif === "0") {
  // Ajusta el nombre real de columna (ej: ucrNotificaciones)
  $where[] = "COALESCE(ucr.ucrNotificaciones,0) = ?";
  $types .= "i";
  $params[] = (int)$notif;
}

// ====== Query principal (agruparemos por sede en PHP) ======
// Ajusta joins según tus tablas:
// usuarios: usId, usNombre, usAPaterno, usAMaterno, usCorreo, usUsername, usAvatar?
// cliente_sede: csId, csNombre, czId
// cliente_zona: czId, czNombre
$sql = "
  SELECT
    u.usId,
    CONCAT_WS(' ', u.usNombre, u.usAPaterno, u.usAMaterno) as nombre,
    COALESCE(u.usCorreo,'') as correo,
    COALESCE(u.usUsername,'') as username,
    COALESCE(u.usImagen,'') as avatar,
    COALESCE(ucr.ucrRol,'') as rol,
    COALESCE(u.usNotificaciones,0) as notificaciones,
    cs.csId,
    COALESCE(cs.csNombre,'') as csNombre,
    cz.czId,
    COALESCE(cz.czNombre,'') as czNombre,
    c.clId,
    COALESCE(c.clNombre,'') as clNombre
  FROM usuario_cliente_rol ucr
  INNER JOIN usuarios u ON u.usId = ucr.usId
  INNER JOIN clientes c ON c.clId = ucr.clId
  LEFT JOIN cliente_sede cs ON cs.csId = ucr.csId
  LEFT JOIN cliente_zona cz ON cz.czId = COALESCE(cs.czId, ucr.czId)
";

if (count($where)) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY c.clNombre, cz.czNombre, cs.csNombre, nombre";

// Ejecutar
$stmt = $conectar->prepare($sql);
if (!$stmt) jexit(['success'=>false,'error'=>'SQL prepare error: '.$conectar->error]);

if ($types !== "") {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rs = $stmt->get_result();
$rows = [];
while($r = $rs->fetch_assoc()) $rows[] = $r;
$stmt->close();

// ====== Armar filtros (listas) ======
$roles = [];
$zonas = [];
$sedes = [];

foreach ($rows as $r) {
  if ($r['rol'] !== '') $roles[strtolower($r['rol'])] = $r['rol'];

  if ((int)$r['czId'] > 0) {
    $zonas[(int)$r['czId']] = ['czId'=>(int)$r['czId'], 'czNombre'=>$r['czNombre']];
  }
  if ((int)$r['csId'] > 0) {
    $sedes[(int)$r['csId']] = ['csId'=>(int)$r['csId'], 'csNombre'=>$r['csNombre'], 'czId'=>(int)$r['czId']];
  }
}

// ====== Agrupar por sede para UI ======
$bySede = [];
foreach ($rows as $r) {
  $key = (int)$r['csId'];
  $groupTitle = trim(($r['clNombre'] ?: 'Cliente') . ' · ' . ($r['csNombre'] ?: 'Sin sede'));

  if (!isset($bySede[$key])) {
    $bySede[$key] = [
      'csId' => (int)$r['csId'],
      'csNombre' => $r['csNombre'],
      'clNombre' => $r['clNombre'],
      'titulo' => $groupTitle,
      'usuarios' => [],
    ];
  }

  $bySede[$key]['usuarios'][] = [
    'usId' => (int)$r['usId'],
    'nombre' => $r['nombre'],
    'correo' => $r['correo'],
    'username' => $r['username'],
    'avatar' => $r['avatar'],
    'rol' => $r['rol'],
    'notificaciones' => (int)$r['notificaciones'],
    'csId' => (int)$r['csId'],
    'csNombre' => $r['csNombre'],
    'czId' => (int)$r['czId'],
    'czNombre' => $r['czNombre'],
  ];
}

jexit([
  'success' => true,
  'scope' => [
    'rol' => $myRol,
    'clId' => $effectiveClId,
    'czId' => $myCzId,
    'csId' => $myCsId,
  ],
  'filters' => [
    'roles' => array_values($roles),
    'zonas' => array_values($zonas),
    'sedes' => array_values($sedes),
  ],
  'sedes' => array_values($bySede),
]);
