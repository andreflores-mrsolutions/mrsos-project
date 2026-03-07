<?php
// admin/tickets.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../php/auth_guard.php';
require_login();

// Ajusta roles a los tuyos reales.
// Si tu sistema usa usRol con MRA/MRSA, agrega una validación aquí.
$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA', 'MRSA', 'ADMIN'], true)) {
    http_response_code(403);
    exit('Sin permisos');
}

require_once __DIR__ . '/../php/csrf.php';
$csrf = csrf_token();
if (empty($_SESSION['usId'])) {
    header('Location: ../login/login.php');
    exit;
}

$clId = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
if ($clId <= 0) {
    http_response_code(400);
    echo "Falta clId";
    exit;
}

$theme = $_COOKIE['mrs_theme'] ?? 'light';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <script>
        window.MRS_CSRF = <?= json_encode(['csrf' => $csrf], JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MR SOS | Tickets</title>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/css.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">

    <style>

    </style>
</head>

<body class="<?php echo ($theme === 'dark') ? 'dark-mode' : ''; ?>">
    <div class="container-fluid">
        <div class="row gx-0">

            <nav id="sidebar" class="col-12 col-md-3 col-lg-2 d-none d-lg-block p-3 mr-side">
                <div class="brand mb-3 px-2">
                    <a class="navbar-brand" href="#">
                        <img src="../img/image.png" alt="Logo" class="rounded-pill" style="max-width: 120px;">
                    </a>
                </div>

                <div class="section-title px-2">Operación</div>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="nuevo_ticket.php"><i class="bi bi-shield-check"></i> Health Checks</a></li>
                    <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nuevo Cliente</a></li>
                    <li class="nav-item"><a class="nav-link" href="nuevo_usuario.php"><i class="bi bi-plus-circle"></i> Nuevo Usuario</a></li>
                </ul>

                <div class="section-title pt-2">Creación</div>
                <div class="section-subtitle ">Tickets</div>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-plus-circle"></i> Nuevo Ticket</a></li>
                    <li class="nav-item"><a class="nav-link" href="nuevo_health.php"><i class="bi bi-plus-circle"></i> Nuevo Health Check</a></li>
                </ul>

                <div class="section-subtitle ">Cliente</div>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nuevo Cliente</a></li>
                    <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nueva Zona</a></li>
                    <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nueva Sede</a></li>

                </ul>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item"><a class="nav-link" href="nuevo_usuario.php"><i class="bi bi-plus-circle"></i> Nuevo Usuario</a></li>
                    <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nuevo Ingeniero</a></li>
                </ul>


                <div class="section-title px-2 mt-3">Gestión</div>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-gear"></i> Pólizas</a></li>
                    <li class="nav-item"><a class="nav-link" href="hojas_de_servicio.php"><i class="bi bi-download"></i> Hojas de Servicio</a></li>
                </ul>

                <div class="section-title px-2 mt-3">Administración</div>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item"><a class="nav-link" href="admin_usuarios.php"><i class="bi bi-shield-lock"></i> Panel Administrador</a></li>
                </ul>

                <div class="section-title px-2 mt-3">General</div>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item"><a class="nav-link" href="misequipos.php"><i class="bi bi-cpu"></i> Equipos</a></li>
                    <li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-person"></i> Mis datos</a></li>
                </ul>
            </nav>

            <main class="col-12 col-lg-10">
                <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <a class="btn btn-sm btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i></a>
                        <span class="badge text-bg-success rounded-pill px-3">Activo</span>
                        <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['usUsername'] ?? 'Admin'); ?></span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="btnTheme" type="button" title="Tema">
                            <i class="bi bi-moon"></i>
                        </button>
                        <a class="btn btn-sm btn-outline-danger" href="../dashboard/logout.php" title="Salir">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="p-3 p-lg-4">
                    <div class="panel">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <div>
                                <h4 class="fw-bold mb-1">Tickets por sede</h4>
                                <div class="muted">Visualiza los tickets abiertos agrupados por cada sede del cliente.</div>
                                <div class="mt-2">
                                    <span class="muted">Cliente:</span>
                                    <span class="fw-bold" id="lblCliente">Cargando...</span>
                                    <span class="muted ms-2">| Total:</span>
                                    <span class="fw-bold" id="lblTotal">—</span>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <div class="btn-group" id="vistaToggle" role="group">
                                    <button class="btn btn-sm btn-outline-secondary active" data-vista="tabla">Tabla</button>
                                    <button class="btn btn-sm btn-outline-secondary" data-vista="cards">Cards</button>
                                    <button class="btn btn-sm btn-outline-secondary" id="btnReload"><i class="bi bi-arrow-clockwise"></i> Recargar</button>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3" style="opacity:.12;">

                        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                            <div class="btn-group" id="tabScope" role="group">
                                <button class="btn btn-sm btn-outline-secondary active" data-scope="todo">Ver Todo</button>
                                <button class="btn btn-sm btn-outline-secondary" data-scope="acciones">Acciones Requeridas</button>
                                <button class="btn btn-sm btn-outline-secondary" data-scope="recientes">Vistos Recientemente</button>
                            </div>

                            <div class="btn-group ms-auto" id="tabEstado" role="group">
                                <button class="btn btn-sm btn-outline-secondary active" data-estado="Abierto">Abiertos</button>
                                <button class="btn btn-sm btn-outline-secondary" data-estado="Pospuesto">Pospuesto</button>
                                <button class="btn btn-sm btn-outline-secondary" data-estado="all">Todos</button>
                            </div>

                            <div class="input-group" style="max-width: 320px;">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input id="searchTickets" class="form-control" placeholder="Buscar (modelo, SN, código)">
                                <button class="btn btn-outline-secondary" id="btnClear">Limpiar</button>
                                <button class="btn btn-outline-primary" id="btnReset">Restablecer</button>
                            </div>
                        </div>

                        <div id="wrapTickets"></div>

                        <div id="emptyState" class="empty-state mt-3 d-none">
                            <div class="fw-bold mb-1">Sin resultados</div>
                            <div class="muted mb-3">Ajusta filtros o borra la búsqueda para regresar.</div>
                            <button class="btn btn-primary btn-sm" id="btnReset2">
                                <i class="bi bi-arrow-counterclockwise"></i> Restablecer
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- OFFCANVAS: Detalles del Ticket -->

    <div class="offcanvas offcanvas-end" tabindex="-1" id="offTicket" aria-labelledby="offTicketLabel" style="width: 420px;">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title fw-bold" id="offTicketLabel">Detalles del Ticket</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body">

            <!-- Imagen equipo -->
            <div class="mb-3">
                <img id="offImgEquipo" src="../img/placeholder_equipo.png" class="w-100 rounded-3" style="max-height:160px; object-fit:contain; background:rgba(0,0,0,.02); border:1px solid rgba(15,23,42,.10);" alt="Equipo">
            </div>

            <!-- HEADER -->
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-bold" id="offCodigo">—</div>
                        <div class="fw-semibold" style="font-size:.95rem;" id="offEquipo">—</div>
                        <div class="muted" style="font-size:.85rem;" id="offSN">—</div>
                    </div>

                    <div class="text-end">
                        <div class="d-flex gap-2 justify-content-end align-items-center">
                            <span id="offCrit"></span>
                            <span id="offEstado"></span>
                        </div>
                        <div class="muted mt-1" style="font-size:.8rem;" id="offMarca"></div>
                    </div>
                </div>
            </div>

            <hr class="my-3" style="opacity:.12;">

            <!-- BLOQUE 1 – ACCIÓN ACTUAL -->
            <div class="mb-3">
                <div class="fw-bold mb-2">Acción requerida</div>
                <div id="offAccionBox"></div>
            </div>

            <!-- BLOQUE 2 – PROGRESO -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-bold">Progreso</div>
                    <div class="muted" id="offPasoActual" style="font-size:.85rem;">—</div>
                </div>

                <div class="progress" style="height:8px; border-radius:999px;">
                    <div class="progress-bar" id="offProgressBar" style="width:0%; border-radius:999px;"></div>
                </div>

                <div class="d-flex justify-content-center mt-1">
                    <div class="muted" style="font-size:.85rem;"><span id="offProgressText">—</span></div>
                </div>
            </div>

            <hr class="my-3" style="opacity:.12;">

            <!-- BLOQUE 3 – MENSAJE CLARO -->
            <div class="mb-3">
                <div class="fw-bold mb-2">Descripción</div>
                <div class="muted" id="offMensaje" style="line-height:1.25;">—</div>
            </div>

            <hr class="my-3" style="opacity:.12;">

            <!-- BLOQUE 4 – HISTORIAL CORTO -->
            <div class="mb-2">
                <div class="fw-bold mb-2">Historial</div>
                <ul class="list-group" id="offHistorial"></ul>
                <div class="muted mt-2" style="font-size:.8rem;">Se muestran los últimos 3 eventos.</div>
            </div>

            <!-- FOOTER -->
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-outline-secondary flex-grow-1" id="offBtnAyuda">
                    <i class="bi bi-question-circle"></i> Ayuda
                </button>
                <button class="btn btn-primary flex-grow-1" id="offBtnAccion">
                    Continuar
                </button>
            </div>

        </div>
    </div>
    <!-- OFFCANVAS: Asignar Ingeniero (ADMIN) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offAsignarIng" aria-labelledby="offAsignarIngLabel" style="width: 980px;">
        <div class="offcanvas-header">
            <div>
                <h5 class="offcanvas-title fw-bold" id="offAsignarIngLabel">Asignación</h5>
                <div class="text-muted" style="font-size:.9rem;">Es momento de asignar un ingeniero, elige la mejor opción</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body">
            <!-- contexto del ticket -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <div class="fw-bold" id="asgTicketCodigo">—</div>
                    <div class="text-muted" style="font-size:.9rem;" id="asgTicketEquipo">—</div>
                    <div class="text-muted" style="font-size:.85rem;" id="asgTicketSN">—</div>
                </div>

                <div class="d-flex gap-2">
                    <span class="badge rounded-pill text-bg-light" id="asgTicketPaso">—</span>
                    <span class="badge rounded-pill text-bg-secondary" id="asgTicketEstado">—</span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <div class="input-group" style="max-width:420px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="asgSearch" class="form-control" placeholder="Buscar ingeniero (nombre, usuario, correo, experto)">
                    <button class="btn btn-outline-secondary" id="asgClear">Limpiar</button>
                </div>

                <div class="ms-auto d-flex gap-2">
                    <select id="asgFiltroExperto" class="form-select" style="max-width:210px;">
                        <option value="">Experto: Todos</option>
                    </select>
                    <select id="asgFiltroTier" class="form-select" style="max-width:160px;">
                        <option value="">Tier: Todos</option>
                        <option value="Tier 1">Tier 1</option>
                        <option value="Tier 2">Tier 2</option>
                        <option value="Tier 3">Tier 3</option>
                    </select>
                </div>
            </div>

            <div id="asgLoading" class="text-muted">Cargando ingenieros…</div>
            <div id="asgEmpty" class="alert alert-warning d-none">No hay ingenieros que coincidan con el filtro.</div>

            <div id="asgWrap"></div>

            <div class="mt-3 d-flex justify-content-end gap-2">
                <button class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cancelar</button>
            </div>
        </div>
    </div>
    <!-- OFFCANVAS: Revisión inicial (ADMIN) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offRevisionInicial" aria-labelledby="offRevisionInicialLabel" style="width: 460px;">
        <div class="offcanvas-header">
            <div>
                <h5 class="offcanvas-title fw-bold mb-0" id="offRevisionInicialLabel">Revisión inicial</h5>
                <div class="muted" style="font-size:.9rem;">Registra el análisis inicial para orientar el diagnóstico y definir el siguiente paso.</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body">

            <!-- Hero (como tu imagen) -->
            <div class="text-center mb-3">
                <div class="d-inline-flex align-items-center justify-content-center rounded-4 mb-2"
                    style="width:86px;height:86px;background:rgba(59,130,246,.10);border:1px solid rgba(59,130,246,.18);">
                    <i class="bi bi-search" style="font-size:38px;"></i>
                </div>
                <div class="fw-bold" style="font-size:1.05rem;">Análisis</div>
                <div class="muted" style="font-size:.9rem; line-height:1.2;">
                    Añade un análisis al ticket levantado. Si aún no hay información suficiente, escribe
                    <b>“Faltan datos”</b>.
                </div>
            </div>

            <!-- Contexto del ticket -->
            <div class="p-3 rounded-4 mb-3" style="border:1px solid rgba(15,23,42,.10); background:rgba(255,255,255,.6);">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-bold" id="revCodigo">—</div>
                        <div class="muted" style="font-size:.9rem;" id="revEquipo">—</div>
                        <div class="muted" style="font-size:.85rem;" id="revSN">—</div>
                    </div>
                    <div class="text-end">
                        <div class="d-flex gap-2 justify-content-end">
                            <span id="revCrit">—</span>
                            <span id="revEstado">—</span>
                        </div>
                        <div class="muted mt-1" style="font-size:.8rem;" id="revPaso">Paso: Revisión inicial</div>
                    </div>
                </div>
            </div>

            <!-- Campo principal -->
            <div class="mb-2">
                <label class="form-label fw-bold mb-1" for="revDiagnostico">Descripción del análisis</label>
                <textarea id="revDiagnostico" class="form-control" rows="6"
                    placeholder="Describe el análisis inicial (síntomas, contexto, hipótesis, evidencias, impacto).&#10;Si no hay información suficiente: “Faltan datos”."></textarea>

                <div class="d-flex justify-content-between mt-2">
                    <div class="muted" style="font-size:.85rem;">
                        Tip: escribe en formato corto tipo checklist para mejor lectura.
                    </div>
                    <div class="muted" style="font-size:.85rem;">
                        <span id="revCount">0</span>/1200
                    </div>
                </div>
            </div>

            <!-- Atajos / Plantillas -->
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-sm btn-outline-secondary" id="revTplFaltan">Insertar “Faltan datos”</button>
                <button class="btn btn-sm btn-outline-secondary" id="revTplChecklist">Insertar checklist</button>
                <button class="btn btn-sm btn-outline-secondary" id="revLimpiar">Limpiar</button>
            </div>

            <hr class="my-3" style="opacity:.12;">

            <!-- Siguiente paso -->
            <div class="mb-3">
                <label class="form-label fw-bold mb-1">Siguiente paso</label>
                <select id="revNext" class="form-select">
                    <option value="logs" selected>Logs (cliente)</option>
                    <option value="meet">Meet (si aplica)</option>
                    <option value="revision especial">Revisión especial</option>
                    <option value="espera refaccion">Espera refacción</option>
                    <option value="visita">Visita</option>
                </select>
                <div class="muted mt-2" style="font-size:.85rem;">
                    Recomendado: <b>Logs</b> para reunir evidencia antes de avanzar.
                </div>
            </div>

            <!-- Footer -->
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary flex-grow-1" data-bs-dismiss="offcanvas">
                    Cancelar
                </button>
                <button class="btn btn-primary flex-grow-1" id="revGuardar">
                    Guardar y continuar
                </button>
            </div>

            <div id="revMsg" class="mt-3"></div>
        </div>
    </div>


    <!-- OFFCANVAS: Ver logs (ADMIN) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offVerLogs" aria-labelledby="offVerLogsLabel" style="width: 980px;">
        <div class="offcanvas-header">
            <div>
                <h5 class="offcanvas-title fw-bold" id="offVerLogsLabel">Ver Logs</h5>
                <div class="text-muted" style="font-size:.9rem;">Revisa y descarga los logs del ticket</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body">
            <!-- contexto del ticket -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <div class="fw-bold" id="asgTicketCodigoLogs">—</div>
                    <div class="text-muted" style="font-size:.9rem;" id="asgTicketEquipoLogs">—</div>
                    <div class="text-muted" style="font-size:.85rem;" id="asgTicketSNLogs">—</div>
                </div>

                <div class="d-flex gap-2">
                    <span class="badge rounded-pill text-bg-light" id="asgTicketPasoLogs">—</span>
                    <span class="badge rounded-pill text-bg-secondary" id="asgTicketEstadoLogs">—</span>
                </div>
            </div>

            <div id="logsLoading" class="text-muted">Cargando logs...</div>
            <div id="logsEmpty" class="alert alert-warning d-none">No hay logs para este ticket.</div>

            <div class="row g-3 mt-1">
                <!-- LISTA -->
                <div class="col-12 col-lg-4">
                    <div class="p-3 border rounded-4" style="background:#fff;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-bold">Archivos</div>
                            <span class="text-muted" style="font-size:.85rem;" id="logsCount">—</span>
                        </div>
                        <div id="logsList"></div>
                    </div>
                </div>

                <!-- VISOR -->
                <div class="col-12 col-lg-8">
                    <div class="p-3 border rounded-4 h-100" style="background:#fff;">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-bold" id="asgLogViewTitle">Selecciona un archivo</div>
                                <div class="text-muted" style="font-size:.85rem;" id="asgLogViewMeta">—</div>
                            </div>
                            <div class="d-flex gap-2">
                                <a class="btn btn-outline-secondary btn-sm d-none" id="btnDownloadLog" href="#" download>
                                    <i class="bi bi-download"></i> Descargar
                                </a>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 d-none" id="logTooLarge">
                            Este archivo es muy extenso para mostrarse en pantalla. Descárgalo para revisarlo completo.
                        </div>

                        <pre id="logViewer"
                            class="mt-3 p-3 rounded-3"
                            style="min-height:420px; max-height:620px; overflow:auto; background:#0b1220; color:#e5e7eb; font-size:.85rem; border:1px solid rgba(15,23,42,.10);"></pre>
                    </div>
                </div>
            </div>
            <!-- Acciones de Logs (ADMIN) -->
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <button class="btn btn-outline-warning" id="btnSolicitarLogs">
                    <i class="bi bi-bell"></i> Solicitar nuevamente logs
                </button>

                <button class="btn btn-outline-success" id="btnLogsOK">
                    <i class="bi bi-check2-circle"></i> Logs correctos · Continuar
                </button>

                <div class="ms-auto text-muted" style="font-size:.9rem;" id="logsHint">
                    Si los archivos no coinciden o son antiguos, solicita nuevamente los logs.
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-end gap-2">

                <button class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cerrar</button>
            </div>
        </div>
    </div>


    <!-- OFFCANVAS: Meet (Admin) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offMeet" aria-labelledby="offMeetLabel" style="width: 440px;">
        <div class="offcanvas-header">
            <div>
                <h5 class="offcanvas-title fw-bold" id="offMeetLabel">Meet de apoyo</h5>
                <div class="muted" style="font-size:.85rem;">
                    El cliente propone 3 horarios. El ingeniero/admin confirma 1.
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body">
            <!-- Header ticket -->
            <div class="p-3 rounded-3 mb-3" style="border:1px solid rgba(15,23,42,.10);">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-bold" id="meetCodigo">—</div>
                        <div class="muted" style="font-size:.9rem;" id="meetEquipo">—</div>
                        <div class="muted" style="font-size:.85rem;" id="meetSN">—</div>
                    </div>
                    <div class="text-end">
                        <span id="meetEstadoBadge" class="badge text-bg-secondary">—</span>
                        <div class="muted mt-1" style="font-size:.8rem;" id="meetAutor"> </div>
                    </div>
                </div>
            </div>

            <!-- Estado -->
            <div class="mb-3">
                <div class="fw-bold mb-2">Estado del Meet</div>
                <div id="meetStatusBox" class="p-3 rounded-3" style="border:1px solid rgba(15,23,42,.10);">
                    <div class="muted">Cargando…</div>
                </div>
            </div>

            <!-- Propuestas -->
            <div class="mb-3">
                <div class="fw-bold mb-2">Opciones propuestas</div>
                <div id="meetOptions" class="d-grid gap-2"></div>
                <div class="muted mt-2" style="font-size:.8rem;">
                    Al aceptar una opción, el Meet queda confirmado y el resto se rechaza.
                </div>
            </div>

            <!-- Proponer por ingeniero (opcional) -->
            <div class="mb-2" id="meetProponerWrap">
                <div class="fw-bold mb-2">Proponer horarios (Ingeniero)</div>

                <div class="p-3 rounded-3" style="border:1px solid rgba(15,23,42,.10);">
                    <div class="muted mb-2" style="font-size:.85rem;">
                        Úsalo cuando el cliente no pueda proponer o para planes de trabajo. (No es lo común, pero está disponible.)
                    </div>

                    <div class="mb-2">
                        <label class="form-label muted mb-1" style="font-size:.85rem;">Opción 1</label>
                        <input type="datetime-local" class="form-control" id="meetOp1">
                    </div>
                    <div class="mb-2">
                        <label class="form-label muted mb-1" style="font-size:.85rem;">Opción 2</label>
                        <input type="datetime-local" class="form-control" id="meetOp2">
                    </div>
                    <div class="mb-2">
                        <label class="form-label muted mb-1" style="font-size:.85rem;">Opción 3</label>
                        <input type="datetime-local" class="form-control" id="meetOp3">
                    </div>

                    <div class="mb-2">
                        <label class="form-label muted mb-1" style="font-size:.85rem;">Plataforma (opcional)</label>
                        <select class="form-select" id="meetPlataforma">
                            <option value="">—</option>
                            <option value="Teams">Teams</option>
                            <option value="Google Meet">Google Meet</option>
                            <option value="Zoom">Zoom</option>
                            <option value="Llamada">Llamada</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label muted mb-1" style="font-size:.85rem;">Motivo (opcional)</label>
                        <input class="form-control" id="meetMotivo" placeholder="Ej: Apoyo extracción de logs">
                    </div>

                    <button class="btn btn-outline-primary w-100" id="btnMeetProponer">
                        <i class="bi bi-calendar-plus"></i> Enviar 3 opciones
                    </button>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-outline-secondary flex-grow-1" id="btnMeetReload">
                    <i class="bi bi-arrow-clockwise"></i> Recargar
                </button>
                <button class="btn btn-primary flex-grow-1" id="btnMeetCerrar" data-bs-dismiss="offcanvas">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- OFFCANVAS: Logs · Acción (ADMIN) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offLogsAccion" aria-labelledby="offLogsAccionLabel" style="width: 520px;">
        <div class="offcanvas-header">
            <div>
                <h5 class="offcanvas-title fw-bold" id="offLogsAccionLabel">Acción</h5>
                <div class="text-muted" style="font-size:.9rem;" id="offLogsAccionSub">—</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body">
            <!-- Contexto -->
            <div class="p-3 rounded-4 border bg-white mb-3">
                <div class="fw-bold" id="laCodigo">—</div>
                <div class="text-muted" style="font-size:.9rem;" id="laEquipo">—</div>
                <div class="text-muted" style="font-size:.85rem;" id="laSN">—</div>
            </div>

            <!-- Texto -->
            <label class="form-label fw-bold" id="laLabel">Descripción</label>
            <textarea class="form-control" id="laTexto" rows="6" maxlength="1200"
                placeholder="Describe el diagnóstico / motivo (síntomas, evidencia, impacto). Si no hay info suficiente: “Faltan datos”."></textarea>

            <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="text-muted" style="font-size:.85rem;">
                    Tip: usa formato corto tipo checklist para mejor lectura.
                </div>
                <div class="text-muted" style="font-size:.85rem;">
                    <span id="laCount">0</span>/1200
                </div>
            </div>

            <!-- Autorrellenos -->
            <div class="d-flex flex-wrap gap-2 mt-2">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-fill="Faltan datos">Insertar “Faltan datos”</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-fill="Falla de disco duro">Falla de disco duro</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-fill="Logs antiguos (fecha fuera de ventana) · Solicito recaptura.">Logs antiguos</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-fill="Logs incorrectos (no corresponde al equipo / SN) · Solicito logs correctos.">Logs incorrectos</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-fill="Checklist:\n- Síntoma:\n- Evidencia:\n- Hipótesis:\n- Impacto:\n- Siguiente paso:">Insertar checklist</button>
                <button class="btn btn-outline-danger btn-sm ms-auto" type="button" id="laClear">Limpiar</button>
            </div>

            <!-- Siguiente paso (solo cuando sea “continuar”) -->
            <div class="mt-3 d-none" id="laNextWrap">
                <label class="form-label fw-bold">Siguiente paso del ticket</label>
                <select class="form-select" id="laNextStep"></select>
                <div class="text-muted mt-1" style="font-size:.85rem;">
                    Elige el proceso al que se moverá el ticket. (Admin decide)
                </div>
            </div>

            <hr class="my-3" style="opacity:.15;">

            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary w-50" data-bs-dismiss="offcanvas">Cancelar</button>
                <button class="btn btn-primary w-50" id="laSubmit">Guardar</button>
            </div>
        </div>
    </div>
    <!-- Toast container -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055">
        <div id="toastSuccess" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ✅ Ticket creado exitosamente.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        <div id="toastError" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ❌ Error al crear el ticket.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- OFFCANVAS: Visita (ADMIN) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offVisita" aria-labelledby="offVisitaLabel" style="width: 980px;">
        <div class="offcanvas-header">
            <div>
                <h5 class="offcanvas-title fw-bold" id="offVisitaLabel">Visita</h5>
                <div class="text-muted" style="font-size:.9rem;">Coordina y confirma la visita del ticket</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body">

            <!-- Contexto ticket -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <div class="fw-bold" id="viTicketCodigo">—</div>
                    <div class="text-muted" style="font-size:.9rem;" id="viTicketEquipo">—</div>
                    <div class="text-muted" style="font-size:.85rem;" id="viTicketSN">—</div>
                </div>

                <div class="d-flex gap-2">
                    <span class="badge rounded-pill text-bg-light" id="viTicketPaso">—</span>
                    <span class="badge rounded-pill text-bg-secondary" id="viTicketEstado">—</span>
                    <span id="viTicketCrit"></span>
                </div>
            </div>

            <hr class="my-3" style="opacity:.12;">

            <!-- BLOQUE 1: Ventanas propuestas -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <div class="fw-bold">Ventanas propuestas</div>
                    <div class="text-muted" style="font-size:.85rem;" id="viEstadoMini">—</div>
                </div>

                <div class="alert alert-light border" id="viNoPropuestas">
                    Aún no hay ventanas propuestas. El cliente debe proponer 3 opciones (o el ingeniero/admin puede proponer si aplica).
                </div>

                <div id="viPropuestasWrap" class="d-none">
                    <div class="row g-2" id="viPropuestasGrid"></div>

                    <div class="text-muted mt-2" style="font-size:.85rem;">
                        Nota: al confirmar una ventana, se bloquea la cancelación del lado cliente (salvo administración).
                    </div>
                </div>
            </div>

            <!-- BLOQUE 2: Confirmación / Bloqueo -->
            <div class="mb-3">
                <div class="fw-bold mb-2">Confirmación</div>

                <div class="p-3 border rounded-4 bg-white">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <div class="fw-semibold">Ventana confirmada</div>
                            <div class="text-muted" style="font-size:.9rem;" id="viConfirmadaTxt">—</div>
                        </div>

                        <div class="d-flex gap-2 align-items-center">
                            <span class="badge rounded-pill text-bg-light border" id="viLockBadge">—</span>
                            <button class="btn btn-outline-danger btn-sm" id="viBtnUnlock">Desbloquear cancelación</button>
                        </div>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-primary" id="viBtnConfirmarVentanaPaso">
                            Confirmar y pasar a siguiente paso
                        </button>
                        <button class="btn btn-outline-secondary" id="viBtnNotificar">
                            Notificar cliente
                        </button>
                    </div>
                </div>
            </div>

            <!-- BLOQUE 3: Folio / Autorización -->
            <div class="mb-3">
                <div class="fw-bold mb-2">Folio de entrada / autorización</div>

                <div class="p-3 border rounded-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fw-semibold" id="viFolioNombre">—</div>
                            <div class="text-muted" style="font-size:.85rem;" id="viFolioEstado">Sin archivo</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm" id="viBtnVerFolio">Ver</button>
                            <button class="btn btn-outline-secondary btn-sm" id="viBtnDescargarFolio">Descargar</button>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label text-muted" style="font-size:.9rem;">Subir nuevo folio (PDF/JPG/PNG)</label>
                        <input type="file" class="form-control" id="viFolioFile" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="mt-2 d-flex gap-2">
                            <button class="btn btn-success" id="viBtnSubirFolio">Subir folio</button>
                            <button class="btn btn-outline-secondary" id="viBtnSolicitarFolio">Solicitar folio al cliente</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BLOQUE 4: Datos de acceso (ingeniero/visita) -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-bold">Datos para acceso al sitio</div>
                <a class="btn btn-outline-primary btn-sm" id="viBtnEditarPaquete" href="#" target="_blank">
                    <i class="bi bi-pencil-square"></i> Editar Paquete
                </a>
            </div>
            <div class="mb-3">
                <div class="p-3 border rounded-4 bg-white" id="viAccesoWrap">
                    <div class="text-muted">—</div>
                </div>
            </div>

            <!-- BLOQUE 5: Historial corto -->
            <div class="mb-3">
                <div class="fw-bold mb-2">Historial (últimos 3)</div>
                <div class="p-3 border rounded-4 bg-white" id="viHistorialWrap">
                    <div class="text-muted">—</div>
                </div>
            </div>

            <!-- BLOQUE 6: Acciones admin -->
            <div class="mb-4">
                <div class="fw-bold mb-2">Acciones</div>

                <div class="p-3 border rounded-4 bg-white">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-lg-6">
                            <label class="form-label text-muted" style="font-size:.9rem;">Avanzar proceso</label>
                            <select class="form-select" id="viNextProceso">
                                <option value="">Selecciona…</option>
                                <option value="fecha asignada">fecha asignada</option>
                                <option value="espera ventana">espera ventana</option>
                                <option value="espera visita">espera visita</option>
                                <option value="en camino">en camino</option>
                                <option value="espera documentacion">espera documentacion</option>
                                <option value="finalizado">finalizado</option>
                                <option value="cancelado">cancelado</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label text-muted" style="font-size:.9rem;">Nota / motivo (opcional)</label>
                            <input class="form-control" id="viNotaAccion" placeholder="Ej: Ventana confirmada, logística en curso…">
                        </div>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" id="viBtnPasarSiguiente">
                            Guardar proceso
                        </button>

                        <button class="btn btn-outline-danger" id="viBtnCancelar">
                            Cancelar visita / ticket (admin)
                        </button>
                    </div>

                    <div class="text-muted mt-2" style="font-size:.85rem;">
                        Si la visita está “lock”, el cliente no podrá cancelar. Aquí solo admin puede desbloquear o cancelar con motivo.
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-end gap-2">
                <button class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cerrar</button>
            </div>

        </div>
    </div>



    <style>
        /* micro-estilo offcanvas tipo screenshot */
        .action-card {
            border: 1px solid rgba(15, 23, 42, .10);
            border-radius: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, .65);
        }

        body.dark-mode .action-card {
            background: rgba(15, 23, 42, .35);
            border-color: rgba(148, 163, 184, .18);
        }
    </style>
    <script>
        // -------------------------
        // THEME
        // -------------------------
        $('#btnTheme').on('click', function() {
            const isDark = document.body.classList.contains('dark-mode');
            document.cookie = "mrs_theme=" + (isDark ? "light" : "dark") + "; path=/; max-age=31536000";
            location.reload();
        });

        const CL_ID = <?php echo (int)$clId; ?>;
    </script>
    <script src="js/tickets.js?v=1.0.0"></script>
</body>

</html>