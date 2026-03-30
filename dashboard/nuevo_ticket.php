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
    <title>MR SOS | Nuevo Ticket</title>

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
            <?php $activeMenu = 'ticket'; ?>
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
                            <h4 class="mb-0">Nuevo Ticket</h4>
                            <div class="text-muted small">
                                El ticket se registrará a tu nombre y con tus datos como contacto inicial.
                            </div>
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
                                        <div class="fw-semibold"><i class="bi bi-ui-checks-grid"></i> Datos del ticket</div>
                                        <span class="badge text-bg-primary-subtle border">Paso 1</span>
                                    </div>

                                    <div id="alertBox" class="alert d-none" role="alert"></div>

                                    <form id="frmTicket" autocomplete="off">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="eqId" id="eqId" value="">
                                        <input type="hidden" name="peId" id="peId" value="">

                                        <div class="mb-3">
                                            <label class="form-label">Autor</label>
                                            <input class="form-control" type="text" value="<?= htmlspecialchars($nombreUsuario) ?>" disabled>
                                            <div class="form-text">Se guardará <code>con el nombre de contacto</code> desde la sesión actual.</div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Sede <span class="text-danger">*</span></label>
                                            <select class="form-select" name="csId" id="csId" required>
                                                <option value="">Cargando...</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Tipo de ticket</label>
                                            <select class="form-select" name="tiTipoTicket" id="tiTipoTicket">
                                                <option value="Servicio">Servicio</option>
                                                <option value="Preventivo">Preventivo</option>
                                                <option value="Extra">Extra</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Nivel de criticidad <span class="text-danger">*</span></label>
                                            <div class="row g-2">
                                                <div class="col-12">
                                                    <input class="form-check-input" type="radio" name="tiNivelCriticidad" id="crit1" value="1" checked>
                                                    <label class="form-check-label ms-2" for="crit1">1 (Crítico)</label>
                                                    <p class="fst-italic text-muted text-justify" for="crit1">El Nivel 1 corresponde a incidentes críticos que implican la caída total del producto, equipo o uno o más de sus subsistemas, generando una interrupción completa de un servicio crítico del cliente.
                                                        La afectación del servicio pone en riesgo directo la continuidad operativa, beneficios o ingresos monetarios del cliente.
                                                    </p>
                                                </div>
                                                <div class="col-12">
                                                    <input class="form-check-input" type="radio" name="tiNivelCriticidad" id="crit2" value="2">
                                                    <label class="form-check-label ms-2" for="crit2">2 (Alta)</label>
                                                    <p class="fst-italic text-muted text-justify" for="crit2">El Nivel 2 corresponde a incidentes donde el servicio o equipo no está disponible o se encuentra seriamente degradado, afectando a uno o varios usuarios, pero sin representar una caída total del servicio crítico.
                                                        La pérdida del servicio puede generar reducción importante en la productividad y eventualmente afectar beneficios o ingresos si no se corrige.
                                                    </p>
                                                </div>
                                                <div class="col-12">
                                                    <input class="form-check-input" type="radio" name="tiNivelCriticidad" id="crit3" value="3">
                                                    <label class="form-check-label ms-2" for="crit3">3 (Media/Baja)</label>
                                                    <p class="fst-italic text-muted text-justify" for="crit3">El Nivel 3 corresponde a incidentes donde el servicio o equipo presenta una afectación menor, permitiendo que el usuario continúe operando, aunque con ciertas limitaciones.
                                                        El incidente puede provocar reducciones menores de productividad, pero no compromete la operación general del cliente.</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Contacto (nombre) <span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="tiNombreContacto" id="tiNombreContacto" required maxlength="120" value="<?= htmlspecialchars($nombreUsuario) ?>">
                                        </div>

                                        <div class="row g-2">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                                                <input class="form-control" type="text" name="tiNumeroContacto" id="tiNumeroContacto" required maxlength="25" value="<?= htmlspecialchars($telefonoUsuario) ?>">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label">Correo <span class="text-danger">*</span></label>
                                                <input class="form-control" type="email" name="tiCorreoContacto" id="tiCorreoContacto" required maxlength="120" value="<?= htmlspecialchars($correoUsuario) ?>">
                                            </div>
                                        </div>

                                        <div class="mt-3 mb-2">
                                            <label class="form-label">Descripción del problema <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="tiDescripcion" id="tiDescripcion" rows="5" required></textarea>
                                        </div>

                                        <div class="selected-box mt-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="fw-semibold"><i class="bi bi-cpu"></i> Equipo seleccionado</div>
                                                <button class="btn btn-sm btn-outline-secondary" id="btnClearEquipo" type="button">Limpiar</button>
                                            </div>
                                            <div class="small text-muted mt-1" id="selEquipoText">Aún no seleccionas un equipo.</div>
                                        </div>

                                        <div class="d-grid mt-3">
                                            <button class="btn btn-primary" id="btnCrear" type="submit">
                                                <i class="bi bi-check2-circle"></i> Crear ticket
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
                                        <div class="fw-semibold"><i class="bi bi-grid-3x3-gap"></i> Selecciona el equipo</div>
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
                                            <select class="form-select" id="fltTicketActivo">
                                                <option value="all">Todos</option>
                                                <option value="with">Con ticket activo</option>
                                                <option value="without">Sin ticket activo</option>
                                            </select>
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

    <script src="js/nuevo_ticket.js"></script>
    <script src="js/theme.js"></script>
    
</body>

</html>