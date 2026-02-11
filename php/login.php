<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/csrf.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

$usId   = trim((string)($_POST["usId"] ?? ''));
$usPass = (string)($_POST["usPass"] ?? '');

if ($usId === '' || $usPass === '') {
    echo json_encode(['success' => false, 'message' => 'Faltan datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();

    // IMPORTANTE: selecciona solo lo que usas (menos superficie)
    $stmt = $pdo->prepare("
        SELECT
            usId, usNombre, usAPaterno, usAMaterno, usRol, usCorreo,
            usPass, usTelefono, usTokenTelefono, usImagen, usNotificaciones,
            clId, usConfirmado, usEstatus, usUsername
        FROM usuarios
        WHERE usId = ?
        LIMIT 1
    ");
    $stmt->execute([$usId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Estados
    $estatus = (string)($user['usEstatus'] ?? '');
    if ($estatus === 'Inactivo' || $estatus === 'Error') {
        echo json_encode(['success' => false, 'message' => 'Cuenta inactiva, contacta a soporte.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Password
    $hash = (string)($user['usPass'] ?? '');
    if ($hash === '' || !password_verify($usPass, $hash)) {
        echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ✅ Regenerar sesión (anti session fixation)
    session_regenerate_id(true);
    $csrf = csrf_token();


    /* ========== 1) Forzar cambio de contraseña ========== */
    if ($estatus === 'NewPass') {
        $_SESSION['usId']             = (int)$user['usId'];
        $_SESSION['usRol']            = (string)$user['usRol'];
        $_SESSION['forzarCambioPass'] = true;
        $_SESSION['usEstatus']        = $estatus;

        echo json_encode([
            'success'          => true,
            'forceChangePass'  => true,
            'message'          => 'Debes actualizar tu contraseña',
            'csrfToken'       => $csrf
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ========== 2) Login normal ========== */
    $_SESSION['usId']             = (int)$user['usId'];
    $_SESSION['usNombre']         = (string)$user['usNombre'];
    $_SESSION['usAPaterno']       = (string)$user['usAPaterno'];
    $_SESSION['usAMaterno']       = (string)$user['usAMaterno'];
    $_SESSION['usRol']            = (string)$user['usRol'];
    $_SESSION['usCorreo']         = (string)$user['usCorreo'];
    $_SESSION['usTelefono']       = (string)$user['usTelefono'];
    $_SESSION['usTokenTelefono']  = (string)$user['usTokenTelefono'];
    $_SESSION['usImagen']         = (string)$user['usImagen'];
    $_SESSION['usNotificaciones'] = (string)$user['usNotificaciones'];
    $_SESSION['clId']             = isset($user['clId']) ? (int)$user['clId'] : null;
    $_SESSION['usConfirmado']     = (string)$user['usConfirmado'];
    $_SESSION['usEstatus']        = $estatus;
    $_SESSION['usUsername']       = (string)$user['usUsername'];

    echo json_encode([
        'success'         => true,
        'forceChangePass' => false,
        'user'            => (string)$user['usNombre'],
        'csrfToken'       => $csrf
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno'], JSON_UNESCAPED_UNICODE);
}
