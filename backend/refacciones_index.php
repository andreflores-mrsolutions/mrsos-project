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
    <title>MR SOS | Refacciones</title>

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
            border: 1px solid rgba(0, 0, 0, .06);
        }

        .muted {
            color: #6c757d;
        }

        .admin-topbar {
            background: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, .06);
            position: sticky;
            top: 0;
            z-index: 1040;
        }

        .mr-side {
            min-height: 100vh;
            background: rgb(15, 15, 48);
            color: #fff;
        }

        .mr-side .nav-link {
            color: rgba(255, 255, 255, .9);
            border-radius: 12px;
        }

        .mr-side .nav-link.active,
        .mr-side .nav-link:hover {
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }

        .section-title {
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            opacity: .75;
            margin-bottom: .5rem;
            margin-top: .75rem;
        }

        .brand img {
            max-width: 120px;
        }

        .pagination-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .dark-mode .panel,
        .dark-mode .admin-topbar {
            background: #1f1f1f;
            color: #f5f5f5;
            border-color: rgba(255, 255, 255, .08);
        }

        .dark-mode .table {
            color: #f5f5f5;
        }

        .dark-mode .form-control,
        .dark-mode .form-select,
        .dark-mode .modal-content,
        .dark-mode .form-control:focus,
        .dark-mode .form-select:focus,
        .dark-mode .form-control::placeholder {
            background: #161616;
            color: #fff;
            border-color: rgba(255, 255, 255, .12);
        }

        .dark-mode .muted {
            color: #b5b5b5;
        }
    </style>
</head>

<body class="<?= ($theme === 'dark') ? 'dark-mode' : '' ?>">
    <div class="container-fluid">
        <div class="row gx-0">

            <?php $activeMenu = 'refacciones'; ?>
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
                                <h4 class="fw-bold">Catálogo de Refacciones</h4>
                                <div class="muted">Administración de parts, discos, RAM, NICs, CPUs y otras piezas.</div>
                            </div>
                            <button class="btn btn-primary" id="btnNuevaRefaccion">
                                <i class="bi bi-plus-circle"></i> Nueva refacción
                            </button>
                            <button class="btn btn-outline-primary" id="btnImportarRefacciones">
                                <i class="bi bi-file-earmark-excel"></i> Carga masiva XLSX
                            </button>

                            <a class="btn btn-outline-secondary" href="api/refacciones/import_template.php">
                                <i class="bi bi-download"></i> Descargar plantilla
                            </a>
                        </div>

                        <hr>

                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Buscar</label>
                                <input id="q" class="form-control" placeholder="Part Number, descripción...">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Marca</label>
                                <select id="maIdFiltro" class="form-select">
                                    <option value="">Todas</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Estatus</label>
                                <select id="estatus" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                    <option value="Cambios">Cambios</option>
                                    <option value="Error">Error</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Por página</label>
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
                                        <th>Part Number</th>
                                        <th>Marca</th>
                                        <th>Tipo refacción</th>
                                        <th>Interfaz</th>
                                        <th>Tipo</th>
                                        <th>Capacidad</th>
                                        <th>Velocidad</th>
                                        <th>Estatus</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbRefacciones">
                                    <tr>
                                        <td colspan="10" class="text-center muted">Cargando...</td>
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

    <div class="modal fade" id="mdlRefaccion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mdlRefaccionTitle">Nueva refacción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="refId">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Marca</label>
                            <select id="maId" class="form-select">
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Part Number</label>
                            <input id="refPartNumber" class="form-control" maxlength="50" placeholder="Ej. 875076-B21">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Tipo de refacción</label>
                            <select id="refTipoRefaccion" class="form-select">
                                <option value="">Seleccione...</option>
                                <option value="Network Card">Network Card</option>
                                <option value="Video Card">Video Card</option>
                                <option value="RAID Card">RAID Card</option>
                                <option value="PCIE Card">PCIE Card</option>
                                <option value="Motherboard">Motherboard</option>
                                <option value="Hard Disk">Hard Disk</option>
                                <option value="DIMM">DIMM</option>
                                <option value="Processador">Processador</option>
                                <option value="Fan Module">Fan Module</option>
                                <option value="Gbics">Gbics</option>
                                <option value="Power Supply">Power Supply</option>
                                <option value="Cinta LTO">Cinta LTO</option>
                                <option value="Backplain">Backplain</option>
                                <option value="Nodo">Nodo</option>
                                <option value="Flash Card">Flash Card</option>
                                <option value="Disipador de Calor">Disipador de Calor</option>
                                <option value="Manage Card">Manage Card</option>
                                <option value="Diagnostic Card">Diagnostic Card</option>
                                <option value="Caddy">Caddy</option>
                                <option value="Sistema Operativo">Sistema Operativo</option>
                                <option value="Swicth Module">Swicth Module</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Interfaz</label>
                            <input id="refInterfaz" class="form-control" maxlength="25" placeholder="Ej. SAS / SATA / PCIe">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <input id="refTipo" class="form-control" maxlength="15" placeholder="Ej. SFF / LFF / DDR4">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Capacidad</label>
                            <input id="refCapacidad" class="form-control" type="number" step="0.01" min="0" placeholder="0">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Unidad capacidad</label>
                            <input id="refTpCapacidad" class="form-control" maxlength="15" placeholder="GB / TB / MHz">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Velocidad</label>
                            <input id="refVelocidad" class="form-control" type="number" step="0.01" min="0" placeholder="0">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Unidad velocidad</label>
                            <input id="refTpVelocidad" class="form-control" maxlength="15" placeholder="RPM / Gbps / MT/s">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Estatus</label>
                            <select id="refEstatus" class="form-select">
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                                <option value="Cambios">Cambios</option>
                                <option value="Error">Error</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea id="refDescripcion" class="form-control" rows="3" placeholder="Descripción técnica de la refacción"></textarea>
                        </div>
                    </div>

                    <div class="alert alert-danger d-none mt-3" id="errRefaccion"></div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button id="btnGuardarRefaccion" class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="mdlImportRefacciones" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Carga masiva de refacciones</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Archivo XLSX</label>
                            <input type="file" id="xlsxRefacciones" class="form-control" accept=".xlsx">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Modo</label>
                            <select id="importMode" class="form-select">
                                <option value="upsert">Upsert</option>
                                <option value="insert_only">Solo insertar</option>
                                <option value="update_only">Solo actualizar</option>
                            </select>
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-outline-primary w-100" id="btnParseXlsx">
                                <i class="bi bi-search"></i> Analizar archivo
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 small muted">
                        Columnas esperadas: marca, part_number, descripcion, tipo_refaccion, interfaz, tipo, capacidad, tp_capacidad, velocidad, tp_velocidad, estatus
                    </div>

                    <div class="alert alert-danger d-none mt-3" id="errImportRef"></div>

                    <div class="mt-3" id="importSummaryWrap" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="panel text-center">
                                    <div class="muted small">Total</div>
                                    <div class="fs-5 fw-bold" id="sumTotal">0</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="panel text-center">
                                    <div class="muted small">Con errores</div>
                                    <div class="fs-5 fw-bold text-danger" id="sumErrors">0</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="panel text-center">
                                    <div class="muted small">Insertables</div>
                                    <div class="fs-5 fw-bold text-success" id="sumInsertables">0</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="panel text-center">
                                    <div class="muted small">Actualizables</div>
                                    <div class="fs-5 fw-bold text-warning" id="sumUpdatables">0</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mt-3" id="importPreviewWrap" style="display:none;">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Línea</th>
                                    <th>Marca</th>
                                    <th>Part Number</th>
                                    <th>Tipo</th>
                                    <th>Existe</th>
                                    <th>Errores</th>
                                </tr>
                            </thead>
                            <tbody id="tbImportPreview"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button class="btn btn-primary" id="btnCommitImport" disabled>
                        <i class="bi bi-check2-square"></i> Confirmar importación
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const csrf = (window.MRS_CSRF && window.MRS_CSRF.csrf) ? window.MRS_CSRF.csrf : '';
            const mdlRefaccion = new bootstrap.Modal(document.getElementById('mdlRefaccion'));

            let PAGE = 1;
            let PER_PAGE = 20;
            let TOTAL_PAGES = 1;
            let DEBOUNCE_TIMER = null;
            const mdlImportRef = new bootstrap.Modal(document.getElementById('mdlImportRefacciones'));
            let IMPORT_PREVIEW_ROWS = [];
            let IMPORT_ERRORS = [];

            $('#btnImportarRefacciones').on('click', function() {
                $('#xlsxRefacciones').val('');
                $('#errImportRef').addClass('d-none').text('');
                $('#importSummaryWrap').hide();
                $('#importPreviewWrap').hide();
                $('#tbImportPreview').html('');
                $('#btnCommitImport').prop('disabled', true);
                IMPORT_ERRORS = [];
                $('#btnDescargarErrores').prop('disabled', true);
                IMPORT_PREVIEW_ROWS = [];
                mdlImportRef.show();
            });

            $('#btnParseXlsx').on('click', async function() {
                $('#errImportRef').addClass('d-none').text('');

                const file = $('#xlsxRefacciones')[0].files[0];
                if (!file) {
                    $('#errImportRef').removeClass('d-none').text('Selecciona un archivo XLSX.');
                    return;
                }

                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('xlsx', file);

                try {
                    const res = await fetch('api/refacciones/import_parse.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'X-CSRF-Token': csrf
                        },
                        body: fd
                    });
                    const data = await res.json();
                    if (!res.ok || data.success === false) {
                        throw new Error(data.error || 'Error al analizar archivo.');
                    }

                    IMPORT_PREVIEW_ROWS = data.rows || [];
                    IMPORT_ERRORS = data.errors || [];

                    $('#btnDescargarErrores').prop('disabled', IMPORT_ERRORS.length === 0);
                    $('#sumTotal').text(data.summary?.total ?? 0);
                    $('#sumErrors').text(data.summary?.with_errors ?? 0);
                    $('#sumInsertables').text(data.summary?.insertables ?? 0);
                    $('#sumUpdatables').text(data.summary?.updatables ?? 0);
                    $('#importSummaryWrap').show();

                    $('#tbImportPreview').html((IMPORT_PREVIEW_ROWS || []).map(r => `
                            <tr>
                                <td>${escapeHtml(r.line)}</td>
                                <td>${escapeHtml(r.marca)}</td>
                                <td><b>${escapeHtml(r.refPartNumber)}</b></td>
                                <td>${escapeHtml(r.refTipoRefaccion)}</td>
                                <td>${r.exists ? '<span class="badge text-bg-warning">Sí</span>' : '<span class="badge text-bg-success">No</span>'}</td>
                                <td>${
                        (r.errors || []).length
                            ? `<div class="text-danger small">${escapeHtml((r.errors || []).join(' | '))}</div>`
                            : '<span class="text-success">OK</span>'
                        }</td>
                            </tr>
                            `).join(''));

                    $('#importPreviewWrap').show();
                    $('#btnCommitImport').prop('disabled', IMPORT_PREVIEW_ROWS.length === 0);

                } catch (e) {
                    $('#errImportRef').removeClass('d-none').text(e.message || 'No se pudo analizar el archivo.');
                }
            });

            $('#btnCommitImport').on('click', async function() {
                if (!IMPORT_PREVIEW_ROWS.length) return;

                const btn = $(this);
                btn.prop('disabled', true);

                try {
                    const res = await apiFetch('api/refacciones/import_commit.php', {
                        method: 'POST',
                        json: {
                            mode: $('#importMode').val(),
                            rows: IMPORT_PREVIEW_ROWS
                        }
                    });

                    mdlImportRef.hide();
                    await loadRefacciones();
                    toast(
                        `Importación completada. Insertados: ${res.summary?.inserted ?? 0}, actualizados: ${res.summary?.updated ?? 0}, omitidos: ${res.summary?.skipped ?? 0}.`
                    );
                } catch (e) {
                    $('#errImportRef').removeClass('d-none').text(e.message || 'No se pudo confirmar la importación.');
                } finally {
                    btn.prop('disabled', false);
                }
            });
            $('#btnDescargarErrores').on('click', async function() {
                if (!IMPORT_ERRORS.length) return;

                try {
                    const res = await fetch('../api/refacciones/import_errors_csv.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify({
                            csrf_token: csrf,
                            errors: IMPORT_ERRORS
                        })
                    });

                    if (!res.ok) {
                        throw new Error('No se pudo generar el CSV de errores.');
                    }

                    const blob = await res.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'errores_import_refacciones.csv';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                } catch (e) {
                    $('#errImportRef').removeClass('d-none').text(e.message || 'No se pudo descargar el CSV de errores.');
                }
            });

            function escapeHtml(s) {
                return String(s ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function badge(e) {
                const map = {
                    Activo: 'success',
                    Inactivo: 'secondary',
                    Cambios: 'warning',
                    Error: 'danger'
                };
                return `<span class="badge text-bg-${map[e]||'secondary'}">${escapeHtml(e || 'N/D')}</span>`;
            }

            function toast(msg, type = 'success') {
                const bg = type === 'success' ? 'text-bg-success' : 'text-bg-danger';
                const wrap = document.createElement('div');
                wrap.innerHTML = `
      <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:2000;">
        <div class="toast align-items-center ${bg} border-0" role="alert">
          <div class="d-flex">
            <div class="toast-body">${escapeHtml(msg)}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
          </div>
        </div>
      </div>
    `;
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
                if (!res.ok || data.success === false) {
                    throw new Error(data.error || 'Error API');
                }
                return data;
            }

            async function loadMarcasSelects() {
                const res = await apiFetch('api/marcas/list.php?perPage=100&page=1');
                const rows = Array.isArray(res.rows) ? res.rows : [];

                const opts = ['<option value="">Seleccione...</option>']
                    .concat(rows.map(r => {
                        const label = r.maEstatus && r.maEstatus !== 'Activo' ?
                            `${escapeHtml(r.maNombre)} (${escapeHtml(r.maEstatus)})` :
                            escapeHtml(r.maNombre);
                        return `<option value="${r.maId}">${label}</option>`;
                    }))
                    .join('');

                $('#maId').html(opts);

                const optsFiltro = ['<option value="">Todas</option>']
                    .concat(rows.map(r => `<option value="${r.maId}">${escapeHtml(r.maNombre)}</option>`))
                    .join('');

                $('#maIdFiltro').html(optsFiltro);
            }

            function resetForm() {
                $('#mdlRefaccionTitle').text('Nueva refacción');
                $('#refId').val('');
                $('#maId').val('');
                $('#refPartNumber').val('');
                $('#refDescripcion').val('');
                $('#refTipoRefaccion').val('');
                $('#refInterfaz').val('');
                $('#refTipo').val('');
                $('#refCapacidad').val('');
                $('#refTpCapacidad').val('');
                $('#refVelocidad').val('');
                $('#refTpVelocidad').val('');
                $('#refEstatus').val('Activo');
                $('#errRefaccion').addClass('d-none').text('');
            }

            function fillForm(r) {
                $('#mdlRefaccionTitle').text('Editar refacción');
                $('#refId').val(r.refId || '');
                $('#maId').val(r.maId || '');
                $('#refPartNumber').val(r.refPartNumber || '');
                $('#refDescripcion').val(r.refDescripcion || '');
                $('#refTipoRefaccion').val(r.refTipoRefaccion || '');
                $('#refInterfaz').val(r.refInterfaz || '');
                $('#refTipo').val(r.refTipo || '');
                $('#refCapacidad').val(r.refCapacidad || '');
                $('#refTpCapacidad').val(r.refTpCapacidad || '');
                $('#refVelocidad').val(r.refVelocidad || '');
                $('#refTpVelocidad').val(r.refTpVelocidad || '');
                $('#refEstatus').val(r.refEstatus || 'Activo');
                $('#errRefaccion').addClass('d-none').text('');
            }

            function renderPagination(p = {}) {
                $('#pageText').text(`Página ${p.page || 1} de ${p.totalPages || 1}`);
                $('#paginationInfo').text(`Mostrando ${p.from || 0} a ${p.to || 0} de ${p.total || 0}`);
                $('#prevPage').prop('disabled', (p.page || 1) <= 1);
                $('#nextPage').prop('disabled', (p.page || 1) >= (p.totalPages || 1));
            }

            async function loadRefacciones() {
                $('#tbRefacciones').html(`<tr><td colspan="10" class="text-center muted">Cargando...</td></tr>`);

                try {
                    const params = new URLSearchParams({
                        q: $('#q').val().trim(),
                        maId: $('#maIdFiltro').val(),
                        estatus: $('#estatus').val(),
                        page: String(PAGE),
                        perPage: String(PER_PAGE)
                    });

                    const data = await apiFetch(`api/refacciones/list.php?${params.toString()}`);
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
                        $('#tbRefacciones').html(`<tr><td colspan="10" class="text-center muted">Sin registros</td></tr>`);
                        renderPagination(p);
                        return;
                    }

                    $('#tbRefacciones').html(rows.map(r => `
        <tr>
          <td>${escapeHtml(r.refId)}</td>
          <td><b>${escapeHtml(r.refPartNumber)}</b></td>
          <td>${escapeHtml(r.maNombre || '')}</td>
          <td>${escapeHtml(r.refTipoRefaccion || '')}</td>
          <td>${escapeHtml(r.refInterfaz || '')}</td>
          <td>${escapeHtml(r.refTipo || '')}</td>
          <td>${escapeHtml(r.refCapacidad || '')} ${escapeHtml(r.refTpCapacidad || '')}</td>
          <td>${escapeHtml(r.refVelocidad || '')} ${escapeHtml(r.refTpVelocidad || '')}</td>
          <td>${badge(r.refEstatus)}</td>
          <td>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-sm btn-outline-primary btnEdit" data-id="${r.refId}">
                <i class="bi bi-pencil-square"></i> Editar
              </button>
              <button class="btn btn-sm ${r.refEstatus === 'Activo' ? 'btn-outline-secondary' : 'btn-outline-success'} btnToggle" data-id="${r.refId}">
                ${r.refEstatus === 'Activo' ? 'Desactivar' : 'Activar'}
              </button>
            </div>
          </td>
        </tr>
      `).join(''));

                    renderPagination(p);
                } catch (e) {
                    $('#tbRefacciones').html(`<tr><td colspan="10" class="text-center text-danger">${escapeHtml(e.message || e)}</td></tr>`);
                }
            }

            function resetAndLoad() {
                PAGE = 1;
                PER_PAGE = Number($('#perPage').val() || 20);
                loadRefacciones();
            }

            $('#btnNuevaRefaccion').on('click', () => {
                resetForm();
                mdlRefaccion.show();
            });

            $('#perPage, #estatus, #maIdFiltro').on('change', resetAndLoad);

            $('#q').on('input', function() {
                clearTimeout(DEBOUNCE_TIMER);
                DEBOUNCE_TIMER = setTimeout(resetAndLoad, 350);
            });

            $('#prevPage').on('click', () => {
                if (PAGE > 1) {
                    PAGE--;
                    loadRefacciones();
                }
            });

            $('#nextPage').on('click', () => {
                if (PAGE < TOTAL_PAGES) {
                    PAGE++;
                    loadRefacciones();
                }
            });

            $(document).on('click', '.btnEdit', async function() {
                const refId = Number($(this).data('id') || 0);
                if (!refId) return;

                try {
                    const res = await apiFetch(`api/refacciones/get.php?refId=${encodeURIComponent(refId)}`);
                    fillForm(res.row || {});
                    mdlRefaccion.show();
                } catch (e) {
                    toast(e.message || 'No se pudo obtener la refacción.', 'error');
                }
            });

            $('#btnGuardarRefaccion').on('click', async function() {
                $('#errRefaccion').addClass('d-none').text('');

                const payload = {
                    refId: Number($('#refId').val() || 0),
                    maId: Number($('#maId').val() || 0),
                    refPartNumber: $('#refPartNumber').val().trim(),
                    refDescripcion: $('#refDescripcion').val().trim(),
                    refTipoRefaccion: $('#refTipoRefaccion').val().trim(),
                    refInterfaz: $('#refInterfaz').val().trim(),
                    refTipo: $('#refTipo').val().trim(),
                    refCapacidad: $('#refCapacidad').val().trim(),
                    refTpCapacidad: $('#refTpCapacidad').val().trim(),
                    refVelocidad: $('#refVelocidad').val().trim(),
                    refTpVelocidad: $('#refTpVelocidad').val().trim(),
                    refEstatus: $('#refEstatus').val()
                };

                if (!payload.maId) return $('#errRefaccion').removeClass('d-none').text('La marca es obligatoria.');
                if (!payload.refPartNumber) return $('#errRefaccion').removeClass('d-none').text('El Part Number es obligatorio.');
                if (!payload.refDescripcion) return $('#errRefaccion').removeClass('d-none').text('La descripción es obligatoria.');
                if (!payload.refTipoRefaccion) return $('#errRefaccion').removeClass('d-none').text('El tipo de refacción es obligatorio.');
                if (!payload.refInterfaz) return $('#errRefaccion').removeClass('d-none').text('La interfaz es obligatoria.');
                if (!payload.refTipo) return $('#errRefaccion').removeClass('d-none').text('El tipo es obligatorio.');

                const btn = $(this);
                btn.prop('disabled', true);

                try {
                    const url = payload.refId > 0 ? 'api/refacciones/update.php' : 'api/refacciones/create.php';
                    const res = await apiFetch(url, {
                        method: 'POST',
                        json: payload
                    });
                    mdlRefaccion.hide();
                    await loadRefacciones();
                    toast(res.message || 'Refacción guardada correctamente.');
                } catch (e) {
                    $('#errRefaccion').removeClass('d-none').text(e.message || 'No se pudo guardar la refacción.');
                } finally {
                    btn.prop('disabled', false);
                }
            });

            $(document).on('click', '.btnToggle', async function() {
                const refId = Number($(this).data('id') || 0);
                if (!refId) return;

                try {
                    const res = await apiFetch('api/refacciones/toggle.php', {
                        method: 'POST',
                        json: {
                            refId
                        }
                    });
                    await loadRefacciones();
                    toast(res.message || 'Estatus actualizado.');
                } catch (e) {
                    toast(e.message || 'No se pudo cambiar el estatus.', 'error');
                }
            });

            $('#btnTheme').on('click', function() {
                document.body.classList.toggle('dark-mode');
                const isDark = document.body.classList.contains('dark-mode');
                document.cookie = `mrs_theme=${isDark ? 'dark' : 'light'}; path=/; max-age=31536000`;
            });

            (async function init() {
                await loadMarcasSelects();
                await loadRefacciones();
            })().catch(err => {
                console.error(err);
                toast(err.message || 'No se pudo inicializar la pantalla.', 'error');
            });
        })();
    </script>
</body>

</html>