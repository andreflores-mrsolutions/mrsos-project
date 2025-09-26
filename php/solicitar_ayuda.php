<?php
// php/solicitar_ayuda.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

session_start();
require_once 'conexion.php';

// TODO: Ajusta la ruta a PHPMailer si la tienes vendorizada con Composer
// require __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1) Autenticación básica
$clId = $_SESSION['clId'] ?? null; // Cliente logeado
$rol  = $_SESSION['usRol']  ?? 'cliente'; // opcional si manejas roles
if (!$clId && $rol !== 'MR_ADMIN') {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// 2) Entradas
$tiId   = isset($_POST['ticketId']) ? (int)$_POST['ticketId'] : 0;
$mensaje = trim($_POST['mensaje'] ?? '');
$pedirM = isset($_POST['solicitar_meet']) ? (int)$_POST['solicitar_meet'] : 0;
$plat   = trim($_POST['plataforma'] ?? '');
$link   = trim($_POST['link'] ?? '');

if ($tiId <= 0 || $mensaje === '') {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

// 3) Verifica ticket y dueño (si no eres MR_ADMIN)
$sql = "SELECT t.tiId, t.clId, t.usIdIng, t.tiDescripcion, t.tiProceso,
               t.tiMeetActivo, t.tiMeetPlataforma, t.tiMeetLink,
               e.eqModelo, m.maNombre, pe.peSN
        FROM ticket_soporte t
        JOIN polizasequipo pe ON pe.peId = t.peId
        JOIN equipos e ON e.eqId = pe.eqId
        JOIN marca m ON m.maId = e.maId
        WHERE t.tiId = ?";
$stmt = $conectar->prepare($sql);
$stmt->bind_param("i", $tiId);
$stmt->execute();
$res = $stmt->get_result();
$ticket = $res->fetch_assoc();
$stmt->close();

if (!$ticket) {
    echo json_encode(['success' => false, 'error' => 'Ticket no encontrado']);
    exit;
}
if ($rol !== 'MR_ADMIN' && (int)$ticket['clId'] !== (int)$clId) {
    echo json_encode(['success' => false, 'error' => 'No autorizado para este ticket']);
    exit;
}

// 4) Datos del ingeniero
$toEmail = null;
$toName = 'Ingeniero';
if (!empty($ticket['usIdIng'])) {
    $sqlIng = "SELECT usNombre, usCorreo FROM usuarios WHERE usId = ?";
    $st2 = $conectar->prepare($sqlIng);
    $st2->bind_param("i", $ticket['usIdIng']);
    $st2->execute();
    $r2 = $st2->get_result();
    if ($rowIng = $r2->fetch_assoc()) {
        $toEmail = $rowIng['usCorreo'] ?: null;
        $toName  = $rowIng['usNombre'] ?: $toName;
    }
    $st2->close();
}

// Fallback si no hay ingeniero asignado
if (!$toEmail) {
    // Cambia por tu buzón de soporte:
    $toEmail = 'soporte@tudominio.com';
    $toName  = 'Soporte MR';
}

// 5) Si solicita Meet, prepara update de meet
if ($pedirM === 1) {
    $meetActivo = 'meet solicitado cliente';
    $fecha = trim($_POST['fecha'] ?? '');
    $hora  = trim($_POST['hora'] ?? '');
    $meetDT = ($fecha && $hora) ? ($fecha . ' ' . $hora . ':00') : null;

    if ($meetDT) {
        $sqlMeet = "UPDATE ticket_soporte
                SET tiMeetActivo=?, tiMeetPlataforma=?, tiMeetLink=?, tiMeetFecha=?, tiProceso='meet'
                WHERE tiId=?";
        $st3 = $conectar->prepare($sqlMeet);
        $st3->bind_param("ssssi", $meetActivo, $plat, $link, $meetDT, $tiId);
    } else {
        // sin fecha
        $sqlMeet = "UPDATE ticket_soporte
              SET tiMeetActivo=?, tiMeetPlataforma=?, tiMeetLink=?, tiMeetFecha=?, tiProceso='meet'
              WHERE tiId=?";
        $st3 = $conectar->prepare($sqlMeet);
        $st3->bind_param("sssi", $meetActivo, $plat, $link, $tiId);
    }
    $st3->execute();
    $st3->close();
}

// 6) Guarda nota en tiExtra (histórico simple)
$nota = "[Ayuda solicitada] " . date('Y-m-d H:i') . " - " . $mensaje;
$sqlExtra = "UPDATE ticket_soporte
             SET tiExtra = CONCAT(COALESCE(tiExtra,''), ?)
             WHERE tiId = ?";
$st4 = $conectar->prepare($sqlExtra);
$sep = "\n" . str_repeat('-', 40) . "\n";
$notaForm = $sep . $nota . $sep;
$st4->bind_param("si", $notaForm, $tiId);
$st4->execute();
$st4->close();

// 7) Enviar correo (PHPMailer)
try {
    // Si usas Composer, descomenta el require arriba y estas líneas:
    // $mail = new PHPMailer(true);

    // Si no usas Composer, incluye manualmente las clases:
    require_once __DIR__ . '../lib/enviar_mail_php-main/vendor/phpmailer/src/Exception.php';
    require_once __DIR__ . '../lib/enviar_mail_php-main/vendor/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '../lib/enviar_mail_php-main/vendor/phpmailer/src/SMTP.php';
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.tudominio.com';   // TODO
    $mail->SMTPAuth   = true;
    $mail->Username   = 'usuario@tudominio.com'; // TODO
    $mail->Password   = '***************';      // TODO
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('no-reply@tudominio.com', 'MR Solutions');
    $mail->addAddress($toEmail, $toName);
    // CC opcional:
    // $mail->addCC('soporte@tudominio.com');

    $mail->isHTML(true);
    $mail->Subject = "Solicitud de ayuda - Ticket #{$ticket['tiId']}";

    $html  = "<p>Se ha solicitado ayuda para el Ticket <strong>#{$ticket['tiId']}</strong>.</p>";
    $html .= "<p><strong>Equipo:</strong> {$ticket['eqModelo']} ({$ticket['maNombre']})<br>";
    $html .= "<strong>SN:</strong> {$ticket['peSN']}<br>";
    $html .= "<strong>Proceso actual:</strong> {$ticket['tiProceso']}</p>";
    $html .= "<p><strong>Mensaje del cliente:</strong><br>" . nl2br(htmlentities($mensaje)) . "</p>";

    if ($pedirM === 1) {
        $html .= "<hr><p><strong>Meet solicitado por el cliente</strong><br>";
        $html .= "<strong>Plataforma:</strong> " . htmlentities($plat ?: '--') . "<br>";
        $html .= "<strong>Enlace:</strong> " . ($link ? '<a href="' . htmlentities($link) . '" target="_blank">' . htmlentities($link) . '</a>' : '—') . "</p>";
    }

    $mail->Body = $html;
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

    $mail->send();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'No se pudo enviar el correo: ' . $mail->ErrorInfo]);
}
