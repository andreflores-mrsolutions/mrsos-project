<?php
// php/adm_sedes_list.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'conexion.php';
session_start();

$rolRaw     = trim($_SESSION['usRol'] ?? '');
$rol        = strtoupper($rolRaw);
$usId       = (int)($_SESSION['usId'] ?? 0);
$clIdSesion = (int)($_SESSION['clId'] ?? 0);

// Normalizamos grupos de roles internos MR (ajusta si usas otros)
$rolesMR = ['MRA', 'MRSA', 'MR', 'ADMIN', 'ADMINISTRADOR', 'SUPERADMIN'];

// Entrada por GET (cliente por id o por nombre)
$clIdGet       = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
$clienteNombre = isset($_GET['cliente']) ? trim($_GET['cliente']) : '';

// Helper: respuesta y salida
$ok   = function (array $data) {
  echo json_encode(['success' => true] + $data);
  exit;
};
$fail = function (string $msg, int $code = 400) {
  http_response_code($code);
  echo json_encode(['success' => false, 'error' => $msg]);
  exit;
};

// 1) Resolver clId objetivo
$clIdObjetivo = 0;

// 1.a) Si viene ?clId explícito
if ($clIdGet > 0) {
  $clIdObjetivo = $clIdGet;
}

// 1.b) Si viene ?cliente=Nombre exacto (e.g., Enel) -> lo resolvemos
if ($clIdObjetivo === 0 && $clienteNombre !== '') {
  $sql = "SELECT clId FROM clientes WHERE clNombre = ? LIMIT 1";
  if ($st = $conectar->prepare($sql)) {
    $st->bind_param('s', $clienteNombre);
    $st->execute();
    $st->bind_result($clIdTmp);
    if ($st->fetch()) $clIdObjetivo = (int)$clIdTmp;
    $st->close();
  }
  if ($clIdObjetivo === 0) $fail("Cliente no encontrado: {$clienteNombre}");
}

// 1.c) Si el usuario de sesión ya trae clId (cliente final) y NO es rol MR, forzamos su cliente
$esRolMR = in_array($rol, $rolesMR, true);
if (!$esRolMR && $clIdSesion > 0) {
  $clIdObjetivo = $clIdSesion;
}

// 1.d) Si seguimos sin clId, y es rol MR, podemos permitir “todas” (sin filtro) o exigir clId.
//     Por tu requerimiento “traer todas las sedes según el cliente”, exigimos clId claro:
if ($clIdObjetivo === 0 && $esRolMR) {
  $fail("Falta seleccionar cliente. Usa ?clId=ID o ?cliente=Nombre.");
}

// 2) Autorización básica
// - Cliente final: debe tener clIdSesion y solo puede ver SU clId
// - Rol AC (admin cliente ligado por sede_usuario) también restringido a sedes asignadas
if (!$esRolMR && $clIdSesion === 0) {
  $fail("No autorizado (sesión cliente inválida).", 401);
}
if (!$esRolMR && $clIdObjetivo !== $clIdSesion) {
  $fail("No autorizado para consultar otro cliente.", 403);
}

try {
  // 3) Caso especial: rol AC (admin cliente ligado a sedes por sede_usuario)
  //    Solo devuelve las sedes activas a las que esté vinculado y del cliente correcto.
  // 3) Caso: rol AC -> DEBE ver TODAS las sedes del cliente (no solo las asignadas)
  if ($rol === 'AC') {
    // Forzamos el cliente de la sesión para AC
    $clIdObjetivo = $clIdSesion ?: $clIdObjetivo;
    if ($clIdObjetivo === 0) {
      $fail("No hay cliente en sesión para AC.");
    }

    $sql = "SELECT csId, csNombre
          FROM cliente_sede
          WHERE clId = ?
          ORDER BY csNombre";
    $st = $conectar->prepare($sql);
    $st->bind_param('i', $clIdObjetivo);
    $st->execute();
    $res  = $st->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $st->close();

    $ok(['sedes' => $rows, 'clId' => $clIdObjetivo, 'filtro' => 'AC_todas_del_cliente']);
  }


  // 4) Resto de roles:
  //    - Cliente normal (UC/EC/Cliente, etc.) con clId forzado a su sesión -> todas las sedes activas de su cliente
  //    - Roles MR (MRA/MRSA/ADMIN/...) con clId objetivo -> todas las sedes activas de ese cliente
  $sql = "SELECT csId, csNombre
          FROM cliente_sede
          WHERE clId = ? AND csEstatus = 'Activo'
          ORDER BY cs.csEsPrincipal DESC, csNombre";
  $st = $conectar->prepare($sql);
  $st->bind_param('i', $clIdObjetivo);
  $st->execute();
  $res  = $st->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $st->close();

  $ok(['sedes' => $rows, 'clId' => $clIdObjetivo, 'filtro' => 'por_cliente']);
} catch (Throwable $e) {
  $fail('DB: ' . $e->getMessage(), 500);
}
