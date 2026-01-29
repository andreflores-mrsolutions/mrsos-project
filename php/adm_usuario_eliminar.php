<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
include "conexion.php";
function jexit($a)
{
    echo json_encode($a, JSON_UNESCAPED_UNICODE);
    exit;
}

$adminId = $_SESSION['usId'] ?? 0;
if (!$adminId) jexit(['success' => false, 'error' => 'Sesión no válida']);

$usId = (int)($_POST['usId'] ?? 0);
if ($usId <= 0) jexit(['success' => false, 'error' => 'usId inválido']);

if (($_SESSION['usRol'] ?? '') !== 'AC') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$usId = (int)($in['usId'] ?? 0);
if ($usId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parámetro usId']);
    exit;
}

$accion = $_POST['accion'] ?? 0;
if (!$accion || $accion === 'desactivar') {
    $st = $conectar->prepare("UPDATE usuarios SET usEstatus='Inactivo' WHERE usId=?");
    $st->bind_param("i", $usId);
    $ok = $st->execute();
    $st->close();

    echo json_encode(['success' => $ok]);
    exit;
}

$st = $conectar->prepare("UPDATE usuarios SET usEstatus='Eliminado' WHERE usId=?");
$st->bind_param("i", $usId);
$ok = $st->execute();
$st->close();

echo json_encode(['success' => $ok]);
