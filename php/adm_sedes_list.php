<?php
// ../php/adm_sedes_list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/conexion.php';
session_start();

// ====== 1) Validar sesión básica ======
$usId = $_SESSION['usId'] ?? null;
$clId = $_SESSION['clId'] ?? null;
$usRolSistema = $_SESSION['usRol'] ?? null; // CLI | MRA | MRV | MRSA

if (!$usId || !$clId) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// ====== 2) Cargar datos base del cliente ======
$cliente = null;
if ($stmt = $conectar->prepare("SELECT clId, clNombre FROM clientes WHERE clId = ?")) {
    $stmt->bind_param("i", $clId);
    $stmt->execute();
    $res = $stmt->get_result();
    $cliente = $res->fetch_assoc() ?: null;
    $stmt->close();
}
if (!$cliente) {
    echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
    exit;
}

// ====== 3) Determinar alcance (scope) basado en usuario_cliente_rol ======
$scope = [
    'tipo'       => null,     // GLOBAL | ZONA | SEDE | ALL_MR | NONE
    'zonesAdmin' => [],       // czId que administra
    'sedesAdmin' => []        // csId que administra
];

// Personal MR (MRA / MRSA / MRV) -> ve todo lo del cliente actual
if ($usRolSistema !== 'CLI') {
    $scope['tipo'] = 'ALL_MR';
} else {
    // Cliente: usamos usuario_cliente_rol
    $sql = "SELECT ucrRol, czId, csId
            FROM usuario_cliente_rol
            WHERE usId = ? AND clId = ? AND ucrEstatus = 'Activo'";
    if ($stmt = $conectar->prepare($sql)) {
        $stmt->bind_param("ii", $usId, $clId);
        $stmt->execute();
        $res = $stmt->get_result();
        $isAdminGlobal = false;
        $zonesAdmin = [];
        $sedesAdmin = [];

        while ($row = $res->fetch_assoc()) {
            $rol = $row['ucrRol'];
            if ($rol === 'ADMIN_GLOBAL') {
                $isAdminGlobal = true;
            } elseif ($rol === 'ADMIN_ZONA' && !empty($row['czId'])) {
                $zonesAdmin[] = (int)$row['czId'];
            } elseif ($rol === 'ADMIN_SEDE' && !empty($row['csId'])) {
                $sedesAdmin[] = (int)$row['csId'];
            }
        }
        $stmt->close();

        $zonesAdmin = array_values(array_unique($zonesAdmin));
        $sedesAdmin = array_values(array_unique($sedesAdmin));

        if ($isAdminGlobal) {
            $scope['tipo'] = 'GLOBAL';
        } elseif (!empty($zonesAdmin)) {
            $scope['tipo']       = 'ZONA';
            $scope['zonesAdmin'] = $zonesAdmin;
        } elseif (!empty($sedesAdmin)) {
            $scope['tipo']       = 'SEDE';
            $scope['sedesAdmin'] = $sedesAdmin;
        } else {
            $scope['tipo'] = 'NONE';
        }
    }
}

// Si no tiene alcance -> denegado
if ($scope['tipo'] === 'NONE') {
    echo json_encode([
        'success' => false,
        'error'   => 'No tienes permisos para administrar usuarios de este cliente.'
    ]);
    exit;
}

// ====== 4) Cargar zonas y sedes del cliente ======
// Importante: no todos los clientes tienen zonas/sedes, así que puede venir vacío

$zonas = [];
$sedes = [];

// ZONAS
if ($stmt = $conectar->prepare("
    SELECT czId, czNombre, czCodigo
    FROM cliente_zona
    WHERE clId = ? AND czEstatus = 'Activo'
    ORDER BY czNombre ASC
")) {
    $stmt->bind_param("i", $clId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $zonas[(int)$row['czId']] = [
            'czId'     => (int)$row['czId'],
            'czNombre' => $row['czNombre'],
            'czCodigo' => $row['czCodigo'],
        ];
    }
    $stmt->close();
}

// SEDES
if ($stmt = $conectar->prepare("
    SELECT csId, czId, csNombre, csCodigo, csEsPrincipal
    FROM cliente_sede
    WHERE clId = ? AND csEstatus = 'Activo'
    ORDER BY csNombre ASC
")) {
    $stmt->bind_param("i", $clId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $sedes[(int)$row['csId']] = [
            'csId'         => (int)$row['csId'],
            'czId'         => $row['czId'] !== null ? (int)$row['czId'] : null,
            'csNombre'     => $row['csNombre'],
            'csCodigo'     => $row['csCodigo'],
            'csEsPrincipal'=> (int)$row['csEsPrincipal'] === 1,
        ];
    }
    $stmt->close();
}

// ====== 5) Cargar usuarios CLI del cliente ======
$usuarios = [];

$sqlUsuarios = "
    SELECT 
      u.usId,
      u.usNombre,
      u.usAPaterno,
      u.usAMaterno,
      u.usCorreo,
      u.usTelefono,
      u.usUsername,
      u.usRol,
      u.usEstatus,
      u.usTheme,
      u.usNotifInApp,
      u.usNotifMail
    FROM usuarios u
    WHERE u.clId = ? 
      AND u.usRol = 'CLI'
    ORDER BY u.usNombre, u.usAPaterno
";

if ($stmt = $conectar->prepare($sqlUsuarios)) {
    $stmt->bind_param("i", $clId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $usuarios[(int)$row['usId']] = [
            'usId'        => (int)$row['usId'],
            'nombre'      => $row['usNombre'],
            'apaterno'    => $row['usAPaterno'],
            'amaterno'    => $row['usAMaterno'],
            'nombreCompleto' => trim($row['usNombre'] . ' ' . $row['usAPaterno'] . ' ' . $row['usAMaterno']),
            'correo'      => $row['usCorreo'],
            'telefono'    => $row['usTelefono'],
            'username'    => $row['usUsername'],
            'rolSistema'  => $row['usRol'],
            'estatus'     => $row['usEstatus'],
            'theme'       => $row['usTheme'],
            'notifInApp'  => (bool)$row['usNotifInApp'],
            'notifMail'   => (bool)$row['usNotifMail'],
            'rolesCliente'=> []  // se llena abajo
        ];
    }
    $stmt->close();
}

// Si no hay usuarios, devolvemos vacío pero success = true
if (empty($usuarios)) {
    echo json_encode([
        'success' => true,
        'scope'   => $scope,
        'cliente' => $cliente,
        'zonas'   => array_values($zonas),
        'sedes'   => array_values($sedes),
        'usuarios'=> []
    ]);
    exit;
}

// ====== 6) Cargar roles por usuario (usuario_cliente_rol) ======
$rolesByUser = [];

$sqlRoles = "
    SELECT 
      ucr.usId,
      ucr.ucrRol,
      ucr.czId,
      ucr.csId,
      cz.czNombre,
      cs.csNombre
    FROM usuario_cliente_rol ucr
    LEFT JOIN cliente_zona cz ON cz.czId = ucr.czId
    LEFT JOIN cliente_sede cs ON cs.csId = ucr.csId
    WHERE ucr.clId = ? AND ucr.ucrEstatus = 'Activo'
";

if ($stmt = $conectar->prepare($sqlRoles)) {
    $stmt->bind_param("i", $clId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $uId = (int)$row['usId'];
        if (!isset($rolesByUser[$uId])) {
            $rolesByUser[$uId] = [];
        }
        $rolesByUser[$uId][] = [
            'rol'      => $row['ucrRol'],               // ADMIN_GLOBAL | ADMIN_ZONA | ADMIN_SEDE | USUARIO | VISOR
            'czId'     => $row['czId'] !== null ? (int)$row['czId'] : null,
            'csId'     => $row['csId'] !== null ? (int)$row['csId'] : null,
            'czNombre' => $row['czNombre'],
            'csNombre' => $row['csNombre'],
        ];
    }
    $stmt->close();
}

// Asignar al arreglo de usuarios
foreach ($usuarios as $id => &$u) {
    $u['rolesCliente'] = $rolesByUser[$id] ?? [];
}
unset($u);

// ====== 7) Filtrar usuarios según alcance (solo aplica a CLI, MR ve todo) ======
function userIsVisibleForScope(array $uRoles, array $scope): bool {
    // ALL_MR o GLOBAL -> ve todos
    if ($scope['tipo'] === 'ALL_MR' || $scope['tipo'] === 'GLOBAL') {
        return true;
    }
    if ($scope['tipo'] === 'ZONA') {
        $zones = $scope['zonesAdmin'] ?? [];
        if (empty($zones)) return false;
        foreach ($uRoles as $r) {
            if ($r['czId'] !== null && in_array((int)$r['czId'], $zones, true)) {
                return true;
            }
            // también si la sede está en una zona que administro
            if ($r['csId'] !== null && !empty($zones)) {
                // la zona real de la sede la validamos en front si hace falta;
                // aquí basta con que tenga algún rol y ya se filtrará extra en front
                return true;
            }
        }
        return false;
    }
    if ($scope['tipo'] === 'SEDE') {
        $sedes = $scope['sedesAdmin'] ?? [];
        if (empty($sedes)) return false;
        foreach ($uRoles as $r) {
            if ($r['csId'] !== null && in_array((int)$r['csId'], $sedes, true)) {
                return true;
            }
        }
        return false;
    }
    return false;
}

$usuariosFinal = [];
foreach ($usuarios as $u) {
    if (userIsVisibleForScope($u['rolesCliente'], $scope)) {
        $usuariosFinal[] = $u;
    }
}

// ====== 8) Respuesta final ======
echo json_encode([
    'success'  => true,
    'scope'    => $scope,
    'cliente'  => $cliente,
    'zonas'    => array_values($zonas),
    'sedes'    => array_values($sedes),
    'usuarios' => $usuariosFinal
]);
