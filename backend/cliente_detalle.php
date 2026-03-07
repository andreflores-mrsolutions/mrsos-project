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

$clId = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
if ($clId <= 0) {
  http_response_code(400);
  exit('Falta clId');
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
  <title>MR SOS | Cliente</title>

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

      <?php $activeMenu = 'poliza'; ?>
      <?php require_once __DIR__ . '/partials/sidebar_cliente.php'; ?>

      <main class="col-12 col-lg-10">
        <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="clientes_index.php"><i class="bi bi-arrow-left"></i></a>
            <span class="fw-bold" id="lblCliente">Cliente</span>
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
                <h4 class="fw-bold mb-1">Administración del cliente</h4>
                <div class="muted" id="lblSub"></div>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" id="btnReload"><i class="bi bi-arrow-clockwise"></i> Recargar</button>
              </div>
            </div>

            <hr>

            <ul class="nav nav-tabs" role="tablist">
              <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tCliente" type="button">Cliente</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tZonas" type="button">Zonas</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tSedes" type="button">Sedes</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tUsuarios" type="button">Usuarios CLI</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tRoles" type="button">Roles</button></li>
            </ul>

            <div class="tab-content pt-3">

              <!-- CLIENTE -->
              <div class="tab-pane fade show active" id="tCliente">
                <div class="row g-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label">Logo actual</label><br>
                    <img id="imgCliente" src="" alt="Logo" style="max-height:90px; max-width:220px;" class="rounded border bg-white p-2">
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Actualizar logo</label>
                    <input id="clLogoFile" type="file" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <button class="btn btn-outline-primary mt-2" id="btnUploadLogo">
                      <i class="bi bi-upload"></i> Subir logo
                    </button>
                  </div>
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
                  <div class="col-12 col-md-4">
                    <label class="form-label">Estatus</label>
                    <select id="clEstatus" class="form-select">
                      <option value="Activo">Activo</option>
                      <option value="Inactivo">Inactivo</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-8 d-flex align-items-end justify-content-end gap-2">
                    <button class="btn btn-outline-secondary" id="btnToggleCliente"><i class="bi bi-toggle-on"></i> Toggle</button>
                    <button class="btn btn-primary" id="btnSaveCliente"><i class="bi bi-check2-circle"></i> Guardar</button>
                  </div>
                </div>
                <div class="alert alert-danger mt-3 d-none" id="errCliente"></div>
              </div>

              <!-- ZONAS -->
              <div class="tab-pane fade" id="tZonas">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="fw-semibold">Zonas</div>
                  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mdlZona"><i class="bi bi-plus-circle"></i> Agregar</button>
                </div>
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead>
                      <tr>
                        <th>Zona</th>
                        <th>Código</th>
                        <th>Estatus</th>
                        <th style="width:240px;">Acciones</th>
                      </tr>
                    </thead>
                    <tbody id="tbZonas">
                      <tr>
                        <td colspan="4" class="text-center muted">Cargando...</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- SEDES -->
              <div class="tab-pane fade" id="tSedes">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="fw-semibold">Sedes</div>
                  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mdlSede"><i class="bi bi-plus-circle"></i> Agregar</button>
                </div>
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead>
                      <tr>
                        <th>Sede</th>
                        <th>Zona</th>
                        <th>Principal</th>
                        <th>Estatus</th>
                        <th style="width:260px;">Acciones</th>
                      </tr>
                    </thead>
                    <tbody id="tbSedes">
                      <tr>
                        <td colspan="5" class="text-center muted">Cargando...</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- USUARIOS -->
              <div class="tab-pane fade" id="tUsuarios">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="fw-semibold">Usuarios CLI</div>
                  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#mdlUsuario"><i class="bi bi-plus-circle"></i> Agregar</button>
                </div>
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead>
                      <tr>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Username</th>
                        <th>Estatus</th>
                        <th style="width:260px;">Acciones</th>
                      </tr>
                    </thead>
                    <tbody id="tbUsuarios">
                      <tr>
                        <td colspan="5" class="text-center muted">Cargando...</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- ROLES -->
              <div class="tab-pane fade" id="tRoles">
                <div class="muted mb-2">Un rol activo por usuario por cliente. Scope por zona/sede según el rol.</div>
                <div class="table-responsive">
                  <table class="table table-hover align-middle">
                    <thead>
                      <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Zona</th>
                        <th>Sede</th>
                        <th style="width:160px;">Acción</th>
                      </tr>
                    </thead>
                    <tbody id="tbRoles">
                      <tr>
                        <td colspan="5" class="text-center muted">Cargando...</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

            </div><!-- tab-content -->
          </div>
        </div>
      </main>

    </div>
  </div>

  <!-- Modal Zona -->
  <div class="modal fade" id="mdlZona" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Zona</h5><button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="czId">
          <div class="row g-2">
            <div class="col-12 col-md-6"><label class="form-label">Nombre</label><input id="czNombre" class="form-control"></div>
            <div class="col-12 col-md-6"><label class="form-label">Código</label><input id="czCodigo" class="form-control"></div>
            <div class="col-12"><label class="form-label">Descripción</label><textarea id="czDescripcion" class="form-control" rows="3"></textarea></div>
          </div>
          <div class="alert alert-danger mt-3 d-none" id="errZona"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" id="btnSaveZona"><i class="bi bi-check2-circle"></i> Guardar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Sede -->
  <div class="modal fade" id="mdlSede" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Sede</h5><button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="csId">
          <div class="row g-2">
            <div class="col-12 col-md-6"><label class="form-label">Nombre</label><input id="csNombre" class="form-control"></div>
            <div class="col-12 col-md-6"><label class="form-label">Zona (opcional)</label><select id="csCzId" class="form-select"></select></div>
            <div class="col-12 col-md-6"><label class="form-label">Código</label><input id="csCodigo" class="form-control"></div>
            <div class="col-12 col-md-6"><label class="form-label">Principal</label>
              <select id="csEsPrincipal" class="form-select">
                <option value="0">No</option>
                <option value="1">Sí</option>
              </select>
            </div>
            <div class="col-12"><label class="form-label">Dirección</label><input id="csDireccion" class="form-control"></div>
          </div>
          <div class="alert alert-danger mt-3 d-none" id="errSede"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" id="btnSaveSede"><i class="bi bi-check2-circle"></i> Guardar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Usuario -->
  <div class="modal fade" id="mdlUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Usuario CLI</h5><button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="usId">
          <div class="row g-2">
            <div class="col-12 col-md-4"><label class="form-label">Nombre</label><input id="usNombre" class="form-control"></div>
            <div class="col-12 col-md-4"><label class="form-label">A. Paterno</label><input id="usAPaterno" class="form-control"></div>
            <div class="col-12 col-md-4"><label class="form-label">A. Materno</label><input id="usAMaterno" class="form-control" value="-"></div>
            <div class="col-12 col-md-6"><label class="form-label">Correo</label><input id="usCorreo" class="form-control"></div>
            <div class="col-12 col-md-3"><label class="form-label">Teléfono</label><input id="usTelefono" class="form-control" inputmode="numeric"></div>
            <div class="col-12 col-md-3"><label class="form-label">Username</label><input id="usUsername" class="form-control"></div>
            <div class="col-12"><label class="form-label">Password (opcional)</label><input id="usPass" class="form-control" placeholder="Si vacío, se genera y queda NewPass"></div>
          </div>
          <div class="alert alert-danger mt-3 d-none" id="errUsuario"></div>
          <div class="alert alert-warning mt-3 d-none" id="warnPass"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" id="btnSaveUsuario"><i class="bi bi-check2-circle"></i> Guardar</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function() {
      const clId = <?= (int)$clId ?>;
      const csrf = (window.MRS_CSRF && window.MRS_CSRF.csrf) ? window.MRS_CSRF.csrf : '';

      let DATA = {
        cliente: null,
        zonas: [],
        sedes: [],
        usuarios: [],
        roles: []
      };

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

      function esc(s) {
        return String(s || '').replace(/[&<>"']/g, m => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        } [m]));
      }

      function badge(estatus) {
        const map = {
          Activo: 'success',
          Inactivo: 'secondary',
          NewPass: 'warning',
          Error: 'danger'
        };
        const cls = map[estatus] || 'secondary';
        return `<span class="badge text-bg-${cls}">${esc(estatus)}</span>`;
      }

      function zonaName(czId) {
        if (czId === null || czId === undefined) return '—';
        const z = DATA.zonas.find(x => Number(x.czId) === Number(czId));
        return z ? z.czNombre : '—';
      }

      function sedeName(csId) {
        const s = DATA.sedes.find(x => Number(x.csId) === Number(csId));
        return s ? s.csNombre : '—';
      }

      function fillZonaSelect() {
        const $sel = $('#csCzId');
        $sel.empty();
        $sel.append(`<option value="">(Sin zona)</option>`);
        DATA.zonas.forEach(z => {
          $sel.append(`<option value="${z.czId}">${esc(z.czNombre)}</option>`);
        });
      }

      function render() {
        const c = DATA.cliente || {};
        const img = (DATA.cliente && DATA.cliente.clImagen) ? ('../img/' + DATA.cliente.clImagen) : '../img/image.png';
        console.log('Render cliente:', DATA.cliente.clImagen);
        $('#imgCliente').attr('src', img + '?v=' + Date.now());
        $('#lblCliente').text(c.clNombre ? `Cliente: ${c.clNombre}` : 'Cliente');
        $('#lblSub').text(c.clCorreo ? `${c.clCorreo} • ${c.clTelefono||''}` : '');

        // cliente form
        $('#clNombre').val(c.clNombre || '');
        $('#clCorreo').val(c.clCorreo || '');
        $('#clDireccion').val(c.clDireccion || '');
        $('#clTelefono').val(c.clTelefono || '');
        $('#clEstatus').val((c.clEstatus === 'Inactivo') ? 'Inactivo' : 'Activo');

        // zonas
        const zr = DATA.zonas.map(z => `
      <tr>
        <td class="fw-semibold">${esc(z.czNombre)}</td>
        <td>${esc(z.czCodigo||'')}</td>
        <td>${badge(z.czEstatus||'')}</td>
        <td>
          <button class="btn btn-sm btn-outline-primary btnEditZona" data-id="${z.czId}"><i class="bi bi-pencil"></i> Editar</button>
          <button class="btn btn-sm btn-outline-secondary btnToggleZona" data-id="${z.czId}" data-to="${(z.czEstatus==='Activo')?'Inactivo':'Activo'}">
            <i class="bi bi-toggle-on"></i> ${z.czEstatus==='Activo'?'Inactivar':'Activar'}
          </button>
        </td>
      </tr>
    `);
        $('#tbZonas').html(zr.length ? zr.join('') : `<tr><td colspan="4" class="text-center muted">Sin zonas</td></tr>`);

        // sedes
        const sr = DATA.sedes.map(s => `
      <tr>
        <td class="fw-semibold">${esc(s.csNombre)}</td>
        <td>${esc(zonaName(s.czId))}</td>
        <td>${s.csEsPrincipal==1?'<span class="badge text-bg-primary">Sí</span>':'—'}</td>
        <td>${badge(s.csEstatus||'')}</td>
        <td>
          <button class="btn btn-sm btn-outline-primary btnEditSede" data-id="${s.csId}"><i class="bi bi-pencil"></i> Editar</button>
          <button class="btn btn-sm btn-outline-secondary btnToggleSede" data-id="${s.csId}" data-to="${(s.csEstatus==='Activo')?'Inactivo':'Activo'}">
            <i class="bi bi-toggle-on"></i> ${s.csEstatus==='Activo'?'Inactivar':'Activar'}
          </button>
        </td>
      </tr>
    `);
        $('#tbSedes').html(sr.length ? sr.join('') : `<tr><td colspan="5" class="text-center muted">Sin sedes</td></tr>`);

        // usuarios
        const ur = DATA.usuarios.map(u => `
      <tr>
        <td class="fw-semibold">${esc(u.usNombre)} ${esc(u.usAPaterno)} ${esc(u.usAMaterno||'')}</td>
        <td>${esc(u.usCorreo)}</td>
        <td>${esc(u.usUsername)}</td>
        <td>${badge(u.usEstatus||'')}</td>
        <td>
          <button class="btn btn-sm btn-outline-primary btnEditUser" data-id="${u.usId}"><i class="bi bi-pencil"></i> Editar</button>
          <button class="btn btn-sm btn-outline-secondary btnToggleUser" data-id="${u.usId}" data-to="${(u.usEstatus==='Activo')?'Inactivo':'Activo'}">
            <i class="bi bi-toggle-on"></i> ${u.usEstatus==='Activo'?'Inactivar':'Activar'}
          </button>
        </td>
      </tr>
    `);
        $('#tbUsuarios').html(ur.length ? ur.join('') : `<tr><td colspan="5" class="text-center muted">Sin usuarios</td></tr>`);

        // roles: 1 por usuario
        const rolesByUs = {};
        (DATA.roles || []).forEach(r => {
          rolesByUs[r.usId] = r;
        });

        const rr = DATA.usuarios.map(u => {
          const r = rolesByUs[u.usId] || {};
          const rol = r.ucrRol || '';
          const czId = (r.czId !== undefined && r.czId !== null) ? Number(r.czId) : '';
          const csId = (r.csId !== undefined && r.csId !== null) ? Number(r.csId) : '';
          return `
        <tr data-usid="${u.usId}">
          <td class="fw-semibold">${esc(u.usNombre)} ${esc(u.usAPaterno)}</td>
          <td>
            <select class="form-select form-select-sm selRol">
              <option value="">(Sin rol)</option>
              <option ${rol==='ADMIN_GLOBAL'?'selected':''} value="ADMIN_GLOBAL">ADMIN_GLOBAL</option>
              <option ${rol==='ADMIN_ZONA'?'selected':''} value="ADMIN_ZONA">ADMIN_ZONA</option>
              <option ${rol==='ADMIN_SEDE'?'selected':''} value="ADMIN_SEDE">ADMIN_SEDE</option>
              <option ${rol==='USUARIO'?'selected':''} value="USUARIO">USUARIO</option>
              <option ${rol==='VISOR'?'selected':''} value="VISOR">VISOR</option>
            </select>
          </td>
          <td>
            <select class="form-select form-select-sm selZona">
              <option value="">(N/A)</option>
              ${DATA.zonas.map(z=>`<option ${czId==Number(z.czId)?'selected':''} value="${z.czId}">${esc(z.czNombre)}</option>`).join('')}
            </select>
          </td>
          <td>
            <select class="form-select form-select-sm selSede">
              <option value="">(N/A)</option>
              ${DATA.sedes.map(s=>`<option ${csId==Number(s.csId)?'selected':''} value="${s.csId}">${esc(s.csNombre)}</option>`).join('')}
            </select>
          </td>
          <td>
            <button class="btn btn-sm btn-primary btnSaveRol"><i class="bi bi-check2-circle"></i> Guardar</button>
          </td>
        </tr>
      `;
        });
        $('#tbRoles').html(rr.length ? rr.join('') : `<tr><td colspan="5" class="text-center muted">Sin usuarios</td></tr>`);

        fillZonaSelect();
      }

      function load() {
        return apiGet('api/clientes/cliente_get.php', {
            clId
          })
          .done(res => {
            if (!res.success) {
              alert(res.error || 'Error');
              return;
            }
            DATA = res;
            render();
          })
          .fail(() => alert('Error de red/servidor'));
      }

      // Cliente actions

      $('#btnUploadLogo').on('click', function() {
        const f = $('#clLogoFile')[0].files[0];
        if (!f) {
          alert('Selecciona una imagen.');
          return;
        }

        const fd = new FormData();
        fd.append('clId', clId);
        fd.append('csrf_token', csrf);
        fd.append('imagen', f);

        $.ajax({
          url: 'api/clientes/cliente_logo_upload.php',
          method: 'POST',
          data: fd,
          processData: false,
          contentType: false
        }).done(res => {
          if (!res.success) {
            alert(res.error || 'Error');
            return;
          }
          load(); // recarga y refresca preview
        }).fail(() => alert('Error de red/servidor'));
      });

      $('#btnSaveCliente').on('click', function() {
        $('#errCliente').addClass('d-none').text('');
        apiPost('api/clientes/cliente_update.php', {
          clId,
          clNombre: $('#clNombre').val(),
          clCorreo: $('#clCorreo').val(),
          clDireccion: $('#clDireccion').val(),
          clTelefono: $('#clTelefono').val(),
          clEstatus: $('#clEstatus').val(),
        }).done(res => {
          if (!res.success) {
            $('#errCliente').removeClass('d-none').text(res.error || 'Error');
            return;
          }
          load();
        }).fail(() => $('#errCliente').removeClass('d-none').text('Error de red/servidor'));
      });

      $('#btnToggleCliente').on('click', function() {
        const to = ($('#clEstatus').val() === 'Activo') ? 'Inactivo' : 'Activo';
        apiPost('api/clientes/cliente_toggle.php', {
          clId,
          to
        }).done(res => {
          if (!res.success) {
            alert(res.error || 'Error');
            return;
          }
          load();
        });
      });

      // Zonas modal save
      $('#btnSaveZona').on('click', function() {
        $('#errZona').addClass('d-none').text('');
        const czId = $('#czId').val();
        const payload = {
          clId,
          czNombre: $('#czNombre').val(),
          czCodigo: $('#czCodigo').val(),
          czDescripcion: $('#czDescripcion').val()
        };
        const url = czId ? 'api/clientes/zona_update.php' : 'api/clientes/zona_create.php';
        if (czId) payload.czId = Number(czId);

        apiPost(url, payload).done(res => {
          if (!res.success) {
            $('#errZona').removeClass('d-none').text(res.error || 'Error');
            return;
          }
          bootstrap.Modal.getInstance(document.getElementById('mdlZona')).hide();
          $('#czId').val('');
          $('#czNombre').val('');
          $('#czCodigo').val('');
          $('#czDescripcion').val('');
          load();
        });
      });

      $(document).on('click', '.btnEditZona', function() {
        const id = Number($(this).data('id'));
        const z = DATA.zonas.find(x => Number(x.czId) === id);
        if (!z) return;
        $('#czId').val(z.czId);
        $('#czNombre').val(z.czNombre || '');
        $('#czCodigo').val(z.czCodigo || '');
        $('#czDescripcion').val(z.czDescripcion || '');
        $('#errZona').addClass('d-none').text('');
        new bootstrap.Modal('#mdlZona').show();
      });

      $(document).on('click', '.btnToggleZona', function() {
        apiPost('api/clientes/zona_toggle.php', {
            clId,
            czId: Number($(this).data('id')),
            to: $(this).data('to')
          })
          .done(res => {
            if (!res.success) {
              alert(res.error || 'Error');
              return;
            }
            load();
          });
      });

      // Sedes modal save
      $('#btnSaveSede').on('click', function() {
        $('#errSede').addClass('d-none').text('');
        const csId = $('#csId').val();
        const payload = {
          clId,
          csNombre: $('#csNombre').val(),
          czId: $('#csCzId').val(), // '' => sede sin zona
          csCodigo: $('#csCodigo').val(),
          csDireccion: $('#csDireccion').val(),
          csEsPrincipal: Number($('#csEsPrincipal').val()),
        };
        const url = csId ? 'api/clientes/sede_update.php' : 'api/clientes/sede_create.php';
        if (csId) payload.csId = Number(csId);

        apiPost(url, payload).done(res => {
          if (!res.success) {
            $('#errSede').removeClass('d-none').text(res.error || 'Error');
            return;
          }
          bootstrap.Modal.getInstance(document.getElementById('mdlSede')).hide();
          $('#csId').val('');
          $('#csNombre').val('');
          $('#csCzId').val('');
          $('#csCodigo').val('');
          $('#csDireccion').val('');
          $('#csEsPrincipal').val('0');
          load();
        });
      });

      $(document).on('click', '.btnEditSede', function() {
        const id = Number($(this).data('id'));
        const s = DATA.sedes.find(x => Number(x.csId) === id);
        if (!s) return;
        $('#csId').val(s.csId);
        $('#csNombre').val(s.csNombre || '');
        $('#csCzId').val(s.czId == null ? '' : s.czId);
        $('#csCodigo').val(s.csCodigo || '');
        $('#csDireccion').val(s.csDireccion || '');
        $('#csEsPrincipal').val(Number(s.csEsPrincipal || 0));
        $('#errSede').addClass('d-none').text('');
        new bootstrap.Modal('#mdlSede').show();
      });

      $(document).on('click', '.btnToggleSede', function() {
        apiPost('api/clientes/sede_toggle.php', {
            clId,
            csId: Number($(this).data('id')),
            to: $(this).data('to')
          })
          .done(res => {
            if (!res.success) {
              alert(res.error || 'Error');
              return;
            }
            load();
          });
      });

      // Usuarios modal save
      $('#btnSaveUsuario').on('click', function() {
        $('#errUsuario').addClass('d-none').text('');
        $('#warnPass').addClass('d-none').text('');
        const usId = $('#usId').val();

        const payload = {
          clId,
          usNombre: $('#usNombre').val(),
          usAPaterno: $('#usAPaterno').val(),
          usAMaterno: $('#usAMaterno').val(),
          usCorreo: $('#usCorreo').val(),
          usTelefono: $('#usTelefono').val(),
          usUsername: $('#usUsername').val(),
          usPass: $('#usPass').val(),
        };

        const url = usId ? 'api/clientes/usuario_cli_update.php' : 'api/clientes/usuario_cli_create.php';
        if (usId) payload.usId = Number(usId);

        apiPost(url, payload).done(res => {
          if (!res.success) {
            $('#errUsuario').removeClass('d-none').text(res.error || 'Error');
            return;
          }
          if (res.generated_password) {
            $('#warnPass').removeClass('d-none').html(`Password generado (solo esta vez): <b>${esc(res.generated_password)}</b>`);
          } else {
            bootstrap.Modal.getInstance(document.getElementById('mdlUsuario')).hide();
            $('#usId').val('');
            $('#usNombre').val('');
            $('#usAPaterno').val('');
            $('#usAMaterno').val('-');
            $('#usCorreo').val('');
            $('#usTelefono').val('');
            $('#usUsername').val('');
            $('#usPass').val('');
            load();
          }
        });
      });

      $(document).on('click', '.btnEditUser', function() {
        const id = Number($(this).data('id'));
        const u = DATA.usuarios.find(x => Number(x.usId) === id);
        if (!u) return;
        $('#usId').val(u.usId);
        $('#usNombre').val(u.usNombre || '');
        $('#usAPaterno').val(u.usAPaterno || '');
        $('#usAMaterno').val(u.usAMaterno || '-');
        $('#usCorreo').val(u.usCorreo || '');
        $('#usTelefono').val(u.usTelefono || '');
        $('#usUsername').val(u.usUsername || '');
        $('#usPass').val('');
        $('#errUsuario').addClass('d-none').text('');
        $('#warnPass').addClass('d-none').text('');
        new bootstrap.Modal('#mdlUsuario').show();
      });

      $(document).on('click', '.btnToggleUser', function() {
        apiPost('api/clientes/usuario_cli_toggle.php', {
            clId,
            usId: Number($(this).data('id')),
            to: $(this).data('to')
          })
          .done(res => {
            if (!res.success) {
              alert(res.error || 'Error');
              return;
            }
            load();
          });
      });

      // Roles save
      $(document).on('click', '.btnSaveRol', function() {
        const $tr = $(this).closest('tr');
        const usId = Number($tr.data('usid'));
        const ucrRol = $tr.find('.selRol').val();
        const czId = $tr.find('.selZona').val();
        const csId = $tr.find('.selSede').val();

        if (!ucrRol) {
          alert('Selecciona un rol.');
          return;
        }

        apiPost('api/clientes/rol_assign.php', {
          clId,
          usId,
          ucrRol,
          czId: czId, // '' permitido, el backend valida
          csId: csId
        }).done(res => {
          if (!res.success) {
            alert(res.error || 'Error');
            return;
          }
          load();
        });
      });

      $('#btnReload').on('click', load);

      // init
      load();
    })();
  </script>
</body>

</html>