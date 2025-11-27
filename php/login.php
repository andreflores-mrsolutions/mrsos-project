<?php
include "conexion.php";
session_start();

header('Content-Type: application/json; charset=utf-8');

$usId   = $_POST["usId"] ?? '';
$usPass = $_POST["usPass"] ?? '';

if ($usId === '' || $usPass === '') {
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

$stmt = $conectar->prepare("SELECT * FROM usuarios WHERE usId = ?");
$stmt->bind_param("s", $usId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos']);
    exit;
}

$user = $result->fetch_object();

// OJO: en la BD no existe 'Eliminado', tus estados son Activo/Inactivo/NewPass/Error :contentReference[oaicite:0]{index=0}
if ($user->usEstatus === 'Inactivo' || $user->usEstatus === 'Error') {
    echo json_encode(['success' => false, 'message' => 'Cuenta inactiva, contacta a soporte.']);
    exit;
}

// Validar la contraseña
if (!password_verify($usPass, $user->usPass)) {
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos']);
    exit;
}

/* ========== 1) Forzar cambio de contraseña ========== */
if ($user->usEstatus === 'NewPass') {
    // Sesión mínima para identificar al usuario en la pantalla de cambio
    $_SESSION['usId']              = (int)$user->usId;
    $_SESSION['usRol']             = $user->usRol;
    $_SESSION['forzarCambioPass']  = true;

    echo json_encode([
        'success'          => true,
        'forceChangePass'  => true,
        'message'          => 'Debes actualizar tu contraseña'
    ]);
    exit;
}

/* ========== 2) Login normal ========== */
$_SESSION['usId']             = $user->usId;
$_SESSION['usNombre']         = $user->usNombre;
$_SESSION['usAPaterno']       = $user->usAPaterno;
$_SESSION['usAMaterno']       = $user->usAMaterno;
$_SESSION['usRol']            = $user->usRol;
$_SESSION['usCorreo']         = $user->usCorreo;
$_SESSION['usPass']           = $user->usPass;
$_SESSION['usTelefono']       = $user->usTelefono;
$_SESSION['usTokenTelefono']  = $user->usTokenTelefono;
$_SESSION['usImagen']         = $user->usImagen;
$_SESSION['usNotificaciones'] = $user->usNotificaciones;
$_SESSION['clId']             = $user->clId;
$_SESSION['usConfirmado']     = $user->usConfirmado;
$_SESSION['usEstatus']        = $user->usEstatus;
$_SESSION['usUsername']       = $user->usUsername;

echo json_encode([
    'success'         => true,
    'forceChangePass' => false,
    'user'            => $user->usNombre
]);
