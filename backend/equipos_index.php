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
        window.SESSION = <?= json_encode([
                                'usId' => (int)($_SESSION['usId'] ?? 0),
                                'usRol' => (string)($_SESSION['usRol'] ?? ''),
                                'usUsername' => (string)($_SESSION['usUsername'] ?? 'Admin'),
                            ], JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MR SOS | Equipos</title>

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
            background: #200f4c;
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

        .brand-logo-sm {
            width: 72px;
            height: 42px;
            object-fit: contain;
            border-radius: 10px;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, .08);
            padding: .25rem;
        }

        .eq-logo-sm {
            width: 84px;
            height: 48px;
            object-fit: contain;
            border-radius: 10px;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, .08);
            padding: .25rem;
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

        .preview-box {
            min-height: 120px;
            border: 1px dashed rgba(0, 0, 0, .15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafafa;
            padding: 1rem;
        }

        .preview-box img {
            max-width: 240px;
            max-height: 110px;
            object-fit: contain;
        }

        .dark-mode .preview-box {
            background: #111;
            border-color: rgba(255, 255, 255, .12);
        }

        .pagination-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
    </style>
</head>

<body class="<?= ($theme === 'dark') ? 'dark-mode' : '' ?>">
    <div class="container-fluid">
        <div class="row gx-0">

            <?php $activeMenu = 'equipos'; ?>
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
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <div>
                                <h4 class="fw-bold mb-1">Catálogo de Equipos</h4>
                                <div class="muted">Alta, edición, búsqueda, paginación y activación/desactivación de equipos/modelos.</div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary" id="btnNuevoEquipo">
                                    <i class="bi bi-plus-circle"></i> Nuevo equipo
                                </button>
                                <button class="btn btn-outline-secondary" id="btnImportarEquipos">
                                    <i class="bi bi-file-earmark-excel"></i> Carga masiva XLSX
                                </button>
                                <a class="btn btn-outline-secondary" href="api/equipos/import_template.php">
                                    <i class="bi bi-download"></i> Descargar plantilla
                                </a>
                            </div>
                        </div>

                        <hr>

                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Buscar</label>
                                <input id="q" class="form-control" placeholder="Modelo, versión, CPU, tipo...">
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">Marca</label>
                                <select id="maIdFiltro" class="form-select">
                                    <option value="">Todas</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-2">
                                <label class="form-label">Estatus</label>
                                <select id="estatus" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                    <option value="Cambios">Cambios</option>
                                    <option value="Error">Error</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-2">
                                <label class="form-label">Por página</label>
                                <select id="perPage" class="form-select">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-1 d-grid">
                                <button id="btnBuscar" class="btn btn-outline-secondary">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>

                        <div class="row g-3 mt-1 mb-2">
                            <div class="col-12 col-md-4">
                                <div class="panel h-100">
                                    <div class="muted small">Total</div>
                                    <div class="fs-3 fw-bold" id="kpiTotal">0</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="panel h-100">
                                    <div class="muted small">Activos</div>
                                    <div class="fs-3 fw-bold text-success" id="kpiActivos">0</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="panel h-100">
                                    <div class="muted small">Inactivos</div>
                                    <div class="fs-3 fw-bold text-secondary" id="kpiInactivos">0</div>
                                </div>
                            </div>
                        </div>


                        <div class="table-responsive mt-3">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="width:90px;">ID</th>
                                        <th style="width:110px;">Imagen</th>
                                        <th>Modelo</th>
                                        <th style="width:170px;">Marca</th>
                                        <th style="width:160px;">Tipo equipo</th>
                                        <th style="width:160px;">Estatus</th>
                                        <th style="width:230px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbEquipos">
                                    <tr>
                                        <td colspan="7" class="text-center muted">Cargando...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="pagination-wrap mt-3">
                            <div id="paginationInfo" class="small muted">Mostrando 0 a 0 de 0 registros</div>

                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-outline-secondary btn-sm" id="pagePrev">
                                    <i class="bi bi-chevron-left"></i> Anterior
                                </button>
                                <span class="small fw-semibold" id="pageText">Página 1 de 1</span>
                                <button class="btn btn-outline-secondary btn-sm" id="pageNext">
                                    Siguiente <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="mdlEquipo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mdlEquipoTitle">Nuevo equipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="eqId">
                    <input type="hidden" id="eqImgPathActual">

                    <div class="row g-3 mb-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Imagen del equipo</label>
                            <input type="file" id="eqImagen" class="form-control" accept=".png,.jpg,.jpeg,.webp,.svg">
                            <div class="form-text">Formatos: PNG, JPG, JPEG, WEBP, SVG. Máximo 2 MB.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Vista previa</label>
                            <div class="preview-box">
                                <img id="eqPreview" src="../img/Equipos/default.png" alt="Preview">
                            </div>
                            <div class="small muted mt-2" id="eqPreviewText">Sin imagen cargada</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Marca</label>
                            <select id="maId" class="form-select">
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Modelo</label>
                            <input type="text" id="eqModelo" class="form-control" maxlength="50" placeholder="Ej. FusionServer 2288H V7">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Versión</label>
                            <input type="text" id="eqVersion" class="form-control" maxlength="25" placeholder="Ej. V7">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Tipo de equipo</label>
                            <input type="text" id="eqTipoEquipo" class="form-control" maxlength="50" placeholder="Ej. Servidor, Storage, Librería">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Tipo</label>
                            <input type="text" id="eqTipo" class="form-control" maxlength="50" placeholder="Ej. Rack 2U">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">CPU</label>
                            <input type="text" id="eqCPU" class="form-control" maxlength="50" placeholder="Ej. Intel Xeon Scalable 4th Gen">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Sockets</label>
                            <input type="text" id="eqSockets" class="form-control" maxlength="50" placeholder="Ej. 2">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">RAM máxima</label>
                            <input type="text" id="eqMaxRAM" class="form-control" maxlength="50" placeholder="Ej. Hasta 8 TB">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">NIC</label>
                            <input type="text" id="eqNIC" class="form-control" maxlength="50" placeholder="Ej. 2 x 10 GbE">
                        </div>

                        <div class="col-12 col-md-8">
                            <label class="form-label">Descripción</label>
                            <textarea id="eqDescripcion" class="form-control" rows="3" placeholder="Descripción del equipo/modelo"></textarea>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Estatus</label>
                            <select id="eqEstatus" class="form-select">
                                <option value="Activo">Activo</option>
                                <option value="Inactivo">Inactivo</option>
                                <option value="Cambios">Cambios</option>
                                <option value="Error">Error</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-danger d-none mt-3" id="errEquipo"></div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button id="btnGuardarEquipo" class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="mdlImportEquipos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Carga masiva de equipos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Archivo XLSX</label>
                            <input type="file" id="xlsxEquipos" class="form-control" accept=".xlsx">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Modo</label>
                            <select id="importModeEquipos" class="form-select">
                                <option value="upsert">Upsert</option>
                                <option value="insert_only">Solo insertar</option>
                                <option value="update_only">Solo actualizar</option>
                            </select>
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-outline-primary w-100" id="btnParseEquiposXlsx">
                                <i class="bi bi-search"></i> Analizar archivo
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 small muted">
                        Columnas esperadas: marca, modelo, version, tipo_equipo, tipo, cpu, sockets, max_ram, nic, descripcion, estatus
                    </div>

                    <div class="alert alert-danger d-none mt-3" id="errImportEquipos"></div>

                    <div class="mt-3" id="importEquiposSummaryWrap" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="panel text-center">
                                    <div class="muted small">Total</div>
                                    <div class="fs-5 fw-bold" id="sumEqTotal">0</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="panel text-center">
                                    <div class="muted small">Con errores</div>
                                    <div class="fs-5 fw-bold text-danger" id="sumEqErrors">0</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="panel text-center">
                                    <div class="muted small">Insertables</div>
                                    <div class="fs-5 fw-bold text-success" id="sumEqInsertables">0</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="panel text-center">
                                    <div class="muted small">Actualizables</div>
                                    <div class="fs-5 fw-bold text-warning" id="sumEqUpdatables">0</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mt-3" id="importEquiposPreviewWrap" style="display:none;">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Línea</th>
                                    <th>Marca</th>
                                    <th>Modelo</th>
                                    <th>Versión</th>
                                    <th>Existe</th>
                                    <th>Errores</th>
                                </tr>
                            </thead>
                            <tbody id="tbEquiposImportPreview"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-danger" id="btnDescargarErroresEquipos" disabled>
                        <i class="bi bi-file-earmark-arrow-down"></i> Descargar errores CSV
                    </button>
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button class="btn btn-primary" id="btnCommitEquiposImport" disabled>
                        <i class="bi bi-check2-square"></i> Confirmar importación
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const csrf = (window.MRS_CSRF && window.MRS_CSRF.csrf) ? window.MRS_CSRF.csrf : '';
            const DEFAULT_IMG = '../img/Equipos/default.png';
            const DEFAULT_BRAND = '../img/Marcas/default.png';
            const mdlEquipo = new bootstrap.Modal(document.getElementById('mdlEquipo'));

            let PAGE = 1;
            let PER_PAGE = 20;
            let TOTAL_PAGES = 1;
            let TOTAL_ROWS = 0;
            let DEBOUNCE_TIMER = null;
            const mdlImportEquipos = new bootstrap.Modal(document.getElementById('mdlImportEquipos'));
            let IMPORT_EQUIPOS_PREVIEW_ROWS = [];
            let IMPORT_EQUIPOS_ERRORS = [];

            $('#btnImportarEquipos').on('click', function() {
                $('#xlsxEquipos').val('');
                $('#errImportEquipos').addClass('d-none').text('');
                $('#importEquiposSummaryWrap').hide();
                $('#importEquiposPreviewWrap').hide();
                $('#tbEquiposImportPreview').html('');
                $('#btnCommitEquiposImport').prop('disabled', true);
                IMPORT_EQUIPOS_PREVIEW_ROWS = [];
                mdlImportEquipos.show();
            });

            $('#btnParseEquiposXlsx').on('click', async function() {
                $('#errImportEquipos').addClass('d-none').text('');

                const file = $('#xlsxEquipos')[0].files[0];
                if (!file) {
                    $('#errImportEquipos').removeClass('d-none').text('Selecciona un archivo XLSX.');
                    return;
                }

                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('xlsx', file);

                try {
                    const res = await fetch('api/equipos/import_parse.php', {
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

                    IMPORT_EQUIPOS_PREVIEW_ROWS = data.rows || [];
                    IMPORT_EQUIPOS_ERRORS = data.errors || [];
                    $('#btnDescargarErroresEquipos').prop('disabled', IMPORT_EQUIPOS_ERRORS.length === 0);

                    $('#sumEqTotal').text(data.summary?.total ?? 0);
                    $('#sumEqErrors').text(data.summary?.with_errors ?? 0);
                    $('#sumEqInsertables').text(data.summary?.insertables ?? 0);
                    $('#sumEqUpdatables').text(data.summary?.updatables ?? 0);
                    $('#importEquiposSummaryWrap').show();

                    $('#tbEquiposImportPreview').html((IMPORT_EQUIPOS_PREVIEW_ROWS || []).map(r => `
      <tr>
        <td>${escapeHtml(r.line)}</td>
        <td>${escapeHtml(r.marca)}</td>
        <td><b>${escapeHtml(r.eqModelo)}</b></td>
        <td>${escapeHtml(r.eqVersion)}</td>
        <td>${r.exists ? '<span class="badge text-bg-warning">Sí</span>' : '<span class="badge text-bg-success">No</span>'}</td>
        <td>${(r.errors || []).length ? `<span class="text-danger small">${escapeHtml((r.errors || []).join(' | '))}</span>` : '<span class="text-success">OK</span>'}</td>
      </tr>
    `).join(''));

                    $('#importEquiposPreviewWrap').show();
                    $('#btnCommitEquiposImport').prop('disabled', IMPORT_EQUIPOS_PREVIEW_ROWS.length === 0);

                } catch (e) {
                    $('#errImportEquipos').removeClass('d-none').text(e.message || 'No se pudo analizar el archivo.');
                }
            });

            $('#btnCommitEquiposImport').on('click', async function() {
                if (!IMPORT_EQUIPOS_PREVIEW_ROWS.length) return;

                const btn = $(this);
                btn.prop('disabled', true);

                try {
                    const res = await apiFetch('api/equipos/import_commit.php', {
                        method: 'POST',
                        json: {
                            mode: $('#importModeEquipos').val(),
                            rows: IMPORT_EQUIPOS_PREVIEW_ROWS
                        }
                    });

                    mdlImportEquipos.hide();
                    await loadEquipos();
                    mostrarToast('success',
                        `Importación completada. Insertados: ${res.summary?.inserted ?? 0}, actualizados: ${res.summary?.updated ?? 0}, omitidos: ${res.summary?.skipped ?? 0}.`
                    );
                } catch (e) {
                    $('#errImportEquipos').removeClass('d-none').text(e.message || 'No se pudo confirmar la importación.');
                } finally {
                    btn.prop('disabled', false);
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

            function badge(estatus) {
                const map = {
                    'Activo': 'success',
                    'Inactivo': 'secondary',
                    'Cambios': 'warning',
                    'Error': 'danger',
                };
                return `<span class="badge text-bg-${map[estatus] || 'secondary'}">${escapeHtml(estatus || 'N/D')}</span>`;
            }

            function joinModelo(modelo, version) {
                const m = String(modelo || '').trim();
                const v = String(version || '').trim();
                return m + (v ? ` ${v}` : '');
            }

            function getEquipoImg(eqImgPath) {
                return eqImgPath ? `../${eqImgPath}` : DEFAULT_IMG;
            }

            function getMarcaLogo(maImgPath) {
                return maImgPath ? `../${maImgPath}` : DEFAULT_BRAND;
            }

            function mostrarToast(tipo, mensaje) {
                const bg = tipo === 'success' ? 'text-bg-success' : 'text-bg-danger';
                const wrap = document.createElement('div');
                wrap.innerHTML = `
                    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:2000;">
                        <div class="toast align-items-center ${bg} border-0" role="alert">
                            <div class="d-flex">
                                <div class="toast-body">${escapeHtml(mensaje)}</div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(wrap);
                const el = wrap.querySelector('.toast');
                const toast = new bootstrap.Toast(el, {
                    delay: 2600
                });
                toast.show();
                el.addEventListener('hidden.bs.toast', () => wrap.remove());
            }

            async function apiFetch(url, {
                method = 'GET',
                json = null,
                body = null,
                headers = {}
            } = {}) {
                const h = new Headers(headers);
                h.set('X-CSRF-Token', csrf);

                let finalBody = body ?? null;

                if (method !== 'GET' && !finalBody) {
                    h.set('Content-Type', 'application/json; charset=utf-8');
                    finalBody = JSON.stringify({
                        ...(json || {}),
                        csrf_token: csrf
                    });
                }

                const res = await fetch(url, {
                    method,
                    credentials: 'include',
                    headers: h,
                    body: method === 'GET' ? null : finalBody,
                    cache: 'no-store'
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.error || 'Error API');
                }
                return data;
            }

            async function loadMarcasSelects() {
                const res = await apiFetch('api/marcas/list.php');
                const rows = Array.isArray(res.rows) ? res.rows : [];

                const optsModal = ['<option value="">Seleccione...</option>']
                    .concat(rows.map(r => {
                        const label = r.maEstatus && r.maEstatus !== 'Activo' ?
                            `${escapeHtml(r.maNombre)} (${escapeHtml(r.maEstatus)})` :
                            escapeHtml(r.maNombre);
                        return `<option value="${r.maId}">${label}</option>`;
                    }))
                    .join('');

                $('#maId').html(optsModal);

                const optsFiltro = ['<option value="">Todas</option>']
                    .concat(rows.map(r => `<option value="${r.maId}">${escapeHtml(r.maNombre)}</option>`))
                    .join('');

                $('#maIdFiltro').html(optsFiltro);
            }

            function resetForm() {
                $('#mdlEquipoTitle').text('Nuevo equipo');
                $('#eqId').val('');
                $('#maId').val('');
                $('#eqModelo').val('');
                $('#eqVersion').val('');
                $('#eqTipoEquipo').val('Servidor');
                $('#eqTipo').val('');
                $('#eqCPU').val('');
                $('#eqSockets').val('');
                $('#eqMaxRAM').val('');
                $('#eqNIC').val('');
                $('#eqDescripcion').val('');
                $('#eqEstatus').val('Activo');
                $('#eqImagen').val('');
                $('#eqImgPathActual').val('');
                $('#eqPreview').attr('src', DEFAULT_IMG);
                $('#eqPreviewText').text('Sin imagen cargada');
                $('#errEquipo').addClass('d-none').text('');
            }

            async function fillForm(row) {
                $('#mdlEquipoTitle').text('Editar equipo');
                $('#eqId').val(row.eqId || '');
                $('#eqModelo').val(row.eqModelo || '');
                $('#eqVersion').val(row.eqVersion || '');
                $('#eqTipoEquipo').val(row.eqTipoEquipo || '');
                $('#eqTipo').val(row.eqTipo || '');
                $('#eqCPU').val(row.eqCPU || '');
                $('#eqSockets').val(row.eqSockets || '');
                $('#eqMaxRAM').val(row.eqMaxRAM || '');
                $('#eqNIC').val(row.eqNIC || '');
                $('#eqDescripcion').val(row.eqDescripcion || '');
                $('#eqEstatus').val(row.eqEstatus || 'Activo');
                $('#eqImgPathActual').val(row.eqImgPath || '');
                $('#eqPreview').attr('src', row.eqImgPath ? ('../' + row.eqImgPath) : DEFAULT_IMG);
                $('#eqPreviewText').text(row.eqImgPath || 'Sin imagen cargada');
                $('#eqImagen').val('');
                $('#errEquipo').addClass('d-none').text('');

                const wanted = String(row.maId || '');
                $('#maId').val(wanted);

                if ($('#maId').val() !== wanted && wanted) {
                    try {
                        const marcasRes = await apiFetch('api/marcas/get.php?maId=' + encodeURIComponent(wanted));
                        const marca = marcasRes.row || null;
                        if (marca) {
                            $('#maId').append(`<option value="${marca.maId}">${escapeHtml(marca.maNombre)}</option>`);
                            $('#maId').val(String(marca.maId));
                        }
                    } catch (_) {}
                }
            }

            function buildFd() {
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('eqId', String($('#eqId').val() || ''));
                fd.append('maId', String($('#maId').val() || ''));
                fd.append('eqModelo', $('#eqModelo').val().trim());
                fd.append('eqVersion', $('#eqVersion').val().trim());
                fd.append('eqTipoEquipo', $('#eqTipoEquipo').val().trim());
                fd.append('eqTipo', $('#eqTipo').val().trim());
                fd.append('eqCPU', $('#eqCPU').val().trim());
                fd.append('eqSockets', $('#eqSockets').val().trim());
                fd.append('eqMaxRAM', $('#eqMaxRAM').val().trim());
                fd.append('eqNIC', $('#eqNIC').val().trim());
                fd.append('eqDescripcion', $('#eqDescripcion').val().trim());
                fd.append('eqEstatus', $('#eqEstatus').val());

                const fileInput = $('#eqImagen')[0];
                const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                if (file) {
                    fd.append('eqImagen', file);
                }

                return fd;
            }

            function renderPagination(pagination = {}) {
                const page = Number(pagination.page || 1);
                const totalPages = Number(pagination.totalPages || 1);
                const total = Number(pagination.total || 0);
                const from = Number(pagination.from || 0);
                const to = Number(pagination.to || 0);

                $('#pageText').text(`Página ${page} de ${totalPages}`);
                $('#paginationInfo').text(`Mostrando ${from} a ${to} de ${total} registros`);

                $('#pagePrev').prop('disabled', page <= 1);
                $('#pageNext').prop('disabled', page >= totalPages);
            }

            async function loadEquipos() {
                $('#tbEquipos').html(`<tr><td colspan="7" class="text-center muted">Cargando...</td></tr>`);

                try {
                    const params = new URLSearchParams({
                        q: $('#q').val().trim(),
                        maId: $('#maIdFiltro').val(),
                        estatus: $('#estatus').val(),
                        page: String(PAGE),
                        perPage: String(PER_PAGE),
                    });

                    const res = await apiFetch(`api/equipos/list.php?${params.toString()}`);
                    const rows = Array.isArray(res.rows) ? res.rows : [];
                    const stats = res.stats || {};
                    const pagination = res.pagination || {};

                    PAGE = Number(pagination.page || 1);
                    PER_PAGE = Number(pagination.perPage || PER_PAGE);
                    TOTAL_PAGES = Number(pagination.totalPages || 1);
                    TOTAL_ROWS = Number(pagination.total || 0);

                    $('#kpiTotal').text(stats.total ?? 0);
                    $('#kpiActivos').text(stats.activos ?? 0);
                    $('#kpiInactivos').text(stats.inactivos ?? 0);

                    if (!rows.length) {
                        $('#tbEquipos').html(`<tr><td colspan="7" class="text-center muted">No hay equipos registrados.</td></tr>`);
                        renderPagination(pagination);
                        return;
                    }

                    $('#tbEquipos').html(rows.map(r => {
                        const isActive = r.eqEstatus === 'Activo';
                        const img = getEquipoImg(r.eqImgPath);
                        const logo = getMarcaLogo(r.maImgPath);

                        return `
                            <tr>
                                <td class="fw-bold">${escapeHtml(r.eqId)}</td>
                                <td>
                                    <img src="${escapeHtml(img)}"
                                        alt="${escapeHtml(joinModelo(r.eqModelo, r.eqVersion))}"
                                        class="eq-logo-sm"
                                        onerror="this.onerror=null;this.src='${escapeHtml(DEFAULT_IMG)}';">
                                </td>
                                <td>
                                    <div class="fw-semibold">${escapeHtml(joinModelo(r.eqModelo, r.eqVersion))}</div>
                                    <div class="small muted">${escapeHtml(r.eqTipo || '')}</div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="${escapeHtml(logo)}"
                                            alt="${escapeHtml(r.maNombre || '')}"
                                            class="brand-logo-sm"
                                            onerror="this.onerror=null;this.src='${escapeHtml(DEFAULT_BRAND)}';">
                                        <span>${escapeHtml(r.maNombre || '')}</span>
                                    </div>
                                </td>
                                <td>${escapeHtml(r.eqTipoEquipo || '')}</td>
                                <td>${badge(r.eqEstatus)}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button class="btn btn-sm btn-outline-primary btnEdit" data-id="${r.eqId}">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </button>
                                        <button class="btn btn-sm ${isActive ? 'btn-outline-secondary' : 'btn-outline-success'} btnToggle" data-id="${r.eqId}">
                                            <i class="bi ${isActive ? 'bi-pause-circle' : 'bi-check-circle'}"></i>
                                            ${isActive ? 'Desactivar' : 'Activar'}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join(''));

                    renderPagination(pagination);

                } catch (e) {
                    $('#tbEquipos').html(`<tr><td colspan="7" class="text-center text-danger">${escapeHtml(e.message || e)}</td></tr>`);
                }
            }

            function resetAndLoad() {
                PAGE = 1;
                PER_PAGE = Number($('#perPage').val() || 20);
                loadEquipos();
            }

            $('#btnNuevoEquipo').on('click', () => {
                resetForm();
                mdlEquipo.show();
            });

            $('#btnBuscar').on('click', resetAndLoad);

            $('#q').on('input', function() {
                clearTimeout(DEBOUNCE_TIMER);
                DEBOUNCE_TIMER = setTimeout(() => {
                    resetAndLoad();
                }, 350);
            });

            $('#estatus, #maIdFiltro, #perPage').on('change', resetAndLoad);

            $('#pagePrev').on('click', function() {
                if (PAGE > 1) {
                    PAGE--;
                    loadEquipos();
                }
            });

            $('#pageNext').on('click', function() {
                if (PAGE < TOTAL_PAGES) {
                    PAGE++;
                    loadEquipos();
                }
            });

            $(document).on('click', '.btnEdit', async function() {
                const eqId = Number($(this).data('id') || 0);
                if (!eqId) return;

                try {
                    const res = await apiFetch(`api/equipos/get.php?eqId=${encodeURIComponent(eqId)}`);
                    await fillForm(res.row || {});
                    mdlEquipo.show();
                } catch (e) {
                    mostrarToast('error', e.message || 'No se pudo obtener el equipo.');
                }
            });

            $('#btnDescargarErroresEquipos').on('click', async function() {
                if (!IMPORT_EQUIPOS_ERRORS.length) return;

                try {
                    const res = await fetch('api/equipos/import_errors_csv.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify({
                            csrf_token: csrf,
                            errors: IMPORT_EQUIPOS_ERRORS
                        })
                    });

                    if (!res.ok) {
                        throw new Error('No se pudo generar el CSV de errores.');
                    }

                    const blob = await res.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'errores_import_equipos.csv';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                } catch (e) {
                    $('#errImportEquipos').removeClass('d-none').text(e.message || 'No se pudo descargar el CSV de errores.');
                }
            });

            $('#btnGuardarEquipo').on('click', async function() {
                $('#errEquipo').addClass('d-none').text('');

                const payload = {
                    eqId: Number($('#eqId').val() || 0),
                    maId: Number($('#maId').val() || 0),
                    eqModelo: $('#eqModelo').val().trim(),
                    eqVersion: $('#eqVersion').val().trim(),
                    eqTipoEquipo: $('#eqTipoEquipo').val().trim(),
                    eqTipo: $('#eqTipo').val().trim(),
                    eqCPU: $('#eqCPU').val().trim(),
                    eqSockets: $('#eqSockets').val().trim(),
                    eqMaxRAM: $('#eqMaxRAM').val().trim(),
                    eqNIC: $('#eqNIC').val().trim(),
                    eqDescripcion: $('#eqDescripcion').val().trim(),
                    eqEstatus: $('#eqEstatus').val()
                };

                if (!payload.maId) {
                    $('#errEquipo').removeClass('d-none').text('La marca es obligatoria.');
                    return;
                }
                if (!payload.eqModelo) {
                    $('#errEquipo').removeClass('d-none').text('El modelo es obligatorio.');
                    return;
                }
                if (!payload.eqVersion) {
                    $('#errEquipo').removeClass('d-none').text('La versión es obligatoria.');
                    return;
                }
                if (!payload.eqTipoEquipo) {
                    $('#errEquipo').removeClass('d-none').text('El tipo de equipo es obligatorio.');
                    return;
                }
                if (!payload.eqTipo) {
                    $('#errEquipo').removeClass('d-none').text('El tipo es obligatorio.');
                    return;
                }
                if (!payload.eqCPU) {
                    $('#errEquipo').removeClass('d-none').text('La CPU es obligatoria.');
                    return;
                }
                if (!payload.eqSockets) {
                    $('#errEquipo').removeClass('d-none').text('Los sockets son obligatorios.');
                    return;
                }
                if (!payload.eqMaxRAM) {
                    $('#errEquipo').removeClass('d-none').text('La RAM máxima es obligatoria.');
                    return;
                }
                if (!payload.eqNIC) {
                    $('#errEquipo').removeClass('d-none').text('La NIC es obligatoria.');
                    return;
                }
                if (!payload.eqDescripcion) {
                    $('#errEquipo').removeClass('d-none').text('La descripción es obligatoria.');
                    return;
                }

                const btn = $(this);
                btn.prop('disabled', true);

                try {
                    const fd = buildFd();
                    const url = payload.eqId > 0 ? 'api/equipos/update.php' : 'api/equipos/create.php';
                    const res = await apiFetch(url, {
                        method: 'POST',
                        body: fd
                    });

                    mdlEquipo.hide();
                    await loadEquipos();
                    mostrarToast('success', res.message || 'Equipo guardado correctamente.');
                } catch (e) {
                    $('#errEquipo').removeClass('d-none').text(e.message || 'No se pudo guardar el equipo.');
                } finally {
                    btn.prop('disabled', false);
                }
            });

            $(document).on('click', '.btnToggle', async function() {
                const eqId = Number($(this).data('id') || 0);
                if (!eqId) return;

                try {
                    const res = await apiFetch('api/equipos/toggle.php', {
                        method: 'POST',
                        json: {
                            eqId
                        }
                    });
                    await loadEquipos();
                    mostrarToast('success', res.message || 'Estatus actualizado.');
                } catch (e) {
                    mostrarToast('error', e.message || 'No se pudo cambiar el estatus.');
                }
            });

            $('#eqImagen').on('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) {
                    const current = $('#eqImgPathActual').val();
                    $('#eqPreview').attr('src', current ? ('../' + current) : DEFAULT_IMG);
                    $('#eqPreviewText').text(current || 'Sin imagen cargada');
                    return;
                }

                const url = URL.createObjectURL(file);
                $('#eqPreview').attr('src', url);
                $('#eqPreviewText').text(file.name);
            });

            $('#btnTheme').on('click', function() {
                document.body.classList.toggle('dark-mode');
                const isDark = document.body.classList.contains('dark-mode');
                document.cookie = `mrs_theme=${isDark ? 'dark' : 'light'}; path=/; max-age=31536000`;
            });

            (async function init() {
                await loadMarcasSelects();
                await loadEquipos();
            })().catch(err => {
                console.error(err);
                mostrarToast('error', err.message || 'No se pudo inicializar la pantalla de equipos.');
            });
        })();
    </script>
</body>

</html>