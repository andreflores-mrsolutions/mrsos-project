<?php
include '../../php/conexion.php';
session_start();
$u        = $_GET['uId'];
// $field = $_POST['field'];
// $value = $_POST['value'];
// $editid = $_POST['id'];
// echo 1;
// $query = $conn->query("SELECT * FROM citas WHERE ctFolio = '$editid'");
// while ($row = $query->fetch_object()) {
//     $ctFolio = $row->ctFolio;
//     $ctDoc = $row->ctDocCoti;
//     $ctCosto = $row->ctCosto;
//     $uNumCliente = $row->uNumCliente;
// }
// $query3 = $conn->query("SELECT * FROM iusuarios WHERE uNumCliente = '$uNumCliente'");
// while ($row3 = $query3->fetch_object()) {
//     $correo = $row3->uEmail;
//     $nombre = $row3->uNombre;
// }  $query2 = $conectar->query("SELECT * FROM order_items WHERE order_id = $u");


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'compras@refaccionariazapata.com';
    $mail->Password = 'Ref4cc10nes*23';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    // $mail->isSMTP();
    // $mail->Host = 'smtp-mail.outlook.com';
    // $mail->SMTPAuth = true;
    // $mail->Username = 'zapatacamionesae@hotmail.com';
    // $mail->Password = 'Z@pataCamiones';
    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    // $mail->Port = 587;

    // $mail->setFrom('zapatacamionesae@hotmail.com', 'Zapata Camiones');
    $mail->setFrom('compras@refaccionariazapata.com', 'Refaccionaria Online Zapata');
    $mail->addAddress('' . $email . '', '' . $nombre . '');
    //$mail->addBCC('jrecoba@zapata.com.mx');
    $mail->addAttachment('../com_pdf/Comprobante_Compra' . $idc . '.pdf');

    $mail->isHTML(true);
    $mail->CharSet = "UTF-8";
    $mail->Encoding = "quoted-printable";
    $mail->Subject = 'Confirmación de compra - Refaccionaria Online Zapata';
    $mail->Body = 'Gracias por comprar en Refaccionaria Online Zapata. Se adjunta en el correo su comprobante de Compra.';
    $mail->send();
    echo 1;
} catch (Exception $e) {
    echo 'Mensaje ' . $mail->ErrorInfo;
}

$mail2 = new PHPMailer(true);

try {
    
    $mail2->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail2->isSMTP();
    $mail2->Host = 'smtp.hostinger.com';
    $mail2->SMTPAuth = true;
    $mail2->Username = 'compras@refaccionariazapata.com';
    $mail2->Password = 'Ref4cc10nes*23';
    $mail2->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail2->Port = 587;
    
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    // $mail->isSMTP();
    // $mail->Host = 'smtp-mail.outlook.com';
    // $mail->SMTPAuth = true;
    // $mail->Username = 'zapatacamionesae@hotmail.com';
    // $mail->Password = 'Z@pataCamiones';
    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    // $mail->Port = 587;

    // $mail->setFrom('zapatacamionesae@hotmail.com', 'Zapata Camiones');
    $mail2->setFrom('compras@refaccionariazapata.com', 'Refaccionaria Online Zapata');
    $mail2->addAddress('jrecoba@zapata.com.mx', 'Ejecutivo(a) de Refacciones Refaccionaria Zapata');
    $mail2->addBCC('mbeltran@zapata.com.mx', 'Valicación de Pago Refaccionaria Zapata');
    $mail2->addAttachment('../com_pdf/Comprobante_Compra' . $idc . '.pdf');

    $mail2->isHTML(true);
    $mail2->CharSet = "UTF-8";
    $mail2->Encoding = "quoted-printable";
    $mail2->Subject = 'Notificación de Compra - Refaccionaria Online Zapata';
    $mail2->Body = 'Aqui se muestra su prefactura';
    $mail2->send();
    echo 1;
} catch (Exception $e) {
    echo 'Mensaje ' . $mail2->ErrorInfo;
}
