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

$pcId = isset($_GET['pcId']) ? (int)$_GET['pcId'] : 0;
if ($pcId <= 0) {
  http_response_code(400);
  exit('Falta pcId');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <script>
    window.MRS_CSRF = <?= json_encode(['csrf' => $csrf], JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MR SOS | Póliza detalle</title>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="css/css.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">

  <style>
    .equipo-card {
      transition: transform .12s ease, box-shadow .12s ease;
    }

    .equipo-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(15, 23, 42, .18);
    }

    .poliza-header-title {
      font-size: .8rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #6b7280;
    }

    .eq-card {
      border: 2px solid #f3b400;
      border-radius: 14px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 10px 25px rgba(15, 23, 42, .06);
    }

    .eq-card__imgwrap {
      background: #fff;
      padding: 14px 14px 0 14px;
    }

    .eq-card__img {
      width: 100%;
      height: 140px;
      object-fit: contain;
      display: block;
    }

    .eq-card__body {
      padding: 14px;
    }

    .eq-card__title {
      font-weight: 700;
      font-size: 18px;
      line-height: 1.15;
      margin: 0 0 10px 0;
    }

    .eq-card__meta {
      font-size: 14px;
      color: #374151;
      margin: 0 0 6px 0;
    }

    .eq-card__meta strong {
      font-weight: 700;
      color: #111827;
    }

    .eq-badge-yellow {
      display: inline-block;
      background: #f3b400;
      color: #111827;
      font-weight: 700;
      font-size: 12px;
      padding: 4px 10px;
      border-radius: 999px;
      margin: 8px 0 6px 0;
    }

    .eq-card__tickets {
      font-size: 14px;
      color: #374151;
    }

    .eq-card__tickets strong {
      color: #111827;
    }

    .eq-card__actions {
      display: flex;
      gap: 8px;
      margin-top: 12px;
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

        <div class="section-title px-2">Operación</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_ticket.php"><i class="bi bi-shield-check"></i> Health Checks</a></li>
          <li class="nav-item"><a class="nav-link" href="clientes_index.php"><i class="bi bi-building"></i> Clientes</a></li>
          <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-shield-lock"></i> Póliza</a></li>
        </ul>

        <div class="section-title px-2 mt-3">General</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-person"></i> Mis datos</a></li>
        </ul>
      </nav>

      <main class="col-12 col-lg-10">
        <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" id="btnBack" href="#"><i class="bi bi-arrow-left"></i></a>
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
                <h4 class="fw-bold mb-1">Detalle de póliza</h4>
                <div class="muted">Administra datos, PDFs, vendedor/cuenta y asignación de equipos por sede.</div>
                <div class="small mt-2">
                  <span class="muted">No. factura:</span> <span class="fw-bold" id="hdrIdentificador">—</span>
                  <span class="muted ms-2">Cliente:</span> <span class="fw-bold" id="hdrCliente">—</span>
                </div>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" id="btnReload"><i class="bi bi-arrow-clockwise"></i> Recargar</button>
              </div>
            </div>

            <hr>

            <ul class="nav nav-tabs" role="tablist">
              <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-datos" type="button">Datos</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-archivos" type="button">Archivos PDF</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-vendedor" type="button">Vendedor</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cuenta" type="button">Cuenta</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-equipos" type="button">Equipos</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-carga-masiva" type="button">Carga masiva</button></li>
            </ul>

            <div class="tab-content pt-3">

              <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                <div class="card shadow-sm">
                  <div class="card-body">
                    <div class="row g-2">
                      <div class="col-12 col-md-6">
                        <label class="form-label">No. Factura (pcIdentificador)</label>
                        <input id="pcIdentificador" class="form-control">
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label">Tipo</label>
                        <input id="pcTipoPoliza" class="form-control">
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
                        <input id="pcUsId" class="form-control" inputmode="numeric">
                      </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                      <button class="btn btn-primary" id="btnSaveDatos"><i class="bi bi-save"></i> Guardar</button>
                    </div>

                    <div class="alert alert-danger mt-3 d-none" id="errDatos"></div>
                    <div class="alert alert-success mt-3 d-none" id="okDatos">Guardado ✅</div>
                  </div>
                </div>
              </div>

              <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
                <div class="card shadow-sm">
                  <div class="card-body">
                    <div class="alert alert-info">
                      <b>Regla:</b> Factura PDF es obligatoria. “Póliza/Contrato” y “WK” son opcionales.
                      Se renombrarán automáticamente como: <code>{pcIdentificador}factura.pdf</code>, <code>{pcIdentificador}poliza.pdf</code>, <code>{pcIdentificador}WK.pdf</code>.
                    </div>

                    <div class="row g-2">
                      <div class="col-12 col-md-4">
                        <label class="form-label">Factura (obligatorio)</label>
                        <input id="file_factura" type="file" class="form-control" accept="application/pdf">
                      </div>
                      <div class="col-12 col-md-4">
                        <label class="form-label">Póliza/Contrato (opcional)</label>
                        <input id="file_poliza" type="file" class="form-control" accept="application/pdf">
                      </div>
                      <div class="col-12 col-md-4">
                        <label class="form-label">WK (opcional)</label>
                        <input id="file_wk" type="file" class="form-control" accept="application/pdf">
                      </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                      <button class="btn btn-success" id="btnUploadPdf"><i class="bi bi-upload"></i> Subir PDFs</button>
                    </div>

                    <div class="alert alert-danger mt-3 d-none" id="errPdf"></div>
                    <div class="alert alert-success mt-3 d-none" id="okPdf">Subido ✅</div>

                    <hr>

                    <div class="small text-muted">Rutas actuales:</div>
                    <ul class="small mb-0">
                      <li>Factura: <span id="lblFactura">—</span></li>
                      <li>Póliza: <span id="lblPoliza">—</span></li>
                      <li>WK: <span id="lblWK">—</span></li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="tab-pane fade" id="tab-vendedor" role="tabpanel">
                <div class="card shadow-sm">
                  <div class="card-body">
                    <div class="row g-2">
                      <div class="col-12 col-md-6">
                        <label class="form-label">Vendedor de la póliza</label>
                        <select id="pvUsId" class="form-select"></select>
                        <div class="form-text">Guarda en <code>polizavendedor</code> (1 activo por póliza).</div>
                      </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                      <button class="btn btn-primary" id="btnSaveVendedor"><i class="bi bi-save"></i> Guardar vendedor</button>
                    </div>
                    <div class="alert alert-danger mt-3 d-none" id="errVend"></div>
                    <div class="alert alert-success mt-3 d-none" id="okVend">Guardado ✅</div>
                  </div>
                </div>
              </div>

              <div class="tab-pane fade" id="tab-cuenta" role="tabpanel">
                <div class="card shadow-sm">
                  <div class="card-body">
                    <div class="row g-2">
                      <div class="col-12 col-md-6">
                        <label class="form-label">Responsable de cuenta</label>
                        <select id="cuUsId" class="form-select"></select>
                        <div class="form-text">Guarda/actualiza en <code>cuentas</code> para esta póliza.</div>
                      </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                      <button class="btn btn-primary" id="btnSaveCuenta"><i class="bi bi-save"></i> Guardar responsable</button>
                    </div>
                    <div class="alert alert-danger mt-3 d-none" id="errCuenta"></div>
                    <div class="alert alert-success mt-3 d-none" id="okCuenta">Guardado ✅</div>
                  </div>
                </div>
              </div>

              <div class="tab-pane fade" id="tab-equipos" role="tabpanel">
                <div class="card shadow-sm">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                      <div class="fw-bold">Equipos asignados a la póliza</div>
                      <button class="btn btn-outline-secondary btn-sm" id="btnReloadEquipos">
                        <i class="bi bi-arrow-clockwise"></i> Recargar
                      </button>
                    </div>

                    <div class="row g-3 mt-2" id="equiposGrid">
                      <div class="col-12">
                        <div class="text-center muted">Cargando...</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="tab-pane fade" id="tab-carga-masiva" role="tabpanel">
                <div class="card shadow-sm">
                  <div class="card-body">

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                      <div>
                        <h5 class="mb-1">Carga masiva de equipos</h5>
                        <div class="text-muted small">
                          Sube un <b>XLSX</b> o <b>CSV</b>. Primero verás un preview y podrás resolver la sede por fila.
                        </div>
                      </div>
                      <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm" id="btn-clear-preview" type="button" disabled>Limpiar</button>
                        <button class="btn btn-primary btn-sm" id="btn-commit" type="button" disabled>Confirmar importación</button>
                      </div>
                    </div>

                    <div class="row g-3 mb-3">
                      <div class="col-12 col-md-4">
                        <label class="form-label">Modo</label>
                        <select class="form-select" id="bulkMode">
                          <option value="insert_only">Insertar nuevos</option>
                          <option value="update_only">Actualizar existentes</option>
                          <option value="upsert">Insertar o actualizar</option>
                          <option value="assign_only">Asignar a póliza</option>
                        </select>
                      </div>

                      <div class="col-12 col-md-8">
                        <label class="form-label">Columnas a actualizar</label>
                        <div class="d-flex flex-wrap gap-3">
                          <label><input type="checkbox" class="bulk-field" value="csId" checked> Sede</label>
                          <label><input type="checkbox" class="bulk-field" value="peSO" checked> SO</label>
                          <label><input type="checkbox" class="bulk-field" value="peDescripcion" checked> Descripción</label>
                          <label><input type="checkbox" class="bulk-field" value="peEstatus" checked> Estatus</label>
                          <?php if ($rol === 'MRSA'): ?>
                            <label><input type="checkbox" class="bulk-field" value="peSN"> SN</label>
                          <?php endif; ?>
                          <label><input type="checkbox" class="bulk-field" value="eqId"> Modelo</label>
                        </div>
                      </div>
                    </div>

                    <div class="alert alert-warning small">
                      Antes de confirmar la importación se generará un <b>backup automático</b>.
                    </div>

                    <div class="row g-3 align-items-end">
                      <div class="col-12 col-lg-8">
                        <label class="form-label">Archivo (XLSX/CSV)</label>
                        <input class="form-control" type="file" id="bulk-file" accept=".xlsx,.csv" />
                      </div>
                      <div class="col-12 col-lg-4">
                        <button class="btn btn-success w-100" id="btn-parse" type="button">Previsualizar</button>
                      </div>
                    </div>

                    <hr class="my-4">
                    <div id="bulk-meta" class="small text-muted mb-2" style="display:none;"></div>

                    <div class="table-responsive">
                      <table class="table table-sm align-middle" id="bulk-preview-table" style="display:none;">
                        <thead class="table-light">
                          <tr>
                            <th style="width:70px;">Línea</th>
                            <th style="width:90px;">eqId</th>
                            <th style="width:160px;">Serial (SN)</th>
                            <th style="width:220px;">Sede (texto)</th>
                            <th style="width:260px;">Resolver sede</th>
                            <th style="width:120px;">Estatus</th>
                            <th>Detalle</th>
                          </tr>
                        </thead>
                        <tbody></tbody>
                      </table>
                    </div>

                    <div id="bulk-result" class="mt-3" style="display:none;"></div>

                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Modal: Editar equipo póliza -->
        <div class="modal fade" id="modalEqEdit" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title mb-0">Editar equipo</h5>
                  <div class="small text-muted" id="eqEditSubtitle">—</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <input type="hidden" id="eq_peId">

                <div class="row g-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label">Sede</label>
                    <select class="form-select" id="eq_csId"></select>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Estatus</label>
                    <select class="form-select" id="eq_peEstatus">
                      <option value="Activo">Activo</option>
                      <option value="Inactivo">Inactivo</option>
                      <option value="Baja">Baja</option>
                      <option value="Cambios">Cambios</option>
                      <option value="Error">Error</option>
                    </select>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Sistema Operativo (peSO)</label>
                    <input class="form-control" id="eq_peSO" placeholder="Windows / Linux / ESXi ...">
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Serial Number (peSN)</label>
                    <input class="form-control" id="eq_peSN" disabled>
                    <div class="form-text" id="snHelp">
                      Solo MRSA puede cambiar el SN (con confirmación).
                    </div>
                  </div>

                  <div class="col-12 d-none" id="snConfirmWrap">
                    <div class="alert alert-warning mb-2">
                      <b>Confirmación requerida:</b> escribe exactamente el nuevo SN para confirmar el cambio.
                    </div>
                    <input class="form-control" id="eq_sn_confirm" placeholder="Escribe el nuevo SN para confirmar">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Descripción / Notas (peDescripcion)</label>
                    <textarea class="form-control" id="eq_peDescripcion" rows="3"></textarea>
                  </div>
                </div>

                <div class="alert alert-danger mt-3 d-none" id="eqEditErr"></div>
                <div class="alert alert-success mt-3 d-none" id="eqEditOk">Guardado ✅</div>
              </div>

              <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>

                <button class="btn btn-outline-warning d-none" id="btnEnableSN">
                  <i class="bi bi-shield-exclamation"></i> Habilitar cambio de SN
                </button>

                <button class="btn btn-primary" id="btnSaveEq">
                  <i class="bi bi-save"></i> Guardar cambios
                </button>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script>
    (function() {
      const csrf = (window.MRS_CSRF && window.MRS_CSRF.csrf) ? window.MRS_CSRF.csrf : '';
      const PC_ID = <?= (int)$pcId ?>;
      const USER_ROLE = <?= json_encode($rol, JSON_UNESCAPED_UNICODE) ?>;

      // admin/ -> api/
      const URL_GET = 'api/polizas/poliza_get.php';
      const URL_SAVE = 'api/polizas/poliza_save.php';
      const URL_FILES = 'api/polizas/poliza_files_upload.php';
      const URL_VEND = 'api/polizas/poliza_vendor_save.php';
      const URL_CUENTA = 'api/polizas/poliza_account_save.php';
      const URL_EQ_LIST = 'api/polizas/poliza_equipo_list.php';
      const URL_EQ_TOG = 'api/polizas/poliza_equipo_toggle.php';
      const URL_EQ_GET_ONE = 'api/polizas/poliza_equipo_get.php';
      const URL_EQ_UPDATE = 'api/polizas/poliza_equipo_update.php';

      const URL_PARSE = 'api/polizas/poliza_equipo_bulk_parse.php';
      const URL_COMMIT = 'api/polizas/poliza_equipo_bulk_commit.php';
      const URL_BACKUP = 'api/polizas/poliza_bulk_backup.php';

      let POLIZA = null;
      let BULK_PARSED = null;
      let EQ_EDIT = {
        allowSN: false,
        originalSN: ''
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
          contentType: 'application/json; charset=utf-8',
          data: JSON.stringify(payload),
          headers: {
            'X-CSRF-TOKEN': csrf
          }
        });
      }

      function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, m => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;'
        } [m]));
      }

      function showErr(id, msg) {
        $(id).removeClass('d-none').text(msg || 'Error');
      }

      function hideMsgs() {
        $('#errDatos,#okDatos,#errPdf,#okPdf,#errVend,#okVend,#errCuenta,#okCuenta').addClass('d-none');
      }

      function isSuccess(r) {
        return r && (r.success === true || r.ok === true);
      }

      function unwrap(r) {
        if (!r) return null;
        if (r.data && typeof r.data === 'object') return r.data;
        return r;
      }

      function pickArray(obj, keys) {
        if (!obj) return [];
        for (const k of keys) {
          if (Array.isArray(obj[k])) return obj[k];
        }
        return [];
      }

      function getBulkUpdateFields() {
        const out = {};
        $('.bulk-field:checked').each(function() {
          out[$(this).val()] = true;
        });
        return out;
      }

      async function loadPoliza() {
        hideMsgs();
        try {
          const r = await apiGet(URL_GET, {
            pcId: PC_ID
          });
          if (!isSuccess(r)) throw new Error(r?.error || r?.msg || 'Error');

          const u = unwrap(r);
          POLIZA = u.poliza || null;
          if (!POLIZA) throw new Error('Sin póliza');

          $('#hdrIdentificador').text(POLIZA.pcIdentificador || '—');
          $('#hdrCliente').text(POLIZA.clNombre || ('Cliente #' + (POLIZA.clId || '')));
          $('#btnBack').attr('href', 'polizas_index.php?clId=' + (POLIZA.clId || 0));

          $('#pcIdentificador').val(POLIZA.pcIdentificador || '');
          $('#pcTipoPoliza').val(POLIZA.pcTipoPoliza || '');
          $('#pcFechaInicio').val(POLIZA.pcFechaInicio || '');
          $('#pcFechaFin').val(POLIZA.pcFechaFin || '');
          $('#pcEstatus').val(POLIZA.pcEstatus || 'Activo');
          $('#pcUsId').val(POLIZA.usId || '');

          $('#lblFactura').text(POLIZA.pcPdfPath || '—');
          $('#lblPoliza').text(POLIZA.pcPdfPolizaPath || '—');
          $('#lblWK').text(POLIZA.pcPdfWKPath || '—');

          const vend = u.vendedores || [];
          const cuentas = u.cuentasUsers || vend;
          const pvSel = parseInt(u.pvUsId || 0, 10);
          const cuSel = parseInt(u.cuUsId || 0, 10);

          $('#pvUsId').html(
            vend.map(v => `<option value="${v.usId}">${esc(v.usNombre)} (${esc(v.usCorreo || '')})</option>`).join('') ||
            '<option value="">(sin datos)</option>'
          );
          if (pvSel) $('#pvUsId').val(String(pvSel));

          $('#cuUsId').html(
            cuentas.map(c => `<option value="${c.usId}">${esc(c.usNombre)} (${esc(c.usCorreo || '')})</option>`).join('') ||
            '<option value="">(sin datos)</option>'
          );
          if (cuSel) $('#cuUsId').val(String(cuSel));

          await loadEquipos();

        } catch (e) {
          alert(e.message || 'Error cargando póliza');
        }
      }

      async function saveDatos() {
        hideMsgs();
        if (!POLIZA) return;

        const payload = {
          pcId: PC_ID,
          clId: parseInt(POLIZA.clId || '0', 10),
          pcIdentificador: $('#pcIdentificador').val().trim(),
          pcTipoPoliza: $('#pcTipoPoliza').val().trim(),
          pcFechaInicio: $('#pcFechaInicio').val(),
          pcFechaFin: $('#pcFechaFin').val(),
          pcEstatus: $('#pcEstatus').val(),
          usId: parseInt($('#pcUsId').val() || '0', 10)
        };

        try {
          const r = await apiPost(URL_SAVE, payload);
          if (!isSuccess(r)) throw new Error(r?.error || r?.msg || 'Error');
          $('#okDatos').removeClass('d-none');
          await loadPoliza();
        } catch (e) {
          showErr('#errDatos', e.message || 'Error al guardar');
        }
      }

      async function uploadPdfs() {
        hideMsgs();
        if (!POLIZA) return;

        const fFactura = $('#file_factura')[0].files[0];
        if (!fFactura) {
          showErr('#errPdf', 'Factura PDF es obligatoria');
          return;
        }

        const fd = new FormData();
        fd.append('pcId', PC_ID);
        fd.append('csrf_token', csrf);
        fd.append('file_factura', fFactura);

        const fPol = $('#file_poliza')[0].files[0];
        const fWk = $('#file_wk')[0].files[0];
        if (fPol) fd.append('file_poliza', fPol);
        if (fWk) fd.append('file_wk', fWk);

        try {
          const res = await fetch(URL_FILES, {
            method: 'POST',
            headers: {
              'X-CSRF-Token': csrf
            },
            body: fd
          });
          const data = await res.json();
          if (!isSuccess(data)) throw new Error(data?.error || data?.msg || 'Error');
          $('#okPdf').removeClass('d-none');
          await loadPoliza();
        } catch (e) {
          showErr('#errPdf', e.message || 'Error subiendo PDFs');
        }
      }

      async function saveVendedor() {
        hideMsgs();
        const usId = parseInt($('#pvUsId').val() || '0', 10);
        if (usId <= 0) {
          showErr('#errVend', 'Selecciona vendedor');
          return;
        }
        try {
          const r = await apiPost(URL_VEND, {
            pcId: PC_ID,
            usId
          });
          if (!isSuccess(r)) throw new Error(r?.error || r?.msg || 'Error');
          $('#okVend').removeClass('d-none');
        } catch (e) {
          showErr('#errVend', e.message || 'Error');
        }
      }

      async function saveCuenta() {
        hideMsgs();
        const usId = parseInt($('#cuUsId').val() || '0', 10);
        if (usId <= 0) {
          showErr('#errCuenta', 'Selecciona responsable');
          return;
        }
        try {
          const r = await apiPost(URL_CUENTA, {
            pcId: PC_ID,
            usId
          });
          if (!isSuccess(r)) throw new Error(r?.error || r?.msg || 'Error');
          $('#okCuenta').removeClass('d-none');
        } catch (e) {
          showErr('#errCuenta', e.message || 'Error');
        }
      }

      async function loadEquipos() {
        $('#equiposGrid').html('<div class="col-12"><div class="text-center muted">Cargando...</div></div>');

        try {
          const r = await apiGet(URL_EQ_LIST, {
            pcId: PC_ID
          });
          if (!isSuccess(r)) throw new Error(r?.error || r?.msg || 'Error');

          const u = unwrap(r);
          const rowsA = pickArray(u, ['equipos', 'items', 'rows']);
          const rows = rowsA.length ? rowsA : pickArray(r, ['equipos', 'items', 'rows']);

          if (!rows.length) {
            $('#equiposGrid').html('<div class="col-12"><div class="text-center muted">Sin equipos asignados</div></div>');
            return;
          }

          const html = rows.map(x => {
            const nombre = x.eqNombre || ((x.maNombre || '') + ' ' + (x.eqModelo || '')).trim() || 'Equipo';
            const tipo = x.eqTipoEquipo || x.eqTipo || '—';
            const sn = x.peSN || x.eqSerial || '';
            const pol = x.pcTipoPoliza || x.pcTipo || '';
            const est = (x.peEstatus || 'Activo');
            const next = (est === 'Activo') ? 'Inactivo' : 'Activo';

            const marca = (x.maNombre || '').trim();
            const modelo = (x.eqModelo || '').trim();
            let img = '../img/Equipos/' + marca + '/' + modelo + '.png';
            img = img.replace(/\\/g, '/').replace(/\/+/g, '/');

            const hasTicket = (x.ticketsActivosCount >= 1 || x.ticketsActivosCount === '1' || x.ticketsActivosCount === true);
            const ticketsTxt = Array.isArray(x.ticketsActivosCodigos) ? x.ticketsActivosCodigos.join(', ') : (x.ticketsActivosCodigos || '');

            return `
              <div class="col-12 col-md-6 col-xl-4">
                ${hasTicket ? `<div class="eq-card h-100">` : `<div class="eq-card h-100" style="border: 1px solid #6b6b6b;">`}
                  <div class="eq-card__imgwrap">
                    <img class="eq-card__img" src="${esc(img)}" alt="Equipo"
                        onerror="this.src='../img/Equipos/default.svg'">
                  </div>

                  <div class="eq-card__body">
                    <div class="eq-card__title">${esc(nombre)}</div>

                    <div class="eq-card__meta">${esc(tipo)}</div>
                    <div class="eq-card__meta">SN: <strong>${esc(sn)}</strong></div>
                    <div class="eq-card__meta">Póliza <strong>${esc(pol)}</strong></div>

                    ${hasTicket ? `<div class="eq-badge-yellow">Ticket activo</div>` : ``}
                    ${ticketsTxt ? `<div class="eq-card__tickets"><strong>Tickets:</strong> ${esc(ticketsTxt)}</div>` : ``}

                    <div class="eq-card__actions">
                      <button class="btn btn-sm btn-outline-primary btnEditEq" data-peid="${x.peId}">
                        <i class="bi bi-pencil"></i>
                      </button>

                      <button class="btn btn-sm btn-outline-secondary btnToggle"
                              data-peid="${x.peId}"
                              data-next="${next}">
                        ${est === 'Activo' ? '<i class="bi bi-pause-circle"></i>' : '<i class="bi bi-play-circle"></i>'}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            `;
          }).join('');

          $('#equiposGrid').html(html);

        } catch (e) {
          console.error(e);
          $('#equiposGrid').html('<div class="col-12"><div class="text-center text-danger">Error cargando equipos</div></div>');
        }
      }

      async function toggleEquipo(peId, next) {
        try {
          const r = await apiPost(URL_EQ_TOG, {
            peId: parseInt(peId, 10),
            peEstatus: next
          });
          if (!isSuccess(r)) throw new Error(r?.error || r?.msg || 'Error');
          await loadEquipos();
        } catch (e) {
          alert(e.message || 'Error');
        }
      }

      function resetEqModal() {
        $('#eqEditErr,#eqEditOk').addClass('d-none');
        $('#snConfirmWrap').addClass('d-none');
        $('#eq_sn_confirm').val('');
        EQ_EDIT.allowSN = false;
      }

      async function openEditEquipo(peId) {
        resetEqModal();

        try {
          const r = await apiGet(URL_EQ_GET_ONE, {
            peId: parseInt(peId, 10)
          });
          if (!isSuccess(r)) throw new Error(r?.error || r?.msg || 'Error');

          const u = unwrap(r);
          const e = u.equipo;

          $('#eq_peId').val(e.peId);
          $('#eqEditSubtitle').text(`${e.maNombre || ''} ${e.eqModelo || ''} | peId ${e.peId}`);

          const sedes = u.sedes || [];
          $('#eq_csId').html(
            sedes.map(s => `<option value="${s.csId}">${esc(s.csNombre)}</option>`).join('') ||
            `<option value="">(sin sedes)</option>`
          );
          $('#eq_csId').val(String(e.csId || ''));

          $('#eq_peEstatus').val(e.peEstatus || 'Activo');
          $('#eq_peSO').val(e.peSO || '');
          $('#eq_peDescripcion').val(e.peDescripcion || '');

          EQ_EDIT.originalSN = e.peSN || '';
          $('#eq_peSN').val(EQ_EDIT.originalSN);

          if (USER_ROLE === 'MRSA') {
            $('#btnEnableSN').removeClass('d-none');
            $('#eq_peSN').prop('disabled', true);
            $('#snHelp').text('MRSA: puedes cambiar SN, pero debes habilitarlo y confirmar.');
          } else {
            $('#btnEnableSN').addClass('d-none');
            $('#eq_peSN').prop('disabled', true);
            $('#snHelp').text('SN bloqueado (solo MRSA puede cambiarlo).');
          }

          new bootstrap.Modal(document.getElementById('modalEqEdit')).show();

        } catch (e) {
          alert(e.message || 'Error abriendo edición');
        }
      }

      function enableSNChange() {
        if (!confirm('Vas a habilitar el cambio de Serial Number. ¿Continuar?')) return;
        if (!confirm('Confirmación final: cambiar SN puede afectar trazabilidad y tickets. ¿Seguro?')) return;

        EQ_EDIT.allowSN = true;
        $('#eq_peSN').prop('disabled', false).focus();
        $('#snConfirmWrap').removeClass('d-none');
      }

      async function saveEquipoEdits() {
        $('#eqEditErr,#eqEditOk').addClass('d-none');

        const peId = parseInt($('#eq_peId').val() || '0', 10);
        const csId = parseInt($('#eq_csId').val() || '0', 10);

        const payload = {
          peId,
          csId,
          peEstatus: $('#eq_peEstatus').val(),
          peSO: $('#eq_peSO').val().trim(),
          peDescripcion: $('#eq_peDescripcion').val().trim(),
        };

        const newSN = $('#eq_peSN').val().trim();
        if (USER_ROLE === 'MRSA' && EQ_EDIT.allowSN && newSN !== EQ_EDIT.originalSN) {
          payload.peSN = newSN;
          payload.sn_confirm = $('#eq_sn_confirm').val().trim();
        }

        try {
          const r = await apiPost(URL_EQ_UPDATE, payload);
          if (!isSuccess(r)) throw new Error(r?.error || r?.msg || 'Error');

          $('#eqEditOk').removeClass('d-none');
          await loadEquipos();
        } catch (e) {
          $('#eqEditErr').removeClass('d-none').text(e.message || 'Error guardando');
        }
      }

      function setMeta(text) {
        const $m = $('#bulk-meta');
        if (!text) {
          $m.hide().text('');
          return;
        }
        $m.show().text(text);
      }

      function badgeFor(status) {
        const map = {
          ok: 'bg-success',
          needs_map: 'bg-warning text-dark',
          ambiguous: 'bg-warning text-dark',
          error: 'bg-danger',
          dup: 'bg-secondary',
          inserted: 'bg-success',
          updated: 'bg-primary'
        };
        const cls = map[status] || 'bg-secondary';
        return `<span class="badge ${cls}">${status}</span>`;
      }

      function setButtonsState() {
        const hasPreview = BULK_PARSED && Array.isArray(BULK_PARSED.rows) && BULK_PARSED.rows.length > 0;
        $('#btn-clear-preview').prop('disabled', !hasPreview);

        let okToCommit = false;
        if (hasPreview) {
          okToCommit = BULK_PARSED.rows.every(r => {
            if (r.status === 'error') return true;
            return (r.csIdResolved && parseInt(r.csIdResolved) > 0);
          });
        }
        $('#btn-commit').prop('disabled', !okToCommit);
      }

      function buildSedeSelect(row, sedesGlobal) {
        const candidates = Array.isArray(row.sedeCandidates) ? row.sedeCandidates : [];
        const seen = new Set();
        const opts = [];

        function addOpt(csId, label) {
          const key = String(csId);
          if (seen.has(key)) return;
          seen.add(key);
          opts.push({
            csId,
            label
          });
        }

        for (const c of candidates) addOpt(c.csId, `★ ${c.csNombre} (${c.score})`);
        if (Array.isArray(sedesGlobal)) {
          for (const s of sedesGlobal) addOpt(s.csId, s.csNombre);
        }

        const current = row.csIdResolved ? String(row.csIdResolved) : '';
        let html = `<select class="form-select form-select-sm bulk-sede-select" data-line="${row.line}">`;
        html += `<option value="">-- Selecciona sede --</option>`;
        for (const o of opts) {
          const sel = (String(o.csId) === current) ? 'selected' : '';
          html += `<option value="${o.csId}" ${sel}>${esc(o.label)}</option>`;
        }
        html += `</select>`;
        return html;
      }

      function renderPreview(parsed) {
        BULK_PARSED = parsed;
        $('#bulk-result').hide().empty();

        const rows = parsed.rows || [];
        const sedesGlobal = parsed.sedes || [];

        if (!rows.length) {
          $('#bulk-preview-table').hide();
          setMeta('Sin filas para mostrar.');
          setButtonsState();
          return;
        }

        setMeta(`Archivo: ${parsed.meta?.fileName || ''} | Filas: ${rows.length}`);

        const $tb = $('#bulk-preview-table tbody');
        $tb.empty();

        for (const r of rows) {
          if (r.status === 'ok' && Array.isArray(r.sedeCandidates) && r.sedeCandidates.length) {
            const c0 = r.sedeCandidates[0];
            if (c0 && c0.score === 100) r.csIdResolved = c0.csId;
          }

          const d = r.data || {};
          const detail = (r.errors && r.errors.length) ? r.errors.join(' | ') : '—';
          const sedeSelectHtml = (r.status === 'error') ? `<span class="text-muted small">—</span>` : buildSedeSelect(r, sedesGlobal);

          $tb.append(`
            <tr>
              <td>${r.line}</td>
              <td>${d.eqId || ''}</td>
              <td><code>${esc(d.peSN || '')}</code></td>
              <td>${esc(d.sede || '')}</td>
              <td>${sedeSelectHtml}</td>
              <td>${badgeFor(r.status)}</td>
              <td class="small">${esc(detail)}</td>
            </tr>
          `);
        }

        $('#bulk-preview-table').show();
        setButtonsState();
      }

      function clearPreview() {
        BULK_PARSED = null;
        setMeta('');
        $('#bulk-preview-table').hide();
        $('#bulk-preview-table tbody').empty();
        $('#bulk-result').hide().empty();
        setButtonsState();
      }

      function syncResolvedFromUI() {
        if (!BULK_PARSED || !Array.isArray(BULK_PARSED.rows)) return;
        const map = new Map();
        $('.bulk-sede-select').each(function() {
          const line = parseInt($(this).data('line'), 10);
          const val = parseInt($(this).val() || '0', 10);
          map.set(line, val > 0 ? val : null);
        });
        for (const r of BULK_PARSED.rows) {
          if (r.status === 'error') continue;
          r.csIdResolved = map.get(r.line) || null;
        }
        setButtonsState();
      }

      async function doParse() {
        const f = $('#bulk-file')[0].files[0];
        if (!f) {
          alert('Selecciona un archivo XLSX/CSV');
          return;
        }

        const fd = new FormData();
        fd.append('pcId', PC_ID);
        fd.append('file', f);
        fd.append('csrf_token', csrf);

        $('#btn-parse').prop('disabled', true).text('Procesando...');
        try {
          const res = await fetch(URL_PARSE, {
            method: 'POST',
            headers: {
              'X-CSRF-Token': csrf
            },
            body: fd
          });
          const data = await res.json();
          if (!isSuccess(data)) throw new Error(data?.error || data?.msg || 'Error parse');
          renderPreview(unwrap(data));
        } catch (e) {
          alert(e.message || 'Error parse');
        } finally {
          $('#btn-parse').prop('disabled', false).text('Previsualizar');
        }
      }

      async function doCommit() {
        if (!BULK_PARSED || !Array.isArray(BULK_PARSED.rows) || !BULK_PARSED.rows.length) return;
        syncResolvedFromUI();

        const rowsToSend = BULK_PARSED.rows.map(r => ({
          line: r.line,
          csIdResolved: r.csIdResolved,
          data: r.data
        }));

        const mode = $('#bulkMode').val();
        const updateFields = getBulkUpdateFields();

        $('#btn-commit').prop('disabled', true).text('Respaldando...');

        try {
          // 1) backup obligatorio
          let res = await fetch(URL_BACKUP, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrf
            },
            body: JSON.stringify({
              pcId: PC_ID,
              csrf_token: csrf
            })
          });

          let backup = await res.json();
          if (!isSuccess(backup)) {
            throw new Error(backup?.error || backup?.msg || 'No se pudo generar backup');
          }

          $('#btn-commit').text('Importando...');

          // 2) commit pro
          res = await fetch(URL_COMMIT, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrf
            },
            body: JSON.stringify({
              pcId: PC_ID,
              mode,
              updateFields,
              rows: rowsToSend,
              csrf_token: csrf
            })
          });

          const data = await res.json();
          if (!isSuccess(data)) throw new Error(data?.error || data?.msg || 'Error commit');

          const out = unwrap(data);
          const b = unwrap(backup);

          const html = `
            <div class="alert alert-info">
              <b>Backup:</b> ${esc(b.filename || 'generado')}<br>
              <b>Resultado:</b>
              Total ${out.total} |
              Insertados ${out.insertados} |
              Actualizados ${out.actualizados || 0} |
              Duplicados ${out.duplicados} |
              Errores ${out.errores}
            </div>

            <div class="table-responsive">
              <table class="table table-sm">
                <thead class="table-light">
                  <tr>
                    <th>Línea</th>
                    <th>Status</th>
                    <th>SN</th>
                    <th>Mensaje</th>
                  </tr>
                </thead>
                <tbody>
                  ${(out.rows || []).map(r => `
                    <tr>
                      <td>${r.line}</td>
                      <td>${badgeFor(r.status)}</td>
                      <td><code>${esc(r.peSN || '')}</code></td>
                      <td class="small">${esc(r.message || '')}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          `;

          $('#bulk-result').html(html).show();
          await loadEquipos();

        } catch (e) {
          alert(e.message || 'Error commit');
        } finally {
          $('#btn-commit').prop('disabled', false).text('Confirmar importación');
          setButtonsState();
        }
      }

      // hooks
      $(document).on('click', '.btnEditEq', function() {
        openEditEquipo($(this).data('peid'));
      });

      $('#btnEnableSN').on('click', enableSNChange);
      $('#btnSaveEq').on('click', saveEquipoEdits);
      $('#btnSaveDatos').on('click', saveDatos);
      $('#btnUploadPdf').on('click', uploadPdfs);
      $('#btnSaveVendedor').on('click', saveVendedor);
      $('#btnSaveCuenta').on('click', saveCuenta);
      $('#btnReload,#btnReloadEquipos').on('click', loadPoliza);

      $(document).on('click', '.btnToggle', function() {
        toggleEquipo($(this).data('peid'), $(this).data('next'));
      });

      $('#btn-parse').on('click', doParse);
      $('#btn-clear-preview').on('click', clearPreview);
      $('#btn-commit').on('click', doCommit);
      $(document).on('change', '.bulk-sede-select', syncResolvedFromUI);

      // init
      clearPreview();
      loadPoliza();

    })();
  </script>
</body>

</html>