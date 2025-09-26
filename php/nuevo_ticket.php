<?php
include("../php/conexion.php");
session_start();
$clienteId = $_SESSION['clienteId'];  // Ajusta según tu sesión

$sql = "SELECT e.eqId, e.eqModelo, e.eqTipoEquipo, e.eqImagen, e.maId, m.maNombre 
        FROM equipos e
        JOIN marca m ON e.maId = m.maId
        JOIN poliza p ON e.eqId = p.eqId
        WHERE p.clienteId = ?";

$stmt = $conectar->prepare($sql);
$stmt->bind_param("i", $clienteId);
$stmt->execute();
$result = $stmt->get_result();

$equipos = [];
while($row = $result->fetch_assoc()) {
  $equipos[] = $row;
}

echo json_encode($equipos);
?>
