<?php
include "conexion.php";
session_start();

$usId = $_POST["usId"];
$usPass = $_POST["usPass"];

$stmt = $conectar->prepare("SELECT * FROM usuarios WHERE usId = ? AND usEstatus = 'Activo'");
$stmt->bind_param("s", $usId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "0";
    exit;
}

$user = $result->fetch_object();

// Validar la contraseña
if (!password_verify($usPass, $user->usPass)) {
    echo "0";
    exit;
}
// $row = datos del usuario
if ($row['usEstatus'] === 'NewPass') {
  // deja sesión mínima y redirige a “cambio de contraseña”
  $_SESSION['usId']   = (int)$row['usId'];
  $_SESSION['usRol']  = $row['usRol'];
  $_SESSION['forzarCambioPass'] = true;
  header('Location: cambiar_password.php');
  exit;
}

// Si todo está bien: iniciar sesión
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

echo json_encode(["success" => true, "user" => $user->usNombre]);

