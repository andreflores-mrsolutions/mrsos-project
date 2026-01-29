<?php
// admin/tickets.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MR SOS | Tickets</title>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <link href="../css/style.css" rel="stylesheet">

    <style>
        :root {
            --mr-bg: #f5f7fb;
            --mr-card: #ffffff;
            --mr-border: rgba(15, 23, 42, .10);
            --mr-text: #0f172a;
            --mr-muted: rgba(15, 23, 42, .65);
            --mr-shadow: 0 8px 22px rgba(15, 23, 42, .08);
            --mr-radius: 14px;
        }

        body {
            background: var(--mr-bg);
        }

        body.dark-mode {
            background: #0b1220;
            color: #e5e7eb;
        }

        .admin-topbar {
            background: rgba(255, 255, 255, .85);
            border-bottom: 1px solid var(--mr-border);
            backdrop-filter: blur(10px);
        }

        body.dark-mode .admin-topbar {
            background: rgba(15, 23, 42, .65);
            border-bottom: 1px solid rgba(148, 163, 184, .18);
        }

        .panel {
            background: var(--mr-card);
            border: 1px solid var(--mr-border);
            border-radius: var(--mr-radius);
            box-shadow: var(--mr-shadow);
            padding: 16px;
        }

        body.dark-mode .panel {
            background: rgba(15, 23, 42, .6);
            border-color: rgba(148, 163, 184, .18);
        }

        .muted {
            color: var(--mr-muted);
        }

        body.dark-mode .muted {
            color: rgba(226, 232, 240, .75);
        }

        .badge-pill-soft {
            border-radius: 999px;
            padding: .2rem .6rem;
            font-weight: 700;
            font-size: .75rem;
            display: inline-block;
        }

        .ticket-card {
            background: var(--mr-card);
            border: 1px solid var(--mr-border);
            border-radius: 12px;
            box-shadow: var(--mr-shadow);
            padding: 12px;
            height: 100%;
        }

        body.dark-mode .ticket-card {
            background: rgba(15, 23, 42, .6);
            border-color: rgba(148, 163, 184, .18);
        }

        .progress {
            height: 8px;
            border-radius: 999px;
        }

        .progress-bar {
            border-radius: 999px;
        }

        .empty-state {
            border: 1px dashed var(--mr-border);
            border-radius: var(--mr-radius);
            padding: 22px;
            text-align: center;
            background: rgba(255, 255, 255, .6);
        }

        body.dark-mode .empty-state {
            background: rgba(15, 23, 42, .35);
            border-color: rgba(148, 163, 184, .18);
        }

        .table thead th {
            font-size: .85rem;
            color: var(--mr-muted);
            font-weight: 800;
            text-transform: none;
        }

        body.dark-mode .table {
            color: #e5e7eb;
        }

        body.dark-mode .table thead th {
            color: rgba(226, 232, 240, .75);
        }

        .row-crit-1 {
            border-left: 6px solid #ef4444;
        }

        .row-crit-2 {
            border-left: 3px solid #f59e0b;
        }

        /* .row-crit-3{ border-left: 6px solid #22c55e; } */
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

        // -------------------------
        // STATE
        // -------------------------
        const state = {
            vista: 'tabla', // tabla | cards
            scope: 'todo', // todo | acciones | recientes
            estado: 'Abierto', // Abierto | Pospuesto | all
            search: '',
            sedes: [],
            meta: {
                clNombre: '',
                total: 0
            },
            recientes: new Set(),
        };

        // -------------------------
        // STEPS (tu flujo real)
        // -------------------------
        const STEPS = [
            'asignacion',
            'revision inicial',
            'logs',
            'meet',
            'revision especial',
            'espera refaccion',
            'visita',
            'fecha asignada',
            'espera ventana',
            'espera visita',
            'en camino',
            'espera documentacion',
            'encuesta satisfaccion',
            'finalizado',
            'cancelado',
            'fuera de alcance',
            'servicio por evento'
        ];

        // Pasos donde el cliente participa (para mostrar "esperando cliente" en Admin)
        const CLIENT_STEPS = new Set(['logs', 'meet', 'visita', 'encuesta satisfaccion']);

        // Pasos típicos que ejecuta el Admin (acción directa en Admin)
        const ADMIN_STEPS = new Set([
            'asignacion',
            'revision inicial',
            'revision especial',
            'espera refaccion',
            'fecha asignada',
            'espera ventana',
            'espera visita',
            'en camino',
            'espera documentacion',
            'finalizado',
            'cancelado',
            'fuera de alcance',
            'servicio por evento'
        ]);


        function normalizeStep(raw) {
            const s = (raw || '').toString().trim().toLowerCase();
            if (!s) return 'asignacion';

            if (s.includes('asign')) return 'asignacion';
            if (s.includes('rev') && s.includes('inicial')) return 'revision inicial';
            if (s.includes('log')) return 'logs';
            if (s.includes('meet')) return 'meet';
            if (s.includes('rev') && s.includes('especial')) return 'revision especial';
            if (s.includes('refac')) return 'espera refaccion';
            if (s.includes('fecha') && s.includes('asign')) return 'fecha asignada';
            if (s.includes('ventana')) return 'espera ventana';
            if (s.includes('visita') && s.includes('espera')) return 'espera visita';
            if (s.includes('visita')) return 'visita';
            if (s.includes('camino')) return 'en camino';
            if (s.includes('doc')) return 'espera documentacion';
            if (s.includes('encuesta')) return 'encuesta satisfaccion';
            if (s.includes('final')) return 'finalizado';
            if (s.includes('cancel')) return 'cancelado';
            if (s.includes('fuera')) return 'fuera de alcance';
            if (s.includes('evento')) return 'servicio por evento';

            return s;
        }

        function stepIndex(step) {
            const idx = STEPS.indexOf(step);
            return idx >= 0 ? idx : 0;
        }

        function stepProgress(step) {
            const idx = stepIndex(step);
            const total = STEPS.length;
            const done = Math.max(0, Math.min(idx, total));
            const pct = Math.round((done / total) * 100);
            return {
                idx,
                total,
                done,
                pct
            };
        }

        function currentActionForStep(step) {
            if (step === 'logs') return {
                key: 'logs',
                title: 'Subir Logs',
                required: true
            };
            if (step === 'meet') return {
                key: 'meet',
                title: 'Confirmar Meet',
                required: false
            };
            if (step === 'visita') return {
                key: 'visita',
                title: 'Confirmar Visita',
                required: true
            };
            if (step === 'encuesta satisfaccion') return {
                key: 'encuesta',
                title: 'Responder Encuesta',
                required: false
            };
            return null;
        }

        function currentAdminActionForStep(step, t) {
            // Puedes usar t.tiExtra / flags si luego lo requieres.
            // Por ahora: mapeo directo por paso.

            // --- pasos del cliente: el admin NO ejecuta, pero sí gestiona ---
            if (step === 'logs') {
                return {
                    key: 'admin_logs',
                    title: 'Validar logs / solicitar corrección',
                    required: true,
                    mode: 'admin_wait_client', // usado para UI
                };
            }

            if (step === 'meet') {
                return {
                    key: 'admin_meet',
                    title: 'Definir / confirmar Meet',
                    required: false,
                    mode: 'admin_action',
                };
            }

            if (step === 'visita') {
                return {
                    key: 'admin_visita',
                    title: 'Crear visita / confirmar preparación',
                    required: true,
                    mode: 'admin_action',
                };
            }

            if (step === 'encuesta satisfaccion') {
                return {
                    key: 'admin_encuesta',
                    title: 'Revisar encuesta / cerrar ciclo',
                    required: false,
                    mode: 'admin_action',
                };
            }

            // --- pasos del admin ---
            if (step === 'asignacion') {
                return {
                    key: 'asignar_ingeniero',
                    title: 'Asignar ingeniero',
                    required: true,
                    mode: 'admin_action'
                };
            }

            if (step === 'revision inicial') {
                return {
                    key: 'revision_inicial',
                    title: 'Registrar revisión inicial',
                    required: true,
                    mode: 'admin_action'
                };
            }

            if (step === 'revision especial') {
                return {
                    key: 'revision_especial',
                    title: 'Registrar revisión especial',
                    required: false,
                    mode: 'admin_action'
                };
            }

            if (step === 'espera refaccion') {
                return {
                    key: 'refaccion',
                    title: 'Gestionar refacción (estatus)',
                    required: false,
                    mode: 'admin_action'
                };
            }

            if (step === 'fecha asignada') {
                return {
                    key: 'fecha_asignada',
                    title: 'Asignar fecha de visita',
                    required: true,
                    mode: 'admin_action'
                };
            }

            if (step === 'espera ventana') {
                return {
                    key: 'ventana',
                    title: 'Asignar / proponer ventana',
                    required: true,
                    mode: 'admin_action'
                };
            }

            if (step === 'espera visita') {
                return {
                    key: 'espera_visita',
                    title: 'Confirmar que visita está lista',
                    required: true,
                    mode: 'admin_action'
                };
            }

            if (step === 'en camino') {
                return {
                    key: 'en_camino',
                    title: 'Marcar “En camino”',
                    required: true,
                    mode: 'admin_action'
                };
            }

            if (step === 'espera documentacion') {
                return {
                    key: 'documentacion',
                    title: 'Cargar / validar documentación',
                    required: true,
                    mode: 'admin_action'
                };
            }

            // terminales / especiales (admin)
            if (step === 'finalizado') {
                return {
                    key: 'finalizado',
                    title: 'Cerrar ticket (finalizado)',
                    required: false,
                    mode: 'admin_action'
                };
            }
            if (step === 'cancelado') {
                return {
                    key: 'cancelado',
                    title: 'Confirmar cancelación',
                    required: false,
                    mode: 'admin_action'
                };
            }
            if (step === 'fuera de alcance') {
                return {
                    key: 'fuera_alcance',
                    title: 'Marcar fuera de alcance',
                    required: false,
                    mode: 'admin_action'
                };
            }
            if (step === 'servicio por evento') {
                return {
                    key: 'servicio_evento',
                    title: 'Convertir a servicio por evento',
                    required: false,
                    mode: 'admin_action'
                };
            }

            // fallback
            return null;
        }


        function ownerForStep(step) {
            return ADMIN_STEPS.has(step) ? 'Cliente' : 'Administrador';
        }

        // -------------------------
        // Recientes (localStorage)
        // -------------------------
        function loadRecientes() {
            try {
                const raw = localStorage.getItem('mrs_admin_recientes_' + CL_ID) || '[]';
                const arr = JSON.parse(raw);
                state.recientes = new Set(arr.map(Number).filter(Boolean));
            } catch (e) {
                state.recientes = new Set();
            }
        }

        function saveRecientes() {
            try {
                const arr = Array.from(state.recientes).slice(0, 80);
                localStorage.setItem('mrs_admin_recientes_' + CL_ID, JSON.stringify(arr));
            } catch (e) {}
        }

        function markVisto(tiId) {
            state.recientes.add(Number(tiId));
            saveRecientes();
        }

        // -------------------------
        // Helpers UI
        // -------------------------
        function escapeHtml(s) {
            return (s ?? '').toString()
                .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
                .replaceAll("'", "&#039;");
        }

        function clientePrefix(nombre) {
            if (!nombre) return 'UNK';
            return nombre.normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/[^A-Za-z]/g, "")
                .substring(0, 3).toUpperCase();
        }

        function badgeEstado(estatus) {
            const e = (estatus || '').toLowerCase();
            if (e === 'abierto') return 'badge-pill-soft bg-success-subtle text-success-emphasis';
            if (e === 'pospuesto') return 'badge-pill-soft bg-warning-subtle text-warning-emphasis';
            if (e === 'cerrado') return 'badge-pill-soft bg-secondary-subtle text-secondary-emphasis';
            return 'badge-pill-soft bg-light text-body-secondary';
        }

        function critClass(n) {
            const c = String(n || '3');
            if (c === '1') return 'row-crit-1';
            if (c === '2') return 'row-crit-2';
            return 'row-crit-3';
        }

        // OJO: aquí hago match con tu screenshot (Nivel 3 rojo, 2 amarillo, 1 gris)
        function critBadge(n) {
            const c = String(n || '3');
            if (c === '3') return '<span class="badge text-bg-danger">Nivel 3</span>';
            if (c === '2') return '<span class="badge text-bg-warning">Nivel 2</span>';
            return '<span class="badge text-bg-secondary">Nivel 1</span>';
        }

        function accionesDeTicket(t) {
            const step = normalizeStep(t.tiProceso);
            const a = currentAdminActionForStep(step, t);
            if (!a) return [];

            // Botones (1-2 máximo) para Admin
            if (a.key === 'ventana') {
                return [{
                        label: 'Asignar Ventana',
                        kind: 'success',
                        action: 'ventana_asignar'
                    },
                    {
                        label: 'Proponer Ventana',
                        kind: 'outline',
                        action: 'ventana_proponer'
                    }
                ];
            }

            if (a.key === 'admin_logs') {
                return [{
                        label: 'Revisar Logs',
                        kind: 'primary',
                        action: 'logs_revisar'
                    },
                    {
                        label: 'Solicitar Logs',
                        kind: 'outline',
                        action: 'logs_solicitar'
                    }
                ];
            }

            // default: una sola acción principal
            return [{
                label: a.title,
                kind: 'primary',
                action: a.key
            }];
        }


        function procesoLabel(t) {
            const step = normalizeStep(t.tiProceso);
            return step;
        }

        function progresoDeProceso(t) {
            const step = normalizeStep(t.tiProceso);
            const p = stepProgress(step);
            return {
                stepName: step,
                ...p
            };
        }

        function formatDate(iso) {
            const s = (iso || '').toString();
            return s.length >= 10 ? s.substring(0, 10) : '—';
        }

        // -------------------------
        // Fetch
        // -------------------------
        async function fetchTickets() {
            $('#wrapTickets').html('<div class="muted">Cargando tickets...</div>');
            $('#emptyState').addClass('d-none');

            const url = `api/tickets_por_sede.php?clId=${encodeURIComponent(CL_ID)}`;
            const res = await fetch(url, {
                credentials: 'include',
                cache: 'no-store'
            });

            if (!res.ok) {
                const txt = await res.text();
                $('#wrapTickets').html(`<div class="alert alert-danger">Error al cargar tickets. ${escapeHtml(txt)}</div>`);
                return;
            }

            const json = await res.json();
            if (!json.success) {
                $('#wrapTickets').html(`<div class="alert alert-danger">Error: ${escapeHtml(json.error || 'Desconocido')}</div>`);
                return;
            }

            state.sedes = json.sedes || [];
            state.meta.clNombre = json.clNombre || '';
            state.meta.total = Number(json.count || 0);

            $('#lblCliente').text(state.meta.clNombre || '—');
            $('#lblTotal').text(state.meta.total);

            applyAndRender();
        }

        // -------------------------
        // Offcanvas: Ticket details
        // -------------------------
        let offTicketInstance = null;

        function findTicketById(tiId) {
            for (const s of (state.sedes || [])) {
                for (const t of (s.tickets || [])) {
                    if (Number(t.tiId) === Number(tiId)) return t;
                }
            }
            return null;
        }

        function buildMiniHistory(t) {
            try {
                const extra = t.tiExtra ? JSON.parse(t.tiExtra) : null;
                if (Array.isArray(extra?.history)) {
                    return extra.history.slice(-3).reverse().map(x => ({
                        title: x.title || 'Evento',
                        meta: x.meta || ''
                    }));
                }
            } catch (e) {}

            return [{
                    title: 'Ticket creado',
                    meta: formatDate(t.tiFechaCreacion)
                },
                {
                    title: 'Proceso actual',
                    meta: normalizeStep(t.tiProceso)
                },
                {
                    title: 'Estatus',
                    meta: t.tiEstatus || '—'
                }
            ].slice(0, 3);
        }

        function ticketCodigo(t) {
            const pref = clientePrefix(state.meta.clNombre);
            return `${pref}-${Number(t.tiId)}`;
        }

        function defaultEquipoImg(t) {
            // Si luego tienes imágenes por eqId o modelo, lo conectamos.
            // Por ahora: placeholder local o una imagen genérica de tu proyecto:
            return '../img/Equipos/' + t.maNombre + '/' + t.eqModelo + '.png';
        }

        function renderOffcanvas(t) {
            const step = normalizeStep(t.tiProceso);
            console.log('Render offcanvas for ticket', t.tiId, 'step:', step);
            const owner = ownerForStep(step);
            const action = currentAdminActionForStep(step, t);

            // Header (similar a tu mock)
            $('#offImgEquipo').attr('src', defaultEquipoImg(t));
            $('#offCodigo').text(ticketCodigo(t));
            $('#offEquipo').text((t.eqModelo || 'Equipo') + (t.eqVersion ? ' · ' + t.eqVersion : ''));
            $('#offSN').text(t.peSN ? ('SN: ' + t.peSN) : 'SN: —');

            $('#offCrit').html(critBadge(t.tiNivelCriticidad));
            $('#offEstado').html(`<span class="${badgeEstado(t.tiEstatus)}">${escapeHtml(t.tiEstatus)}</span>`);
            $('#offMarca').text(t.maNombre ? t.maNombre : '');

            // Paso/Progreso
            const prog = stepProgress(step);
            $('#offPasoActual').text(`Paso actual: ${step}`);
            $('#offProgressText').text(`${prog.done}/${prog.total}`);
            $('#offProgressBar').css('width', `${prog.pct}%`);

            // Mensaje claro
            const nextStep = STEPS[Math.min(stepIndex(step) + 1, STEPS.length - 1)];
            $('#offMensaje').html(`
      <div><b>Qué está pasando:</b> El ticket está en <b>${escapeHtml(step)}</b>.</div>
      <div><b>Quién tiene la acción:</b> <b>${escapeHtml(owner)}</b>.</div>
      <div><b>Qué sigue:</b> ${escapeHtml(nextStep || '—')}.</div>
    `);

            // Acción única, guiada y clara
            if (action) {
                const req = action.required ?
                    '<span class="badge text-bg-danger ms-2">Obligatoria</span>' :
                    '<span class="badge text-bg-secondary ms-2">Opcional</span>';

                // Ajuste por acción (layout “tipo screenshot”)
                if (action.key === 'logs') {
                    $('#offAccionBox').html(`
          <div class="action-card">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="fw-bold">Acción requerida: ${escapeHtml(action.title)} ${req}</div>
              <div class="muted" style="font-size:.85rem;">Responsable: <b>${escapeHtml(owner)}</b></div>
            </div>

            <div class="mt-2 muted" style="font-size:.9rem;">
              Sube los logs del equipo para continuar con el diagnóstico.
            </div>

            <div class="mt-3 d-flex gap-2">
              <input type="file" id="offFileLogs" class="form-control form-control-sm" multiple>
              <button class="btn btn-primary btn-sm" id="offPrimaryAction" data-action="logs" data-ti="${t.tiId}">
                Subir Logs
              </button>
            </div>

            <div class="muted mt-2" style="font-size:.8rem;">
              Acepta .log, .txt o comprimidos (.zip/.rar). Estado: <b>Pendiente</b>
            </div>

            <div class="mt-2 d-flex gap-2">
              <button class="btn btn-outline-secondary btn-sm" id="offHelpAction" data-action="logs_help">
                ¿Cómo extraer logs?
              </button>
              <button class="btn btn-outline-secondary btn-sm" id="offMailHelp" data-action="mail_help">
                Pedir ayuda por correo
              </button>
            </div>
          </div>
        `);
                } else {
                    $('#offAccionBox').html(`
          <div class="action-card">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="fw-bold">Acción requerida: ${escapeHtml(action.title)} ${req}</div>
              <div class="muted" style="font-size:.85rem;">Responsable: <b>${escapeHtml(owner)}</b></div>
            </div>

            <div class="mt-2 muted" style="font-size:.9rem;">
              Esta acción se completa desde este panel.
            </div>

            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary btn-sm flex-grow-1" id="offPrimaryAction" data-action="${escapeHtml(action.key)}" data-ti="${t.tiId}">
                ${escapeHtml(action.title)}
              </button>
              <button class="btn btn-outline-secondary btn-sm" id="offHelpAction" data-action="${escapeHtml(action.key)}_help">
                Ayuda
              </button>
            </div>

            <div class="muted mt-2" style="font-size:.8rem;">Estado: <b>Pendiente</b></div>
          </div>
        `);
                }

                $('#offBtnAccion').text('Continuar').prop('disabled', false);
            } else {
                $('#offAccionBox').html(`
        <div class="action-card">
          <div class="fw-bold">Sin acción del cliente</div>
          <div class="muted mt-2" style="font-size:.9rem;">
            Este paso corresponde al <b>Administrador</b>. El flujo está avanzando internamente.
          </div>
        </div>
      `);
                $('#offBtnAccion').text('Cerrar').prop('disabled', false);
            }

            // Historial corto
            const history = buildMiniHistory(t);
            const $ul = $('#offHistorial');
            $ul.empty();
            history.forEach(h => {
                $ul.append(`
        <li class="list-group-item d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-semibold">${escapeHtml(h.title)}</div>
            <div class="muted" style="font-size:.85rem;">${escapeHtml(h.meta)}</div>
          </div>
        </li>
      `);
            });

            // Footer buttons
            $('#offBtnAyuda').off('click').on('click', () => {
                alert('Ayuda contextual del paso: ' + step);
            });

            $('#offBtnAccion').off('click').on('click', () => {
                if (!action) {
                    const el = document.getElementById('offTicket');
                    bootstrap.Offcanvas.getInstance(el)?.hide();
                    return;
                }
                $('#offPrimaryAction').trigger('click');
            });
        }

        function openTicketOffcanvasById(tiId) {
            const t = findTicketById(tiId);
            if (!t) return;

            markVisto(t.tiId);
            renderOffcanvas(t);

            const el = document.getElementById('offTicket');
            offTicketInstance = bootstrap.Offcanvas.getOrCreateInstance(el, {
                backdrop: true,
                scroll: false
            });
            offTicketInstance.show();
        }

        // -------------------------
        // FILTROS JS
        // -------------------------
        function aplicarFiltros() {
            const q = (state.search || '').trim().toLowerCase();
            const prefix = clientePrefix(state.meta.clNombre);
            const sedesFiltradas = [];

            (state.sedes || []).forEach(s => {
                const tickets = (s.tickets || []).filter(t => {
                    // estado
                    if (state.estado !== 'all') {
                        if ((t.tiEstatus || '') !== state.estado) return false;
                    }

                    // scope
                    if (state.scope === 'acciones') {
                        if (accionesDeTicket(t).length === 0) return false;
                    }
                    if (state.scope === 'recientes') {
                        if (!state.recientes.has(Number(t.tiId))) return false;
                    }

                    // búsqueda
                    if (q) {
                        const modelo = (t.eqModelo || '').toLowerCase();
                        const marca = (t.maNombre || '').toLowerCase();
                        const sn = (t.peSN || '').toLowerCase();
                        const codigo = `${prefix}-${Number(t.tiId) || ''}`.toLowerCase();
                        if (!modelo.includes(q) && !marca.includes(q) && !sn.includes(q) && !codigo.includes(q)) return false;
                    }

                    return true;
                });

                if (tickets.length) sedesFiltradas.push({
                    ...s,
                    tickets
                });
            });

            return sedesFiltradas;
        }

        function applyAndRender() {
            const sedes = aplicarFiltros();
            const totalVisibles = sedes.reduce((acc, s) => acc + (s.tickets || []).length, 0);

            if (totalVisibles === 0) {
                $('#wrapTickets').html('');
                $('#emptyState').removeClass('d-none');
                return;
            }

            $('#emptyState').addClass('d-none');

            if (state.vista === 'cards') renderCards(sedes);
            else renderTabla(sedes);
        }

        // -------------------------
        // RENDER TABLA
        // -------------------------
        function renderTabla(sedes) {
            const wrap = $('#wrapTickets');
            wrap.empty();

            sedes.forEach(s => {
                const sedeTitle = `${escapeHtml(state.meta.clNombre)} · ${escapeHtml(s.csNombre)}`;
                const count = (s.tickets || []).length;

                const block = $(`
        <div class="mb-4">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-bold">${sedeTitle}</div>
            <div class="muted" style="font-size:.85rem;">${count} ticket(s)</div>
          </div>

          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th style="width:120px;"># Ticket</th>
                  <th style="width:120px;">Estado</th>
                  <th style="width:220px;">Proceso actual</th>
                  <th>Información del equipo</th>
                  <th style="width:120px;">Criticidad</th>
                  <th style="width:160px;">Fecha</th>
                  <th style="width:240px;">Acciones</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      `);

                const tbody = block.find('tbody');
                const prefix = clientePrefix(state.meta.clNombre);

                (s.tickets || []).forEach(t => {
                    const codigo = `${prefix}-${Number(t.tiId)}`;
                    const acciones = accionesDeTicket(t);
                    const proc = progresoDeProceso(t);

                    const btns = acciones.length ?
                        acciones.map(a => {
                            const cls = a.kind === 'primary' ? 'btn btn-sm btn-outline-primary' :
                                a.kind === 'success' ? 'btn btn-sm btn-outline-success' :
                                'btn btn-sm btn-outline-secondary';
                            return `<button class="${cls} me-1 btnAccion" data-ti="${t.tiId}" data-action="${a.action}">${escapeHtml(a.label)}</button>`;
                        }).join('') :
                        `<span class="muted">—</span>`;

                    const tr = $(`
          <tr class="${critClass(t.tiNivelCriticidad)} ticket-row" data-ti="${t.tiId}">
            <td class="fw-bold">${escapeHtml(codigo)}</td>
            <td><span class="${badgeEstado(t.tiEstatus)}">${escapeHtml(t.tiEstatus)}</span></td>
            <td class="muted">
              <div>${escapeHtml(procesoLabel(t))}</div>
              <div class="muted" style="font-size:.8rem;">${proc.done}/${proc.total}</div>
            </td>
            <td>
              <div class="fw-semibold">${escapeHtml(t.eqModelo || 'Equipo')}</div>
              <div class="muted" style="font-size:.85rem;">
                ${escapeHtml(t.maNombre || '')} ${escapeHtml(t.eqVersion || '')}
                ${t.peSN ? `· SN: ${escapeHtml(t.peSN)}` : ''}
              </div>
            </td>
            <td>${critBadge(t.tiNivelCriticidad)}</td>
            <td class="muted" style="font-size:.9rem;">${escapeHtml(formatDate(t.tiFechaCreacion))}</td>
            <td>${btns}</td>
          </tr>
        `);

                    tr.css('cursor', 'pointer');
                    tbody.append(tr);
                });

                wrap.append(block);
            });
        }

        // -------------------------
        // RENDER CARDS
        // -------------------------
        function renderCards(sedes) {
            const wrap = $('#wrapTickets');
            wrap.empty();

            sedes.forEach(s => {
                const sedeTitle = `${escapeHtml(state.meta.clNombre)} · ${escapeHtml(s.csNombre)}`;
                const count = (s.tickets || []).length;

                wrap.append(`
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="fw-bold">${sedeTitle}</div>
          <div class="muted" style="font-size:.85rem;">${count} ticket(s)</div>
        </div>
      `);

                const row = $('<div class="row g-3 mb-4"></div>');
                const prefix = clientePrefix(state.meta.clNombre);

                (s.tickets || []).forEach(t => {
                    const codigo = `${prefix}-${Number(t.tiId)}`;
                    const acciones = accionesDeTicket(t);
                    const proc = progresoDeProceso(t);

                    const btns = acciones.length ?
                        acciones.map(a => {
                            const cls = a.kind === 'primary' ? 'btn btn-sm btn-outline-primary' :
                                a.kind === 'success' ? 'btn btn-sm btn-outline-success' :
                                'btn btn-sm btn-outline-secondary';
                            return `<button class="${cls} me-1 btnAccion" data-ti="${t.tiId}" data-action="${a.action}">${escapeHtml(a.label)}</button>`;
                        }).join('') :
                        `<span class="muted">—</span>`;

                    row.append(`
          <div class="col-12 col-md-6 col-xl-4">
            <div class="ticket-card ${critClass(t.tiNivelCriticidad)}" data-ti="${t.tiId}" style="cursor:pointer;">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div class="d-flex gap-2 flex-wrap">
                  <span class="${badgeEstado(t.tiEstatus)}">${escapeHtml(t.tiEstatus)}</span>
                  ${critBadge(t.tiNivelCriticidad)}
                </div>
                <div class="fw-bold">${escapeHtml(codigo)}</div>
              </div>

              <div class="fw-semibold">${escapeHtml(t.eqModelo || 'Equipo')}</div>
              <div class="muted" style="font-size:.85rem;">
                ${escapeHtml(t.maNombre || '')} ${escapeHtml(t.eqVersion || '')}
                ${t.peSN ? `· SN: ${escapeHtml(t.peSN)}` : ''}
              </div>

              <div class="mt-2">
                <div class="muted" style="font-size:.85rem;"><b>Paso actual:</b> ${escapeHtml(procesoLabel(t))}</div>
                <div class="progress mt-2">
                  <div class="progress-bar" style="width:${proc.pct}%"></div>
                </div>
                <div class="muted mt-1" style="font-size:.8rem;">${proc.done}/${proc.total}</div>
              </div>

              <div class="mt-3">
                ${btns}
              </div>
            </div>
          </div>
        `);
                });

                wrap.append(row);
            });
        }

        // -------------------------
        // UI EVENTS
        // -------------------------
        $('#vistaToggle').on('click', 'button[data-vista]', function() {
            $('#vistaToggle button[data-vista]').removeClass('active');
            $(this).addClass('active');
            state.vista = $(this).data('vista');
            applyAndRender();
        });

        $('#btnReload').on('click', fetchTickets);

        $('#tabScope').on('click', 'button[data-scope]', function() {
            $('#tabScope button').removeClass('active');
            $(this).addClass('active');
            state.scope = $(this).data('scope');
            applyAndRender();
        });

        $('#tabEstado').on('click', 'button[data-estado]', function() {
            $('#tabEstado button').removeClass('active');
            $(this).addClass('active');
            state.estado = $(this).data('estado');
            applyAndRender();
        });

        $('#searchTickets').on('input', function() {
            state.search = $(this).val() || '';
            applyAndRender();
        });

        $('#btnClear').on('click', function() {
            state.search = '';
            $('#searchTickets').val('');
            applyAndRender();
        });

        function resetAll() {
            state.vista = 'tabla';
            state.scope = 'todo';
            state.estado = 'Abierto';
            state.search = '';

            $('#vistaToggle button[data-vista]').removeClass('active');
            $('#vistaToggle button[data-vista="tabla"]').addClass('active');

            $('#tabScope button').removeClass('active');
            $('#tabScope button[data-scope="todo"]').addClass('active');

            $('#tabEstado button').removeClass('active');
            $('#tabEstado button[data-estado="Abierto"]').addClass('active');

            $('#searchTickets').val('');
            applyAndRender();
        }
        $('#btnReset, #btnReset2').on('click', resetAll);

        // Click fila completa => offcanvas
        $(document).on('click', '.ticket-row', function(e) {
            if ($(e.target).closest('button, a, input, label').length) return;
            const tiId = Number($(this).data('ti'));
            if (tiId) openTicketOffcanvasById(tiId);
        });

        // Click card completo => offcanvas
        $(document).on('click', '.ticket-card', function(e) {
            if ($(e.target).closest('button, a, input, label').length) return;
            const tiId = Number($(this).data('ti'));
            if (tiId) openTicketOffcanvasById(tiId);
        });

        // Click botones de acción => abre offcanvas + enfoca acción
        $(document).on('click', '.btnAccion', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const tiId = Number($(this).data('ti'));
            const action = String($(this).data('action') || '');

            openTicketOffcanvasById(tiId);

            setTimeout(() => {
                const btn = document.getElementById('offPrimaryAction');
                if (btn) btn.focus();
            }, 150);
        });

        // Acciones principales dentro del offcanvas (placeholder)
        $(document).on('click', '#offPrimaryAction', function() {
            const tiId = Number($(this).data('ti'));
            const action = String($(this).data('action') || '');

            if (action === 'logs') {
                alert('Abrir flujo SUBIR LOGS (Offcanvas guiado) para tiId ' + tiId);
                return;
            }
            if (action === 'asignar_ingeniero') {
                openAsignarIngenieroOffcanvas(tiId);
                return;
            }

            if (action === 'revision_inicial') {
                openRevisionInicialOffcanvas(tiId);
                return;
            }


            if (action === 'meet') {
                alert('Abrir flujo MEET para tiId ' + tiId);
                return;
            }
            if (action === 'visita') {
                alert('Abrir flujo CONFIRMAR VISITA para tiId ' + tiId);
                return;
            }
            if (action === 'encuesta') {
                alert('Abrir flujo ENCUESTA para tiId ' + tiId);
                return;
            }
        });

        $(document).on('click', '#offHelpAction', function() {
            alert('Abrir ayuda guiada del paso actual.');
        });

        $(document).on('click', '#offMailHelp', function() {
            alert('Abrir “Pedir ayuda por correo” (placeholder).');
        });

        // ==============================
        // OFFCANVAS ASIGNAR INGENIERO
        // ==============================

        const API_INGENIEROS = 'api/obtener_ingenieros.php';
        const API_ASIGNAR = 'api/asignar_ingeniero.php';

        let offAsignarIngInstance = null;
        let asgContext = {
            tiId: 0,
            ticket: null,
            ingenieros: [],
            search: '',
            filtroTier: '',
            filtroExperto: ''
        };

        // abre el offcanvas y carga ingenieros
        async function openAsignarIngenieroOffcanvas(tiId) {
            const t = findTicketById(tiId);
            if (!t) return;

            asgContext.tiId = Number(tiId);
            asgContext.ticket = t;
            asgContext.search = '';
            asgContext.filtroTier = '';
            asgContext.filtroExperto = '';

            // header
            const pref = clientePrefix(state.meta.clNombre);
            $('#asgTicketCodigo').text(`${pref}-${Number(t.tiId)}`);
            $('#asgTicketEquipo').text((t.eqModelo || 'Equipo') + (t.eqVersion ? ' · ' + t.eqVersion : ''));
            $('#asgTicketSN').text(t.peSN ? ('SN: ' + t.peSN) : 'SN: —');
            $('#asgTicketPaso').text('Paso: Asignación');
            $('#asgTicketEstado').text(t.tiEstatus || '—');

            $('#asgSearch').val('');
            $('#asgFiltroTier').val('');
            $('#asgFiltroExperto').val('');

            $('#asgWrap').html('');
            $('#asgEmpty').addClass('d-none');
            $('#asgLoading').removeClass('d-none');

            const el = document.getElementById('offAsignarIng');
            offAsignarIngInstance = bootstrap.Offcanvas.getOrCreateInstance(el, {
                backdrop: true,
                scroll: false
            });
            offAsignarIngInstance.show();

            await fetchIngenieros();
            buildExpertoOptions(asgContext.ingenieros);
            renderIngenierosAsignacion();
        }

        async function fetchIngenieros() {
            try {
                const res = await fetch(API_INGENIEROS, {
                    cache: 'no-store',
                    credentials: 'include'
                });
                if (!res.ok) {
                    const txt = await res.text();
                    $('#asgLoading').addClass('d-none');
                    $('#asgWrap').html(`<div class="alert alert-danger">Error cargando ingenieros: ${escapeHtml(txt)}</div>`);
                    return;
                }
                const json = await res.json();
                if (!json.success) {
                    $('#asgLoading').addClass('d-none');
                    $('#asgWrap').html(`<div class="alert alert-danger">Error: ${escapeHtml(json.error || 'Desconocido')}</div>`);
                    return;
                }
                asgContext.ingenieros = Array.isArray(json.ingenieros) ? json.ingenieros : [];
                $('#asgLoading').addClass('d-none');
            } catch (e) {
                $('#asgLoading').addClass('d-none');
                $('#asgWrap').html(`<div class="alert alert-danger">Error: ${escapeHtml(e.message || e)}</div>`);
            }
        }

        function buildExpertoOptions(ingenieros) {
            const set = new Set();
            ingenieros.forEach(i => {
                if (i.ingExperto) set.add(String(i.ingExperto));
            });
            const arr = Array.from(set).sort((a, b) => a.localeCompare(b));

            const $sel = $('#asgFiltroExperto');
            $sel.html('<option value="">Experto: Todos</option>');
            arr.forEach(x => {
                $sel.append(`<option value="${escapeHtml(x)}">${escapeHtml(x)}</option>`);
            });
        }

        function normalizeText(s) {
            return (s || '').toString().trim().toLowerCase();
        }

        function filtrarIngenieros() {
            const q = normalizeText(asgContext.search);
            const tier = asgContext.filtroTier;
            const experto = asgContext.filtroExperto;

            return (asgContext.ingenieros || []).filter(i => {
                if (tier && i.ingTier !== tier) return false;
                if (experto && i.ingExperto !== experto) return false;

                if (q) {
                    const hay = [
                        i.usNombre, i.usAPaterno, i.usUsername, i.usCorreo,
                        i.ingExperto, i.ingTier, i.ingDescripcion
                    ].some(v => normalizeText(v).includes(q));
                    if (!hay) return false;
                }
                return true;
            });
        }

        function groupByTier(list) {
            const map = {
                'Tier 1': [],
                'Tier 2': [],
                'Tier 3': []
            };
            list.forEach(i => {
                const t = i.ingTier || 'Tier 3';
                if (!map[t]) map[t] = [];
                map[t].push(i);
            });
            return map;
        }

        function ingAvatar(usId) {
            // Ajusta si tu ruta real cambia:
            // ejemplo que ya manejas: /img/Ingeniero/idUsIng.svg
            return `../img/Ingeniero/${Number(usId)}.svg`;
        }

        function renderIngenierosAsignacion() {
            const list = filtrarIngenieros();
            const groups = groupByTier(list);

            $('#asgWrap').empty();
            $('#asgEmpty').toggleClass('d-none', list.length !== 0);

            // Orden fijo Tier 1, Tier 2, Tier 3
            ['Tier 1', 'Tier 2', 'Tier 3'].forEach(tier => {
                const arr = groups[tier] || [];
                if (arr.length === 0) return;

                const $section = $(`
      <div class="mb-4">
        <div class="fw-bold mb-2">${escapeHtml(tier)}</div>
        <div class="row g-3" id="grid_${tier.replace(' ','_')}"></div>
      </div>
    `);

                const $grid = $section.find('div.row');

                arr.forEach(i => {
                    const fullName = `${i.usNombre || ''} ${i.usAPaterno || ''}`.trim() || 'Ingeniero';
                    const experto = i.ingExperto || 'General';
                    const desc = i.ingDescripcion || `Ingeniero experto en ${experto}`;
                    const tel = i.usTelefono || '—';
                    const user = i.usUsername || '—';
                    const mail = i.usCorreo || '—';

                    // badge “experto”
                    const badge = `<span class="badge rounded-pill text-bg-light border">${escapeHtml(experto)}</span>`;

                    const card = $(`
        <div class="col-12 col-lg-6">
          <div class="p-3 border rounded-4 h-100" style="background:#fff;">
            <div class="d-flex gap-3">
              <div style="width:86px; height:86px; border-radius:16px; overflow:hidden; background:#f1f5f9; flex:0 0 auto;">
                <img src="${ingAvatar(i.usId)}" alt="Ing" style="width:100%; height:100%; object-fit:cover;"
                  onerror="this.onerror=null;this.src='../img/avatar_default.png';">
              </div>

              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div class="fw-bold">${escapeHtml(fullName)}</div>
                  ${badge}
                </div>

                <div class="text-muted" style="font-size:.9rem;">
                  <div class="d-flex align-items-center gap-2"><i class="bi bi-person"></i> ${escapeHtml(user)}</div>
                  <div class="d-flex align-items-center gap-2"><i class="bi bi-envelope"></i> ${escapeHtml(mail)}</div>
                  <div class="d-flex align-items-center gap-2"><i class="bi bi-telephone"></i> ${escapeHtml(tel)}</div>
                  <div class="d-flex align-items-center gap-2"><i class="bi bi-shield-check"></i> ${escapeHtml(desc)}</div>
                </div>

                <div class="mt-3 d-flex gap-2">
                  <button class="btn btn-success btn-sm px-3 btnAsignarIng"
                    data-usid="${Number(i.usId)}"
                    data-name="${escapeHtml(fullName)}">
                    Asignar
                  </button>

                  <button class="btn btn-dark btn-sm px-3 btnVerMasIng"
                    data-usid="${Number(i.usId)}">
                    Ver más
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      `);

                    $grid.append(card);
                });

                $('#asgWrap').append($section);
            });
        }

        // eventos filtros/búsqueda
        $('#asgSearch').on('input', function() {
            asgContext.search = $(this).val() || '';
            renderIngenierosAsignacion();
        });
        $('#asgClear').on('click', function() {
            asgContext.search = '';
            $('#asgSearch').val('');
            renderIngenierosAsignacion();
        });
        $('#asgFiltroTier').on('change', function() {
            asgContext.filtroTier = $(this).val() || '';
            renderIngenierosAsignacion();
        });
        $('#asgFiltroExperto').on('change', function() {
            asgContext.filtroExperto = $(this).val() || '';
            renderIngenierosAsignacion();
        });

        // ver más (placeholder)
        $(document).on('click', '.btnVerMasIng', function(e) {
            e.preventDefault();
            const usId = Number($(this).data('usid'));
            alert('Aquí abrimos "Ver más" del ingeniero usId=' + usId + ' (lo conectamos después).');
        });

        // asignar ingeniero (POST a tu php ya existente)
        $(document).on('click', '.btnAsignarIng', async function(e) {
            e.preventDefault();

            const usIdIng = Number($(this).data('usid'));
            const name = String($(this).data('name') || 'Ingeniero');

            const tiId = Number(asgContext.tiId);
            if (!tiId || !usIdIng) return;

            if (!confirm(`¿Asignar a ${name} al ticket?`)) return;

            const fd = new FormData();
            fd.append('tiId', String(tiId));
            fd.append('usIdIng', String(usIdIng));
            fd.append('nextProceso', 'revision inicial'); // tu regla

            try {
                // feedback
                $(this).prop('disabled', true).text('Asignando…');

                const res = await fetch(API_ASIGNAR, {
                    method: 'POST',
                    body: fd,
                    credentials: 'include'
                });
                const json = await res.json().catch(() => null);

                if (!res.ok || !json || !json.success) {
                    const err = (json && json.error) ? json.error : 'Error asignando ingeniero';
                    alert(err);
                    $(this).prop('disabled', false).text('Asignar');
                    return;
                }

                // ✅ actualizar ticket en memoria para reflejar UI
                const t = findTicketById(tiId);
                if (t) {
                    t.usIdIng = usIdIng;
                    t.tiProceso = 'revision inicial';
                }

                // cerrar offcanvas asignación
                const el = document.getElementById('offAsignarIng');
                bootstrap.Offcanvas.getInstance(el)?.hide();

                // refrescar UI principal
                applyAndRender();

                // re-render del offTicket si está abierto
                // (si tienes una variable de ticket abierto, úsala; si no, solo lo abrimos de nuevo)
                openTicketOffcanvasById(tiId);

            } catch (err) {
                alert('Error: ' + (err.message || err));
                $(this).prop('disabled', false).text('Asignar');
            }
        });

        const API_GUARDAR_ANALISIS = 'api/guardar_analisis.php';

        let offRevisionInstance = null;
        let revCtx = {
            tiId: 0
        };

        // Abre offcanvas y precarga datos del ticket
        function openRevisionInicialOffcanvas(tiId) {
            const t = findTicketById(tiId);
            if (!t) return;

            revCtx.tiId = Number(tiId);

            const pref = clientePrefix(state.meta.clNombre);
            $('#revCodigo').text(`${pref}-${Number(t.tiId)}`);
            $('#revEquipo').text((t.eqModelo || 'Equipo') + (t.eqVersion ? ' · ' + t.eqVersion : ''));
            $('#revSN').text(t.peSN ? ('SN: ' + t.peSN) : 'SN: —');

            $('#revCrit').html(critBadge(t.tiNivelCriticidad));
            $('#revEstado').html(`<span class="${badgeEstado(t.tiEstatus)}">${escapeHtml(t.tiEstatus)}</span>`);
            $('#revPaso').text('Paso: Revisión inicial');

            // Si ya hay diagnóstico previo, lo mostramos (si tu API lo trae)
            $('#revDiagnostico').val((t.tiDiagnostico || '').toString());
            $('#revNext').val('logs');

            updateRevCount();
            $('#revMsg').html('');

            const el = document.getElementById('offRevisionInicial');
            offRevisionInstance = bootstrap.Offcanvas.getOrCreateInstance(el, {
                backdrop: true,
                scroll: false
            });
            offRevisionInstance.show();

            // foco para escribir rápido
            setTimeout(() => document.getElementById('revDiagnostico')?.focus(), 150);
        }

        // contador
        function updateRevCount() {
            const v = ($('#revDiagnostico').val() || '').toString();
            $('#revCount').text(v.length);
            if (v.length > 1200) {
                $('#revCount').addClass('text-danger');
            } else {
                $('#revCount').removeClass('text-danger');
            }
        }
        $('#revDiagnostico').on('input', updateRevCount);

        // templates
        $('#revTplFaltan').on('click', function() {
            $('#revDiagnostico').val('Faltan datos');
            updateRevCount();
            $('#revDiagnostico').focus();
        });

        $('#revTplChecklist').on('click', function() {
            const tpl =
                `• Síntomas reportados:
• Evidencia disponible:
• Hipótesis inicial:
• Información faltante:
• Siguiente paso recomendado:`;
            $('#revDiagnostico').val(tpl);
            updateRevCount();
            $('#revDiagnostico').focus();
        });

        $('#revLimpiar').on('click', function() {
            $('#revDiagnostico').val('');
            updateRevCount();
            $('#revDiagnostico').focus();
        });

        // Guardar
        $('#revGuardar').on('click', async function() {
            const tiId = Number(revCtx.tiId);
            if (!tiId) return;

            const diag = ($('#revDiagnostico').val() || '').toString().trim();
            const next = ($('#revNext').val() || 'logs').toString();

            // UX: si viene vacío, dejamos que backend ponga "Faltan datos",
            // pero damos un micro-aviso para evitar “guardé en blanco”
            const payload = new FormData();
            payload.append('tiId', String(tiId));
            payload.append('tiDiagnostico', diag);
            payload.append('nextProceso', next);

            $('#revMsg').html('');
            const $btn = $(this);
            $btn.prop('disabled', true).text('Guardando…');

            try {
                const res = await fetch(API_GUARDAR_ANALISIS, {
                    method: 'POST',
                    body: payload,
                    credentials: 'include'
                });
                const json = await res.json().catch(() => null);

                if (!res.ok || !json || !json.success) {
                    const err = (json && json.error) ? json.error : 'Error guardando análisis';
                    $('#revMsg').html(`<div class="alert alert-danger mb-0">${escapeHtml(err)}</div>`);
                    $btn.prop('disabled', false).text('Guardar y continuar');
                    return;
                }

                // ✅ Actualizar estado local del ticket (para UI inmediata)
                const t = findTicketById(tiId);
                if (t) {
                    t.tiDiagnostico = diag ? diag : 'Faltan datos';
                    t.tiProceso = next;
                }

                // cerrar offcanvas
                const el = document.getElementById('offRevisionInicial');
                bootstrap.Offcanvas.getInstance(el)?.hide();

                // refrescar vista
                applyAndRender();
                openTicketOffcanvasById(tiId);

            } catch (e) {
                $('#revMsg').html(`<div class="alert alert-danger mb-0">Error: ${escapeHtml(e.message || e)}</div>`);
                $btn.prop('disabled', false).text('Guardar y continuar');
            }
        });



        // -------------------------
        // INIT
        // -------------------------
        loadRecientes();
        fetchTickets();
    </script>


</body>

</html>