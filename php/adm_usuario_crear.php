<?php
// ../php/adm_usuario_crear.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/conexion.php';

try {
    // 1) Validar sesión
    if (empty($_SESSION['usId']) || empty($_SESSION['clId'])) {
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        exit;
    }

    $US_ID = (int)$_SESSION['usId'];
    $CL_ID = (int)$_SESSION['clId'];   // ESTE es el que debemos usar para clId en usuarios

    // 2) Verificar que el cliente exista (para evitar FK)
    if ($stmt = $conectar->prepare("SELECT clNombre FROM clientes WHERE clId = ? LIMIT 1")) {
        $stmt->bind_param("i", $CL_ID);
        $stmt->execute();
        $res = $stmt->get_result();
        $cliRow = $res->fetch_assoc();
        if (!$cliRow) {
            echo json_encode(['success' => false, 'error' => 'Cliente no válido en sesión']);
            exit;
        }
        $stmt->close();
    }
    $CL_NOMBRE = $cliRow['clNombre'] ?? 'Tu empresa';

    // 3) Leer JSON de entrada
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? [];

    $nombre   = trim($data['nombre']   ?? '');
    $apaterno = trim($data['apaterno'] ?? '');
    $amaterno = trim($data['amaterno'] ?? '');
    $correo   = trim($data['correo']   ?? '');
    $telefono = trim($data['telefono'] ?? '');
    $username = trim($data['username'] ?? '');
    $nivel    = trim($data['nivel']    ?? '');   // ADMIN_GLOBAL / ADMIN_ZONA / ADMIN_SEDE / USUARIO / VISOR
    $zonaId   = $data['zonaId'] ?? null;        // opcional
    $sedeId   = $data['sedeId'] ?? null;        // opcional
    $nota     = trim($data['nota']     ?? '');

    // 4) Validaciones básicas
    if ($nombre === '' || $apaterno === '') {
        echo json_encode(['success' => false, 'error' => 'Nombre y apellido paterno son obligatorios.']);
        exit;
    }
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Correo electrónico no válido.']);
        exit;
    }
    if ($username === '') {
        echo json_encode(['success' => false, 'error' => 'El usuario (login) es obligatorio.']);
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9_-]{3,20}$/', $username)) {
        echo json_encode(['success' => false, 'error' => 'Usuario inválido. Usa de 3 a 20 caracteres (letras, números, "-" y "_").']);
        exit;
    }
    if ($nivel === '') {
        echo json_encode(['success' => false, 'error' => 'Debes seleccionar un nivel.']);
        exit;
    }
    if ($nivel === 'ADMIN_ZONA' && empty($zonaId)) {
        echo json_encode(['success' => false, 'error' => 'Para ADMIN_ZONA debes seleccionar una zona.']);
        exit;
    }
    if ($nivel === 'ADMIN_SEDE' && empty($sedeId)) {
        echo json_encode(['success' => false, 'error' => 'Para ADMIN_SEDE debes seleccionar una sede.']);
        exit;
    }

    // 5) Verificar que el username no exista para este cliente
    if ($stmt = $conectar->prepare("SELECT usId FROM usuarios WHERE usUsername = ? AND clId = ? LIMIT 1")) {
        $stmt->bind_param("si", $username, $CL_ID);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->fetch_assoc()) {
            echo json_encode(['success' => false, 'error' => 'El nombre de usuario ya está en uso para este cliente.']);
            exit;
        }
        $stmt->close();
    }
    // 5.1) Verificar que el correo no exista para este cliente
    if ($stmt = $conectar->prepare("SELECT usId FROM usuarios WHERE usCorreo = ? AND clId = ? LIMIT 1")) {
        $stmt->bind_param("si", $correo, $CL_ID);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->fetch_assoc()) {
            echo json_encode(['success' => false, 'error' => 'El correo electrónico ya está en uso para este cliente.']);
            exit;
        }
        $stmt->close();
    }
    // 5.2) Verificar que el teléfono no exista para este cliente
    if ($stmt = $conectar->prepare("SELECT usId FROM usuarios WHERE usTelefono = ? AND clId = ? LIMIT 1")) {
        $stmt->bind_param("si", $telefono, $CL_ID);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->fetch_assoc()) {
            echo json_encode(['success' => false, 'error' => 'El teléfono ya está en uso para este cliente.']);
            exit;
        }
        $stmt->close();
    }


    // 6) Normalizar zona/sede
    $czId = null;
    if ($zonaId !== null && $zonaId !== '') {
        $czId = (int)$zonaId;
    }
    $csId = null;
    if ($sedeId !== null && $sedeId !== '') {
        $csId = (int)$sedeId;
    }

    // 7) Datos para insertar en usuarios
    $usRol     = 'CLI';                // todos los usuarios de cliente usan CLI
    $usEstatus = 'NewPass';      // para forzar cambio de contraseña después
    $tmpPass   = bin2hex(random_bytes(4)); // 8 caracteres hex, si quieres más, cambia el 4
    $hash      = password_hash($tmpPass, PASSWORD_DEFAULT);

    // 8) Transacción usuarios + usuario_cliente_rol
    $conectar->begin_transaction();

    // 8.1) Insertar en usuarios
    $sqlUsr = "
        INSERT INTO usuarios
            (clId, usRol, usNombre, usAPaterno, usAMaterno, usCorreo, usTelefono, usUsername, usPass, usEstatus)
        VALUES
            (?,    ?,     ?,        ?,          ?,          ?,        ?,          ?,          ?,      ?)
    ";
    if (!($stmt = $conectar->prepare($sqlUsr))) {
        throw new Exception("Error prepare usuarios: " . $conectar->error);
    }

    $stmt->bind_param(
        "isssssssss",
        $CL_ID,
        $usRol,
        $nombre,
        $apaterno,
        $amaterno,
        $correo,
        $telefono,
        $username,
        $hash,
        $usEstatus
    );
    $stmt->execute();
    $nuevoUsId = $stmt->insert_id;
    $stmt->close();

    // 8.2) Insertar rol por cliente y zona/sede en usuario_cliente_rol
    $sqlRol = "
        INSERT INTO usuario_cliente_rol
            (usId, clId, ucrRol, czId, csId)
        VALUES
            (?,    ?,    ?,      ?,    ?)
    ";
    if (!($stmt = $conectar->prepare($sqlRol))) {
        throw new Exception("Error prepare usuario_cliente_rol: " . $conectar->error);
    }

    $stmt->bind_param(
        "iisii",
        $nuevoUsId,
        $CL_ID,
        $nivel,
        $czId,
        $csId
    );
    $stmt->execute();
    $stmt->close();

    // (Opcional) Si tienes campo de nota en alguna tabla, aquí podrías actualizar

    // 9) Confirmar transacción
    $conectar->commit();

    /* =======================================================
     * 10) Enviar correo de bienvenida al nuevo usuario
     * ======================================================= */
    try {
        // Branding / datos base (igual que otros correos)
        $NOMBRE_EMPRESA    = 'MR Solutions';
        $URL_LOGO          = 'https://www.ventasdeseguridad.com/media/com_mtree/images/listings/o/2018_03_16_19_03_54_Logo.jpg';
        $DIRECCION_EMPRESA = 'Alhambra 813 Bis, Portales Sur, Benito Juárez, 03300 Ciudad de México, CDMX';

        // PHPMailer
        require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Logs de envío
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile  = $logDir . '/mail_usuario_nuevo.log';
        $smtpLog  = '';
        $debugOut = function ($str) use (&$smtpLog) {
            $smtpLog .= $str . "\n";
        };

        $DRY_RUN = false; // pon true si quieres probar sin enviar

        // Config SMTP (igual que en tus otros scripts)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lizma0116@gmail.com';
        $mail->Password   = 'acxh hduf vpqd xgnn'; // App Password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->SMTPDebug   = 0;
        $mail->Debugoutput = $debugOut;

        // From / To
        $mail->setFrom('lizma0116@gmail.com', $NOMBRE_EMPRESA . ' Notificaciones');
        $mail->addReplyTo('lizma0116@gmail.com', 'Mesa de Ayuda');

        $mail->addAddress($correo, $nombre);

        // Asunto / preheader
        $mail->Subject = 'Tu acceso a MR SoS';
        $preheader     = 'Tu usuario ha sido creado. Aquí encontrarás tus datos de acceso a MR SoS.';

        // Paleta
        $primary    = '#4F46E5';
        $primaryHov = '#4338CA';
        $text       = '#111827';
        $muted      = '#6B7280';
        $bg         = '#F3F4F6';
        $card       = '#FFFFFF';
        $border     = '#E5E7EB';

        // Cuerpo HTML
        $nombreHtml   = htmlspecialchars($nombre,   ENT_QUOTES, 'UTF-8');
        $usernameHtml = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $tmpPassHtml  = htmlspecialchars($tmpPass,  ENT_QUOTES, 'UTF-8');
        $cliNombreHtml= htmlspecialchars($CL_NOMBRE, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Body = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>{$mail->Subject}</title>
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <style>a{text-decoration:none}</style>
</head>
<body style="margin:0;padding:0;background:{$bg};">
<span style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;color:transparent;">
  {$preheader}
</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{$bg};padding:24px 0;">
  <tr>
    <td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:{$card};border:1px solid {$border};border-radius:12px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
        <!-- Header -->
        <tr>
          <td style="padding:20px 24px;border-bottom:1px solid {$border};">
            <table width="100%">
              <tr>
                <td align="left">
                  <img src="{$URL_LOGO}" alt="{$NOMBRE_EMPRESA}" height="36" style="display:block;border:0;outline:none;text-decoration:none;height:36px;">
                </td>
                <td align="right" style="font-size:12px;color:{$muted};">Acceso a la plataforma</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Contenido principal -->
        <tr>
          <td style="padding:24px;">
            <h1 style="margin:0 0 8px 0;font-size:20px;line-height:1.3;color:{$text};">
              Hola {$nombreHtml},
            </h1>
            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:{$text};">
              Te hemos dado de alta en la plataforma <strong>MR SoS</strong> para el cliente
              <strong>{$cliNombreHtml}</strong>.
            </p>

            <p style="margin:0 0 8px 0;font-size:14px;line-height:1.6;color:{$text};">
              A continuación encontrarás tus datos de acceso:
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;font-size:14px;color:{$text};">
              <tr>
                <td style="padding:4px 0;width:140px;"><strong>Usuario:</strong></td>
                <td style="padding:4px 0;">{$usernameHtml}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;width:140px;"><strong>ID:</strong></td>
                <td style="padding:4px 0;">{$nuevoUsId}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;"><strong>Contraseña temporal:</strong></td>
                <td style="padding:4px 0;">{$tmpPassHtml}</td>
              </tr>
            </table>

            <p style="margin:0 0 16px 0;font-size:13px;line-height:1.6;color:{$muted};">
              Por seguridad, al ingresar por primera vez se te solicitará cambiar esta contraseña
              por una nueva que sólo tú conozcas.
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
              <tr>
                <td align="center" bgcolor="{$primary}" style="border-radius:10px;">
                  <a href="https://mrsolutions.com.mx" target="_blank"
                     style="display:inline-block;padding:12px 20px;color:#ffffff;background:{$primary};border-radius:10px;font-size:14px;font-weight:bold;">
                    Ir a MR SoS
                  </a>
                </td>
              </tr>
            </table>

            <p style="margin:0;font-size:12px;line-height:1.6;color:{$muted};">
              Si no reconoces este registro o crees que se trata de un error, por favor contacta a tu área de sistemas
              o a la Mesa de Ayuda de {$NOMBRE_EMPRESA}.
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:16px 24px;border-top:1px solid {$border};">
            <p style="margin:0;font-size:11px;color:{$muted};">
              © {$NOMBRE_EMPRESA}. Todos los derechos reservados.
              <br>{$DIRECCION_EMPRESA}
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        // Texto plano
        $mail->AltBody =
            "Acceso a MR SoS - {$NOMBRE_EMPRESA}\n\n" .
            "Hola {$nombre},\n\n" .
            "Se ha creado tu usuario en MR SoS para el cliente {$CL_NOMBRE}.\n\n" .
            "ID: {$nuevoUsId}\n" .
            "Usuario: {$username}\n" .
            "Contraseña temporal: {$tmpPass}\n\n" .
            "Al ingresar por primera vez se te pedirá cambiar la contraseña.\n\n" .
            "Si no reconoces este registro, contacta a tu área de sistemas o a la Mesa de Ayuda de {$NOMBRE_EMPRESA}.\n";

        if ($DRY_RUN === false) {
            $mail->send();
        }

        // Log básico
        if ($DRY_RUN === false) {
            $line = "[" . date('Y-m-d H:i:s') . "] nuevoUsId={$nuevoUsId}, correo={$correo}\nSMTP:\n" . $smtpLog . "\n\n";
            @file_put_contents($logFile, $line, FILE_APPEND);
        }

    } catch (Throwable $eMail) {
        // No rompemos el flujo si falla el correo
        @error_log("[adm_usuario_crear mail] usId={$nuevoUsId} error=" . $eMail->getMessage());
    }

    // 11) Respuesta OK (incluyo tmpPass por si quieres mostrarla al admin)
    echo json_encode([
        'success' => true,
        'usId'    => $nuevoUsId,
        'tmpPass' => $tmpPass
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($conectar) && $conectar instanceof mysqli) {
        @ $conectar->rollback();
    }
    @error_log("[adm_usuario_crear] " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'error'   => 'Error interno: ' . $e->getMessage()
    ]);
    exit;
}
