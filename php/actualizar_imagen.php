<?php
include("conexion.php");
session_start();

header('Content-Type: application/json');

$usId = $_SESSION['usId'] ?? null;
if (!$usId) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Archivo no recibido']);
    exit;
}

$archivoTmp = $_FILES['imagen']['tmp_name'];
$nombreArchivo = uniqid() . '_' . basename($_FILES['imagen']['name']);
$rutaDestino = "../img/perfil/" . $nombreArchivo;

if (move_uploaded_file($archivoTmp, $rutaDestino)) {
    $rutaRelativa = "img/perfil/" . $nombreArchivo;
    $sql = "UPDATE usuarios SET imagen = ? WHERE usId = ?";
    $stmt = $conectar->prepare($sql);
    $stmt->bind_param("si", $rutaRelativa, $usId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'nuevaRuta' => $rutaRelativa]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar imagen']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al mover el archivo']);
}
?>
