<?php

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../php/auth_guard.php';
require_login();

$rol = $_SESSION['usRol'] ?? '';
if (!in_array($rol, ['MRA', 'MRSA', 'MRV'], true)) {
  http_response_code(403);
  exit('Sin permisos');
}

require_once __DIR__ . '/../php/csrf.php';
$csrf = csrf_token();
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
  <title>MR SOS | Clientes</title>

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

      <!-- Sidebar (igual que tickets.php) -->
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
          <li class="nav-item"><a class="nav-link active" href="clientes_index.php"><i class="bi bi-building"></i> Clientes</a></li>
        </ul>

        <div class="section-title px-2 mt-3">General</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-person"></i> Mis datos</a></li>
        </ul>
      </nav>

      <main class="col-12 col-lg-10">
        <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-success rounded-pill px-3">Admin</span>
            <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['usUsername'] ?? 'Admin'); ?></span>
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
                <h4 class="fw-bold mb-1">Clientes</h4>
                <div class="muted">Administración de clientes, zonas, sedes y usuarios CLI.</div>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mdlCliente">
                  <i class="bi bi-plus-circle"></i> Nuevo cliente
                </button>
              </div>
            </div>

            <hr>

            <div class="row g-2 align-items-end">
              <div class="col-12 col-md-6">
                <label class="form-label">Buscar</label>
                <input id="q" class="form-control" placeholder="Nombre del cliente...">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label">Estatus</label>
                <select id="estatus" class="form-select">
                  <option value="">Todos</option>
                  <option value="Activo">Activo</option>
                  <option value="Inactivo">Inactivo</option>
                  <option value="NewPass">NewPass</option>
                  <option value="Error">Error</option>
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
                    <th>Cliente</th>
                    <th>Estatus</th>
                    <th style="width:220px;">Acciones</th>
                  </tr>
                </thead>
                <tbody id="tbClientes">
                  <tr>
                    <td colspan="3" class="text-center muted">Cargando...</td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Modal: crear cliente -->
  <div class="modal fade" id="mdlCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Nuevo cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">

          <div class="row g-2">
            <div class="col-12 col-md-6">
              <label class="form-label">Nombre</label>
              <input id="clNombre" class="form-control">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Correo</label>
              <input id="clCorreo" class="form-control">
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label">Dirección</label>
              <input id="clDireccion" class="form-control">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Teléfono</label>
              <input id="clTelefono" class="form-control" inputmode="numeric">
            </div>
            <div class="col-12">
              <label class="form-label">Imagen (logo) del cliente</label>
              <input id="clImagenFile" type="file" class="form-control" accept="image/png,image/jpeg,image/webp">
              <div class="form-text">PNG/JPG/WEBP, máximo 2MB.</div>
            </div>
          </div>

          <hr>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="crearSedePrincipal" checked>
            <label class="form-check-label" for="crearSedePrincipal">Crear sede principal automáticamente</label>
          </div>

          <div class="row g-2 mt-2" id="boxSedePrincipal">
            <div class="col-12 col-md-6">
              <label class="form-label">Nombre sede principal</label>
              <input id="csNombrePrincipal" class="form-control" value="Principal">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Código</label>
              <input id="csCodigoPrincipal" class="form-control" placeholder="Opcional">
            </div>
            <div class="col-12">
              <label class="form-label">Dirección sede</label>
              <input id="csDireccionPrincipal" class="form-control" placeholder="Opcional">
            </div>
          </div>

          <div class="alert alert-danger mt-3 d-none" id="errCreate"></div>

        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button id="btnCrearCliente" class="btn btn-primary">
            <i class="bi bi-check2-circle"></i> Crear
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function() {
      const csrf = (window.MRS_CSRF && window.MRS_CSRF.csrf) ? window.MRS_CSRF.csrf : '';

      function apiGet(url, data) {
        return $.ajax({
          url,
          method: 'GET',
          data,
          headers: {
            'X-CSRF-TOKEN': csrf
          }
        });
      }

      function apiPost(url, payload) {
        payload = payload || {};
        payload.csrf_token = csrf;
        return $.ajax({
          url,
          method: 'POST',
          data: JSON.stringify(payload),
          contentType: 'application/json; charset=utf-8'
        });
      }

      function badge(estatus) {
        const map = {
          Activo: 'success',
          Inactivo: 'secondary',
          NewPass: 'warning',
          Error: 'danger'
        };
        const cls = map[estatus] || 'secondary';
        return `<span class="badge text-bg-${cls}">${estatus}</span>`;
      }

      function uploadClienteLogo(clId, file) {
        const fd = new FormData();
        fd.append('clId', clId);
        fd.append('csrf_token', csrf);
        fd.append('imagen', file);

        return $.ajax({
          url: 'api/clientes/cliente_logo_upload.php',
          method: 'POST',
          data: fd,
          processData: false,
          contentType: false
        });
      }



      function loadClientes() {
        $('#tbClientes').html(`<tr><td colspan="3" class="text-center muted">Cargando...</td></tr>`);
        apiGet('api/clientes/cliente_list.php', {
            q: $('#q').val(),
            estatus: $('#estatus').val()
          })
          .done(res => {
            if (!res.success) return;
            const rows = (res.clientes || []).map(c => `
          <tr>
            <td class="fw-semibold">${escapeHtml(c.clNombre||'')}</td>
            <td>${badge(c.clEstatus||'')}</td>
            <td>
              <a class="btn btn-sm btn-outline-primary" href="cliente_detalle.php?clId=${c.clId}">
                <i class="bi bi-gear"></i> Administrar
              </a>
            </td>
          </tr>
        `);
            $('#tbClientes').html(rows.length ? rows.join('') : `<tr><td colspan="3" class="text-center muted">Sin resultados</td></tr>`);
          })
          .fail(() => $('#tbClientes').html(`<tr><td colspan="3" class="text-center text-danger">Error al cargar</td></tr>`));
      }

      function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        } [m]));
      }

      $('#btnBuscar').on('click', loadClientes);
      $('#q').on('keydown', e => {
        if (e.key === 'Enter') loadClientes();
      });

      $('#crearSedePrincipal').on('change', function() {
        $('#boxSedePrincipal').toggleClass('d-none', !this.checked);
      });

      $('#btnCrearCliente').on('click', function() {
        $('#errCreate').addClass('d-none').text('');
        const payload = {
          clNombre: $('#clNombre').val(),
          clCorreo: $('#clCorreo').val(),
          clDireccion: $('#clDireccion').val(),
          clTelefono: $('#clTelefono').val(),
          crearSedePrincipal: $('#crearSedePrincipal').is(':checked'),
          csNombrePrincipal: $('#csNombrePrincipal').val(),
          csCodigoPrincipal: $('#csCodigoPrincipal').val(),
          csDireccionPrincipal: $('#csDireccionPrincipal').val(),
        };

        apiPost('api/clientes/cliente_create.php', payload)
          .done(res => {
            if (!res.success) {
              $('#errCreate').removeClass('d-none').text(res.error || 'Error');
              return;
            }
            const f = $('#clImagenFile')[0].files[0];
            if (!f) {
              window.location.href = `cliente_detalle.php?clId=${res.clId}`;
              return;
            }

            uploadClienteLogo(res.clId, f)
              .always(() => window.location.href = `cliente_detalle.php?clId=${res.clId}`);
          })
          .fail(() => $('#errCreate').removeClass('d-none').text('Error de red / servidor'));
      });

      // init
      loadClientes();
    })();
  </script>
</body>

</html>