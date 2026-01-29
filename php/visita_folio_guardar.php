<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'conexion.php';

try {
    if (empty($_SESSION['usId'])) {
        echo json_encode(['success'=>false, 'error'=>'No autenticado']); exit;
    }
    $usId = (int)$_SESSION['usId'];

    $tiId   = isset($_POST['tiId'])   ? (int)$_POST['tiId'] : 0;
    $folio  = isset($_POST['folio'])  ? trim((string)$_POST['folio']) : '';
    $coment = isset($_POST['coment']) ? trim((string)$_POST['coment']) : '';

    if ($tiId <= 0 || $folio === '') {
        echo json_encode(['success'=>false, 'error'=>'Parámetros inválidos']); exit;
    }

    // Validar que exista el ticket
    $st = $conectar->prepare("SELECT tiId FROM ticket_soporte WHERE tiId=? LIMIT 1");
    $st->bind_param("i", $tiId);
    $st->execute();
    $rs = $st->get_result();
    if (!$rs->fetch_assoc()) {
        echo json_encode(['success'=>false, 'error'=>'Ticket no encontrado']); exit;
    }
    $st->close();

    // Manejo de archivo opcional
    $archivoNombre = null;
    if (!empty($_FILES['archivo']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/folios';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $ext  = pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION);
        $base = 'folio_' . $tiId . '_' . date('YmdHis');
        $fileName = $base . ($ext ? '.'.$ext : '');
        $destino  = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
            echo json_encode(['success'=>false, 'error'=>'No se pudo guardar el archivo']); exit;
        }
        $archivoNombre = $fileName;
    }

    // Insert histórico
    $sqlIns = "INSERT INTO ticket_folio_entrada (tiId, folio, archivoRuta, comentario, creadoPor)
               VALUES (?,?,?,?,?)";
    $ins = $conectar->prepare($sqlIns);
    $ins->bind_param("isssi", $tiId, $folio, $archivoNombre, $coment, $usId);
    $ins->execute();
    $ins->close();

    // Actualizar campos rápidos del ticket
    $now = date('Y-m-d H:i:s');
    $sqlUp = "UPDATE ticket_soporte
              SET tiFolioEntrada = ?, tiFolioArchivo = ?, tiFolioCreadoEn = ?, tiFolioCreadoPor = ?, tiVisitaEstado = 'folio_generado'
              WHERE tiId = ?";
    $up = $conectar->prepare($sqlUp);
    $up->bind_param("sssii", $folio, $archivoNombre, $now, $usId, $tiId);
    $up->execute();
    $up->close();

    echo json_encode(['success'=>true]);
    exit;

} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'error'=>'Error interno']); exit;
}
