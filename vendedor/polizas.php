<?php
// vendedor/polizas.php
declare(strict_types=1);
require_once '../php/auth_guard.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usId'])) { header('Location: ../login/login.php'); exit; }

$rol = (string)($_SESSION['usRol'] ?? '');
if (!in_array($rol, ['MRV'], true)) { http_response_code(403); echo "Sin permisos"; exit; }

$clId = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
if ($clId <= 0) { http_response_code(400); echo "Falta clId"; exit; }

$theme = $_COOKIE['mrs_theme'] ?? 'light';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MR SOS | Pólizas</title>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">

  <style>
    :root{
      --mr-bg:#f5f7fb; --mr-card:#fff; --mr-border:rgba(15,23,42,.10);
      --mr-text:#0f172a; --mr-muted:rgba(15,23,42,.65);
      --mr-shadow:0 8px 22px rgba(15,23,42,.08); --mr-radius:14px;
    }
    body{ background:var(--mr-bg); }
    body.dark-mode{ background:#0b1220; color:#e5e7eb; }

    .admin-topbar{
      background:rgba(255,255,255,.85);
      border-bottom:1px solid var(--mr-border);
      backdrop-filter:blur(10px);
    }
    body.dark-mode .admin-topbar{
      background:rgba(15,23,42,.65);
      border-bottom:1px solid rgba(148,163,184,.18);
    }

    .panel{
      background:var(--mr-card);
      border:1px solid var(--mr-border);
      border-radius:var(--mr-radius);
      box-shadow:var(--mr-shadow);
      padding:16px;
    }
    body.dark-mode .panel{
      background:rgba(15,23,42,.6);
      border-color:rgba(148,163,184,.18);
    }
    .muted{ color:var(--mr-muted); }
    body.dark-mode .muted{ color:rgba(226,232,240,.75); }

    .pol-card{
      background:var(--mr-card);
      border:1px solid var(--mr-border);
      border-radius:12px;
      box-shadow:var(--mr-shadow);
      padding:14px;
      height:100%;
    }
    body.dark-mode .pol-card{
      background:rgba(15,23,42,.6);
      border-color:rgba(148,163,184,.18);
    }

    .badge-soft{
      border-radius:999px;
      padding:.2rem .6rem;
      font-weight:800;
      font-size:.75rem;
      display:inline-flex;
      align-items:center;
      gap:.35rem;
    }
    .b-vig{ background:rgba(34,197,94,.12); color:#16a34a; border:1px solid rgba(34,197,94,.22); }
    .b-pv{ background:rgba(245,158,11,.12); color:#b45309; border:1px solid rgba(245,158,11,.22); }
    .b-ven{ background:rgba(239,68,68,.12); color:#ef4444; border:1px solid rgba(239,68,68,.22); }

    .empty-state{
      border:1px dashed var(--mr-border);
      border-radius:var(--mr-radius);
      padding:22px;
      text-align:center;
      background:rgba(255,255,255,.6);
    }
    body.dark-mode .empty-state{
      background:rgba(15,23,42,.35);
      border-color:rgba(148,163,184,.18);
    }
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

      <div class="section-title px-2">Vendedor</div>
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item">
          <a class="nav-link" href="index.php"><i class="bi bi-people"></i> Mis clientes</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="#"><i class="bi bi-shield-check"></i> Pólizas</a>
        </li>
      </ul>

      <div class="section-title px-2 mt-3">Usuario</div>
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item">
          <a class="nav-link" href="../dashboard/logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a>
        </li>
      </ul>
    </nav>

    <main class="col-12 col-lg-10">
      <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <a class="btn btn-sm btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i></a>
          <span class="badge text-bg-success rounded-pill px-3">Activo</span>
          <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['usUsername'] ?? 'Vendedor'); ?></span>
        </div>

        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-secondary" id="btnTheme" type="button" title="Tema">
            <i class="bi bi-moon"></i>
          </button>
        </div>
      </div>

      <div class="p-3 p-lg-4">
        <div class="panel">
          <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
              <h4 class="fw-bold mb-1">Pólizas del cliente</h4>
              <div class="muted">Se muestran solo las pólizas asignadas a tu cuenta (tabla <b>cuentas</b>).</div>
              <div class="mt-2">
                <span class="muted">Cliente:</span> <span class="fw-bold" id="lblCliente">—</span>
                <span class="muted ms-2">| Total:</span> <span class="fw-bold" id="lblTotal">—</span>
              </div>
            </div>

            <div class="d-flex align-items-center gap-2">
              <div class="input-group" style="max-width: 360px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input id="searchPol" class="form-control" placeholder="Buscar póliza (ID, tipo)">
                <button class="btn btn-outline-secondary" id="btnClear">Limpiar</button>
                <button class="btn btn-outline-primary" id="btnReload"><i class="bi bi-arrow-clockwise"></i></button>
              </div>
            </div>
          </div>

          <hr class="my-3" style="opacity:.12;">
          <div id="wrapPolizas"></div>

          <div id="emptyState" class="empty-state mt-3 d-none">
            <div class="fw-bold mb-1">Sin resultados</div>
            <div class="muted">Prueba con otra búsqueda.</div>
          </div>
        </div>
      </div>
    </main>

  </div>
</div>

<script>
  $('#btnTheme').on('click', function() {
    const isDark = document.body.classList.contains('dark-mode');
    document.cookie = "mrs_theme=" + (isDark ? "light" : "dark") + "; path=/; max-age=31536000";
    location.reload();
  });

  const CL_ID = <?php echo (int)$clId; ?>;
  const state = { q:'', polizas:[], clNombre:'' };

  function escapeHtml(s){
    return (s ?? '').toString()
      .replaceAll('&','&amp;').replaceAll('<','&lt;')
      .replaceAll('>','&gt;').replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function badge(b){
    if(b==='vencida') return `<span class="badge-soft b-ven"><i class="bi bi-x-octagon"></i> Vencida</span>`;
    if(b==='por_vencer') return `<span class="badge-soft b-pv"><i class="bi bi-clock"></i> Por vencer</span>`;
    return `<span class="badge-soft b-vig"><i class="bi bi-check-circle"></i> Vigente</span>`;
  }

  async function fetchPolizas(){
    $('#wrapPolizas').html('<div class="muted">Cargando pólizas...</div>');
    $('#emptyState').addClass('d-none');

    const url = `api/polizas.php?clId=${encodeURIComponent(CL_ID)}&q=${encodeURIComponent(state.q||'')}`;
    const res = await fetch(url, { credentials:'include', cache:'no-store' });

    if(!res.ok){
      const txt = await res.text();
      $('#wrapPolizas').html(`<div class="alert alert-danger">Error al cargar. ${escapeHtml(txt)}</div>`);
      return;
    }

    const json = await res.json();
    if(!json.success){
      $('#wrapPolizas').html(`<div class="alert alert-danger">Error: ${escapeHtml(json.error||'Desconocido')}</div>`);
      return;
    }

    state.polizas = json.polizas || [];
    state.clNombre = json.clNombre || '';

    $('#lblCliente').text(state.clNombre || '—');
    $('#lblTotal').text(json.count ?? state.polizas.length);

    renderPolizas();
  }

  function renderPolizas(){
    const wrap = $('#wrapPolizas');
    wrap.empty();

    const q = (state.q||'').trim().toLowerCase();
    const items = (state.polizas||[]).filter(p => {
      if(!q) return true;
      return (p.pcIdentificador||'').toLowerCase().includes(q)
        || (p.pcTipoPoliza||'').toLowerCase().includes(q);
    });

    if(items.length === 0){
      $('#emptyState').removeClass('d-none');
      return;
    }
    $('#emptyState').addClass('d-none');

    const row = $('<div class="row g-3"></div>');

    items.forEach(p => {
      row.append(`
        <div class="col-12 col-md-6 col-xl-4">
          <div class="pol-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div class="fw-bold">${escapeHtml(p.pcIdentificador || ('Póliza #' + p.pcId))}</div>
              <div>${badge(p.badge)}</div>
            </div>

            <div class="muted" style="font-size:.9rem;">
              <div><b>Tipo:</b> ${escapeHtml(p.pcTipoPoliza || '—')}</div>
              <div><b>Equipos:</b> ${Number(p.totalEquipos||0)}</div>
              <div><b>Vence:</b> ${escapeHtml((p.pcFechaFin||'').toString())}</div>
            </div>

            <div class="mt-3 d-flex gap-2">
              <a class="btn btn-sm btn-outline-secondary flex-grow-1" href="../admin/tickets.php?clId=${encodeURIComponent(CL_ID)}">
                Ver tickets
              </a>
              <button class="btn btn-sm btn-outline-primary flex-grow-1" disabled title="Siguiente paso: detalle de póliza">
                Ver detalle
              </button>
            </div>
          </div>
        </div>
      `);
    });

    wrap.append(row);
  }

  $('#searchPol').on('input', function(){
    state.q = $(this).val() || '';
    renderPolizas();
  });

  $('#btnClear').on('click', function(){
    state.q = '';
    $('#searchPol').val('');
    renderPolizas();
  });

  $('#btnReload').on('click', fetchPolizas);

  fetchPolizas();
</script>
</body>
</html>
