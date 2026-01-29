<?php
// php/adm_usuario_detalle.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
require 'conexion.php';

function jexit(array $a): void {
  echo json_encode($a, JSON_UNESCAPED_UNICODE);
  exit;
}

function norm_role(string $r): string {
  return strtoupper(trim($r));
}

function is_admin_global(string $r): bool { return norm_role($r) === 'ADMIN_GLOBAL'; }
function is_admin_zona(string $r): bool   { return norm_role($r) === 'ADMIN_ZONA'; }
function is_admin_sede(string $r): bool   { return norm_role($r) === 'ADMIN_SEDE'; }

// ---- Guard: sesión ----
$meUsId = (int)($_SESSION['usId'] ?? 0);
$meClId = (int)($_SESSION['clId'] ?? 0);

if ($meUsId <= 0 || $meClId <= 0) {
  jexit(['success' => false, 'error' => 'No autenticado']);
}

$targetUsId = (int)($_POST['usId'] ?? 0);
$filterCzId = (int)($_POST['czIdFiltro'] ?? ($_POST['czId'] ?? 0)); // <- nuevo

if ($targetUsId <= 0) {
  jexit(['success' => false, 'error' => 'Parámetros inválidos (usId)']);
}

// ---- 1) Obtener rol/scope del usuario logueado (desde usuario_cliente_rol) ----
// Ajusta ucrRol / czId / csId si tus columnas se llaman diferente.
$sqlMe = "
  SELECT
    COALESCE(ucr.ucrRol,'') AS rol,
    COALESCE(ucr.czId,0)    AS czId,
    COALESCE(ucr.csId,0)    AS csId
  FROM usuario_cliente_rol ucr
  WHERE ucr.usId = ? AND ucr.clId = ?
  LIMIT 1
";
$stmt = $conectar->prepare($sqlMe);
if (!$stmt) jexit(['success'=>false,'error'=>'SQL prepare error (me): '.$conectar->error]);
$stmt->bind_param("ii", $meUsId, $meClId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$me) {
  jexit(['success'=>false,'error'=>'No existe relación usuario_cliente_rol para el usuario en sesión.']);
}

$meRol  = norm_role((string)$me['rol']);
$meCzId = (int)$me['czId'];
$meCsId = (int)$me['csId'];

// ---- 2) Traer detalle del usuario objetivo con joins ----
// IMPORTANTE: filtramos por clId de sesión (cliente).
// Además aplicamos restricción de zona/sede según el rol del usuario en sesión.
$where = " u.clId = ? AND u.usId = ? ";
$types = "ii";
$params = [$meClId, $targetUsId];

// Scope adicional:
if (is_admin_global($meRol)) {
  // ve todo el cliente
  $where .= " AND u.usId != ? ";
  $types .= "i";
  $params[] = $meUsId; // no puede verse a sí mismo
} elseif (is_admin_zona($meRol)) {
  // restringe a su zona
  // si tu Admin Zona no tiene czId, NO debería ver todo; mejor bloquear (seguro).
  if ($meCzId > 0) {
    $where .= " AND (cz.czId = ? OR ucr.czId = ?) AND u.usId != ? ";
    $types .= "iii";
    $params[] = $meCzId;
    $params[] = $meCzId;
    $params[] = $meUsId; // no puede verse a sí mismo
  } else {
    // seguro: si admin_zona no trae zona, no mostramos nada
    $where .= " AND 1=0 ";
  }
} elseif (is_admin_sede($meRol)) {
  // restringe a su sede
  if ($meCsId > 0) {
    $where .= " AND (cs.csId = ? OR ucr.csId = ?) AND u.usId != ? ";
    $types .= "iii";
    $params[] = $meCsId;
    $params[] = $meCsId;
    $params[] = $meUsId; // no puede verse a sí mismo
  } else {
    $where .= " AND 1=0 ";
  }
} else {
  // roles no admin: no deberían entrar aquí
  $where .= " AND 1=0 ";
}

$sqlU = "
  SELECT
    u.usId,
    u.clId,
    COALESCE(u.usNombre,'')   AS nombre,
    COALESCE(u.usAPaterno,'') AS apaterno,
    COALESCE(u.usAMaterno,'') AS amaterno,
    COALESCE(u.usCorreo,'')   AS correo,
    COALESCE(u.usTelefono,'') AS telefono,
    COALESCE(u.usUsername,'') AS username,
    COALESCE(u.usEstatus,'Activo') AS estatus,
    COALESCE(u.usRol,'CLI')   AS rolSistema,

    COALESCE(ucr.ucrRol,'')   AS nivel,
    COALESCE(ucr.csId, 0)     AS csId,
    COALESCE(ucr.czId, 0)     AS czId,

    COALESCE(cs.csNombre,'')  AS sedeNombre,
    COALESCE(cz.czNombre,'')  AS zonaNombre,

    COALESCE(u.usImagen,'')   AS avatar
  FROM usuarios u
  LEFT JOIN usuario_cliente_rol ucr
    ON ucr.usId = u.usId AND ucr.clId = u.clId
  LEFT JOIN cliente_sede cs
    ON cs.csId = ucr.csId
  LEFT JOIN cliente_zona cz
    ON cz.czId = COALESCE(cs.czId, ucr.czId)
  WHERE $where
  LIMIT 1
";

$stmt = $conectar->prepare($sqlU);
if (!$stmt) jexit(['success'=>false,'error'=>'SQL prepare error (usuario): '.$conectar->error]);

// bind dinámico
$stmt->bind_param($types, ...$params);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
  jexit(['success'=>false,'error'=>'Usuario no encontrado o fuera de tu alcance.']);
}

// Armar avatarUrl (ajusta tu ruta real)
$avatar = trim((string)$user['avatar']);
$image = trim((string)$user['username']);
$user['avatarUrl'] = $avatar !== '0' && $avatar !== ''
  ? "../img/Usuario/".$image.".jpg"
  : "../img/Usuario/user.webp";

// Normalizar nulls esperados por Flutter
$user['csId'] = (int)$user['csId'];
$user['czId'] = (int)$user['czId'];
$user['rol']  = (string)$user['nivel']; // <-- para que tu Flutter use user['rol']
unset($user['nivel']); // opcional

// ---- 3) Listas para dropdowns ----

// 3.1 Roles (puedes devolver estáticos + los existentes)
$roles = ['ADMIN_GLOBAL','ADMIN_ZONA','ADMIN_SEDE','USUARIO','VISOR'];

// 3.2 Zonas del cliente (si admin_zona, puedes filtrar a su zona para evitar ruido)
$sqlZ = "SELECT czId, COALESCE(czNombre,'') AS czNombre FROM cliente_zona WHERE clId = ? ";
$tz = "i";
$pz = [$meClId];

if (is_admin_zona($meRol) && $meCzId > 0) {
  $sqlZ .= " AND czId = ? ";
  $tz .= "i";
  $pz[] = $meCzId;
}
$sqlZ .= " ORDER BY czNombre";

$stmt = $conectar->prepare($sqlZ);
if (!$stmt) jexit(['success'=>false,'error'=>'SQL prepare error (zonas): '.$conectar->error]);
$stmt->bind_param($tz, ...$pz);
$stmt->execute();
$rs = $stmt->get_result();
$zonas = [];
while ($r = $rs->fetch_assoc()) {
  $zonas[] = ['czId'=>(int)$r['czId'], 'czNombre'=>$r['czNombre']];
}
$stmt->close();

// 3.3 Sedes del cliente (si admin_sede, filtra a su sede; si admin_zona filtra a su zona)
$sqlS = "
  SELECT
    cs.csId,
    COALESCE(cs.csNombre,'') AS csNombre,
    COALESCE(cs.czId,0)      AS czId,
    c.clId,
    COALESCE(c.clNombre,'')  AS clNombre
  FROM cliente_sede cs
  INNER JOIN clientes c ON c.clId = cs.clId
  WHERE cs.clId = ?
";
$ts = "i";
$ps = [$meClId];

if (is_admin_sede($meRol) && $meCsId > 0) {
  $sqlS .= " AND cs.csId = ? ";
  $ts .= "i";
  $ps[] = $meCsId;
} elseif (is_admin_zona($meRol) && $meCzId > 0) {
  $sqlS .= " AND cs.czId = ? ";
  $ts .= "i";
  $ps[] = $meCzId;
}

// Filtro adicional por zona seleccionado en la UI (opcional).
// - Para ADMIN_ZONA, siempre se fuerza la zona del usuario en sesión.
// - Para ADMIN_SEDE, no aplica (ya está restringido por csId).
$effectiveCzId = 0;
if (is_admin_zona($meRol) && $meCzId > 0) {
  $effectiveCzId = $meCzId;
} elseif (!is_admin_sede($meRol) && $filterCzId > 0) {
  $effectiveCzId = $filterCzId;
}

if ($effectiveCzId > 0 && !is_admin_sede($meRol)) {
  $sqlS .= " AND cs.czId = ? ";
  $ts .= "i";
  $ps[] = $effectiveCzId;
}

$sqlS .= " ORDER BY cs.csNombre";


$stmt = $conectar->prepare($sqlS);
if (!$stmt) jexit(['success'=>false,'error'=>'SQL prepare error (sedes): '.$conectar->error]);
$stmt->bind_param($ts, ...$ps);
$stmt->execute();
$rs = $stmt->get_result();
$sedes = [];
while ($r = $rs->fetch_assoc()) {
  $sedes[] = [
    'csId' => (int)$r['csId'],
    'csNombre' => $r['csNombre'],
    'czId' => (int)$r['czId'],
    'clId' => (int)$r['clId'],
    'clNombre' => $r['clNombre'],
  ];
}
$stmt->close();

jexit([
  'success' => true,
  'usuario' => [
    'usId' => (int)$user['usId'],
    'clId' => (int)$user['clId'],
    'nombre' => (string)$user['nombre'],
    'apaterno' => (string)$user['apaterno'],
    'amaterno' => (string)$user['amaterno'],
    'correo' => (string)$user['correo'],
    'telefono' => (string)$user['telefono'],
    'username' => (string)$user['username'],
    'estatus' => (string)$user['estatus'],
    'rolSistema' => (string)$user['rolSistema'],

    'rol' => (string)$user['rol'],      // <-- Flutter usa user['rol']
    'czId' => (int)$user['czId'],
    'csId' => (int)$user['csId'],

    'sedeNombre' => (string)$user['sedeNombre'],
    'zonaNombre' => (string)$user['zonaNombre'],
    'avatarUrl' => (string)$user['avatarUrl'],
  ],
  'listas' => [
    'roles' => $roles,
    'zonas' => $zonas,
    'sedes' => $sedes,
  ],
  'scope' => [
    'rol' => $meRol,
    'clId' => $meClId,
    'czId' => $meCzId,
    'csId' => $meCsId,
  ]
]);
