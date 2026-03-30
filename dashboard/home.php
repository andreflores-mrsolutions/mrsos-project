<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../php/auth_guard.php';
require_login();

$rol = $_SESSION['usRol'] ?? '';
if ($rol !== 'CLI' && $rol !== 'MRSA' && $rol !== 'MRA' && $rol !== 'MRV') {
  http_response_code(403);
  exit('Sin permisos' . $rol);
}

require_once __DIR__ . '/../php/csrf.php';
$csrf = csrf_token();

$theme = $_COOKIE['mrs_theme'] ?? ($_SESSION['usTheme'] ?? 'light');
$nombreUsuario = $_SESSION['usUsername'] ?? $_SESSION['usNombre'] ?? 'Cliente';
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MR SOS | Mis Tickets</title>

  <script>
    window.MRS_CSRF = <?= json_encode(['csrf' => $csrf], JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <link href="../css/style.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
  <link href="css/css.css" rel="stylesheet">

  <style>
    :root {
      --mrs-primary: #200f4c;
      --mrs-soft: #f6f7fb;
      --mrs-border: rgba(15, 23, 42, .08);
      --mrs-text: #0f172a;
      --mrs-muted: #64748b;
    }

    body {
      background: #f5f7fb;
      color: var(--mrs-text);
    }

    .topbar {
      background: #fff;
      border-bottom: 1px solid var(--mrs-border);
      position: sticky;
      top: 0;
      z-index: 1030;
    }

    .panel {
      background: #fff;
      border: 1px solid var(--mrs-border);
      border-radius: 1.25rem;
      box-shadow: 0 10px 25px rgba(15, 23, 42, .04);
    }

    .muted {
      color: var(--mrs-muted);
    }

    .stat-card {
      border: 1px solid var(--mrs-border);
      border-radius: 1rem;
      background: linear-gradient(180deg, #fff 0%, #fafbff 100%);
      padding: 1rem;
      height: 100%;
    }

    .ticket-card {
      border: 1px solid rgba(15, 23, 42, .10);
      border-radius: 1.1rem;
      background: #fff;
      transition: .18s ease;
      cursor: pointer;
      box-shadow: none;
    }

    .ticket-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
    }

    .mrs-ticket-table tbody tr {
      border-color: rgba(15, 23, 42, .08);
    }

    .mrs-ticket-table tbody tr td {
      padding-top: 1rem;
      padding-bottom: 1rem;
      vertical-align: middle;
    }

    .progress-thin {
      height: 8px;
      border-radius: 999px;
      background: #e6eaf0;
    }

    .progress-thin .progress-bar {
      border-radius: 999px;
      background: #1f6fff;
    }

    .off-section {
      border: 1px solid rgba(15, 23, 42, .10);
      border-radius: 1rem;
      background: #fff;
      padding: 1rem;
      margin-bottom: 1rem;
    }



    .badge-soft {
      background: #eef2ff;
      color: #3730a3;
      border: 1px solid rgba(67, 56, 202, .12);
    }

    .badge-critical-1 {
      background: #ecfdf5;
      color: #166534;
    }

    .badge-critical-2 {
      background: #eff6ff;
      color: #1d4ed8;
    }

    .badge-critical-3 {
      background: #fff7ed;
      color: #c2410c;
    }

    .badge-critical-4 {
      background: #fef2f2;
      color: #b91c1c;
    }

    .progress-thin {
      height: 8px;
      border-radius: 999px;
    }

    .empty-state {
      border: 1px dashed rgba(100, 116, 139, .35);
      border-radius: 1rem;
      background: #fff;
      padding: 2rem;
      text-align: center;
    }

    .off-section {
      border: 1px solid var(--mrs-border);
      border-radius: 1rem;
      background: #fff;
      padding: 1rem;
      margin-bottom: 1rem;
    }

    .timeline-mini {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .timeline-mini li {
      position: relative;
      padding-left: 1rem;
      margin-bottom: .85rem;
    }

    .timeline-mini li::before {
      content: "";
      position: absolute;
      left: 0;
      top: .42rem;
      width: .5rem;
      height: .5rem;
      border-radius: 50%;
      background: #4338ca;
    }

    .table thead th {
      white-space: nowrap;
      font-size: .85rem;
    }
  </style>
</head>

<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">
  <div class="container-fluid">
    <div class="row gx-0">
      <?php $activeMenu = 'dashboard'; ?>
      <?php require_once __DIR__ . '/partials/sidebar_cliente.php'; ?>
      <main class="col-12 col-lg-10">
        <div class="topbar py-2 px-3">
          <div class="container-fluid d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
                <i class="bi bi-list"></i>
              </button>

              <span class="badge rounded-pill text-bg-success px-3">Cliente</span>
              <div>
                <div class="fw-bold">MR SOS</div>
                <div class="small muted">Mis tickets y acciones pendientes</div>
              </div>
            </div>

            <div class="d-flex align-items-center gap-2">
              <span class="small muted d-none d-md-inline"><?= htmlspecialchars((string)$nombreUsuario) ?></span>
              <button class="btn btn-sm btn-outline-secondary" id="btnThemeDesktop" type="button" title="Tema">
                <i class="bi bi-moon"></i>
              </button>
              <a class="btn btn-sm btn-outline-danger" href="../dashboard/logout.php">
                <i class="bi bi-box-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>

        <div class="container-fluid py-4 px-3 px-lg-4">
          <div class="panel p-3 p-lg-4 mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
              <div>
                <h3 class="fw-bold mb-1">Mis tickets</h3>
                <div class="muted">
                  Aquí verás qué está haciendo MR Solutions, qué sigue y cuándo te toca responder.
                </div>
              </div>

              <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="btn-group" id="vistaToggle" role="group">
                  <button type="button" class="btn btn-outline-secondary active" data-vista="tabla">Tabla</button>
                  <button type="button" class="btn btn-outline-secondary" data-vista="cards">Cards</button>
                </div>

                <button class="btn btn-outline-primary" id="btnReload">
                  <i class="bi bi-arrow-clockwise"></i> Recargar
                </button>
              </div>
            </div>

            <hr>

            <div class="row g-3 mb-3">
              <div class="col-12 col-md-3">
                <div class="stat-card">
                  <div class="muted small">Total</div>
                  <div class="fs-4 fw-bold" id="statTotal">0</div>
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="stat-card">
                  <div class="muted small">Abiertos</div>
                  <div class="fs-4 fw-bold" id="statAbiertos">0</div>
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="stat-card">
                  <div class="muted small">Requieren mi acción</div>
                  <div class="fs-4 fw-bold" id="statAccion">0</div>
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="stat-card">
                  <div class="muted small">Confirmados / en curso</div>
                  <div class="fs-4 fw-bold" id="statCurso">0</div>
                </div>
              </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
              <div class="btn-group" id="scopeToggle">
                <button class="btn btn-outline-secondary active" data-scope="todo">Todos</button>
                <button class="btn btn-outline-secondary" data-scope="accion">Requieren mi acción</button>
                <button class="btn btn-outline-secondary" data-scope="abiertos">Abiertos</button>
              </div>

              <div class="btn-group" id="estadoToggle">
                <button class="btn btn-outline-secondary active" data-estado="all">Todos</button>
                <button class="btn btn-outline-secondary" data-estado="Abierto">Abiertos</button>
                <button class="btn btn-outline-secondary" data-estado="Pospuesto">Pospuestos</button>

              </div>

              <div class="ms-auto" style="max-width:360px; width:100%;">
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-search"></i></span>
                  <input id="searchTickets" class="form-control" placeholder="Buscar por folio, equipo, marca o SN">
                  <button class="btn btn-outline-secondary" id="btnClear">Limpiar</button>
                </div>
              </div>
            </div>


            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
              <div class="small muted" id="ticketsSummary">—</div>

              <div class="d-flex align-items-center gap-2">
                <label for="perPageSelect" class="small muted mb-0">Por página</label>
                <select id="perPageSelect" class="form-select" style="width: 110px;">
                  <option value="5">5</option>
                  <option value="10">10</option>
                  <option value="20" selected>20</option>
                  <option value="50">50</option>
                </select>
              </div>
            </div>

            <div id="wrapTickets"></div>

            <div class="empty-state d-none" id="emptyState">
              <div class="fw-bold mb-2">No encontramos tickets con esos filtros</div>
              <div class="muted mb-3">Prueba borrando la búsqueda o regresando a “Todos”.</div>
              <button class="btn btn-primary" id="btnResetFilters">
                <i class="bi bi-arrow-counterclockwise"></i> Restablecer
              </button>
            </div>
          </div>
        </div>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="offTicket" style="width:min(440px, 100vw);">
          <div class="offcanvas-header">
            <div>
              <div class="offcanvas-title fw-bold" id="offCodigo">Ticket</div>
              <div class="muted small" id="offHeaderSub">Detalle del caso</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
          </div>

          <div class="offcanvas-body">
            <div class="off-section">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-bold" id="offEquipo">—</div>
                  <div class="muted small" id="offMarcaSn">—</div>
                </div>
                <div class="text-end">
                  <div id="offCriticidad"></div>
                  <div class="mt-1" id="offEstado"></div>
                </div>
              </div>
            </div>

            <div class="off-section">
              <div class="fw-bold mb-2">Acción actual</div>
              <div id="offAccionActual">Cargando...</div>
            </div>

            <div class="off-section">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-bold">Progreso</div>
                <span class="small muted" id="offPasoActual">—</span>
              </div>
              <div class="progress progress-thin mb-2">
                <div class="progress-bar" id="offProgressBar" role="progressbar" style="width:0%"></div>
              </div>
              <div class="small muted" id="offProgresoTexto">—</div>
            </div>

            <div class="off-section">
              <div class="fw-bold mb-2">¿Qué está pasando?</div>
              <div id="offMensajeClaro" class="muted">—</div>
            </div>

            <!-- <div class="off-section">
              <div class="fw-bold mb-2">Últimos movimientos</div>
              <div id="offHistorial">Cargando historial...</div>
            </div> -->

            <div class="d-grid gap-2" id="offFooterActions"></div>
          </div>
        </div>

        <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
          <div id="toastOk" class="toast align-items-center text-bg-success border-0" role="alert">
            <div class="d-flex">
              <div class="toast-body"></div>
              <button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
          </div>
          <div id="toastErr" class="toast align-items-center text-bg-danger border-0" role="alert">
            <div class="d-flex">
              <div class="toast-body"></div>
              <button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
          </div>
        </div>
        <!-- OFFCANVAS LOGS -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offLogs" style="width:min(520px, 100vw);">
          <div class="offcanvas-header">
            <div>
              <div class="offcanvas-title fw-bold" id="offLogsTitle">Logs</div>
              <div class="muted small" id="offLogsSub">Subir archivos de diagnóstico</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
          </div>
          <div class="offcanvas-body">
            <div id="offLogsBody" class="muted">Cargando...</div>
          </div>
        </div>

        <!-- OFFCANVAS AYUDA -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offHelp" style="width:min(560px, 100vw);">
          <div class="offcanvas-header">
            <div>
              <div class="offcanvas-title fw-bold" id="offHelpTitle">Solicitar ayuda</div>
              <div class="muted small" id="offHelpSub">Cuéntanos qué necesitas para avanzar</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
          </div>
          <div class="offcanvas-body">
            <div id="offHelpBody" class="muted">Cargando...</div>
          </div>
        </div>

        <!-- OFFCANVAS MEET -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offMeet" style="width:min(520px, 100vw);">
          <div class="offcanvas-header">
            <div>
              <div class="offcanvas-title fw-bold" id="offMeetTitle">Meet</div>
              <div class="muted small" id="offMeetSub">Coordinar sesión remota</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
          </div>
          <div class="offcanvas-body">
            <div id="offMeetBody" class="muted">Cargando...</div>
          </div>
        </div>
        <!-- OFFCANVAS VISITA -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offVisita" style="width:min(560px, 100vw);">
          <div class="offcanvas-header">
            <div>
              <div class="offcanvas-title fw-bold" id="offVisitaTitle">Visita</div>
              <div class="muted small" id="offVisitaSub">Coordinación de atención en sitio</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
          </div>
          <div class="offcanvas-body">
            <div id="offVisitaBody" class="muted">Cargando...</div>
          </div>
        </div>

        <!-- OFFCANVAS ENCUESTA -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offEncuesta" style="width:min(560px, 100vw);">
          <div class="offcanvas-header">
            <div>
              <div class="offcanvas-title fw-bold" id="offEncuestaTitle">Encuesta de satisfacción</div>
              <div class="muted small" id="offEncuestaSub">Tu experiencia con el servicio</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
          </div>
          <div class="offcanvas-body">
            <div id="offEncuestaBody" class="muted">Cargando...</div>
          </div>
        </div>

        <script src="js/tickets_cliente.js"></script>
        <script src="js/theme.js"></script>
      </main>
    </div>
  </div>
</body>

</html>