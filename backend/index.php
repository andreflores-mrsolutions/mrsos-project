<?php
// admin/index.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

// Seguridad mínima (vista)
if (empty($_SESSION['usId'])) {
  header('Location: ../login/login.php');
  exit;
}

$theme = $_COOKIE['mrs_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MR SOS | Admin</title>

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

    .kpi-card,
    .filters-row,
    .client-card {
      background: var(--mr-card);
      border: 1px solid var(--mr-border);
      border-radius: var(--mr-radius);
      box-shadow: var(--mr-shadow);
    }

    body.dark-mode .kpi-card,
    body.dark-mode .filters-row,
    body.dark-mode .client-card {
      background: rgba(15, 23, 42, .6);
      border-color: rgba(148, 163, 184, .18);
      color: #e5e7eb;
    }

    .kpi-card {
      padding: 14px;
      height: 100%;
    }

    .kpi-title {
      font-size: .85rem;
      color: var(--mr-muted);
    }

    .kpi-value {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--mr-text);
    }

    body.dark-mode .kpi-title {
      color: rgba(226, 232, 240, .75);
    }

    body.dark-mode .kpi-value {
      color: #e5e7eb;
    }

    .filters-row {
      padding: 12px;
    }

    .client-card {
      padding: 14px;
      height: 100%;
      cursor: pointer;
      transition: transform .12s ease, box-shadow .12s ease;
    }

    .client-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(15, 23, 42, .10);
    }

    .client-logo {
      height: 54px;
      width: 100%;
      object-fit: contain;
    }

    .muted {
      color: var(--mr-muted);
    }

    body.dark-mode .muted {
      color: rgba(226, 232, 240, .75);
    }

    .pill {
      border: 1px solid var(--mr-border);
      border-radius: 999px;
      padding: 2px 10px;
      font-size: .74rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(2, 6, 23, .02);
    }

    body.dark-mode .pill {
      border-color: rgba(148, 163, 184, .18);
      background: rgba(148, 163, 184, .06);
    }

    .pill-danger {
      border-color: rgba(239, 68, 68, .35);
      background: rgba(239, 68, 68, .08);
      color: #b91c1c;
    }

    .pill-warn {
      border-color: rgba(245, 158, 11, .35);
      background: rgba(245, 158, 11, .10);
      color: #b45309;
    }

    .group-title {
      margin-top: 18px;
      margin-bottom: 10px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 10px;
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
  </style>
</head>

<body class="<?php echo ($theme === 'dark') ? 'dark-mode' : ''; ?>">
  <div class="container-fluid">
    <div class="row gx-0">

      <!-- SIDEBAR (simple, puedes alinearlo a tu sidebar real) -->
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
      <!-- OFFCANVAS (xs/sm) y como sidebar en lg mediante .offcanvas-lg -->
      <div class="offcanvas offcanvas-start offcanvas-xl mr-side" tabindex="-1" id="offcanvasSidebar">
        <div class="p-3 d-flex align-items-center justify-content-between">
          <div class="brand">
            <a class="navbar-brand" href="#">
              <img src="../img/image.png" alt="Logo" class="rounded-pill">
            </a>
          </div>
          <button type="button" class="btn btn-outline-light close-btn" data-bs-dismiss="offcanvas" aria-label="Close">
            <i class="bi bi-chevron-left"></i>
          </button>
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

      </div>


      <main class="col-12 col-lg-10">
        <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary d-lg-none me-2"
              data-bs-toggle="offcanvas"
              data-bs-target="#offcanvasSidebar"
              aria-controls="offcanvasSidebar">
              <i class="bi bi-list"></i>
            </button>
            <span class="badge text-bg-success rounded-pill px-3">Activo</span>
            <span class="fw-bold" id="topUser">Admin</span>
            <span class="muted">| Admin</span>
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
          <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
            <div>
              <h3 class="mb-1 fw-bold">Dashboard Administrador</h3>
              <div class="muted">Prioriza por riesgo, criticidad y tickets abiertos por cliente.</div>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-secondary btn-sm" id="btnReload">
                <i class="bi bi-arrow-clockwise"></i> Recargar
              </button>
            </div>
          </div>

          <!-- KPIs -->
          <div class="row g-3 mb-3" id="kpiRow">
            <div class="col-12 col-md-6 col-xl-3">
              <div class="kpi-card">
                <div class="kpi-title"><i class="bi bi-inboxes me-1"></i> Tickets abiertos</div>
                <div class="kpi-value" id="kpiOpen">—</div>
                <div class="muted" style="font-size:.85rem;">En todos los clientes</div>
              </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
              <div class="kpi-card">
                <div class="kpi-title"><i class="bi bi-hourglass-split me-1"></i> SLA en riesgo</div>
                <div class="kpi-value" id="kpiRisk">—</div>
                <div class="muted" style="font-size:.85rem;">Criticidad 1–2 (abiertos)</div>
              </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
              <div class="kpi-card">
                <div class="kpi-title"><i class="bi bi-exclamation-triangle me-1"></i> Tickets críticos</div>
                <div class="kpi-value" id="kpiCritical">—</div>
                <div class="muted" style="font-size:.85rem;">Criticidad 1 (abiertos)</div>
              </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
              <div class="kpi-card">
                <div class="kpi-title"><i class="bi bi-building me-1"></i> Clientes activos</div>
                <div class="kpi-value" id="kpiClients">—</div>
                <div class="muted" style="font-size:.85rem;">Con estatus Activo</div>
              </div>
            </div>
          </div>

          <!-- FILTERS + SEARCH (JS) -->
          <div class="filters-row mb-3">
            <div class="row g-2 align-items-center">
              <div class="col-12 col-lg-6">
                <div class="btn-group" role="group" aria-label="Filtro pólizas" id="filterGroup">
                  <button class="btn btn-sm btn-outline-secondary active" data-filter="VIGENTE" type="button">Vigente</button>
                  <button class="btn btn-sm btn-outline-secondary" data-filter="POR_VENCER" type="button">Por vencer</button>
                  <button class="btn btn-sm btn-outline-secondary" data-filter="VENCIDO" type="button">Vencido</button>
                  <button class="btn btn-sm btn-outline-secondary" data-filter="TODOS" type="button">Todos</button>
                </div>
                <div class="muted mt-2" style="font-size:.85rem;">
                  Regla UX: lo normal no se marca. Solo aparece badge en excepción.
                </div>
              </div>

              <div class="col-12 col-lg-6">
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-search"></i></span>
                  <input id="searchClient" type="text" class="form-control" placeholder="Buscar cliente (nombre)...">
                  <button class="btn btn-outline-secondary" id="btnClear" type="button">Limpiar</button>
                  <button class="btn btn-outline-primary" id="btnReset" type="button" title="Restablecer filtros y búsqueda">
                    Restablecer
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Contenedor render -->
          <div id="cardsRoot"></div>

          <!-- Empty state -->
          <div id="emptyState" class="empty-state mt-3 d-none">
            <div class="fw-bold mb-1">Sin resultados</div>
            <div class="muted mb-3">Prueba con otro filtro o borra la búsqueda.</div>
            <button class="btn btn-primary btn-sm" id="btnReset2"><i class="bi bi-arrow-counterclockwise"></i> Restablecer</button>
          </div>

        </div>
      </main>
    </div>
  </div>

  

  <script>
    // ---------------------------
    // Tema
    // ---------------------------
    $('#btnTheme').on('click', function() {
      const isDark = document.body.classList.contains('dark-mode');
      document.cookie = "mrs_theme=" + (isDark ? "light" : "dark") + "; path=/; max-age=31536000";
      location.reload();
    });

    // ---------------------------
    // Estado de UI (persistente)
    // ---------------------------
    const state = {
      filter: 'VIGENTE',
      search: '',
      data: null, // respuesta del API
      filteredCards: [], // cards ya filtradas
    };

    function saveState() {
      try {
        localStorage.setItem('mrs_admin_dashboard_state', JSON.stringify({
          filter: state.filter,
          search: state.search
        }));
      } catch (e) {}
    }

    function loadState() {
      try {
        const raw = localStorage.getItem('mrs_admin_dashboard_state');
        if (!raw) return;
        const s = JSON.parse(raw);
        if (s.filter) state.filter = s.filter;
        if (typeof s.search === 'string') state.search = s.search;
      } catch (e) {}
    }

    function setActiveFilterButton() {
      $('#filterGroup [data-filter]').removeClass('active');
      $('#filterGroup [data-filter="' + state.filter + '"]').addClass('active');
    }

    // ---------------------------
    // Fetch data
    // ---------------------------
    async function fetchDashboard() {
      $('#cardsRoot').html('<div class="muted">Cargando...</div>');
      $('#emptyState').addClass('d-none');

      const res = await fetch('api/clientes_dashboard.php', {
        credentials: 'include'
      });
      if (!res.ok) {
        const txt = await res.text();
        $('#cardsRoot').html('<div class="alert alert-danger">Error al cargar dashboard. ' + txt + '</div>');
        return;
      }
      const json = await res.json();
      if (!json.success) {
        $('#cardsRoot').html('<div class="alert alert-danger">Error: ' + (json.error || 'Desconocido') + '</div>');
        return;
      }
      state.data = json;

      // KPIs
      $('#kpiOpen').text(json.kpi.open);
      $('#kpiRisk').text(json.kpi.risk);
      $('#kpiCritical').text(json.kpi.critical);
      $('#kpiClients').text(json.kpi.clients);

      $('#topUser').text(json.user?.name || 'Admin');

      applyFiltersAndRender();
    }

    // ---------------------------
    // Filtrado (JS)
    // ---------------------------
    function applyFiltersAndRender() {
      if (!state.data) return;

      const q = (state.search || '').trim().toLowerCase();
      const f = state.filter;

      const all = state.data.cards || [];
      const filtered = all.filter(c => {
        const name = (c.name || '').toLowerCase();
        const matchText = !q || name.includes(q);
        const matchFilter = (f === 'TODOS') || (c.status === f);
        return matchText && matchFilter;
      });

      state.filteredCards = filtered;

      renderCardsGrouped(filtered);

      const hasAny = filtered.length > 0;
      $('#emptyState').toggleClass('d-none', hasAny);
    }

    // ---------------------------
    // Render grouped
    // ---------------------------
    function badgeHtml(status) {
      if (status === 'VENCIDO') {
        return '<span class="pill pill-danger"><i class="bi bi-x-circle"></i> Vencido</span>';
      }
      if (status === 'POR_VENCER') {
        return '<span class="pill pill-warn"><i class="bi bi-clock-history"></i> Por vencer</span>';
      }
      return ''; // Vigente => sin badge
    }

    function escapeHtml(s) {
      return (s ?? '').toString()
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", "&#039;");
    }

    function renderCardsGrouped(cards) {
      const root = $('#cardsRoot');
      root.empty();

      if (!cards.length) {
        root.html(''); // empty state lo muestra aparte
        return;
      }

      // agrupar
      const groups = {};
      for (const c of cards) {
        const g = c.group || '#';
        groups[g] = groups[g] || [];
        groups[g].push(c);
      }

      // orden de grupos fijo
      const order = ['A–F', 'G–L', 'M–R', 'S–Z', '#'];

      for (const gName of order) {
        if (!groups[gName] || !groups[gName].length) continue;

        root.append(`
        <div class="group-title">
          <span>${escapeHtml(gName)}</span>
          <small class="muted">(${groups[gName].length})</small>
        </div>
      `);

        const row = $('<div class="row g-3"></div>');

        for (const it of groups[gName]) {
          const logo = escapeHtml(it.logo || '');
          const name = escapeHtml(it.name || '');
          const clId = it.clId;

          row.append(`
          <div class="col-12 col-md-6 col-xl-4">
            <div class="client-card" data-clid="${clId}">
              <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                <div class="d-flex flex-column gap-1">
                  ${badgeHtml(it.status)}
                  <div class="fw-bold" style="font-size:1.05rem;">${name}</div>
                </div>

                <div class="dropdown">
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="tickets.php?clId=${clId}">
                      <i class="bi bi-ticket-perforated me-2"></i> Ver Tickets</a></li>
                    <li><a class="dropdown-item" href="polizas.php?clId=${clId}">
                      <i class="bi bi-file-earmark-text me-2"></i> Ver Pólizas</a></li>
                  </ul>
                </div>
              </div>

              <div class="mb-2">
                <img class="client-logo" src="${logo}" alt="Logo ${name}"
                  onerror="this.onerror=null;this.src='../img/Clientes/cliente_default.png';">
              </div>

              <div class="d-flex flex-wrap gap-3 mb-2">
                <span class="muted" style="font-size:.85rem;"><i class="bi bi-geo-alt"></i> ${it.sedes} Sedes</span>
                <span class="muted" style="font-size:.85rem;"><i class="bi bi-file-earmark"></i> ${it.polizas} Pólizas</span>
              </div>

              <hr class="my-2" style="opacity:.12;">

              <div class="d-flex flex-wrap gap-2">
                <span class="muted" style="font-size:.85rem;"><i class="bi bi-inboxes"></i> Abiertos: <b>${it.open}</b></span>
                <span class="muted" style="font-size:.85rem;"><i class="bi bi-hourglass-split"></i> Riesgo: <b>${it.risk}</b></span>
                <span class="muted" style="font-size:.85rem;"><i class="bi bi-exclamation-triangle"></i> Críticos: <b>${it.critical}</b></span>
              </div>

              <div class="mt-3 d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary flex-grow-1" href="polizas.php?clId=${clId}">Ver Pólizas</a>
                <a class="btn btn-sm btn-primary flex-grow-1" href="tickets.php?clId=${clId}">Ver Tickets</a>
              </div>
            </div>
          </div>
        `);
        }

        root.append(row);
      }
    }

    // ---------------------------
    // Eventos UI
    // ---------------------------
    $('#filterGroup').on('click', '[data-filter]', function() {
      state.filter = $(this).data('filter');
      setActiveFilterButton();
      saveState();
      applyFiltersAndRender();
    });

    $('#searchClient').on('input', function() {
      state.search = $(this).val();
      saveState();
      applyFiltersAndRender();
    });

    $('#btnClear').on('click', function() {
      state.search = '';
      $('#searchClient').val('');
      saveState();
      applyFiltersAndRender();
    });

    function resetAll() {
      state.filter = 'VIGENTE';
      state.search = '';
      $('#searchClient').val('');
      setActiveFilterButton();
      saveState();
      applyFiltersAndRender();
    }

    $('#btnReset, #btnReset2').on('click', resetAll);

    $('#btnReload').on('click', fetchDashboard);

    // Click card: tickets por defecto (urgencia)
    $(document).on('click', '.client-card', function(e) {
      if ($(e.target).closest('.dropdown, a, button').length) return;
      const clId = $(this).data('clid');
      window.location.href = "tickets.php?clId=" + encodeURIComponent(clId);
    });

    // Init
    loadState();
    $('#searchClient').val(state.search);
    setActiveFilterButton();
    fetchDashboard();
  </script>
</body>

</html>