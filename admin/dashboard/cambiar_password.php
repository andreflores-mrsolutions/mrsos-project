<?php

declare(strict_types=1);
session_start();
if (empty($_SESSION['usId'])) {
    header('Location: ../login/login.php');
    exit;
}
// headers.php (parte superior)
$theme = $_COOKIE['mrs_theme'] ?? 'light';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../php/conexion.php"; // ajusta la ruta si aplica

// Permite fijar la póliza activa desde ?pcId= y guardarla en sesión
if (isset($_GET['pcId'])) {
  $_SESSION['pcId'] = (int)$_GET['pcId'];
}

$clId  = $_SESSION['clId'] ?? null;      // cliente del usuario logueado
$pcId  = $_SESSION['pcId'] ?? null;      // póliza activa (opcional)

// Helper: localizar la foto de usuario con varias extensiones
function findUserAvatarUrl(string $username): string
{
  // Ajusta las rutas base según tu estructura
  $urlBase = "../img/Usuario/";                         // para el src en <img>
  $fsBase  = realpath(__DIR__ . "/../img/Usuario");     // en disco

  if (!$fsBase) return $urlBase . "user.webp";

  $exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  foreach ($exts as $ext) {
    $fs = $fsBase . DIRECTORY_SEPARATOR . $username . "." . $ext;
    if (file_exists($fs)) {
      return $urlBase . $username . "." . $ext;
    }
  }
  return $urlBase . "user.webp";
}

// Buscar vendedor (usId) para el cliente (y póliza si está definida)
$vend = null;
if ($clId) {
  if ($pcId) {
    $sql = "SELECT u.usId, u.usNombre, u.usAPaterno, u.usUsername
            FROM cuentas c
            JOIN polizascliente pc ON pc.pcId = c.pcId
            JOIN usuarios u ON u.usId = c.usId
            WHERE c.clId = ? AND c.pcId = ?
            LIMIT 1";
    $stmt = $conectar->prepare($sql);
    $stmt->bind_param("ii", $clId, $pcId);
  } else {
    // Si no hay póliza activa, toma cualquiera del cliente (la más reciente)
    $sql = "SELECT u.usId, u.usNombre, u.usAPaterno, u.usUsername
            FROM cuentas c
            JOIN polizascliente pc ON pc.pcId = c.pcId
            JOIN usuarios u ON u.usId = c.usId
            WHERE c.clId = ?
            ORDER BY c.cuId DESC
            LIMIT 1";
    $stmt = $conectar->prepare($sql);
    $stmt->bind_param("i", $clId);
  }

  if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    $vend = $res->fetch_assoc() ?: null;
  }
  if (isset($stmt) && $stmt) $stmt->close();
}

$vendNombre = $vend ? trim(($vend['usNombre'] ?? '') . ' ' . ($vend['usAPaterno'] ?? '')) : 'Responsable del proyecto';
$vendAvatar = $vend ? findUserAvatarUrl($vend['usUsername'] ?? '') : '../img/Usuario/user.webp';

if (empty($_SESSION['clId'])) {
  header('Location: ../login/login.php');
  exit;
}

// ... tu lógica de sesión previa
$ROL   = $_SESSION['usRol']  ?? null;   // 'AC' | 'UC' | 'EC' | 'MRA'
$CL_ID = $_SESSION['clId'] ?? null;
$US_ID = $_SESSION['usId'] ?? null;

$CAN_CREATE = ($ROL === 'AC' || $ROL === 'UC' || $ROL === 'MRA'); // EC no crea
// ------------------ Config & helpers ------------------
function pdo(): PDO
{
    $DB_HOST = '127.0.0.1';
    $DB_NAME = 'mrsos';
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_CHARSET = 'utf8mb4';
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
    return new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$pdo = pdo();
$now = (new DateTime('now'))->format('Y-m-d H:i:s');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ------------------ POST: guardar nueva contraseña ------------------
if ($method === 'POST') {
    header('Content-Type: text/html; charset=utf-8');

    $usId  = isset($_POST['usId']) ? (int)$_POST['usId'] : 0;
    // OJO: el token es BIGINT(34) en la BD; trátalo como string para no truncarlo en PHP
    $pass = trim((string)($_POST['pass'] ?? ''));
    $pass1 = trim((string)($_POST['pass1'] ?? ''));
    $pass2 = trim((string)($_POST['pass2'] ?? ''));

    // Validaciones básicas
    if ($usId <= 0 || $pass === '' || strlen($pass1) < 8 || $pass1 !== $pass2) {
        http_response_code(400);
        echo "<p>Error: datos inválidos o contraseñas no coinciden (mínimo 8 caracteres).</p>";
        exit;
    }
    // Buscar usuario con token vigente
    $stmt = $pdo->prepare("
    SELECT *
    FROM usuarios
    WHERE usId = ?
    LIMIT 1
  ");
    $stmt->execute([$usId]);
    $user = $stmt->fetch();
    $passOld = $user['usPass'];
    if (!$user || !password_verify($pass, $passOld)) {
        http_response_code(400);
        echo '
    <!doctype html>
    <html lang="es">

    <head>
        <meta charset="utf-8">
        <title>Restablecer contraseña</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- Si ya cargas Bootstrap global, puedes omitir esta CDN -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- /Bootstrap -->
        <link href="../css/style.css" rel="stylesheet">
    </head>
    <body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center">
                            <img src="../img/logo MR.webp" style="width: 125px;" class="mb-3" alt="logo">
                            <h4 class="mt-1 mb-5 pb-1">Nuevo de Password</h4>
                        </div>
                        <h4 class="mb-3">Nueva contraseña</h4>
                        <div class="alert alert-danger">
                            <p>Problemas para encontrar el usuario. <a href="login.php">Ir al dashboard</a> '.$hash.'</p>
                        </div>
                        <p></p>
                    </div>
                </div>
                </div>
        </div>
    </div>
        </body>';
        exit;
    }

    // Actualizar password y limpiar token
    $hash = password_hash($pass1, PASSWORD_BCRYPT, ['cost' => 12]);

    $upd = $pdo->prepare("
    UPDATE usuarios
    SET usPass = ?
    WHERE usId = ?
  ");
    $upd->execute([$hash, $usId]);

    echo '
    <!doctype html>
    <html lang="es">

    <head>
        <meta charset="utf-8">
        <title>Restablecer contraseña</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- Si ya cargas Bootstrap global, puedes omitir esta CDN -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- /Bootstrap -->
        <link href="../css/style.css" rel="stylesheet">
    </head>
    <body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center">
                            <img src="../img/logo MR.webp" style="width: 125px;" class="mb-3" alt="logo">
                            <h4 class="mt-1 mb-5 pb-1">Reseteo de Password</h4>
                        </div>
                        <h4 class="mb-3">Restablecer contraseña</h4>
                        <div class="alert alert-success">
                            ¡Listo!
                            Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión
                            <a href="login.php">Ir al login</a>
                        </div>
                        <p></p>
                    </div>
                </div>
                </div>
        </div>
    </div>
        </body>';
    $usCorreo = $user['usCorreo'];
    $usNombre = $user['usNombre'];
    
/* === Enviar correo de bienvenida === */
/* === Enviar correo de bienvenida con PHPMailer === */

// Branding / datos base
$NOMBRE_EMPRESA    = 'MR Solutions';
$URL_LOGO          = 'https://www.ventasdeseguridad.com/media/com_mtree/images/listings/o/2018_03_16_19_03_54_Logo.jpg';
$DIRECCION_EMPRESA = 'Alhambra 813 Bis, Portales Sur, Benito Juárez, 03300 Ciudad de México, CDMX';

// 1) Includes de PHPMailer
require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../lib/enviar_mail_php-main/vendor/phpmailer/phpmailer/src/Exception.php';

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

// 2) Logs opcionales
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile  = $logDir . '/mail_bienvenida.log';
$smtpLog  = '';
$debugOut = function ($str) use (&$smtpLog) {
    $smtpLog .= $str . "\n";
};

// Si quieres probar sin enviar de verdad, pon true
$DRY_RUN = false;

try {
    // 3) Config SMTP (igual que en el reset)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'lizma0116@gmail.com';       // remitente
    $mail->Password   = 'acxh hduf vpqd xgnn';       // App Password
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Debug: 0 en producción
    $mail->SMTPDebug   = 0;
    $mail->Debugoutput = $debugOut;

    // 4) From / To
    $mail->setFrom('lizma0116@gmail.com', $NOMBRE_EMPRESA . ' Notificaciones');
    $mail->addReplyTo('lizma0116@gmail.com', 'Mesa de Ayuda');
    $mail->addAddress($usCorreo, $usNombre);

    // 5) Asunto + preheader
    $mail->Subject = 'Actualización de datos de cuenta';
    $preheader     = 'Tu cuenta ha sido actualizada correctamente. Gracias por confiar en MR Solutions.';

    // Paleta
    $primary    = '#4F46E5';
    $primaryHov = '#4338CA';
    $text       = '#111827';
    $muted      = '#6B7280';
    $bg         = '#F3F4F6';
    $card       = '#FFFFFF';
    $border     = '#E5E7EB';

    // 6) Cuerpo HTML
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
                <td align="right" style="font-size:12px;color:{$muted};">Actualización de datos de cuenta</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Contenido principal -->
        <tr>
          <td style="padding:24px;">
            <h1 style="margin:0 0 8px 0;font-size:20px;line-height:1.3;color:{$text};">
              Se han cambiado los datos de tu cuenta, {$usNombre}
            </h1>
            <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:{$text};">
              Se han detectado cambios en la información de tu cuenta. Si fuiste tú, no es necesario que hagas nada más.
            </p>
            

            <p style="margin:0 0 20px 0;font-size:14px;line-height:1.6;color:{$text};">
              Si no reconoces estos cambios, por favor contacta a nuestro equipo de soporte inmediatamente para asegurar la integridad de tu cuenta.
              Correo de soporte: soporte@mrsolutions.com.mx
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
        "Datos actualizados" .
        "Hola {$usNombre},\n\n" .
        "Tu cuenta ha sido actualizada correctamente.\n" .
        "Gracias por confiar en MR Solutions.\n";

    if ($DRY_RUN === false) {
        $mail->send();
    }
} catch (Throwable $ex) {
    // No rompemos el flujo del usuario si el correo falla, solo lo registramos
    $line = "[" . date('Y-m-d H:i:s') . "] usId={$usId} ex=" . $ex->getMessage() . "\nSMTP:\n" . $smtpLog . "\n\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
    error_log("[MRSoS mail bienvenida] " . $line);
}


/* Redirigir a la plataforma */

$_SESSION = [];

// Borrar cookie de sesión si existe
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
exit;
        
    
}

// ------------------ GET: validar token y mostrar formulario ------------------
header('Content-Type: text/html; charset=utf-8');

?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Restablecer contraseña</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Si ya cargas Bootstrap global, puedes omitir esta CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- /Bootstrap -->
    <link href="../css/style.css" rel="stylesheet">
</head>

<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center">
                            <img src="../img/logo MR.webp" style="width: 125px;" class="mb-3" alt="logo">
                            <h4 class="mt-1 mb-5 pb-1">Reseteo de Password</h4>
                        </div>
                        <h4 class="mb-3">Restablecer contraseña</h4>
                            <form method="post" id="form-reset-pass" class="was-validated">
                                <p>Por favor llena los campos con la información solicitada</p>
                                <input type="hidden" name="usId" value="<?php echo $_SESSION['usId']; ?>">
                                <div class="form-outline mb-3">
                                    <label for="pass" class="form-label">Contraseña anterior</label>
                                    <input type="password" class="form-control" id="pass" name="pass" minlength="8" placeholder="*******" required>
                                    <div class="form-text">Mínimo 8 caracteres.</div>
                                    <div class="valid-feedback">Valido.</div>
                                    <div class="invalid-feedback">Por favor llena este campo para proceder.</div>
                                </div>
                                <div class="form-outline mb-3">
                                    <label for="pass1" class="form-label">Nueva contraseña</label>
                                    <input type="password" class="form-control" id="pass1" name="pass1" minlength="8" placeholder="*******" required>
                                    <div class="form-text">Mínimo 8 caracteres.</div>
                                    <div class="valid-feedback">Valido.</div>
                                    <div class="invalid-feedback">Por favor llena este campo para proceder.</div>
                                </div>

                                <div class="form-outline mb-3">
                                    <label for="pass2" class="form-label">Confirmar contraseña</label>
                                    <input type="password" class="form-control" id="pass2" name="pass2" minlength="8" placeholder="*******" required>
                                    <div class="valid-feedback">Valido.</div>
                                    <div class="invalid-feedback">Por favor llena este campo para proceder.</div>
                                </div>

                                <button class="btn btn-primary w-100" type="submit">Guardar nueva contraseña</button>
                            </form>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- (Opcional) Validación UX simple -->
    <script>
        document.getElementById('form-reset-pass')?.addEventListener('submit', function(e) {
            const p1 = document.getElementById('pass1').value;
            const p2 = document.getElementById('pass2').value;
            if (p1.length < 8) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 8 caracteres.');
                return false;
            }
            if (p1 !== p2) {
                e.preventDefault();
                alert('Las contraseñas no coinciden.');
                return false;
            }
        });
    </script>
</body>

</html>