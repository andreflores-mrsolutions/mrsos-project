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

$stmt = $conectar->prepare("
  SELECT 
    u.*,
    ucr.ucrRol   AS ucrRol,
    ucr.clId     AS ucrClId,
    ucr.czId     AS ucrCzId,
    ucr.csId     AS ucrCsId
  FROM usuarios u
  LEFT JOIN usuario_cliente_rol ucr 
    ON ucr.usId = u.usId
   AND ucr.ucrEstatus = 'Activo'
  WHERE u.usId = ?
  ORDER BY ucr.ucrId DESC
  LIMIT 1
");

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


$needsOnboarding = false;


/* ========== 1) Forzar cambio de contraseña ========== */
if ($user->usEstatus === 'NewPass') {
    $needsOnboarding = true;
    $_SESSION['usId']             = (int)$user->usId;
    $_SESSION['usRol']            = $user->usRol;
    $_SESSION['forzarCambioPass'] = true;
    $_SESSION['usEstatus']        = $user->usEstatus;

    echo json_encode([
        'success'          => true,
        'forceChangePass'  => true,
        'onboardingRequired' => true, // 👈 forzamos wizard
        'message'          => 'Debes actualizar tu contraseña',
        'user' => [
            'usId'       => $user->usId,
            'usNombre'   => $user->usNombre,
            'usAPaterno' => $user->usAPaterno,
            'usAMaterno' => $user->usAMaterno,
            'usCorreo'   => $user->usCorreo,
            'usTelefono' => $user->usTelefono,
            'usUsername' => $user->usUsername,
            'usImagen'   => $user->usImagen,
            'usRol'      => $user->usRol,
            'usEstatus'  => $user->usEstatus,
            'usConfirmado' => $user->usConfirmado,
        ]
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
$_SESSION['usNotifInApp']     = $user->usNotifInApp;
$_SESSION['usNotifMail']      = $user->usNotifMail;
$_SESSION['usNotifTicketCambio'] = $user->usNotifTicketCambio;
$_SESSION['usNotifMeet']      = $user->usNotifMeet;
$_SESSION['usNotifVisita']    = $user->usNotifVisita;
$_SESSION['usNotifFolio']     = $user->usNotifFolio;
$_SESSION['ucrRol']  = $user->ucrRol ?? null;
$_SESSION['ucrClId'] = $user->ucrClId ?? null;
$_SESSION['ucrCzId'] = $user->ucrCzId ?? null;
$_SESSION['ucrCsId'] = $user->ucrCsId ?? null;


echo json_encode([
    'success'         => true,
    'forceChangePass' => false,
    'onboardingRequired' => $needsOnboarding,
    'user' => [
        'usId'        => $user->usId,
        'usNombre'    => $user->usNombre,
        'usAPaterno'  => $user->usAPaterno,
        'usAMaterno'  => $user->usAMaterno,
        'usCorreo'    => $user->usCorreo,
        'usTelefono'  => $user->usTelefono,
        'usUsername'  => $user->usUsername,
        'usImagen'    => $user->usImagen,
        'usRol'       => $user->usRol,
        'usEstatus'   => $user->usEstatus,
        'ucrRol' => $user->ucrRol ?? null,
        'czId'   => $user->ucrCzId ?? null,
        'csId'   => $user->ucrCsId ?? null,
        'ucrClId' => $user->ucrClId ?? null,
        'usNotificaciones' => $user->usNotificaciones,
        'usConfirmado' => $user->usConfirmado,
        'usTokenTelefono' => $user->usTokenTelefono,
        'clId'        => $user->clId,
        'usNotifInApp' => $user->usNotifInApp,
        'usNotifMail' => $user->usNotifMail,
        'usNotifTicketCambio' => $user->usNotifTicketCambio,
        'usNotifMeet' => $user->usNotifMeet,
        'usNotifVisita' => $user->usNotifVisita,
        'usNotifFolio' => $user->usNotifFolio,
    ]
]);
