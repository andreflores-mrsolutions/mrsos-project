<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../php/auth_guard.php';
require_login();

$rol = (string)($_SESSION['usRol'] ?? '');
if (!in_array($rol, ['CLI', 'MRSA', 'MRA', 'MRV'], true)) {
    http_response_code(403);
    exit('Sin permisos');
}

require_once __DIR__ . '/../php/csrf.php';
$csrf = csrf_token();

$theme = $_COOKIE['mrs_theme'] ?? ($_SESSION['usTheme'] ?? 'light');

$clId = (int)($_SESSION['clId'] ?? 0);
$csIdSession = (int)($_SESSION['csId'] ?? 0);
$czIdSession = (int)($_SESSION['czId'] ?? 0);
$ucrRol = (string)($_SESSION['ucrRol'] ?? '');

if ($clId <= 0) {
    http_response_code(400);
    exit('Sesión sin cliente.');
}

$prefCsId = isset($_GET['csId']) ? (int)$_GET['csId'] : 0;
$prefPeId = isset($_GET['peId']) ? (int)$_GET['peId'] : 0;
$prefEqId = isset($_GET['eqId']) ? (int)$_GET['eqId'] : 0;

$nombreUsuario = trim(
    (string)($_SESSION['usNombre'] ?? '') . ' ' .
        (string)($_SESSION['usAPaterno'] ?? '') . ' ' .
        (string)($_SESSION['usAMaterno'] ?? '')
);
$nombreUsuario = trim($nombreUsuario) !== '' ? trim($nombreUsuario) : (string)($_SESSION['usUsername'] ?? 'Cliente');

$correoUsuario = (string)($_SESSION['usCorreo'] ?? '');
$telefonoUsuario = (string)($_SESSION['usTelefono'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MR SOS | Nuevo Health Check</title>

    <script>
        window.MRS_CSRF = <?= json_encode(['csrf' => $csrf], JSON_UNESCAPED_UNICODE) ?>;
        window.MRS_CTX = <?= json_encode([
                                'clId' => $clId,
                                'csIdSession' => $csIdSession,
                                'czIdSession' => $czIdSession,
                                'ucrRol' => $ucrRol,
                                'pref' => [
                                    'csId' => $prefCsId,
                                    'peId' => $prefPeId,
                                    'eqId' => $prefEqId,
                                ],
                                'user' => [
                                    'usId' => (int)($_SESSION['usId'] ?? 0),
                                    'nombre' => $nombreUsuario,
                                    'correo' => $correoUsuario,
                                    'telefono' => $telefonoUsuario,
                                ],
                                'baseApi' => 'api'
                            ], JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <link href="../css/style.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

    
</head>

<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">
    <div class="container-fluid">
        <div class="row gx-0">
            <?php $activeMenu = 'health'; ?>
            <?php require_once __DIR__ . '/partials/sidebar_cliente.php'; ?>

            <main class="col-12 col-lg-10">
                <div class="topbar px-3 py-2 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
              <i class="bi bi-list"></i>
            </button>
            <a class="btn btn-sm btn-outline-secondary" href="home.php"><i class="bi bi-arrow-left"></i></a>
                        <span class="badge text-bg-success rounded-pill px-3">Activo</span>
                        <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['usUsername'] ?? 'Admin'); ?></span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="btnThemeDesktop" type="button" title="Tema">
                            <i class="bi bi-moon"></i>
                        </button>
                        <a class="btn btn-sm btn-outline-danger" href="logout.php" title="Salir">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="px-3 py-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <h4 class="mb-0">Nuevo Health Check</h4>
                            <div class="text-muted small">Se registrará a tu nombre y para tus equipos visibles.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-secondary btn-sm" id="btnReload">
                                <i class="bi bi-arrow-clockwise"></i> Recargar
                            </button>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-xl-5">
                            <div class="card mrs-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="fw-semibold"><i class="bi bi-clipboard2-pulse"></i> Datos del Health Check</div>
                                        <span class="badge text-bg-primary-subtle border">Paso 1</span>
                                    </div>

                                    <div id="alertBox" class="alert d-none" role="alert"></div>

                                    <form id="frmHealth" autocomplete="off">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="items_json" id="items_json" value="[]">

                                        <div class="mb-3">
                                            <label class="form-label">Autor</label>
                                            <input class="form-control" type="text" value="<?= htmlspecialchars($nombreUsuario) ?>" disabled>
                                            <div class="form-text">Se guardará <code>con este nombre de contacto</code> desde tu sesión.</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Sede <span class="text-danger">*</span></label>
                                            <select class="form-select" name="csId" id="csId" required>
                                                <option value="">Cargando...</option>
                                            </select>
                                        </div>

                                        <div class="row g-2">
                                            <div class="col-12 col-md-7">
                                                <label class="form-label">Fecha y hora <span class="text-danger">*</span></label>
                                                <input type="datetime-local" class="form-control" id="hcFechaHora" name="hcFechaHora" required>
                                            </div>
                                            <div class="col-12 col-md-5">
                                                <label class="form-label">Duración</label>
                                                <select class="form-select" id="hcDuracionMins" name="hcDuracionMins">
                                                    <option value="60">60 min</option>
                                                    <option value="120">120 min</option>
                                                    <option value="240" selected>240 min</option>
                                                    <option value="480">480 min</option>
                                                </select>
                                            </div>
                                        </div>

                                        <hr class="my-3">

                                        <div class="mb-3">
                                            <label class="form-label">Contacto (nombre) <span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="hcNombreContacto" id="hcNombreContacto" required maxlength="120" value="<?= htmlspecialchars($nombreUsuario) ?>">
                                        </div>

                                        <div class="row g-2">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                                                <input class="form-control" type="text" name="hcNumeroContacto" id="hcNumeroContacto" required maxlength="25" value="<?= htmlspecialchars($telefonoUsuario) ?>">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label">Correo <span class="text-danger">*</span></label>
                                                <input class="form-control" type="email" name="hcCorreoContacto" id="hcCorreoContacto" required maxlength="120" value="<?= htmlspecialchars($correoUsuario) ?>">
                                            </div>
                                        </div>

                                        <div class="selected-box mt-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="fw-semibold"><i class="bi bi-list-check"></i> Equipos seleccionados</div>
                                                <button class="btn btn-sm btn-outline-secondary" id="btnClearSel" type="button">Limpiar</button>
                                            </div>
                                            <div class="small text-muted mt-1" id="selCountText">0 equipos</div>
                                            <div class="mt-2" id="selList"></div>
                                        </div>

                                        <div class="d-grid mt-3">
                                            <button class="btn btn-primary" id="btnCrear" type="submit">
                                                <i class="bi bi-check2-circle"></i> Programar Health Check
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <div class="card mrs-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                        <div class="fw-semibold"><i class="bi bi-grid-3x3-gap"></i> Selecciona equipos</div>
                                        <span class="badge text-bg-primary-subtle border">Paso 2</span>
                                    </div>

                                    <div class="row g-2 align-items-center mb-3">
                                        <div class="col-12 col-md-8">
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                                <input type="text" class="form-control" id="txtBuscarEquipo" placeholder="Buscar por modelo, tipo, SN, póliza...">
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <button class="btn btn-outline-dark w-100" id="btnSelectAll" type="button">
                                                <i class="bi bi-check2-square"></i> Seleccionar visibles
                                            </button>
                                        </div>
                                    </div>

                                    <div id="equiposSkeleton" class="mrs-skeleton-grid">
                                        <div class="mrs-skel"></div>
                                        <div class="mrs-skel"></div>
                                        <div class="mrs-skel"></div>
                                        <div class="mrs-skel"></div>
                                        <div class="mrs-skel"></div>
                                        <div class="mrs-skel"></div>
                                    </div>

                                    <div class="row" id="equiposGrid" style="display:none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1100;">
                    <div id="toastSuccess" class="toast align-items-center text-bg-success border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">OK</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                    <div id="toastError" class="toast align-items-center text-bg-danger border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">Error</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="js/nuevo_health.js"></script>
    <script src="js/theme.js"></script>
</body>

</html>