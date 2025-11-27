<?php

declare(strict_types=1);

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
    $token = isset($_POST['token']) ? preg_replace('/\D+/', '', (string)$_POST['token']) : '';
    $pass1 = trim((string)($_POST['pass1'] ?? ''));
    $pass2 = trim((string)($_POST['pass2'] ?? ''));

    // Validaciones básicas
    if ($usId <= 0 || $token === '' || strlen($pass1) < 8 || $pass1 !== $pass2) {
        http_response_code(400);
        echo "<p>Error: datos inválidos o contraseñas no coinciden (mínimo 8 caracteres).</p>";
        exit;
    }

    // Buscar usuario con token vigente
    $stmt = $pdo->prepare("
    SELECT usId, usResetToken, usResetTokenExpira, usEstatus
    FROM usuarios
    WHERE usId = ? AND usResetToken = ? AND usResetTokenExpira >= ?
    LIMIT 1
  ");
    $stmt->execute([$usId, $token, $now]);
    $user = $stmt->fetch();

    if (!$user) {
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
                            <h4 class="mt-1 mb-5 pb-1">Reseteo de Password</h4>
                        </div>
                        <h4 class="mb-3">Restablecer contraseña</h4>
                        <div class="alert alert-danger">
                            <p>El enlace es inválido o ha expirado. Solicita un nuevo restablecimiento. <a href="login.php">Ir al login</a></p>
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
    SET usPass = ?, usResetToken = 0, usResetTokenExpira = '1970-01-01 00:00:00', usEstatus = 'Activo'
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
    exit;
}

// ------------------ GET: validar token y mostrar formulario ------------------
header('Content-Type: text/html; charset=utf-8');

$usId  = isset($_GET['usId']) ? (int)$_GET['usId'] : 0;
// Mantener token como string
$token = isset($_GET['token']) ? preg_replace('/\D+/', '', (string)$_GET['token']) : '';

$valid = false;
if ($usId > 0 && $token !== '') {
    $stmt = $pdo->prepare("
    SELECT usId
    FROM usuarios
    WHERE usId = ? AND usResetToken = ? AND usResetTokenExpira >= ?
    LIMIT 1
  ");
    $stmt->execute([$usId, $token, $now]);
    $valid = (bool)$stmt->fetch();
}
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

                        <?php if (!$valid): ?>
                            <div class="alert alert-danger">
                                El enlace es inválido o ha expirado. Por favor solicita un nuevo restablecimiento desde la página de inicio de sesión.
                            </div>
                        <?php else: ?>
                            <form method="post" id="form-reset-pass" class="was-validated">
                                <p>Por favor llena los campos con la información solicitada</p>
                                <input type="hidden" name="usId" value="<?= h((string)$usId) ?>">
                                <input type="hidden" name="token" value="<?= h($token) ?>">

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
                        <?php endif; ?>
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