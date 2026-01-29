<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once 'conexion.php';
session_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $tiId  = isset($_POST['tiId']) ? (int)$_POST['tiId'] : 0;
    $folio = trim($_POST['folio'] ?? '');

    if ($tiId <= 0 || $folio === '') {
        throw new Exception('Parámetros incompletos');
    }

    // Validar folio: letras, números, guion y guion bajo
    if (!preg_match('/^[A-Za-z0-9\-_]{3,100}$/', $folio)) {
        throw new Exception('El folio sólo puede contener letras, números, guion y guion bajo (3-100 caracteres).');
    }

    // Validar que el ticket exista
    $st = $conectar->prepare("SELECT tiId FROM ticket_soporte WHERE tiId = ?");
    if (!$st) {
        throw new Exception('Error de conexión.');
    }
    $st->bind_param('i', $tiId);
    $st->execute();
    $rs = $st->get_result();
    if ($rs->num_rows === 0) {
        throw new Exception('Ticket no encontrado.');
    }
    $st->close();

    // Manejo del archivo opcional
    $soportePath = null;

    if (!empty($_FILES['soporte']['name'])) {
        $file = $_FILES['soporte'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo (código '.$file['error'].').');
        }

        // Tamaño máx ~10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('El archivo no debe superar los 10MB.');
        }

        // Extensiones permitidas
        $extPermitidas = ['pdf','jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $extPermitidas, true)) {
            throw new Exception('Formato no permitido. Usa PDF o imagen (JPG, PNG, GIF, WEBP).');
        }

        // Carpeta de destino
        $baseDir = __DIR__ . '/../uploads/folios';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        // Nombre final: tiId_folio.ext (sanitizado)
        $folioSafe = preg_replace('/[^A-Za-z0-9\-_]/', '_', $folio);
        $fileName  = 'ticket_'.$tiId.'_'.$folioSafe.'.'.$ext;
        $destPath  = $baseDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new Exception('No se pudo guardar el archivo en el servidor.');
        }

        // Ruta relativa para link en front
        $soportePath = 'uploads/folios/'.$fileName;
    }

    // Actualizar ticket
    $sql = "UPDATE ticket_soporte
            SET tiAccesoFolio       = ?,
                tiAccesoSoportePath = ?
            WHERE tiId = ?";

    $st2 = $conectar->prepare($sql);
    if (!$st2) {
        throw new Exception('Error al preparar actualización.');
    }

    $st2->bind_param('ssi',
        $folio,
        $soportePath,
        $tiId
    );
    $st2->execute();

    if ($st2->affected_rows < 0) {
        throw new Exception('No fue posible actualizar el ticket.');
    }

    echo json_encode([
        'success' => true,
        'folio'   => $folio,
        'path'    => $soportePath
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
