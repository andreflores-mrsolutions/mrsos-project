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
  <title>MR SOS | Inventario</title>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <link href="css/css.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">


  <style>
    .panel {
      background: #fff;
      border-radius: 18px;
      padding: 1.25rem;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .06);
      border: 1px solid rgba(0, 0, 0, .06)
    }

    .muted {
      color: #6c757d
    }

    .admin-topbar {
      background: #fff;
      border-bottom: 1px solid rgba(0, 0, 0, .06);
      position: sticky;
      top: 0;
      z-index: 1040
    }

    .mr-side {
      min-height: 100vh;
      background: rgb(15, 15, 48);
      color: #fff
    }

    .mr-side .nav-link {
      color: rgba(255, 255, 255, .9);
      border-radius: 12px
    }

    .mr-side .nav-link.active,
    .mr-side .nav-link:hover {
      background: rgba(255, 255, 255, .12);
      color: #fff
    }

    .section-title {
      font-size: .8rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      opacity: .75;
      margin-bottom: .5rem;
      margin-top: .75rem
    }

    .brand img {
      max-width: 120px
    }

    .pagination-wrap {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap
    }

    .dark-mode .panel,
    .dark-mode .admin-topbar {
      background: #1f1f1f;
      color: #f5f5f5;
      border-color: rgba(255, 255, 255, .08)
    }

    .dark-mode .table {
      color: #f5f5f5
    }

    .dark-mode .form-control,
    .dark-mode .form-select,
    .dark-mode .modal-content,
    .dark-mode .form-control:focus,
    .dark-mode .form-select:focus,
    .dark-mode .form-control::placeholder {
      background: #161616;
      color: #fff;
      border-color: rgba(255, 255, 255, .12)
    }

    .dark-mode .muted {
      color: #b5b5b5
    }
  </style>
</head>

<body class="<?= ($theme === 'dark') ? 'dark-mode' : '' ?>">
  <div class="container-fluid">
    <div class="row gx-0">
      <?php $activeMenu = 'inventario'; ?>
      <?php require_once __DIR__ . '/partials/sidebar.php'; ?>


      <main class="col-12 col-lg-10">
        <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
              <i class="bi bi-list"></i>
            </button>
            <span class="badge text-bg-success rounded-pill px-3">Admin</span>
            <span class="fw-bold"><?= htmlspecialchars($_SESSION['usUsername'] ?? 'Admin') ?></span>
          </div>

          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="btnTheme" type="button">
              <i class="bi bi-moon"></i>
            </button>
            <a class="btn btn-sm btn-outline-danger" href="../dashboard/logout.php">
              <i class="bi bi-box-arrow-right"></i>
            </a>
          </div>
        </div>

        <div class="p-3 p-lg-4">
          <div class="panel">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
              <div>
                <h4 class="fw-bold">Inventario</h4>
                <div class="muted">Administración de piezas serializadas por refacción.</div>
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-primary" id="btnImportarInventario"><i class="bi bi-file-earmark-excel"></i> Carga masiva XLSX</button>
                <a class="btn btn-outline-secondary" href="api/inventario/import_template.php"><i class="bi bi-download"></i> Descargar plantilla</a>
                <button class="btn btn-primary" id="btnNuevoInventario"><i class="bi bi-plus-circle"></i> Nueva pieza</button>
              </div>
            </div>

            <hr>

            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input id="q" class="form-control" placeholder="Serie, PN, descripción...">
              </div>
              <div class="col-md-3">
                <label class="form-label">Marca</label>
                <select id="maIdFiltro" class="form-select">
                  <option value="">Todas</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Tipo refacción</label>
                <select id="tipoFiltro" class="form-select">
                  <option value="">Todos</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Estatus</label>
                <select id="estatus" class="form-select">
                  <option value="">Todos</option>
                  <option value="Activo">Activo</option>
                  <option value="Inactivo">Inactivo</option>
                  <option value="Cambios">Cambios</option>
                  <option value="Error">Error</option>
                </select>
              </div>
              <div class="col-md-1">
                <label class="form-label">Por pág.</label>
                <select id="perPage" class="form-select">
                  <option value="10">10</option>
                  <option value="20" selected>20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
              </div>
            </div>

            <hr>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <div class="panel text-center">
                  <div class="muted small">Total</div>
                  <div class="fs-3 fw-bold" id="kpiTotal">0</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="panel text-center">
                  <div class="muted small">Activas</div>
                  <div class="fs-3 fw-bold text-success" id="kpiActivos">0</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="panel text-center">
                  <div class="muted small">Inactivas</div>
                  <div class="fs-3 fw-bold text-secondary" id="kpiInactivos">0</div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Serie</th>
                    <th>Part Number</th>
                    <th>Marca</th>
                    <th>Tipo refacción</th>
                    <th>Ubicación</th>
                    <th>Estatus</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody id="tbInventario">
                  <tr>
                    <td colspan="8" class="text-center muted">Cargando...</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="pagination-wrap mt-3">
              <div id="paginationInfo" class="muted small"></div>
              <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="prevPage">Anterior</button>
                <span id="pageText" class="small fw-semibold"></span>
                <button class="btn btn-sm btn-outline-secondary" id="nextPage">Siguiente</button>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade" id="mdlInventario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="mdlInventarioTitle">Nueva pieza</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="invId">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Marca</label>
              <select id="maIdRefFilter" class="form-select">
                <option value="">Todas</option>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">Refacción</label>
              <select id="refId" class="form-select">
                <option value="">Seleccione...</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Serial Number</label>
              <input id="invSerialNumber" class="form-control" maxlength="30" placeholder="Ej. 2106195YSAXEP2000221">
            </div>

            <div class="col-md-4">
              <label class="form-label">Ubicación</label>
              <input id="invUbicacion" class="form-control" maxlength="15" placeholder="Ej. A1-R1">
            </div>

            <div class="col-md-4">
              <label class="form-label">Estatus</label>
              <select id="invEstatus" class="form-select">
                <option value="Activo">Activo</option>
                <option value="Inactivo">Inactivo</option>
                <option value="Cambios">Cambios</option>
                <option value="Error">Error</option>
              </select>
            </div>

            <div class="col-12">
              <div class="small muted" id="refInfo">Selecciona una refacción para ver su detalle.</div>
            </div>
          </div>

          <div class="alert alert-danger d-none mt-3" id="errInventario"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button id="btnGuardarInventario" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Guardar</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="mdlImportInventario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Carga masiva de inventario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label">Archivo XLSX</label>
              <input type="file" id="xlsxInventario" class="form-control" accept=".xlsx">
            </div>
            <div class="col-md-3">
              <label class="form-label">Modo</label>
              <select id="importModeInventario" class="form-select">
                <option value="upsert">Upsert</option>
                <option value="insert_only">Solo insertar</option>
                <option value="update_only">Solo actualizar</option>
              </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button class="btn btn-outline-primary w-100" id="btnParseInventarioXlsx"><i class="bi bi-search"></i> Analizar archivo</button>
            </div>
          </div>

          <div class="mt-3 small muted">Columnas esperadas: part_number, serial_number, ubicacion, estatus</div>

          <div class="alert alert-danger d-none mt-3" id="errImportInventario"></div>

          <div class="mt-3" id="importInvSummaryWrap" style="display:none;">
            <div class="row g-3">
              <div class="col-md-3">
                <div class="panel text-center">
                  <div class="muted small">Total</div>
                  <div class="fs-5 fw-bold" id="sumInvTotal">0</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="panel text-center">
                  <div class="muted small">Con errores</div>
                  <div class="fs-5 fw-bold text-danger" id="sumInvErrors">0</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="panel text-center">
                  <div class="muted small">Insertables</div>
                  <div class="fs-5 fw-bold text-success" id="sumInvInsertables">0</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="panel text-center">
                  <div class="muted small">Actualizables</div>
                  <div class="fs-5 fw-bold text-warning" id="sumInvUpdatables">0</div>
                </div>
              </div>
            </div>
          </div>

          <div class="table-responsive mt-3" id="importInvPreviewWrap" style="display:none;">
            <table class="table table-sm table-hover align-middle">
              <thead>
                <tr>
                  <th>Línea</th>
                  <th>PN</th>
                  <th>Serie</th>
                  <th>Ubicación</th>
                  <th>Existe</th>
                  <th>Errores</th>
                </tr>
              </thead>
              <tbody id="tbInvImportPreview"></tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-danger" id="btnDescargarErroresInventario" disabled><i class="bi bi-file-earmark-arrow-down"></i> Descargar errores CSV</button>
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button class="btn btn-primary" id="btnCommitInventarioImport" disabled><i class="bi bi-check2-square"></i> Confirmar importación</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    (() => {
      const csrf = (window.MRS_CSRF && window.MRS_CSRF.csrf) ? window.MRS_CSRF.csrf : '';
      const mdlInventario = new bootstrap.Modal(document.getElementById('mdlInventario'));
      const mdlImportInventario = new bootstrap.Modal(document.getElementById('mdlImportInventario'));

      let PAGE = 1,
        PER_PAGE = 20,
        TOTAL_PAGES = 1,
        DEBOUNCE_TIMER = null;
      let REFACCIONES_CACHE = [];
      let IMPORT_INVENTARIO_PREVIEW_ROWS = [];
      let IMPORT_INVENTARIO_ERRORS = [];

      function escapeHtml(s) {
        return String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
      }

      function badge(e) {
        const map = {
          Activo: 'success',
          Inactivo: 'secondary',
          Cambios: 'warning',
          Error: 'danger'
        };
        return `<span class="badge text-bg-${map[e]||'secondary'}">${escapeHtml(e||'N/D')}</span>`;
      }

      function toast(msg, type = 'success') {
        const bg = type === 'success' ? 'text-bg-success' : 'text-bg-danger';
        const wrap = document.createElement('div');
        wrap.innerHTML = `<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:2000;"><div class="toast align-items-center ${bg} border-0" role="alert"><div class="d-flex"><div class="toast-body">${escapeHtml(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div></div>`;
        document.body.appendChild(wrap);
        const el = wrap.querySelector('.toast');
        const t = new bootstrap.Toast(el, {
          delay: 2600
        });
        t.show();
        el.addEventListener('hidden.bs.toast', () => wrap.remove());
      }

      async function apiFetch(url, {
        method = 'GET',
        json = null,
        headers = {}
      } = {}) {
        const h = new Headers(headers);
        h.set('X-CSRF-Token', csrf);
        let body = null;
        if (method !== 'GET') {
          h.set('Content-Type', 'application/json; charset=utf-8');
          body = JSON.stringify({
            ...(json || {}),
            csrf_token: csrf
          });
        }
        const res = await fetch(url, {
          method,
          credentials: 'include',
          headers: h,
          body,
          cache: 'no-store'
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) throw new Error(data.error || 'Error API');
        return data;
      }

      async function loadFilters() {
        const marcasRes = await apiFetch('api/marcas/list.php?perPage=100&page=1');
        const marcas = marcasRes.rows || [];
        const marcasOpts = ['<option value="">Todas</option>'].concat(marcas.map(m => `<option value="${m.maId}">${escapeHtml(m.maNombre)}</option>`)).join('');
        $('#maIdFiltro').html(marcasOpts);
        $('#maIdRefFilter').html(marcasOpts.replace('Todas', 'Todas'));

        const refRes = await apiFetch('api/refacciones/list.php?perPage=100&page=1');
        const tipos = [...new Set((refRes.rows || []).map(r => String(r.refTipoRefaccion || '').trim()).filter(Boolean))].sort();
        $('#tipoFiltro').html(['<option value="">Todos</option>'].concat(tipos.map(t => `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`)).join(''));

        await loadRefaccionesSelect();
      }

      async function loadRefaccionesSelect(maId = '') {
        const params = new URLSearchParams({
          page: '1',
          perPage: '100'
        });
        if (maId) params.set('maId', maId);
        const res = await apiFetch(`api/refacciones/list.php?${params.toString()}`);
        REFACCIONES_CACHE = res.rows || [];

        $('#refId').html(
          ['<option value="">Seleccione...</option>']
          .concat(REFACCIONES_CACHE.map(r =>
            `<option value="${r.refId}" data-pn="${escapeHtml(r.refPartNumber)}" data-marca="${escapeHtml(r.maNombre)}" data-tipo="${escapeHtml(r.refTipoRefaccion||'')}">${escapeHtml(r.refPartNumber)} | ${escapeHtml(r.maNombre)} | ${escapeHtml(r.refTipoRefaccion||'')}</option>`
          )).join('')
        );
      }

      function refreshRefInfo() {
        const opt = $('#refId option:selected');
        const refId = $('#refId').val();
        if (!refId) {
          $('#refInfo').text('Selecciona una refacción para ver su detalle.');
          return;
        }
        $('#refInfo').html(`PN: <b>${escapeHtml(opt.data('pn')||'')}</b> | Marca: <b>${escapeHtml(opt.data('marca')||'')}</b> | Tipo: <b>${escapeHtml(opt.data('tipo')||'')}</b>`);
      }

      function resetForm() {
        $('#mdlInventarioTitle').text('Nueva pieza');
        $('#invId').val('');
        $('#maIdRefFilter').val('');
        $('#refId').val('');
        $('#invSerialNumber').val('');
        $('#invUbicacion').val('');
        $('#invEstatus').val('Activo');
        $('#errInventario').addClass('d-none').text('');
        $('#refInfo').text('Selecciona una refacción para ver su detalle.');
      }

      async function fillForm(r) {
        $('#mdlInventarioTitle').text('Editar pieza');
        $('#invId').val(r.invId || '');
        await loadRefaccionesSelect(String(r.maId || ''));
        $('#maIdRefFilter').val(r.maId || '');
        $('#refId').val(r.refId || '');
        $('#invSerialNumber').val(r.invSerialNumber || '');
        $('#invUbicacion').val(r.invUbicacion || '');
        $('#invEstatus').val(r.invEstatus || 'Activo');
        $('#errInventario').addClass('d-none').text('');
        refreshRefInfo();
      }

      function renderPagination(p = {}) {
        $('#pageText').text(`Página ${p.page||1} de ${p.totalPages||1}`);
        $('#paginationInfo').text(`Mostrando ${p.from||0} a ${p.to||0} de ${p.total||0} registros`);
        $('#prevPage').prop('disabled', (p.page || 1) <= 1);
        $('#nextPage').prop('disabled', (p.page || 1) >= (p.totalPages || 1));
      }

      async function loadInventario() {
        $('#tbInventario').html(`<tr><td colspan="8" class="text-center muted">Cargando...</td></tr>`);
        try {
          const params = new URLSearchParams({
            q: $('#q').val().trim(),
            maId: $('#maIdFiltro').val(),
            tipo: $('#tipoFiltro').val(),
            estatus: $('#estatus').val(),
            page: String(PAGE),
            perPage: String(PER_PAGE)
          });

          const data = await apiFetch(`api/inventario/list.php?${params.toString()}`);
          const rows = data.rows || [];
          const p = data.pagination || {};
          const s = data.stats || {};

          PAGE = Number(p.page || 1);
          PER_PAGE = Number(p.perPage || PER_PAGE);
          TOTAL_PAGES = Number(p.totalPages || 1);

          $('#kpiTotal').text(s.total ?? 0);
          $('#kpiActivos').text(s.activos ?? 0);
          $('#kpiInactivos').text(s.inactivos ?? 0);

          if (!rows.length) {
            $('#tbInventario').html(`<tr><td colspan="8" class="text-center muted">Sin registros</td></tr>`);
            renderPagination(p);
            return;
          }

          $('#tbInventario').html(rows.map(r => `
        <tr>
          <td>${escapeHtml(r.invId)}</td>
          <td><b>${escapeHtml(r.invSerialNumber)}</b></td>
          <td>${escapeHtml(r.refPartNumber||'')}</td>
          <td>${escapeHtml(r.maNombre||'')}</td>
          <td>${escapeHtml(r.refTipoRefaccion||'')}</td>
          <td>${escapeHtml(r.invUbicacion||'')}</td>
          <td>${badge(r.invEstatus)}</td>
          <td>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-sm btn-outline-primary btnEdit" data-id="${r.invId}"><i class="bi bi-pencil-square"></i> Editar</button>
              <button class="btn btn-sm ${r.invEstatus==='Activo'?'btn-outline-secondary':'btn-outline-success'} btnToggle" data-id="${r.invId}">
                ${r.invEstatus==='Activo'?'Desactivar':'Activar'}
              </button>
            </div>
          </td>
        </tr>
      `).join(''));

          renderPagination(p);
        } catch (e) {
          $('#tbInventario').html(`<tr><td colspan="8" class="text-center text-danger">${escapeHtml(e.message||e)}</td></tr>`);
        }
      }

      function resetAndLoad() {
        PAGE = 1;
        PER_PAGE = Number($('#perPage').val() || 20);
        loadInventario();
      }

      $('#btnNuevoInventario').on('click', async () => {
        resetForm();
        await loadRefaccionesSelect();
        mdlInventario.show();
      });
      $('#maIdRefFilter').on('change', async function() {
        await loadRefaccionesSelect($(this).val());
        $('#refId').val('');
        refreshRefInfo();
      });
      $('#refId').on('change', refreshRefInfo);

      $('#perPage,#maIdFiltro,#tipoFiltro,#estatus').on('change', resetAndLoad);
      $('#q').on('input', function() {
        clearTimeout(DEBOUNCE_TIMER);
        DEBOUNCE_TIMER = setTimeout(resetAndLoad, 350);
      });
      $('#prevPage').on('click', () => {
        if (PAGE > 1) {
          PAGE--;
          loadInventario();
        }
      });
      $('#nextPage').on('click', () => {
        if (PAGE < TOTAL_PAGES) {
          PAGE++;
          loadInventario();
        }
      });

      $(document).on('click', '.btnEdit', async function() {
        const invId = Number($(this).data('id') || 0);
        if (!invId) return;
        try {
          const res = await apiFetch(`api/inventario/get.php?invId=${encodeURIComponent(invId)}`);
          await fillForm(res.row || {});
          mdlInventario.show();
        } catch (e) {
          toast(e.message || 'No se pudo obtener la pieza.', 'error');
        }
      });

      $('#btnGuardarInventario').on('click', async function() {
        $('#errInventario').addClass('d-none').text('');

        const payload = {
          invId: Number($('#invId').val() || 0),
          refId: Number($('#refId').val() || 0),
          invSerialNumber: $('#invSerialNumber').val().trim(),
          invUbicacion: $('#invUbicacion').val().trim(),
          invEstatus: $('#invEstatus').val()
        };

        if (!payload.refId) return $('#errInventario').removeClass('d-none').text('La refacción es obligatoria.');
        if (!payload.invSerialNumber) return $('#errInventario').removeClass('d-none').text('El Serial Number es obligatorio.');
        if (!payload.invUbicacion) return $('#errInventario').removeClass('d-none').text('La ubicación es obligatoria.');

        const btn = $(this);
        btn.prop('disabled', true);

        try {
          const url = payload.invId > 0 ? 'api/inventario/update.php' : 'api/inventario/create.php';
          const res = await apiFetch(url, {
            method: 'POST',
            json: payload
          });
          mdlInventario.hide();
          await loadInventario();
          toast(res.message || 'Pieza guardada correctamente.');
        } catch (e) {
          $('#errInventario').removeClass('d-none').text(e.message || 'No se pudo guardar la pieza.');
        } finally {
          btn.prop('disabled', false);
        }
      });

      $(document).on('click', '.btnToggle', async function() {
        const invId = Number($(this).data('id') || 0);
        if (!invId) return;
        try {
          const res = await apiFetch('api/inventario/toggle.php', {
            method: 'POST',
            json: {
              invId
            }
          });
          await loadInventario();
          toast(res.message || 'Estatus actualizado.');
        } catch (e) {
          toast(e.message || 'No se pudo cambiar el estatus.', 'error');
        }
      });

      $('#btnImportarInventario').on('click', function() {
        $('#xlsxInventario').val('');
        $('#errImportInventario').addClass('d-none').text('');
        $('#importInvSummaryWrap').hide();
        $('#importInvPreviewWrap').hide();
        $('#tbInvImportPreview').html('');
        $('#btnCommitInventarioImport').prop('disabled', true);
        $('#btnDescargarErroresInventario').prop('disabled', true);
        IMPORT_INVENTARIO_PREVIEW_ROWS = [];
        IMPORT_INVENTARIO_ERRORS = [];
        mdlImportInventario.show();
      });

      $('#btnParseInventarioXlsx').on('click', async function() {
        $('#errImportInventario').addClass('d-none').text('');
        const file = $('#xlsxInventario')[0].files[0];
        if (!file) {
          $('#errImportInventario').removeClass('d-none').text('Selecciona un archivo XLSX.');
          return;
        }

        const fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('xlsx', file);

        try {
          const res = await fetch('api/inventario/import_parse.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
              'X-CSRF-Token': csrf
            },
            body: fd
          });
          const data = await res.json();
          if (!res.ok || data.success === false) throw new Error(data.error || 'Error al analizar archivo.');

          IMPORT_INVENTARIO_PREVIEW_ROWS = data.rows || [];
          IMPORT_INVENTARIO_ERRORS = data.errors || [];

          $('#sumInvTotal').text(data.summary?.total ?? 0);
          $('#sumInvErrors').text(data.summary?.with_errors ?? 0);
          $('#sumInvInsertables').text(data.summary?.insertables ?? 0);
          $('#sumInvUpdatables').text(data.summary?.updatables ?? 0);
          $('#importInvSummaryWrap').show();

          $('#tbInvImportPreview').html((IMPORT_INVENTARIO_PREVIEW_ROWS || []).map(r => `
        <tr>
          <td>${escapeHtml(r.line)}</td>
          <td>${escapeHtml(r.refPartNumber)}</td>
          <td><b>${escapeHtml(r.invSerialNumber)}</b></td>
          <td>${escapeHtml(r.invUbicacion)}</td>
          <td>${r.exists ? '<span class="badge text-bg-warning">Sí</span>' : '<span class="badge text-bg-success">No</span>'}</td>
          <td>${(r.errors||[]).length ? `<span class="text-danger small">${escapeHtml((r.errors||[]).join(' | '))}</span>` : '<span class="text-success">OK</span>'}</td>
        </tr>
      `).join(''));

          $('#importInvPreviewWrap').show();
          $('#btnCommitInventarioImport').prop('disabled', IMPORT_INVENTARIO_PREVIEW_ROWS.length === 0);
          $('#btnDescargarErroresInventario').prop('disabled', IMPORT_INVENTARIO_ERRORS.length === 0);
        } catch (e) {
          $('#errImportInventario').removeClass('d-none').text(e.message || 'No se pudo analizar el archivo.');
        }
      });

      $('#btnCommitInventarioImport').on('click', async function() {
        if (!IMPORT_INVENTARIO_PREVIEW_ROWS.length) return;
        const btn = $(this);
        btn.prop('disabled', true);

        try {
          const res = await apiFetch('api/inventario/import_commit.php', {
            method: 'POST',
            json: {
              mode: $('#importModeInventario').val(),
              rows: IMPORT_INVENTARIO_PREVIEW_ROWS
            }
          });

          mdlImportInventario.hide();
          await loadInventario();
          toast(`Importación completada. Insertados: ${res.summary?.inserted ?? 0}, actualizados: ${res.summary?.updated ?? 0}, omitidos: ${res.summary?.skipped ?? 0}.`);
        } catch (e) {
          $('#errImportInventario').removeClass('d-none').text(e.message || 'No se pudo confirmar la importación.');
        } finally {
          btn.prop('disabled', false);
        }
      });

      $('#btnDescargarErroresInventario').on('click', async function() {
        if (!IMPORT_INVENTARIO_ERRORS.length) return;
        try {
          const res = await fetch('api/inventario/import_errors_csv.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json; charset=utf-8',
              'X-CSRF-Token': csrf
            },
            body: JSON.stringify({
              csrf_token: csrf,
              errors: IMPORT_INVENTARIO_ERRORS
            })
          });
          if (!res.ok) throw new Error('No se pudo generar el CSV de errores.');

          const blob = await res.blob();
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = 'errores_import_inventario.csv';
          document.body.appendChild(a);
          a.click();
          a.remove();
          URL.revokeObjectURL(url);
        } catch (e) {
          $('#errImportInventario').removeClass('d-none').text(e.message || 'No se pudo descargar el CSV de errores.');
        }
      });

      $('#btnTheme').on('click', function() {
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');
        document.cookie = `mrs_theme=${isDark ? 'dark' : 'light'}; path=/; max-age=31536000`;
      });

      (async function init() {
        await loadFilters();
        await loadInventario();
      })().catch(err => {
        console.error(err);
        toast(err.message || 'No se pudo inicializar la pantalla.', 'error');
      });
    })();
  </script>
</body>

</html>