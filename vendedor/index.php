<?php
declare(strict_types=1);

require_once '../php/auth_guard.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['usId'])) { header('Location: ../login/login.php'); exit; }

$rol = (string)($_SESSION['usRol'] ?? '');
if (!in_array($rol, ['MRV'], true)) { http_response_code(403); echo "Sin permisos"; exit; }

$theme = $_COOKIE['mrs_theme'] ?? 'light';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MR SOS | Vendedor</title>

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

    .client-card{
      background:var(--mr-card);
      border:1px solid var(--mr-border);
      border-radius:12px;
      box-shadow:var(--mr-shadow);
      padding:14px;
      height:100%;
      cursor:pointer;
      transition:transform .12s ease, box-shadow .12s ease;
    }
    .client-card:hover{ transform:translateY(-2px); }
    body.dark-mode .client-card{
      background:rgba(15,23,42,.6);
      border-color:rgba(148,163,184,.18);
    }

    .logo-box{
      width:100%;
      height:92px;
      display:flex;
      align-items:center;
      justify-content:center;
      border-radius:12px;
      background:rgba(15,23,42,.03);
      overflow:hidden;
    }
    body.dark-mode .logo-box{ background:rgba(255,255,255,.05); }
    .logo-box img{ max-height:70px; max-width:90%; object-fit:contain; }

    .badge-soft{
      border-radius:999px;
      padding:.2rem .6rem;
      font-weight:800;
      font-size:.75rem;
      display:inline-flex;
      align-items:center;
      gap:.35rem;
    }
    .b-vencido{ background:rgba(239,68,68,.12); color:#ef4444; border:1px solid rgba(239,68,68,.25); }
    .b-porv{ background:rgba(245,158,11,.12); color:#b45309; border:1px solid rgba(245,158,11,.25); }

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

    <!-- Sidebar (puedes ajustar links para vendedor) -->
    <nav id="sidebar" class="col-12 col-md-3 col-lg-2 d-none d-lg-block p-3 mr-side">
      <div class="brand mb-3 px-2">
        <a class="navbar-brand" href="#">
          <img src="../img/image.png" alt="Logo" class="rounded-pill" style="max-width: 120px;">
        </a>
      </div>

      <div class="section-title px-2">Vendedor</div>
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item">
          <a class="nav-link active" href="index.php"><i class="bi bi-people"></i> Mis clientes</a>
        </li>
      </ul>

      <div class="section-title px-2 mt-3">Usuario</div>
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item">
          <a class="nav-link" href="../dashboard/configuracion.php"><i class="bi bi-person"></i> Mis datos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="../../php/logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a>
        </li>
      </ul>
    </nav>

    <main class="col-12 col-lg-10">
      <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <span class="badge text-bg-success rounded-pill px-3">Activo</span>
          <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['usUsername'] ?? 'Vendedor'); ?></span>
        </div>

        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-secondary" id="btnTheme" type="button" title="Tema">
            <i class="bi bi-moon"></i>
          </button>
           <a class="btn btn-sm btn-outline-danger" href="../../php/logout.php" title="Salir">
              <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
      </div>

      <div class="p-3 p-lg-4">
        <div class="panel">
          <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
              <h4 class="fw-bold mb-1">Mis clientes</h4>
              <div class="muted">Solo verás los clientes asignados a tu cuenta (tabla <b>cuentas</b>).</div>
              <div class="mt-2">
                <span class="muted">Total:</span> <span class="fw-bold" id="lblTotal">—</span>
              </div>
            </div>

            <div class="d-flex align-items-center gap-2">
              <div class="input-group" style="max-width: 360px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input id="searchClientes" class="form-control" placeholder="Buscar cliente">
                <button class="btn btn-outline-secondary" id="btnClear">Limpiar</button>
                <button class="btn btn-outline-primary" id="btnReload"><i class="bi bi-arrow-clockwise"></i></button>
              </div>
            </div>
          </div>

          <hr class="my-3" style="opacity:.12;">
          <div id="wrapClientes"></div>

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
  // Theme
  $('#btnTheme').on('click', function() {
    const isDark = document.body.classList.contains('dark-mode');
    document.cookie = "mrs_theme=" + (isDark ? "light" : "dark") + "; path=/; max-age=31536000";
    location.reload();
  });

  const state = { q:'', clientes:[] };

  function escapeHtml(s){
    return (s ?? '').toString()
      .replaceAll('&','&amp;').replaceAll('<','&lt;')
      .replaceAll('>','&gt;').replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function badgeHtml(badge){
    if(badge === 'vencido') return `<span class="badge-soft b-vencido"><i class="bi bi-exclamation-triangle"></i> Vencido</span>`;
    if(badge === 'por_vencer') return `<span class="badge-soft b-porv"><i class="bi bi-clock"></i> Por vencer</span>`;
    return ``; // lo normal no se marca
  }

  function logoSrc(clId){
    // intenta convención (ajústala si tu backend usa otra)
    return `../img/Cliente/${clId}.png`;
  }

  async function fetchClientes(){
    $('#wrapClientes').html('<div class="muted">Cargando clientes...</div>');
    $('#emptyState').addClass('d-none');

    const url = `api/clientes.php?q=${encodeURIComponent(state.q||'')}`;
    const res = await fetch(url, { credentials:'include', cache:'no-store' });

    if(!res.ok){
      const txt = await res.text();
      $('#wrapClientes').html(`<div class="alert alert-danger">Error al cargar. ${escapeHtml(txt)}</div>`);
      return;
    }
    const json = await res.json();
    if(!json.success){
      $('#wrapClientes').html(`<div class="alert alert-danger">Error: ${escapeHtml(json.error||'Desconocido')}</div>`);
      return;
    }

    state.clientes = json.clientes || [];
    $('#lblTotal').text(json.count ?? state.clientes.length);

    renderClientes();
  }

  function renderClientes(){
    const wrap = $('#wrapClientes');
    wrap.empty();

    const q = (state.q||'').trim().toLowerCase();
    const items = (state.clientes||[]).filter(c => {
      if(!q) return true;
      return (c.clNombre||'').toLowerCase().includes(q);
    });

    if(items.length === 0){
      $('#emptyState').removeClass('d-none');
      return;
    }
    $('#emptyState').addClass('d-none');

    const row = $('<div class="row g-3"></div>');

    items.forEach(c => {
      const clId = Number(c.clId);
      const name = escapeHtml(c.clNombre || 'Cliente');

      row.append(`
        <div class="col-12 col-md-6 col-xl-4">
          <div class="client-card" data-cl="${clId}">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div class="fw-bold">${name}</div>
              <div>${badgeHtml(c.badge)}</div>
            </div>

            <div class="logo-box mb-3">
              <img src="${logoSrc(clId)}" alt="${name}" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\\'fw-bold\\'>${name.substring(0,2)}</div>';">
            </div>

            <div class="d-flex justify-content-between">
              <div class="muted" style="font-size:.9rem;"><b>${Number(c.sedes||0)}</b> sedes</div>
              <div class="muted" style="font-size:.9rem;"><b>${Number(c.polizas||0)}</b> pólizas</div>
            </div>

            <div class="mt-3 d-flex gap-2">
              <a class="btn btn-sm btn-outline-primary flex-grow-1" href="polizas.php?clId=${clId}">
                Ver pólizas
              </a>
              <a class="btn btn-sm btn-outline-secondary flex-grow-1" href="../admin/tickets.php?clId=${clId}">
                Ver tickets
              </a>
            </div>
          </div>
        </div>
      `);
    });

    wrap.append(row);
  }

  $('#searchClientes').on('input', function(){
    state.q = $(this).val() || '';
    renderClientes(); // filtro UI instantáneo
  });

  $('#btnClear').on('click', function(){
    state.q = '';
    $('#searchClientes').val('');
    renderClientes();
  });

  $('#btnReload').on('click', fetchClientes);

  // Click en card = ir a pólizas (flujo natural)
  $(document).on('click', '.client-card', function(e){
    if($(e.target).closest('a,button').length) return;
    const clId = Number($(this).data('cl'));
    if(clId) window.location.href = `polizas.php?clId=${clId}`;
  });

  fetchClientes();
</script>
</body>
</html>
