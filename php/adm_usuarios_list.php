<?php
// php/adm_usuarios_list.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once 'conexion.php';
session_start();

/* --------- Rol / sesión --------- */
$rolSesion = strtoupper(trim($_SESSION['usRol'] ?? ''));
if (in_array($rolSesion, ['MRA', 'MRSA'], true)) $rolSesion = 'MR-ADMIN'; // normaliza
$usIdSesion = (int)($_SESSION['usId'] ?? 0);
$clIdSesion = (int)($_SESSION['clId'] ?? 0);

/* Solo AC o MR-ADMIN pueden usar este listado */
if (!in_array($rolSesion, ['AC', 'MR-ADMIN'], true)) {
  echo json_encode(['success' => false, 'error' => 'No autorizado']);
  exit;
}

/* --------- Parámetros (POST JSON) --------- */
$body    = json_decode(file_get_contents('php://input'), true) ?: [];
$page    = max(1, (int)($body['page']    ?? 1));
$perPage = max(1, min(30, (int)($body['perPage'] ?? 30)));
$q       = trim($body['q'] ?? '');
$csId    = (int)($body['csId'] ?? 0);           // sede
$acId    = (int)($body['acId'] ?? 0);           // filtrar por sedes del AC elegido
$rolFil  = strtoupper(trim($body['rol'] ?? '')); // AC/UC/EC (opcional)
$clId    = 0;

/* Determina cliente a listar */
if ($rolSesion === 'MR-ADMIN') {
  // MR-ADMIN debe indicar a qué cliente mirar (o se usa el de sesión si lo hay)
  $clId = (int)($body['clId'] ?? 0);
  if (!$clId && $clIdSesion) $clId = $clIdSesion;
  if (!$clId) {
    echo json_encode(['success' => false, 'error' => 'Falta clId']);
    exit;
  }
} else {
  // AC obligado a su cliente
  $clId = $clIdSesion;
  if (!$clId) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
  }
}

/* --------- WHERE base (scope por cliente) ---------
   Usuarios cuyo u.clId sea el cliente o, si u.clId es NULL,
   aquellos vinculados por sedes cuyo cs.clId sea el cliente. */
$where  = [];
$types  = "";
$params = [];

$where[] = "(u.clId = ? OR (u.clId IS NULL AND cs.clId = ?))";
$types  .= "ii";
$params[] = $clId;
$params[] = $clId;

/* Filtro por sede específica (opcional) */
if ($csId > 0) {
  $where[] = "cs.csId = ?";
  $types  .= "i";
  $params[] = $csId;
}

/* Filtro por rol (AC/UC/EC) opcional */
if (in_array($rolFil, ['AC', 'UC', 'EC'], true)) {
  $where[] = "u.usRol = ?";
  $types  .= "s";
  $params[] = $rolFil;
}

/* Búsqueda libre */
if ($q !== '') {
  $where[] = "(CONCAT_WS(' ', u.usNombre, u.usAPaterno, u.usAMaterno) LIKE ?
            OR  u.usUsername LIKE ?
            OR  u.usCorreo   LIKE ?)";
  $types  .= "sss";
  $like    = "%$q%";
  array_push($params, $like, $like, $like);
}

/* “Usuarios que tiene un AC”: sedes del AC (siempre dentro del mismo cliente) */
if ($acId > 0) {
  $sqlS = "
    SELECT su.csId
    FROM sede_usuario su
    INNER JOIN cliente_sede cs2 ON cs2.csId = su.csId
    WHERE su.usId = ? AND cs2.clId = ? AND usEstatus = 'Activo'
  ";
  $stS = $conectar->prepare($sqlS);
  $stS->bind_param("ii", $acId, $clId);
  $stS->execute();
  $rsS = $stS->get_result();
  $ids = [];
  while ($r = $rsS->fetch_assoc()) $ids[] = (int)$r['csId'];
  $stS->close();

  if (count($ids)) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $where[] = "cs.csId IN ($in)";
    $types  .= str_repeat('i', count($ids));
    array_push($params, ...$ids);
  } else {
    // Si el AC no tiene sedes para este cliente, no regresamos nada
    $where[] = "1=0";
  }
}

$whereSQL = "WHERE usEstatus = 'Activo' AND usId != '$usIdSesion' AND " . implode(" AND ", $where);

/* --------- COUNT total (para paginación) --------- */
$sqlCount = "
  SELECT COUNT(DISTINCT u.usId) AS total
  FROM usuarios u
  LEFT JOIN sede_usuario su ON su.usId = u.usId
  LEFT JOIN cliente_sede cs ON cs.csId = su.csId
  $whereSQL
";
$stC = $conectar->prepare($sqlCount);
if ($types !== "") $stC->bind_param($types, ...$params);
$stC->execute();
$total = (int)($stC->get_result()->fetch_assoc()['total'] ?? 0);
$stC->close();

$offset = ($page - 1) * $perPage;

/* --------- QUERY datos ---------
   Agrupo por usuario y concateno sedes (si tiene varias). */
$sql = "
  SELECT
    u.usId, u.usUsername, u.usNombre, u.usAPaterno, u.usAMaterno,
    u.usCorreo, u.usTelefono, u.usRol,
    GROUP_CONCAT(DISTINCT cs.csNombre ORDER BY cs.csNombre SEPARATOR ', ') AS csNombres
  FROM usuarios u
  LEFT JOIN sede_usuario su ON su.usId = u.usId
  LEFT JOIN cliente_sede cs ON cs.csId = su.csId
  $whereSQL
  GROUP BY u.usId
  ORDER BY u.usNombre, u.usAPaterno 
  LIMIT ? OFFSET ?
";
$st = $conectar->prepare($sql);
$types2  = $types . "ii";
$params2 = $params;
$params2[] = $perPage;
$params2[] = $offset;
$st->bind_param($types2, ...$params2);
$st->execute();
$res = $st->get_result();

/* Resolver imagen del usuario por extensión */
$exts = ['webp', 'jpg', 'jpeg', 'png', 'gif'];
$rows = [];
while ($r = $res->fetch_assoc()) {
  $img = null;
  foreach ($exts as $e) {
    $abs = __DIR__ . "/../img/Usuario/{$r['usUsername']}.$e";
    if (is_file($abs)) {
      $img = "../img/Usuario/{$r['usUsername']}.$e";
      break;
    }
  }
  $r['imgUrl']   = $img ?: "../img/Usuario/user.webp";
  $r['csNombre'] = $r['csNombres'] ?: '—';
  unset($r['csNombres']);
  $rows[] = $r;
}
$st->close();

echo json_encode([
  'success'  => true,
  'page'     => $page,
  'perPage'  => $perPage,
  'total'    => $total,
  'users'    => $rows
]);
