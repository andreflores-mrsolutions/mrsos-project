<?php

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/csrf.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

$usId   = trim((string)($_POST['usId'] ?? ''));
$usPass = (string)($_POST['usPass'] ?? '');

if ($usId === '' || $usPass === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Faltan datos'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Obtiene el alcance del usuario cliente desde usuario_cliente_rol
 * usando la estructura real de tu BD:
 * - ucrId
 * - usId
 * - clId
 * - czId
 * - csId
 * - ucrRol
 * - ucrEstatus
 */
function obtenerScopeCliente(PDO $pdo, int $usId): array
{
    $scope = [
        'ucrId'   => null,
        'ucrRol'  => null,
        'clId'    => null,
        'czId'    => null,
        'csId'    => null,
    ];

    $sql = "
        SELECT
            ucrId,
            usId,
            clId,
            czId,
            csId,
            ucrRol,
            ucrEstatus
        FROM usuario_cliente_rol
        WHERE usId = ?
          AND ucrEstatus = 'Activo'
        ORDER BY ucrId DESC
        LIMIT 1
    ";

    $st = $pdo->prepare($sql);
    $st->execute([$usId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        respond([
            'success' => false,
            'message' => 'El usuario cliente no tiene alcance asignado.'
        ], 403);
    }

    $scope['ucrId']  = isset($row['ucrId']) ? (int)$row['ucrId'] : null;
    $scope['ucrRol'] = (string)($row['ucrRol'] ?? '');
    $scope['clId']   = isset($row['clId']) && $row['clId'] !== null ? (int)$row['clId'] : null;
    $scope['czId']   = isset($row['czId']) && $row['czId'] !== null ? (int)$row['czId'] : null;
    $scope['csId']   = isset($row['csId']) && $row['csId'] !== null ? (int)$row['csId'] : null;

    return $scope;
}

try {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT
            usId,
            usNombre,
            usAPaterno,
            usAMaterno,
            usRol,
            usCorreo,
            usPass,
            usTelefono,
            usTokenTelefono,
            usImagen,
            usNotificaciones,
            clId,
            usConfirmado,
            usEstatus,
            usUsername
        FROM usuarios
        WHERE usId = ?
        LIMIT 1
    ");
    $stmt->execute([$usId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        respond([
            'success' => false,
            'message' => 'Usuario o contraseña incorrectos'
        ], 401);
    }

    $estatus = (string)($user['usEstatus'] ?? '');
    if ($estatus === 'Inactivo' || $estatus === 'Error') {
        respond([
            'success' => false,
            'message' => 'Cuenta inactiva, contacta a soporte.'
        ], 403);
    }

    $hash = (string)($user['usPass'] ?? '');
    if ($hash === '' || !password_verify($usPass, $hash)) {
        respond([
            'success' => false,
            'message' => 'Usuario o contraseña incorrectos'
        ], 401);
    }

    session_regenerate_id(true);
    $csrf = csrf_token();

    $rol = (string)($user['usRol'] ?? '');

    $clienteScope = [
        'ucrId'  => null,
        'ucrRol' => null,
        'clId'   => isset($user['clId']) && $user['clId'] !== null ? (int)$user['clId'] : null,
        'czId'   => null,
        'csId'   => null,
    ];

    if ($rol === 'CLI') {
        $clienteScope = obtenerScopeCliente($pdo, (int)$user['usId']);
    }

    /* ========== 1) Forzar cambio de contraseña ========== */
    if ($estatus === 'NewPass') {
        $_SESSION['usId']             = (int)$user['usId'];
        $_SESSION['usRol']            = $rol;
        $_SESSION['forzarCambioPass'] = true;
        $_SESSION['usEstatus']        = $estatus;

        $_SESSION['clId']   = $clienteScope['clId'];
        $_SESSION['ucrId']  = $clienteScope['ucrId'];
        $_SESSION['ucrRol'] = $clienteScope['ucrRol'];
        $_SESSION['czId']   = $clienteScope['czId'];
        $_SESSION['csId']   = $clienteScope['csId'];

        respond([
            'success'         => true,
            'forceChangePass' => true,
            'message'         => 'Debes actualizar tu contraseña',
            'csrfToken'       => $csrf
        ]);
    }

    /* ========== 2) Login normal ========== */
    $_SESSION['usId']             = (int)$user['usId'];
    $_SESSION['usNombre']         = (string)($user['usNombre'] ?? '');
    $_SESSION['usAPaterno']       = (string)($user['usAPaterno'] ?? '');
    $_SESSION['usAMaterno']       = (string)($user['usAMaterno'] ?? '');
    $_SESSION['usRol']            = $rol;
    $_SESSION['usCorreo']         = (string)($user['usCorreo'] ?? '');
    $_SESSION['usTelefono']       = (string)($user['usTelefono'] ?? '');
    $_SESSION['usTokenTelefono']  = (string)($user['usTokenTelefono'] ?? '');
    $_SESSION['usImagen']         = (string)($user['usImagen'] ?? '');
    $_SESSION['usNotificaciones'] = (string)($user['usNotificaciones'] ?? '');
    $_SESSION['clId']             = $clienteScope['clId'];
    $_SESSION['usConfirmado']     = (string)($user['usConfirmado'] ?? '');
    $_SESSION['usEstatus']        = $estatus;
    $_SESSION['usUsername']       = (string)($user['usUsername'] ?? '');

    // alcance cliente real
    $_SESSION['ucrId']            = $clienteScope['ucrId'];
    $_SESSION['ucrRol']           = $clienteScope['ucrRol'];
    $_SESSION['czId']             = $clienteScope['czId'];
    $_SESSION['csId']             = $clienteScope['csId'];

    respond([
        'success'         => true,
        'forceChangePass' => false,
        'user'            => (string)($user['usNombre'] ?? ''),
        'rol'             => $rol,
        'csrfToken'       => $csrf
    ]);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => 'Error interno',
        'debug'   => $e->getMessage()
    ], 500);
}