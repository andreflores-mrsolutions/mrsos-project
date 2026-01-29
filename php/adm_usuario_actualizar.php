<?php
// php/adm_usuario_actualizar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

session_start();
require 'conexion.php';

try {
    if (empty($_SESSION['usId']) || empty($_SESSION['clId'])) {
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        exit;
    }

    $ADMIN_ID = (int)$_SESSION['usId'];
    $CL_ID    = (int)$_SESSION['clId'];
    $ROL_SYS  = $_SESSION['usRol'] ?? 'CLI'; // CLI, MRA, etc.

    // Podemos exigir que sea cliente (CLI) o MRA para editar
    if (!in_array($ROL_SYS, ['CLI', 'MRA'], true)) {
        echo json_encode(['success' => false, 'error' => 'Sin permisos para editar usuarios']);
        exit;
    }

    // Leemos JSON
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'error' => 'Entrada inválida'.$raw]);
        exit;
    }

    $usId     = (int)($data['usId']     ?? 0);
    $nombre   = trim((string)($data['nombre']   ?? ''));
    $apaterno = trim((string)($data['apaterno'] ?? ''));
    $amaterno = trim((string)($data['amaterno'] ?? ''));
    $correo   = trim((string)($data['correo']   ?? ''));
    $telefono = trim((string)($data['telefono'] ?? ''));
    $username = trim((string)($data['username'] ?? ''));
    $nivel    = trim((string)($data['nivel']    ?? ''));   // ADMIN_GLOBAL / ADMIN_ZONA / ...
    $sedeId   = isset($data['sedeId']) && $data['sedeId'] !== '' ? (int)$data['sedeId'] : null;
    $newPass  = (string)($data['newPass']  ?? '');
    $newPass2 = (string)($data['newPass2'] ?? '');

    if ($usId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Usuario inválido']);
        exit;
    }

    if ($nombre === '' || $apaterno === '' || $correo === '') {
        echo json_encode(['success' => false, 'error' => 'Nombre, apellido y correo son obligatorios.']);
        exit;
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Correo no válido.']);
        exit;
    }
    if ($username === '') {
        echo json_encode(['success' => false, 'error' => 'Nombre de usuario requerido.']);
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9_-]{3,20}$/', $username)) {
        echo json_encode(['success' => false, 'error' => 'Nombre de usuario con formato inválido.']);
        exit;
    }

    $malas = ['puta','puto','mierda','pendejo','pendeja','idiota'];
    $lower = mb_strtolower($username, 'UTF-8');
    foreach ($malas as $m) {
        if (str_contains($lower, $m)) {
            echo json_encode(['success' => false, 'error' => 'Nombre de usuario no permitido.']);
            exit;
        }
    }

    if ($nivel === '') {
        echo json_encode(['success' => false, 'error' => 'Debes seleccionar un nivel de cliente.']);
        exit;
    }
    if (!in_array($nivel, ['ADMIN_GLOBAL','ADMIN_ZONA','ADMIN_SEDE','USUARIO','VISOR'], true)) {
        echo json_encode(['success' => false, 'error' => 'Nivel desconocido.']);
        exit;
    }

    // Validación de contraseña solo si se mandó algo
    $cambiarPass = false;
    if ($newPass !== '' || $newPass2 !== '') {
        if ($newPass === '' || $newPass2 === '' || $newPass !== $newPass2) {
            echo json_encode(['success' => false, 'error' => 'Las contraseñas no coinciden o están vacías.']);
            exit;
        }
        if (
            strlen($newPass) < 8 ||
            !preg_match('/[A-Z]/', $newPass) ||
            !preg_match('/[a-z]/', $newPass) ||
            !preg_match('/[0-9]/', $newPass) ||
            !preg_match('/[!@#$%^&*()_\-+={}[\]:;"\'<>,.?\/~`\\\\|]/', $newPass)
        ) {
            echo json_encode(['success' => false, 'error' => 'La contraseña no cumple con los requisitos mínimos.']);
            exit;
        }
        $cambiarPass = true;
    }

    // Verificar que el usuario exista y pertenezca a este cliente
    $stmt = $conectar->prepare("SELECT usId FROM usuarios WHERE usId=? AND clId=? AND usRol='CLI' LIMIT 1");
    $stmt->bind_param("ii", $usId, $CL_ID);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res->fetch_assoc()) {
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado para este cliente.']);
        exit;
    }
    $stmt->close();

    // Verificar unicidad de username
    $stmt = $conectar->prepare("SELECT usId FROM usuarios WHERE usUsername=? AND clId=? AND usId <> ?");
    $stmt->bind_param("sii", $username, $CL_ID, $usId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'El nombre de usuario ya está en uso.']);
        exit;
    }
    $stmt->close();

    // Empezamos "transacción" simple
    $conectar->begin_transaction();

    // Actualizar usuarios
    if ($cambiarPass) {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $sqlUp = "
          UPDATE usuarios
          SET usNombre=?, usAPaterno=?, usAMaterno=?, usCorreo=?, usTelefono=?, usUsername=?,
              usPass=?, usRol='CLI'
          WHERE usId=? AND clId=?
        ";
        $stmt = $conectar->prepare($sqlUp);
        $stmt->bind_param(
            "sssssssii",
            $nombre, $apaterno, $amaterno, $correo, $telefono, $username,
            $hash,
            $usId, $CL_ID
        );
    } else {
        $sqlUp = "
          UPDATE usuarios
          SET usNombre=?, usAPaterno=?, usAMaterno=?, usCorreo=?, usTelefono=?, usUsername=?,
              usRol='CLI'
          WHERE usId=? AND clId=?
        ";
        $stmt = $conectar->prepare($sqlUp);
        $stmt->bind_param(
            "ssssssii",
            $nombre, $apaterno, $amaterno, $correo, $telefono, $username,
            $usId, $CL_ID
        );
    }

    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar usuario: ' . $stmt->error);
    }
    $stmt->close();

    // Actualizar/insertar usuario_cliente_rol
    // Intentamos update primero
    $sqlUcr = "
      UPDATE usuario_cliente_rol
      SET ucrRol = ?, csId = ?
      WHERE usId = ? AND clId = ?
    ";
    $stmt = $conectar->prepare($sqlUcr);
    $scId = $sedeId ?: null;
    $stmt->bind_param("siii", $nivel, $scId, $usId, $CL_ID);
    $stmt->execute();
    $af = $stmt->affected_rows;
    $stmt->close();

    

    $conectar->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($conectar) && $conectar->errno === 0) {
        $conectar->rollback();
    }
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno: ' . $e->getMessage()
    ]);
}
