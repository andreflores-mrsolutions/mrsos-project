<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../php/auth_guard.php';
require_login();

$rol = $_SESSION['usRol'] ?? '';
if (!in_array($rol, ['MRA','MRSA','MRV'], true)) {
  http_response_code(403);
  exit('Sin permisos');
}

require_once __DIR__ . '/../php/csrf.php';
$csrf = csrf_token();
$theme = $_COOKIE['mrs_theme'] ?? 'light';

$clId = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
if ($clId <= 0) { http_response_code(400); exit('Falta clId'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <script>
    window.MRS_CSRF = <?= json_encode(['csrf' => $csrf], JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MR SOS | Pólizas</title>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="css/css.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">
</head>

<body class="<?php echo ($theme === 'dark') ? 'dark-mode' : ''; ?>">
<div class="container-fluid">
  <div class="row gx-0">

    <!-- Sidebar (igual estilo clientes_index.php) -->
    <nav id="sidebar" class="col-12 col-md-3 col-lg-2 d-none d-lg-block p-3 mr-side">
      <div class="brand mb-3 px-2">
        <a class="navbar-brand" href="#">
          <img src="../img/image.png" alt="Logo" class="rounded-pill" style="max-width: 120px;">
        </a>
      </div>

      <div class="section-title px-2">Operación</div>
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="nuevo_ticket.php"><i class="bi bi-shield-check"></i> Health Checks</a></li>
        <li class="nav-item"><a class="nav-link" href="clientes_index.php"><i class="bi bi-building"></i> Clientes</a></li>
        <li class="nav-item"><a class="nav-link active" href="polizas_index.php?clId=<?= (int)$clId ?>"><i class="bi bi-shield-lock"></i> Pólizas</a></li>
      </ul>

      <div class="section-title px-2 mt-3">General</div>
      <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-person"></i> Mis datos</a></li>
      </ul>
    </nav>

    <main class="col-12 col-lg-10">
      <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <a class="btn btn-sm btn-outline-secondary" href="cliente_detalle.php?clId=<?= (int)$clId ?>"><i class="bi bi-arrow-left"></i></a>
          <span class="badge text-bg-success rounded-pill px-3">Admin</span>
          <span class="fw-bold"><?= htmlspecialchars($_SESSION['usUsername'] ?? 'Admin'); ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-secondary" id="btnTheme" type="button"><i class="bi bi-moon"></i></button>
          <a class="btn btn-sm btn-outline-danger" href="../dashboard/logout.php"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>

      <div class="p-3 p-lg-4">
        <div class="panel">

          <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
              <h4 class="fw-bold mb-1">Pólizas del cliente</h4>
              <div class="muted">Crea pólizas por cliente (sin sede). Los equipos se asignan por sede dentro de la póliza.</div>
              <div class="small mt-2">
                <span class="muted">Cliente:</span> <span class="fw-bold" id="lblCliente">Cargando...</span>
              </div>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mdlPoliza">
                <i class="bi bi-plus-circle"></i> Nueva póliza
              </button>
            </div>
          </div>

          <hr>

          <div class="row g-2 align-items-end">
            <div class="col-12 col-md-6">
              <label class="form-label">Buscar</label>
              <input id="q" class="form-control" placeholder="No. factura / tipo / estatus...">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label">Estatus</label>
              <select id="estatus" class="form-select">
                <option value="">Todos</option>
                <option value="Activo">Activo</option>
                <option value="Inactivo">Inactivo</option>
                <option value="Vencida">Vencida</option>
                <option value="Error">Error</option>
                <option value="Cambios">Cambios</option>
              </select>
            </div>
            <div class="col-12 col-md-3 d-grid">
              <button id="btnBuscar" class="btn btn-outline-secondary"><i class="bi bi-search"></i> Buscar</button>
            </div>
          </div>

          <div class="table-responsive mt-3">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>No. Factura</th>
                  <th>Tipo</th>
                  <th>Vigencia</th>
                  <th>Estatus</th>
                  <th style="width:220px;">Acciones</th>
                </tr>
              </thead>
              <tbody id="tbPolizas">
                <tr><td colspan="5" class="text-center muted">Cargando...</td></tr>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </main>
  </div>
</div>

<!-- Modal: crear póliza -->
<div class="modal fade" id="mdlPoliza" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nueva póliza</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">

        <div class="row g-2">
          <div class="col-12 col-md-6">
            <label class="form-label">No. Factura (pcIdentificador)</label>
            <input id="pcIdentificador" class="form-control" placeholder="Ej. FAC-12345">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Tipo (Platinum/Gold/Silver)</label>
            <input id="pcTipoPoliza" class="form-control" placeholder="Ej. Platinum">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Fecha inicio</label>
            <input id="pcFechaInicio" type="date" class="form-control">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Fecha fin</label>
            <input id="pcFechaFin" type="date" class="form-control">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Estatus</label>
            <select id="pcEstatus" class="form-select">
              <option value="Activo">Activo</option>
              <option value="Inactivo">Inactivo</option>
              <option value="Vencida">Vencida</option>
              <option value="Cambios">Cambios</option>
              <option value="Error">Error</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">usId (creador)</label>
            <input id="pcUsId" class="form-control" inputmode="numeric" value="<?= (int)($_SESSION['usId'] ?? 0) ?>">
            <div class="form-text">Se guarda en polizascliente.usId (según tu BD).</div>
          </div>
        </div>

        <div class="alert alert-danger mt-3 d-none" id="errPoliza"></div>

      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button id="btnCrearPoliza" class="btn btn-primary">
          <i class="bi bi-check2-circle"></i> Crear
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const csrf = (window.MRS_CSRF && window.MRS_CSRF.csrf) ? window.MRS_CSRF.csrf : '';
  const CL_ID = <?= (int)$clId ?>;

  function apiGet(url, data){
    return $.ajax({ url, method:'GET', data, headers:{ 'X-CSRF-TOKEN': csrf } });
  }
  function apiPost(url, payload){
    payload = payload || {};
    payload.csrf_token = csrf;
    return $.ajax({
      url, method:'POST',
      contentType:'application/json; charset=utf-8',
      data: JSON.stringify(payload),
      headers:{ 'X-CSRF-TOKEN': csrf }
    });
  }
  function esc(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  async function loadClienteLabel(){
    // Si ya tienes un endpoint para cliente_get, úsalo; si no, solo mostramos clId.
    try {
      const r = await apiGet('api/clientes/cliente_get.php', { clId: CL_ID });
      if (r && r.success) $('#lblCliente').text(r.data?.clNombre || ('Cliente #' + CL_ID));
      else $('#lblCliente').text('Cliente #' + CL_ID);
    } catch { $('#lblCliente').text('Cliente #' + CL_ID); }
  }

  

  async function loadPolizas(){
    const q = $('#q').val().trim();
    const estatus = $('#estatus').val();

    $('#tbPolizas').html('<tr><td colspan="5" class="text-center muted">Cargando...</td></tr>');
    try {
      const r = await apiGet('api/polizas/poliza_list.php', { clId: CL_ID, q, estatus });
      if (!r || !r.success) throw new Error(r?.error || 'Error');
      const rows = (r.data && r.data.polizas) ? r.data.polizas : (r.polizas || []);

      if (!rows.length){
        $('#tbPolizas').html('<tr><td colspan="5" class="text-center muted">Sin pólizas</td></tr>');
        return;
      }

      const html = rows.map(p => {
        const vig = `${esc(p.pcFechaInicio || '—')} → ${esc(p.pcFechaFin || '—')}`;
        return `
          <tr>
            <td class="fw-semibold">${esc(p.pcIdentificador)}</td>
            <td>${esc(p.pcTipoPoliza)}</td>
            <td class="small">${vig}</td>
            <td><span class="badge text-bg-${p.pcEstatus==='Activo'?'success':(p.pcEstatus==='Inactivo'?'secondary':'warning')}">${esc(p.pcEstatus)}</span></td>
            <td>
              <a class="btn btn-sm btn-outline-primary" href="poliza_detalle.php?pcId=${p.pcId}">
                <i class="bi bi-eye"></i> Detalle
              </a>
            </td>
          </tr>
        `;
      }).join('');
      $('#tbPolizas').html(html);
    } catch (e){
      $('#tbPolizas').html('<tr><td colspan="5" class="text-center text-danger">Error cargando pólizas</td></tr>');
    }
  }

  async function crearPoliza(){
    $('#errPoliza').addClass('d-none').text('');

    const payload = {
      pcId: 0,
      clId: CL_ID,
      pcIdentificador: $('#pcIdentificador').val().trim(),
      pcTipoPoliza: $('#pcTipoPoliza').val().trim(),
      pcFechaInicio: $('#pcFechaInicio').val(),
      pcFechaFin: $('#pcFechaFin').val(),
      pcEstatus: $('#pcEstatus').val(),
      usId: parseInt($('#pcUsId').val() || '0', 10)
    };

    try {
      const r = await apiPost('api/polizas/poliza_save.php', payload);
      if (!r || !r.success) throw new Error(r?.error || 'Error');
      const pcId = r.data?.pcId || 0;
      bootstrap.Modal.getInstance(document.getElementById('mdlPoliza')).hide();
      await loadPolizas();
      if (pcId > 0) window.location.href = 'poliza_detalle.php?pcId=' + pcId;
    } catch (e){
      $('#errPoliza').removeClass('d-none').text(e.message || 'Error al crear póliza');
    }
  }

  $('#btnBuscar').on('click', loadPolizas);
  $('#btnCrearPoliza').on('click', crearPoliza);

  loadClienteLabel();
  loadPolizas();
})();
</script>
</body>
</html>