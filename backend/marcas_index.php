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
    <title>MR SOS | Marcas</title>

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

        .marca-chip {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .4rem .75rem;
            border-radius: 999px;
            background: #f8f9fa;
            border: 1px solid rgba(0, 0, 0, .08);
            font-weight: 600;
        }

        .preview-box {
            min-height: 110px;
            border: 1px dashed rgba(0, 0, 0, .15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafafa;
            padding: 1rem;
        }

        .preview-box img {
            max-width: 220px;
            max-height: 90px;
            object-fit: contain;
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

        .dark-mode .preview-box {
            background: #111;
            border-color: rgba(255, 255, 255, .12);
        }
    </style>
</head>

<body class="<?= ($theme === 'dark') ? 'dark-mode' : '' ?>">
    <div class="container-fluid">
        <div class="row gx-0">

            <?php $activeMenu = 'marcas'; ?>
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
                                <h4 class="fw-bold mb-1">Catálogo de Marcas</h4>
                                <div class="muted">Alta, edición, búsqueda, paginación y activación/desactivación de marcas.</div>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-primary" id="btnNuevaMarca">
                                    <i class="bi bi-plus-circle"></i> Nueva marca
                                </button>
                            </div>
                        </div>

                        <hr>

                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-5">
                                <label class="form-label">Buscar</label>
                                <input id="q" class="form-control" placeholder="Nombre de la marca...">
                            </div>

                            <div class="col-12 col-md-3">
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

                            <div class="col-12 col-md-2 d-grid">
                                <button id="btnBuscar" class="btn btn-outline-secondary">
                                    <i class="bi bi-search"></i> Buscar
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
                                    <div class="muted small">Activas</div>
                                    <div class="fs-3 fw-bold text-success" id="kpiActivas">0</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="panel h-100">
                                    <div class="muted small">Inactivas</div>
                                    <div class="fs-3 fw-bold text-secondary" id="kpiInactivas">0</div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="width:90px;">ID</th>
                                        <th style="width:110px;">Logo</th>
                                        <th>Marca</th>
                                        <th style="width:160px;">Estatus</th>
                                        <th style="width:220px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbMarcas">
                                    <tr>
                                        <td colspan="5" class="text-center muted">Cargando...</td>
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

    <div class="modal fade" id="mdlMarca" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mdlMarcaTitle">Nueva marca</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="maId">
                    <input type="hidden" id="maImgPathActual">

                    <div class="row g-3">
                        <div class="col-12 col-md-7">
                            <div class="mb-3">
                                <label class="form-label">Nombre de la marca</label>
                                <input type="text" id="maNombre" class="form-control" maxlength="50" placeholder="Ej. Dell">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Estatus</label>
                                <select id="maEstatus" class="form-select">
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                    <option value="Cambios">Cambios</option>
                                    <option value="Error">Error</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Imagen / logo</label>
                                <input type="file" id="maImagen" class="form-control" accept=".png,.jpg,.jpeg,.webp,.svg">
                                <div class="form-text">Formatos: PNG, JPG, JPEG, WEBP, SVG. Máximo 2 MB.</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-5">
                            <label class="form-label">Vista previa</label>
                            <div class="preview-box">
                                <img id="maPreview" src="../img/Marcas/default.png" alt="Preview">
                            </div>
                            <div class="small muted mt-2" id="maPreviewText">Sin imagen cargada</div>
                        </div>
                    </div>

                    <div class="alert alert-danger d-none mt-3" id="errMarca"></div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button id="btnGuardarMarca" class="btn btn-primary">
                        <i class="bi bi-check2-circle"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const csrf = (window.MRS_CSRF && window.MRS_CSRF.csrf) ? window.MRS_CSRF.csrf : '';
            const DEFAULT_IMG = '../img/Marcas/default.png';
            const mdlMarca = new bootstrap.Modal(document.getElementById('mdlMarca'));

            let PAGE = 1;
            let PER_PAGE = 20;
            let TOTAL_PAGES = 1;
            let TOTAL_ROWS = 0;
            let DEBOUNCE_TIMER = null;

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

            function resetForm() {
                $('#mdlMarcaTitle').text('Nueva marca');
                $('#maId').val('');
                $('#maNombre').val('');
                $('#maEstatus').val('Activo');
                $('#maImagen').val('');
                $('#maImgPathActual').val('');
                $('#maPreview').attr('src', DEFAULT_IMG);
                $('#maPreviewText').text('Sin imagen cargada');
                $('#errMarca').addClass('d-none').text('');
            }

            function fillForm(row) {
                $('#mdlMarcaTitle').text('Editar marca');
                $('#maId').val(row.maId || '');
                $('#maNombre').val(row.maNombre || '');
                $('#maEstatus').val(row.maEstatus || 'Activo');
                $('#maImgPathActual').val(row.maImgPath || '');
                $('#maPreview').attr('src', row.maImgPath ? ('../' + row.maImgPath) : DEFAULT_IMG);
                $('#maPreviewText').text(row.maImgPath || 'Sin imagen cargada');
                $('#maImagen').val('');
                $('#errMarca').addClass('d-none').text('');
            }

            function buildFd() {
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('maId', String($('#maId').val() || ''));
                fd.append('maNombre', $('#maNombre').val().trim());
                fd.append('maEstatus', $('#maEstatus').val());

                const fileInput = $('#maImagen')[0];
                const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                if (file) {
                    fd.append('maImagen', file);
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

            async function loadMarcas() {
                $('#tbMarcas').html(`<tr><td colspan="5" class="text-center muted">Cargando...</td></tr>`);

                try {
                    const params = new URLSearchParams({
                        q: $('#q').val().trim(),
                        estatus: $('#estatus').val(),
                        page: String(PAGE),
                        perPage: String(PER_PAGE),
                    });

                    const res = await apiFetch(`api/marcas/list.php?${params.toString()}`);
                    const rows = Array.isArray(res.rows) ? res.rows : [];
                    const stats = res.stats || {};
                    const pagination = res.pagination || {};

                    PAGE = Number(pagination.page || 1);
                    PER_PAGE = Number(pagination.perPage || PER_PAGE);
                    TOTAL_PAGES = Number(pagination.totalPages || 1);
                    TOTAL_ROWS = Number(pagination.total || 0);

                    $('#kpiTotal').text(stats.total ?? 0);
                    $('#kpiActivas').text(stats.activas ?? 0);
                    $('#kpiInactivas').text(stats.inactivas ?? 0);

                    if (!rows.length) {
                        $('#tbMarcas').html(`<tr><td colspan="5" class="text-center muted">No hay marcas registradas.</td></tr>`);
                        renderPagination(pagination);
                        return;
                    }

                    $('#tbMarcas').html(rows.map(r => {
                        const isActive = (r.maEstatus === 'Activo');
                        const img = r.maImgPath ? ('../' + r.maImgPath) : DEFAULT_IMG;

                        return `
                            <tr>
                                <td class="fw-bold">${escapeHtml(r.maId)}</td>
                                <td>
                                    <img src="${escapeHtml(img)}"
                                        alt="${escapeHtml(r.maNombre)}"
                                        class="brand-logo-sm"
                                        onerror="this.onerror=null;this.src='${escapeHtml(DEFAULT_IMG)}';">
                                </td>
                                <td>
                                    <div class="marca-chip">
                                        <i class="bi bi-bookmark-star"></i>
                                        <span>${escapeHtml(r.maNombre)}</span>
                                    </div>
                                </td>
                                <td>${badge(r.maEstatus)}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button class="btn btn-sm btn-outline-primary btnEdit" data-id="${r.maId}">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </button>
                                        <button class="btn btn-sm ${isActive ? 'btn-outline-secondary' : 'btn-outline-success'} btnToggle" data-id="${r.maId}">
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
                    $('#tbMarcas').html(`<tr><td colspan="5" class="text-center text-danger">${escapeHtml(e.message || e)}</td></tr>`);
                }
            }

            function resetAndLoad() {
                PAGE = 1;
                PER_PAGE = Number($('#perPage').val() || 20);
                loadMarcas();
            }

            $('#btnNuevaMarca').on('click', () => {
                resetForm();
                mdlMarca.show();
            });

            $('#btnBuscar').on('click', resetAndLoad);

            $('#q').on('input', function() {
                clearTimeout(DEBOUNCE_TIMER);
                DEBOUNCE_TIMER = setTimeout(() => {
                    resetAndLoad();
                }, 350);
            });

            $('#estatus, #perPage').on('change', resetAndLoad);

            $('#pagePrev').on('click', function() {
                if (PAGE > 1) {
                    PAGE--;
                    loadMarcas();
                }
            });

            $('#pageNext').on('click', function() {
                if (PAGE < TOTAL_PAGES) {
                    PAGE++;
                    loadMarcas();
                }
            });

            $('#maImagen').on('change', function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) {
                    const current = $('#maImgPathActual').val();
                    $('#maPreview').attr('src', current ? ('../' + current) : DEFAULT_IMG);
                    $('#maPreviewText').text(current || 'Sin imagen cargada');
                    return;
                }

                const url = URL.createObjectURL(file);
                $('#maPreview').attr('src', url);
                $('#maPreviewText').text(file.name);
            });

            $(document).on('click', '.btnEdit', async function() {
                const maId = Number($(this).data('id') || 0);
                if (!maId) return;

                try {
                    const res = await apiFetch(`api/marcas/get.php?maId=${encodeURIComponent(maId)}`);
                    fillForm(res.row || {});
                    mdlMarca.show();
                } catch (e) {
                    mostrarToast('error', e.message || 'No se pudo obtener la marca.');
                }
            });

            $('#btnGuardarMarca').on('click', async function() {
                const maId = Number($('#maId').val() || 0);
                const maNombre = $('#maNombre').val().trim();

                $('#errMarca').addClass('d-none').text('');

                if (!maNombre) {
                    $('#errMarca').removeClass('d-none').text('El nombre de la marca es obligatorio.');
                    return;
                }

                const btn = $(this);
                btn.prop('disabled', true);

                try {
                    const fd = buildFd();
                    const url = maId > 0 ? 'api/marcas/update.php' : 'api/marcas/create.php';
                    const res = await apiFetch(url, {
                        method: 'POST',
                        body: fd
                    });

                    mdlMarca.hide();
                    await loadMarcas();
                    mostrarToast('success', res.message || 'Marca guardada correctamente.');
                } catch (e) {
                    $('#errMarca').removeClass('d-none').text(e.message || 'No se pudo guardar la marca.');
                } finally {
                    btn.prop('disabled', false);
                }
            });

            $(document).on('click', '.btnToggle', async function() {
                const maId = Number($(this).data('id') || 0);
                if (!maId) return;

                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('maId', String(maId));

                try {
                    const res = await apiFetch('api/marcas/toggle.php', {
                        method: 'POST',
                        body: fd
                    });
                    await loadMarcas();
                    mostrarToast('success', res.message || 'Estatus actualizado.');
                } catch (e) {
                    mostrarToast('error', e.message || 'No se pudo cambiar el estatus.');
                }
            });

            $('#btnTheme').on('click', function() {
                document.body.classList.toggle('dark-mode');
                const isDark = document.body.classList.contains('dark-mode');
                document.cookie = `mrs_theme=${isDark ? 'dark' : 'light'}; path=/; max-age=31536000`;
            });

            loadMarcas();
        })();
    </script>
</body>

</html>