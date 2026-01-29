<?php
session_start();
include 'conexion.php';

if (empty($_SESSION['usId'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

$mustChangePass = !empty($_SESSION['forzarCambioPass']);


$usId       = (int)($_POST['usId'] ?? 0);
$pass1      = $_POST['pass1'] ?? '';
$pass2      = $_POST['pass2'] ?? '';
$usNombre   = trim($_POST['usNombre'] ?? '');
$usAPaterno = trim($_POST['usAPaterno'] ?? '');
$usAMaterno = trim($_POST['usAMaterno'] ?? '');
$usCorreo   = trim($_POST['usCorreo'] ?? '');
$usTelefono = trim($_POST['usTelefono'] ?? '');
$usUsername = trim($_POST['usUsername'] ?? '');


/* === Validaciones básicas === */
$changingPass = ($pass1 !== '' || $pass2 !== '');

if ($mustChangePass && !$changingPass) {
    echo json_encode(['success' => false, 'message' => 'Debes capturar la nueva contraseña']);
    exit;
}

if ($changingPass) {
    if ($pass1 === '' || $pass2 === '' || $pass1 !== $pass2) {
        echo json_encode(['success' => false, 'message' => 'La contraseña no coincide o está vacía']);
    }
}

if (
    strlen($pass1) < 8 ||
    !preg_match('/[A-Z]/', $pass1) ||
    !preg_match('/[a-z]/', $pass1) ||
    !preg_match('/[0-9]/', $pass1) ||
    !preg_match('/[!@#$%^&*()_\-+={}[\]:;"\'<>,.?\/~`\\\\|]/', $pass1)
) {
    echo json_encode(['success' => false, 'message' => 'La contraseña no cumple con los requisitos mínimos.']);
}
if ($usNombre === '' || $usAPaterno === '' || $usCorreo === '') {
    echo json_encode(['success' => false, 'message' => 'Nombre, apellido y correo son obligatorios.']);
}
if (!filter_var($usCorreo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Correo no válido.']);
}
if ($usUsername === '') {
    echo json_encode(['success' => false, 'message' => 'Nombre de usuario requerido.']);
}
if (!preg_match('/^[A-Za-z0-9_-]{3,20}$/', $usUsername)) {
    echo json_encode(['success' => false, 'message' => 'Nombre de usuario con formato inválido.']);
}
$malas = ['puta', 'puto', 'mierda', 'pendejo', 'pendeja', 'idiota'];
$lower = mb_strtolower($usUsername, 'UTF-8');
foreach ($malas as $m) {
    if (str_contains($lower, $m)) {
        echo json_encode(['success' => false, 'message' => 'Nombre de usuario no permitido.']);
    }
}

/* === Verificar que el username sea único === */
$stmt = $conectar->prepare("SELECT usId FROM usuarios WHERE usUsername = ? AND usId <> ?");
$stmt->bind_param("si", $usUsername, $usId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya está en uso.']);
}

/* === Manejo de avatar (opcional) === */
$avatarFilename = null;
if (!empty($_FILES['usAvatar']['name'])) {
    $file     = $_FILES['usAvatar'];
    $tmpName  = $file['tmp_name'];
    $size     = $file['size'];
    $error    = $file['error'];
    $name     = $file['name'];

    if ($error === UPLOAD_ERR_OK && $tmpName) {
        if ($size > 2 * 1024 * 1024) { // 2MB
            echo json_encode(['success' => false, 'message' => 'La imagen de perfil no debe superar los 2MB.']);
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Formato de imagen no permitido. Usa JPG, PNG o WEBP.']);
        }

        // Guardar con el username final
        $uploadDir = __DIR__ . '/../img/Usuario/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $finalName = $usUsername . '.' . $ext;
        $destPath  = $uploadDir . $finalName;

        if (!move_uploaded_file($tmpName, $destPath)) {
            echo json_encode(['success' => false, 'message' => 'No se pudo guardar la imagen de perfil.']);
        }

        $avatarFilename = $finalName;
    }
}

/* === Actualizar usuario === */
$hash = password_hash($pass1, PASSWORD_DEFAULT);

if ($avatarFilename) {
    $stmt = $conectar->prepare("
        UPDATE usuarios
        SET usPass = ?,
            usNombre = ?,
            usAPaterno = ?,
            usAMaterno = ?,
            usCorreo = ?,
            usTelefono = ?,
            usUsername = ?,
            usImagen = ?,
            usEstatus = 'Activo',
            usConfirmado = 'Si'
        WHERE usId = ?
    ");
    $stmt->bind_param(
        "ssssssssi",
        $hash,
        $usNombre,
        $usAPaterno,
        $usAMaterno,
        $usCorreo,
        $usTelefono,
        $usUsername,
        $avatarFilename,
        $usId
    );
} else {
    if ($changingPass) {
        $stmt = $conectar->prepare("
        UPDATE usuarios
        SET usPass = ?,
            usNombre = ?,
            usAPaterno = ?,
            usAMaterno = ?,
            usCorreo = ?,
            usTelefono = ?,
            usUsername = ?,
            usEstatus = 'Activo'
            usConfirmado = 'Si'
        WHERE usId = ?
    ");
        $stmt->bind_param(
            "sssssssi",
            $hash,
            $usNombre,
            $usAPaterno,
            $usAMaterno,
            $usCorreo,
            $usTelefono,
            $usUsername,
            $usId
        );
    } else {
        $stmt = $conectar->prepare("
        UPDATE usuarios
        SET
            usNombre = ?,
            usAPaterno = ?,
            usAMaterno = ?,
            usCorreo = ?,
            usTelefono = ?,
            usUsername = ?,
            usEstatus = 'Activo'
            usConfirmado = 'Si'
        WHERE usId = ?");
         $stmt->bind_param(
            "ssssssi",
            $usNombre,
            $usAPaterno,
            $usAMaterno,
            $usCorreo,
            $usTelefono,
            $usUsername,
            $usId
        );
    }
}

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar los cambios.']);
}

/* Actualizar sesión básica */
$_SESSION['usNombre']   = $usNombre;
$_SESSION['usAPaterno'] = $usAPaterno;
$_SESSION['usAMaterno'] = $usAMaterno;
$_SESSION['usCorreo']   = $usCorreo;
$_SESSION['usTelefono'] = $usTelefono;
$_SESSION['usUsername'] = $usUsername;
$_SESSION['usEstatus']  = 'Activo';
if ($avatarFilename) {
    $_SESSION['usImagen'] = $avatarFilename;
}

unset($_SESSION['forzarCambioPass']);

/* === Enviar correo de bienvenida === */
/* === Enviar correo de bienvenida con PHPMailer === */
echo json_encode(['success' => true, 'message' => 'Guardar los cambios.']);

