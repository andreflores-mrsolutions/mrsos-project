<?php
require_once __DIR__ . '/../../php/admin_bootstrap.php';
require_login();
require_usRol(['MRSA', 'MRA']);

$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;
if ($tiId <= 0) die('tiId inválido');

$csrf = csrf_token();

if (empty($_SESSION['usId'])) {
  header('Location: ../login/login.php');
  exit;
}

$theme = $_COOKIE['mrs_theme'] ?? 'light';
?>
<script>
  window.MRS_CSRF = <?= json_encode($csrf) ?>;
</script>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MR SOS | Admin</title>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <link href="../../css/style.css" rel="stylesheet">

  <style>
    :root {
      --mr-bg: #f5f7fb;
      --mr-card: #ffffff;
      --mr-border: rgba(15, 23, 42, .10);
      --mr-text: #0f172a;
      --mr-muted: rgba(15, 23, 42, .65);
      --mr-shadow: 0 8px 22px rgba(15, 23, 42, .08);
      --mr-radius: 14px;
    }

    body {
      background: var(--mr-bg);
    }

    body.dark-mode {
      background: #0b1220;
    }

    .admin-topbar {
      background: rgba(255, 255, 255, .85);
      border-bottom: 1px solid var(--mr-border);
      backdrop-filter: blur(10px);
    }

    body.dark-mode .admin-topbar {
      background: rgba(15, 23, 42, .65);
      border-bottom: 1px solid rgba(148, 163, 184, .18);
    }

    .kpi-card,
    .filters-row,
    .client-card {
      background: var(--mr-card);
      border: 1px solid var(--mr-border);
      border-radius: var(--mr-radius);
      box-shadow: var(--mr-shadow);
    }

    body.dark-mode .kpi-card,
    body.dark-mode .filters-row,
    body.dark-mode .client-card {
      background: rgba(15, 23, 42, .6);
      border-color: rgba(148, 163, 184, .18);
      color: #e5e7eb;
    }

    .kpi-card {
      padding: 14px;
      height: 100%;
    }

    .kpi-title {
      font-size: .85rem;
      color: var(--mr-muted);
    }

    .kpi-value {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--mr-text);
    }

    body.dark-mode .kpi-title {
      color: rgba(226, 232, 240, .75);
    }

    body.dark-mode .kpi-value {
      color: #e5e7eb;
    }

    .filters-row {
      padding: 12px;
    }

    .client-card {
      padding: 14px;
      height: 100%;
      cursor: pointer;
      transition: transform .12s ease, box-shadow .12s ease;
    }

    .client-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(15, 23, 42, .10);
    }

    .client-logo {
      height: 54px;
      width: 100%;
      object-fit: contain;
    }

    .muted {
      color: var(--mr-muted);
    }

    body.dark-mode .muted {
      color: rgba(226, 232, 240, .75);
    }

    .pill {
      border: 1px solid var(--mr-border);
      border-radius: 999px;
      padding: 2px 10px;
      font-size: .74rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(2, 6, 23, .02);
    }

    body.dark-mode .pill {
      border-color: rgba(148, 163, 184, .18);
      background: rgba(148, 163, 184, .06);
    }

    .pill-danger {
      border-color: rgba(239, 68, 68, .35);
      background: rgba(239, 68, 68, .08);
      color: #b91c1c;
    }

    .pill-warn {
      border-color: rgba(245, 158, 11, .35);
      background: rgba(245, 158, 11, .10);
      color: #b45309;
    }

    .group-title {
      margin-top: 18px;
      margin-bottom: 10px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .empty-state {
      border: 1px dashed var(--mr-border);
      border-radius: var(--mr-radius);
      padding: 22px;
      text-align: center;
      background: rgba(255, 255, 255, .6);
    }

    body.dark-mode .empty-state {
      background: rgba(15, 23, 42, .35);
      border-color: rgba(148, 163, 184, .18);
    }
  </style>
  <style>
    :root {
      --card-radius: 18px;
    }

    body {
      background: #f6f7fb;
    }

    .card {
      border-radius: var(--card-radius);
    }

    .shadow-soft {
      box-shadow: 0 10px 30px rgba(16, 24, 40, .06);
    }

    .small-muted {
      font-size: .86rem;
      color: #667085;
    }

    .kpi-pill {
      border: 1px solid rgba(0, 0, 0, .08);
      background: #fff;
      border-radius: 999px;
      padding: .25rem .6rem;
      font-size: .82rem;
    }

    .engineer-item {
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: 14px;
      background: #fff;
      padding: .75rem;
      transition: .15s;
      cursor: pointer;
    }

    .engineer-item:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 18px rgba(16, 24, 40, .07);
    }

    .engineer-item.selected {
      border-color: #0d6efd;
      box-shadow: 0 10px 18px rgba(13, 110, 253, .12);
    }

    .sticky-actions {
      position: sticky;
      bottom: 16px;
      z-index: 10;
    }

    .tab-pane {
      padding-top: 12px;
    }

    .req-dot {
      width: 8px;
      height: 8px;
      background: #dc3545;
      border-radius: 999px;
      display: inline-block;
      margin-left: 6px;
    }

    .ok-dot {
      width: 8px;
      height: 8px;
      background: #198754;
      border-radius: 999px;
      display: inline-block;
      margin-left: 6px;
    }

    .mono {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
  </style>
</head>

<body class="<?php echo ($theme === 'dark') ? 'dark-mode' : ''; ?>">
  <div class="container-fluid">
    <div class="row gx-0">

      <!-- SIDEBAR (simple, puedes alinearlo a tu sidebar real) -->
      <nav id="sidebar" class="col-12 col-md-3 col-lg-2 d-none d-lg-block p-3 mr-side">
        <div class="brand mb-3 px-2">
          <a class="navbar-brand" href="#">
            <img src="../../img/image.png" alt="Logo" class="rounded-pill" style="max-width: 120px;">
          </a>
        </div>

        <div class="section-title px-2">Operación</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_ticket.php"><i class="bi bi-shield-check"></i> Health Checks</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nuevo Cliente</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_usuario.php"><i class="bi bi-plus-circle"></i> Nuevo Usuario</a></li>
        </ul>

        <div class="section-title px-2 mt-3">Gestión</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-gear"></i> Pólizas</a></li>
          <li class="nav-item"><a class="nav-link" href="hojas_de_servicio.php"><i class="bi bi-download"></i> Hojas de Servicio</a></li>
        </ul>

        <div class="section-title px-2 mt-3">Administración</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="admin_usuarios.php"><i class="bi bi-shield-lock"></i> Panel Administrador</a></li>
        </ul>

        <div class="section-title px-2 mt-3">General</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="misequipos.php"><i class="bi bi-cpu"></i> Equipos</a></li>
          <li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-person"></i> Mis datos</a></li>
        </ul>
      </nav>
      <!-- OFFCANVAS (xs/sm) y como sidebar en lg mediante .offcanvas-lg -->
      <div class="offcanvas offcanvas-start offcanvas-xl mr-side" tabindex="-1" id="offcanvasSidebar">
        <div class="p-3 d-flex align-items-center justify-content-between">
          <div class="brand">
            <a class="navbar-brand" href="#">
              <img src="../img/image.png" alt="Logo" class="rounded-pill">
            </a>
          </div>
          <button type="button" class="btn btn-outline-light close-btn" data-bs-dismiss="offcanvas" aria-label="Close">
            <i class="bi bi-chevron-left"></i>
          </button>
        </div>



        <div class="section-title px-2">Operación</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_ticket.php"><i class="bi bi-shield-check"></i> Health Checks</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nuevo Cliente</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_usuario.php"><i class="bi bi-plus-circle"></i> Nuevo Usuario</a></li>
        </ul>

        <div class="section-title px-2 mt-3">Gestión</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-gear"></i> Pólizas</a></li>
          <li class="nav-item"><a class="nav-link" href="hojas_de_servicio.php"><i class="bi bi-download"></i> Hojas de Servicio</a></li>
        </ul>

        <div class="section-title px-2 mt-3">Administración</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="admin_usuarios.php"><i class="bi bi-shield-lock"></i> Panel Administrador</a></li>
        </ul>

        <div class="section-title px-2 mt-3">General</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="misequipos.php"><i class="bi bi-cpu"></i> Equipos</a></li>
          <li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-person"></i> Mis datos</a></li>
        </ul>

      </div>


      <main class="col-12 col-lg-10">
        <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary d-lg-none me-2"
              data-bs-toggle="offcanvas"
              data-bs-target="#offcanvasSidebar"
              aria-controls="offcanvasSidebar">
              <i class="bi bi-list"></i>
            </button>
            <span class="badge text-bg-success rounded-pill px-3">Activo</span>
            <span class="fw-bold" id="topUser">Admin</span>
            <span class="muted">| Admin</span>
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

        <div class="container-fluid px-3 px-lg-4 py-4">

          <!-- HEADER -->
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
              <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold mb-0">Paquete de Acceso</h4>
                <span class="kpi-pill">Ticket <span class="mono">#<?= $tiId ?></span></span>
              </div>
              <div class="small-muted mt-1">
                Configura ingeniero(s), credencial y datos opcionales. Al guardar, el cliente podrá subir folio.
              </div>
            </div>

            <div class="d-flex align-items-center gap-2">
              <span id="vpBadgeReady" class="badge rounded-pill text-bg-secondary">Cargando…</span>
              <button class="btn btn-outline-secondary" onclick="history.back()">
                <i class="bi bi-arrow-left"></i> Volver
              </button>
            </div>
          </div>

          <div id="vpAlert" class="alert alert-danger d-none"></div>

          <div class="row g-3">

            <!-- LEFT: Ingenieros -->
            <div class="col-12 col-xl-4">
              <div class="card shadow-soft">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-bold">
                      <i class="bi bi-people"></i> Ingenieros
                    </div>
                    <span class="kpi-pill" id="vpCountIng">0 seleccionados</span>
                  </div>

                  <div class="input-group mb-2">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input id="vpSearchIng" class="form-control" placeholder="Buscar por nombre, correo, tier…">
                  </div>

                  <div class="small-muted mb-3">
                    Click para seleccionar. El primero es <b>principal</b>; los demás son <b>apoyo</b>.
                  </div>

                  <div id="engineersList" class="d-grid gap-2">
                    <div class="text-muted">Cargando ingenieros…</div>
                  </div>

                  <hr class="my-3" style="opacity:.12;">

                  <div class="fw-semibold mb-2">Selección actual</div>
                  <div id="vpSelectedChips" class="d-flex flex-wrap gap-2">
                    <span class="small-muted">—</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- RIGHT: Tabs -->
            <div class="col-12 col-xl-8">
              <div class="card shadow-soft">
                <div class="card-body">

                  <ul class="nav nav-pills gap-2" id="vpTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabDocs" type="button" role="tab">
                        Documentos <span id="dotDocs" class="req-dot d-none"></span><span id="dotDocsOk" class="ok-dot d-none"></span>
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabVeh" type="button" role="tab">
                        Vehículo
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabEq" type="button" role="tab">
                        Equipos
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabTools" type="button" role="tab">
                        Herramientas / EPP
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabPiezas" type="button" role="tab">
                        Piezas
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabNotas" type="button" role="tab">
                        Notas
                      </button>
                    </li>
                  </ul>

                  <div class="tab-content mt-3">

                    <!-- DOCS -->
                    <div class="tab-pane fade show active" id="tabDocs" role="tabpanel">
                      <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                          <div class="fw-bold">Documentos</div>
                          <div class="small-muted">
                            Obligatorio: <b>Credencial de trabajo (PDF)</b>. INE/NSS son opcionales por ahora.
                          </div>
                        </div>
                      </div>
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-semibold">Documentos</div>
                        <button class="btn btn-outline-primary btn-sm" id="vpBtnAddDoc" disabled>
                          <i class="bi bi-plus"></i> Agregar documento
                        </button>
                      </div>
                      <div id="docsContainer" class="text-muted">Selecciona ingeniero(s) primero.</div>

                    </div>

                    <!-- VEH -->
                    <div class="tab-pane fade" id="tabVeh" role="tabpanel">
                      <div class="fw-bold">Vehículo (opcional)</div>
                      <div class="small-muted">Si aplica, selecciona un vehículo del catálogo del ingeniero.</div>
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-semibold">Vehículo (opcional)</div>
                        <button class="btn btn-outline-primary btn-sm" id="vpBtnAddVeh" disabled>
                          <i class="bi bi-plus"></i> Agregar vehículo
                        </button>
                      </div>
                      <div id="vehiculosContainer" class="text-muted">Selecciona ingeniero(s) primero.</div>
                    </div>

                    <!-- EQ -->
                    <div class="tab-pane fade" id="tabEq" role="tabpanel">
                      <div class="fw-bold">Equipos del ingeniero</div>
                      <div class="small-muted">Laptop/celular/otros. Puedes seleccionar más de uno.</div>
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-semibold">Equipos del Ingeniero</div>
                        <button class="btn btn-outline-primary btn-sm" id="vpBtnAddEq" disabled>
                          <i class="bi bi-plus"></i> Agregar equipo
                        </button>
                      </div>
                      <div id="equiposContainer" class="text-muted">Selecciona ingeniero(s) primero.</div>
                    </div>

                    <!-- TOOLS -->
                    <div class="tab-pane fade" id="tabTools" role="tabpanel">
                      <div class="fw-bold">Herramientas / EPP / Vestimenta</div>
                      <div class="small-muted">
                        Opcional. Vestimenta default: <b>pantalón mezclilla · camisa/polo · botas/zapatos</b>.
                      </div>
                      <div id="toolsContainer" class="mt-3">
                        <div class="text-muted">Selecciona ingeniero(s) para ver catálogo.</div>
                      </div>
                      <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="fw-semibold">Catálogo por ingeniero</div>
                        <button class="btn btn-outline-primary btn-sm" id="vpBtnEpp" disabled>
                          <i class="bi bi-shield-check"></i> Configurar EPP
                        </button>
                      </div>
                      <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="fw-semibold">Herramientas</div>
                        <button class="btn btn-outline-primary btn-sm" id="vpBtnAddTool" disabled>
                          <i class="bi bi-plus"></i> Agregar herramienta
                        </button>
                      </div>
                    </div>

                    <!-- PIEZAS -->
                    <div class="tab-pane fade" id="tabPiezas" role="tabpanel">
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <div class="fw-bold">Piezas (si aplica)</div>
                          <div class="small-muted">Puedes capturar por tipo/PN/SN y nota.</div>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" id="btnAddPieza">
                          <i class="bi bi-plus"></i> Agregar pieza
                        </button>
                      </div>

                      <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle">
                          <thead>
                            <tr class="small-muted">
                              <th style="width:18%">Tipo</th>
                              <th style="width:22%">PartNumber</th>
                              <th style="width:22%">SerialNumber</th>
                              <th>Notas</th>
                              <th style="width:1%"></th>
                            </tr>
                          </thead>
                          <tbody id="piezasTbody">
                            <tr>
                              <td colspan="5" class="text-muted">Sin piezas.</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <!-- NOTAS -->
                    <div class="tab-pane fade" id="tabNotas" role="tabpanel">
                      <div class="fw-bold">Notas de acceso</div>
                      <div class="small-muted">Ej: “presentarse en recepción”, “acceso por puerta 3”, etc.</div>
                      <textarea class="form-control mt-3" id="vpNotasAcceso" rows="4"
                        placeholder="Escribe aquí notas de acceso..."></textarea>
                    </div>

                  </div>

                  <!-- Sticky actions -->
                  <div class="sticky-actions mt-3">
                    <div class="card shadow-soft">
                      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="small-muted">
                          <span class="fw-semibold">Requisito:</span> Credencial de trabajo seleccionada
                          <span id="vpReqCred" class="req-dot"></span>
                        </div>
                        <div class="d-flex gap-2">
                          <button class="btn btn-outline-secondary" onclick="window.close()">
                            Cerrar
                          </button>
                          <button class="btn btn-primary" id="btnGuardarPaquete">
                            <i class="bi bi-save"></i> Guardar paquete
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                </div><!-- card-body -->
              </div><!-- card -->
            </div><!-- col -->
          </div><!-- row -->
        </div><!-- container -->
        <!-- Modal Docs -->
        <div class="modal fade" id="vpModalDoc" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Agregar documento de ingeniero</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
              </div>
              <div class="modal-body">

                <div class="alert alert-info small mb-3">
                  Sube un <b>PDF</b> y se guardará en el catálogo del ingeniero para reutilizarse en otros tickets.
                </div>

                <div class="row g-2">
                  <div class="col-12 col-md-5">
                    <label class="form-label">Ingeniero</label>
                    <select class="form-select" id="vpDocIng"></select>
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" id="vpDocTipo">
                      <option value="credencial_trabajo">Credencial de trabajo (obligatoria)</option>
                      <option value="INE">INE</option>
                      <option value="NSS">NSS</option>
                      <option value="OTRO">Otro</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">Label</label>
                    <input class="form-control" id="vpDocLabel" placeholder="Ej: Credencial MR 2026">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Archivo (PDF)</label>
                    <input type="file" class="form-control" id="vpDocFile" accept="application/pdf">
                    <div class="form-text">Solo PDF. Máx recomendado: 10MB.</div>
                  </div>
                </div>

                <div class="alert alert-danger d-none mt-3" id="vpDocErr"></div>

              </div>
              <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="vpDocSave">
                  <i class="bi bi-upload"></i> Guardar documento
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Vehiculos -->
        <div class="modal fade" id="vpModalVeh" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Agregar vehículo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
              </div>
              <div class="modal-body">

                <div class="row g-2">
                  <div class="col-12 col-md-4">
                    <label class="form-label">Ingeniero</label>
                    <select class="form-select" id="vpVehIng"></select>
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">Placas <span class="text-danger">*</span></label>
                    <input class="form-control" id="vpVehPlacas" placeholder="ABC-123-A">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">Año</label>
                    <input class="form-control" id="vpVehAnio" placeholder="2024">
                  </div>

                  <div class="col-12 col-md-4">
                    <label class="form-label">Marca</label>
                    <input class="form-control" id="vpVehMarca" placeholder="Nissan">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">Modelo</label>
                    <input class="form-control" id="vpVehModelo" placeholder="Versa">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">Color</label>
                    <input class="form-control" id="vpVehColor" placeholder="Gris">
                  </div>
                </div>

                <div class="alert alert-danger d-none mt-3" id="vpVehErr"></div>

              </div>
              <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="vpVehSave">
                  <i class="bi bi-save"></i> Guardar vehículo
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Equipos -->
        <div class="modal fade" id="vpModalEq" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Agregar equipo del ingeniero</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
              </div>
              <div class="modal-body">

                <div class="row g-2">
                  <div class="col-12 col-md-4">
                    <label class="form-label">Ingeniero</label>
                    <select class="form-select" id="vpEqIng"></select>
                  </div>

                  <div class="col-12 col-md-4">
                    <label class="form-label">Tipo <span class="text-danger">*</span></label>
                    <input class="form-control" id="vpEqTipo" placeholder="Laptop / Celular / Tablet / etc">
                  </div>

                  <div class="col-12 col-md-4">
                    <label class="form-label">Marca</label>
                    <input class="form-control" id="vpEqMarca" placeholder="Dell / Samsung / etc">
                  </div>

                  <div class="col-12 col-md-4">
                    <label class="form-label">Modelo</label>
                    <input class="form-control" id="vpEqModelo" placeholder="Inspiron 15 / S24 Ultra">
                  </div>

                  <div class="col-12 col-md-4">
                    <label class="form-label">Serie</label>
                    <input class="form-control" id="vpEqSerie" placeholder="Serial / IMEI">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <input class="form-control" id="vpEqDesc" placeholder="Ej: Equipo de trabajo / Teléfono personal">
                  </div>
                </div>

                <div class="alert alert-danger d-none mt-3" id="vpEqErr"></div>

              </div>
              <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="vpEqSave">
                  <i class="bi bi-save"></i> Guardar equipo
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal EPP -->
        <div class="modal fade" id="vpModalEpp" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">EPP del ingeniero</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
              </div>

              <div class="modal-body">
                <div class="row g-2">
                  <div class="col-12 col-md-5">
                    <label class="form-label">Ingeniero</label>
                    <select class="form-select" id="vpEppIng"></select>
                  </div>

                  <div class="col-12 col-md-7">
                    <label class="form-label">Equipo de Protección Personal</label>
                    <div class="d-flex flex-wrap gap-3 mt-1">
                      <label class="form-check">
                        <input class="form-check-input" type="checkbox" id="vpEppCasco">
                        <span class="form-check-label">Casco</span>
                      </label>
                      <label class="form-check">
                        <input class="form-check-input" type="checkbox" id="vpEppChaleco">
                        <span class="form-check-label">Chaleco</span>
                      </label>
                      <label class="form-check">
                        <input class="form-check-input" type="checkbox" id="vpEppBotas">
                        <span class="form-check-label">Botas</span>
                      </label>
                    </div>
                    <div class="form-text">Estos datos se guardan en el catálogo del ingeniero (se reutilizan en tickets).</div>
                  </div>

                  <div class="col-12">
                    <label class="form-label">Notas</label>
                    <input class="form-control" id="vpEppNotas" placeholder="Ej: casco talla M, botas dieléctricas, etc">
                  </div>
                </div>

                <div class="alert alert-danger d-none mt-3" id="vpEppErr"></div>
              </div>

              <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="vpEppSave">
                  <i class="bi bi-save"></i> Guardar EPP
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Herramienta -->
        <div class="modal fade" id="vpModalTool" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Agregar herramienta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
              </div>

              <div class="modal-body">
                <div class="row g-2">
                  <div class="col-12 col-md-5">
                    <label class="form-label">Ingeniero</label>
                    <select class="form-select" id="vpToolIng"></select>
                  </div>

                  <div class="col-12 col-md-7">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input class="form-control" id="vpToolNombre" placeholder="Ej: Kit destornilladores / Multímetro / etc">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Detalle (opcional)</label>
                    <input class="form-control" id="vpToolDetalle" placeholder="Ej: marca, modelo, notas">
                  </div>
                </div>

                <div class="alert alert-danger d-none mt-3" id="vpToolErr"></div>
              </div>

              <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="vpToolSave">
                  <i class="bi bi-save"></i> Guardar herramienta
                </button>
              </div>
            </div>
          </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
          const tiId = <?= (int)$tiId ?>;
          const csrfToken = <?= json_encode($csrf) ?>;

          // IMPORTANTE: estás en backend/admin -> tus APIs deben ser backend/admin/api/...
          function apiGetUrl() {
            const sel = (state.selectedIng && state.selectedIng.length) ? `&sel=${encodeURIComponent(state.selectedIng.join(','))}` : '';
            return `../api/visita_paquete_get.php?tiId=${encodeURIComponent(tiId)}${sel}`;
          }
          const API_SAVE = `../api/visita_paquete_save.php`;

          const state = {
            allEngineers: [],
            selectedIng: [], // [usIdIng]
            selectedDocs: {}, // { usIdIng: { credencial_trabajo: idocId, INE: idocId, NSS: idocId, OTRO: [idocId...] } }
            selectedVeh: {}, // { usIdIng: viId }
            selectedEquipos: {}, // { usIdIng: Set(ieId) }
            selectedTools: {}, // { usIdIng: Set(ihtId) }
            piezas: [], // array piezas
            notas: '',

            // catálogos retornados por backend (pueden venir vacíos si no hay datos)
            catalogos: {
              docs: [],
              vehiculos: [],
              equipos: [],
              herramientas: [],
              epp: [],
              vestimenta: []
            },
            snapshot: null,
            acceso_ready: 0
          };

          function esc(s) {
            return (s || '').toString().replace(/[&<>"']/g, m => ({
              '&': '&amp;',
              '<': '&lt;',
              '>': '&gt;',
              '"': '&quot;',
              "'": '&#039;'
            } [m]));
          }

          function showErr(msg) {
            const el = document.getElementById('vpAlert');
            el.classList.remove('d-none');
            el.innerHTML = `<b>Error:</b> ${esc(msg)}`;
          }

          function setReadyBadge() {
            const b = document.getElementById('vpBadgeReady');
            if (state.acceso_ready) {
              b.className = 'badge rounded-pill text-bg-success';
              b.textContent = 'Acceso listo';
            } else {
              b.className = 'badge rounded-pill text-bg-warning text-dark';
              b.textContent = 'Paquete incompleto';
            }
          }

          function updateReqCred() {
            const hasCred = hasCredencialSelected();
            document.getElementById('vpReqCred').className = hasCred ? 'ok-dot' : 'req-dot';

            document.getElementById('dotDocs').classList.toggle('d-none', hasCred);
            document.getElementById('dotDocsOk').classList.toggle('d-none', !hasCred);
          }

          function hasCredencialSelected() {
            // válido si al menos 1 ingeniero tiene credencial_trabajo seleccionada (idocId)
            for (const usId of state.selectedIng) {
              const o = state.selectedDocs[usId];
              if (o && o.credencial_trabajo) return true;
            }
            return false;
          }

          async function load() {
            try {
              const r = await fetch(apiGetUrl(), {
                credentials: 'include',
                cache: 'no-store'
              });
              const j = await r.json().catch(() => null);
              if (!j || j.success === false) throw new Error((j && j.error) ? j.error : 'No se pudo leer respuesta de API');

              state.allEngineers = j.ingenieros_disponibles || [];
              // Dedup por usIdIng (tu backend puede regresar repetidos por Tier)
              (function() {
                const seen = new Map();
                (state.allEngineers || []).forEach(e => {
                  const id = Number(e.usIdIng || 0);
                  if (!id) return;
                  if (!seen.has(id)) seen.set(id, e);
                });
                state.allEngineers = Array.from(seen.values());
              })();

              state.catalogos = j.catalogos || state.catalogos;
              state.snapshot = j.snapshot || null;
              state.acceso_ready = Number(j.acceso_ready || 0) === 1 ? 1 : 0;

              // Precargar snapshot si existe
              if (!state.selectedIng.length && state.snapshot && Array.isArray(state.snapshot.ingenieros) && state.snapshot.ingenieros.length) {
                state.selectedIng = state.snapshot.ingenieros.map(x => Number(x.usIdIng)).filter(Boolean);
              }

              // Precargar docs/veh/equipos/tools desde snapshot
              if (state.snapshot) {
                // docs snapshot: si viene idocId úsalo
                (state.snapshot.docs || []).forEach(d => {
                  const us = Number(d.usIdIng || 0);
                  if (!us) return;
                  if (!state.selectedDocs[us]) state.selectedDocs[us] = {
                    OTRO: []
                  };
                  const tipo = (d.tipo || '').toString();
                  if (tipo === 'OTRO') {
                    if (d.idocId) state.selectedDocs[us].OTRO.push(Number(d.idocId));
                  } else {
                    if (d.idocId) state.selectedDocs[us][tipo] = Number(d.idocId);
                  }
                });

                (state.snapshot.vehiculos || []).forEach(v => {
                  const us = Number(v.usIdIng || 0);
                  if (!us) return;
                  if (v.viId) state.selectedVeh[us] = Number(v.viId);
                });

                (state.snapshot.equipos || []).forEach(e => {
                  const us = Number(e.usIdIng || 0);
                  if (!us) return;
                  if (!state.selectedEquipos[us]) state.selectedEquipos[us] = new Set();
                  if (e.ieId) state.selectedEquipos[us].add(Number(e.ieId));
                });

                (state.snapshot.herramientas || []).forEach(h => {
                  const us = Number(h.usIdIng || 0);
                  if (!us) return;
                  if (!state.selectedTools[us]) state.selectedTools[us] = new Set();
                  if (h.ihtId) state.selectedTools[us].add(Number(h.ihtId));
                });

                // piezas
                const ps = state.snapshot.piezas || [];
                state.piezas = ps.map(p => ({
                  tipo_pieza: (p.tipo_pieza || '').toString(),
                  partNumber: (p.partNumber || '').toString(),
                  serialNumber: (p.serialNumber || '').toString(),
                  notas: (p.notas || '').toString(),
                  invId: p.invId ?? null
                }));
              }

              renderEngineers();
              renderSelectedChips();
              renderAllTabs();
              renderPiezas();
              vpSyncAddDocBtn();
              vpSyncAddEqBtn();
              vpSyncAddVehBtn();
              vpSyncEppBtn();
              vpSyncAddToolBtn();
              renderVehiculos();
              renderEquipos();

              setReadyBadge();
              updateReqCred();

            } catch (e) {
              showErr(e.message || String(e));
              document.getElementById('engineersList').innerHTML = `<div class="text-danger">No se pudieron cargar ingenieros.</div>`;
              document.getElementById('vpBadgeReady').textContent = 'Error';
            }
          }

          async function refreshCatalogosOnly() {
            try {
              const r = await fetch(apiGetUrl(), {
                credentials: 'include',
                cache: 'no-store'
              });
              const j = await r.json().catch(() => null);
              if (!j || j.success === false) throw new Error((j && j.error) ? j.error : 'No se pudo leer catálogos');
              state.catalogos = j.catalogos || state.catalogos;
              // no tocamos selectedIng aquí
              renderAllTabs();
              vpSyncAddDocBtn();
              vpSyncAddEqBtn();
              vpSyncAddVehBtn();
              vpSyncEppBtn();
              vpSyncAddToolBtn();
              updateReqCred();
            } catch (e) {
              showErr(e.message || String(e));
            }
          }



          function renderEngineers() {
            const list = document.getElementById('engineersList');
            const q = (document.getElementById('vpSearchIng').value || '').trim().toLowerCase();

            const rows = state.allEngineers
              .filter(e => {
                if (!q) return true;
                const s = `${e.usNombre||''} ${e.usAPaterno||''} ${e.usAMaterno||''} ${e.usCorreo||''} ${e.ingTier||''} ${e.ingExperto||''}`.toLowerCase();
                return s.includes(q);
              })
              .map(e => {
                const id = Number(e.usIdIng);
                const sel = state.selectedIng.includes(id);
                return `
        <div class="engineer-item ${sel?'selected':''}" data-id="${id}">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="fw-semibold">${esc(e.usNombre)} ${esc(e.usAPaterno||'')} ${esc(e.usAMaterno||'')}</div>
              <div class="small-muted">${esc(e.usCorreo||'')}</div>
              <div class="small-muted">Tier: ${esc(e.ingTier||'—')} · ${esc(e.ingExperto||'')}</div>
            </div>
            <div class="text-end">
              <span class="badge rounded-pill ${sel?'text-bg-primary':'text-bg-light border'}">${sel?'Seleccionado':'Elegir'}</span>
            </div>
          </div>
        </div>
      `;
              }).join('');

            list.innerHTML = rows || `<div class="text-muted">Sin resultados.</div>`;
            document.getElementById('vpCountIng').textContent = `${state.selectedIng.length} seleccionados`;

            // click handlers
            list.querySelectorAll('.engineer-item').forEach(el => {
              el.addEventListener('click', () => {
                const id = Number(el.dataset.id);
                toggleEngineer(id);
              });
            });
          }

          function toggleEngineer(usId) {
            const idx = state.selectedIng.indexOf(usId);
            if (idx >= 0) {
              state.selectedIng.splice(idx, 1);
            } else {
              state.selectedIng.push(usId);
            }

            // Si es el primero que selecciona y no hay docs seleccionados, intenta preseleccionar credencial si existe en catálogo
            if (!state.selectedDocs[usId]) state.selectedDocs[usId] = {
              OTRO: []
            };

            // Preselección credencial si existe en catálogo docs
            const cand = (state.catalogos.docs || []).filter(d => Number(d.usIdIng) === usId && (d.tipo || '') === 'credencial_trabajo');
            if (cand.length && !state.selectedDocs[usId].credencial_trabajo) {
              state.selectedDocs[usId].credencial_trabajo = Number(cand[0].idocId);
            }

            renderEngineers();
            renderSelectedChips();
            // refresca catálogos según selección (docs/vehículos/equipos/...)
            refreshCatalogosOnly();
          }

          function renderSelectedChips() {
            const wrap = document.getElementById('vpSelectedChips');
            if (!state.selectedIng.length) {
              wrap.innerHTML = `<span class="small-muted">—</span>`;
              return;
            }
            wrap.innerHTML = state.selectedIng.map((id, i) => {
              const e = state.allEngineers.find(x => Number(x.usIdIng) === id);
              const name = e ? `${e.usNombre||''} ${e.usAPaterno||''} ${e.usAMaterno||''}` : `#${id}`;
              const role = (i === 0) ? 'principal' : 'apoyo';
              return `<span class="badge rounded-pill text-bg-light border">${esc(name)} · ${role}</span>`;
            }).join(' ');
            vpSyncAddDocBtn();
            vpSyncAddVehBtn();
            vpSyncAddEqBtn();
            vpSyncAddToolBtn();
            vpSyncEppBtn();
          }

          function groupBy(arr, keyFn) {
            const m = new Map();
            arr.forEach(x => {
              const k = keyFn(x);
              if (!m.has(k)) m.set(k, []);
              m.get(k).push(x);
            });
            return m;
          }

          function renderAllTabs() {
            renderDocs();
            renderVehiculos();
            renderEquipos();
            renderTools();
          }

          function renderDocs() {
            const cont = document.getElementById('docsContainer');

            if (!state.selectedIng.length) {
              cont.innerHTML = `<div class="text-muted">Selecciona ingeniero(s) para ver su catálogo.</div>`;
              return;
            }

            const docs = (state.catalogos.docs || []).filter(d => state.selectedIng.includes(Number(d.usIdIng)));
            const byIng = groupBy(docs, d => Number(d.usIdIng));

            let html = '';
            state.selectedIng.forEach(usId => {
              const e = state.allEngineers.find(x => Number(x.usIdIng) === usId);
              const name = e ? `${e.usNombre||''} ${e.usAPaterno||''} ${e.usAMaterno||''}` : `Ingeniero #${usId}`;
              const list = byIng.get(usId) || [];

              const cred = list.filter(x => x.tipo === 'credencial_trabajo');
              const ine = list.filter(x => x.tipo === 'INE');
              const nss = list.filter(x => x.tipo === 'NSS');
              const oth = list.filter(x => x.tipo === 'OTRO');

              html += `
      <div class="border rounded-4 p-3 mb-3 bg-white">
        <div class="d-flex justify-content-between align-items-center">
          <div class="fw-semibold">${esc(name)}</div>
          <span class="small-muted">Docs: ${list.length}</span>
        </div>

        <div class="mt-3">
          <div class="fw-semibold">Credencial de trabajo <span class="req-dot"></span></div>
          ${renderDocSelect(usId, 'credencial_trabajo', cred, true)}
        </div>

        <div class="mt-3">
          <div class="fw-semibold">INE (opcional)</div>
          ${renderDocSelect(usId, 'INE', ine, false)}
        </div>

        <div class="mt-3">
          <div class="fw-semibold">NSS (opcional)</div>
          ${renderDocSelect(usId, 'NSS', nss, false)}
        </div>

        <div class="mt-3">
          <div class="fw-semibold">Otros</div>
          ${renderDocMulti(usId, oth)}
        </div>
      </div>
    `;
            });

            cont.innerHTML = html;

            // binds
            cont.querySelectorAll('[data-doc-select]').forEach(sel => {
              sel.addEventListener('change', () => {
                const us = Number(sel.dataset.us);
                const tipo = sel.dataset.tipo;
                const val = sel.value ? Number(sel.value) : null;
                if (!state.selectedDocs[us]) state.selectedDocs[us] = {
                  OTRO: []
                };
                state.selectedDocs[us][tipo] = val;
                updateReqCred();
              });
            });

            cont.querySelectorAll('[data-doc-multi]').forEach(cb => {
              cb.addEventListener('change', () => {
                const us = Number(cb.dataset.us);
                const idoc = Number(cb.value);
                if (!state.selectedDocs[us]) state.selectedDocs[us] = {
                  OTRO: []
                };
                const arr = state.selectedDocs[us].OTRO || [];
                const idx = arr.indexOf(idoc);
                if (cb.checked && idx < 0) arr.push(idoc);
                if (!cb.checked && idx >= 0) arr.splice(idx, 1);
                state.selectedDocs[us].OTRO = arr;
              });
            });
          }

          function renderDocSelect(usId, tipo, items, required) {
            const selected =
              (state.selectedDocs[usId] && state.selectedDocs[usId][tipo]) ?
              Number(state.selectedDocs[usId][tipo]) :
              0;

            const opts = items.map(d => {
              const id = Number(d.idocId);
              const isSel = (selected === id) ? 'selected' : '';
              const ver = (Number(d.verificado) === 1) ? ' · verificado' : '';
              return `<option value="${id}" ${isSel}>${esc(d.label || d.tipo)}${ver}</option>`;
            }).join('');

            const hint = items.length ?
              '' :
              `<div class="small-muted mt-1">No hay documentos cargados en catálogo.</div>`;

            return `
    <select class="form-select mt-2" data-doc-select="1" data-us="${usId}" data-tipo="${esc(tipo)}">
      <option value="">${required ? 'Selecciona una credencial…' : '— Ninguno —'}</option>
                ${opts}
              </select>
              ${hint}
            `;
          }

          function renderDocMulti(usId, items) {
            if (!items.length) return `<div class="small-muted mt-2">—</div>`;

            const selected =
              (state.selectedDocs[usId] && Array.isArray(state.selectedDocs[usId].OTRO)) ?
              state.selectedDocs[usId].OTRO : [];

            return `
    <div class="mt-2 d-grid gap-2">
      ${items.map(d => {
        const id = Number(d.idocId);
        const checked = selected.includes(id) ? 'checked' : '';
        const ver = (Number(d.verificado) === 1)
          ? '<span class="badge text-bg-success ms-auto">verificado</span>'
          : '';
        return `
          <label class="d-flex align-items-center gap-2">
            <input type="checkbox" class="form-check-input m-0" data-doc-multi="1" data-us="${usId}" value="${id}" ${checked}>
            <span>${esc(d.label || 'Documento')}</span>
            ${ver}
          </label>
        `;
      }).join('')}
    </div>
  `;
          }

          function renderVehiculos() {
            const cont = document.getElementById('vehiculosContainer');
            const selected = getSelectedIngIds();

            if (!selected.length) {
              cont.innerHTML = '<div class="text-muted">Selecciona ingeniero(s) primero.</div>';
              return;
            }

            const vehs = (state.catalogos && Array.isArray(state.catalogos.vehiculos)) ? state.catalogos.vehiculos : [];

            cont.innerHTML = selected.map(usIdIng => {
              const e = (state.ingenierosDisponibles || []).find(x => Number(x.usIdIng) === Number(usIdIng));
              const nombre = e ? `${e.usNombre||''} ${e.usAPaterno||''}`.trim() : `Ingeniero #${usIdIng}`;

              const items = vehs.filter(v => Number(v.usIdIng) === Number(usIdIng));

              // selección (1 vehículo por ingeniero)
              const selectedVeh =
                (state.selectedVehiculos && state.selectedVehiculos[usIdIng]) ?
                Number(state.selectedVehiculos[usIdIng]) :
                0;

              const options = items.map(v => {
                const id = Number(v.viId);
                const isSel = (selectedVeh === id) ? 'selected' : '';
                const desc = `${v.placas||''} · ${(v.marca||'')} ${(v.modelo||'')}`.trim();
                return `<option value="${id}" ${isSel}>${esc(desc || ('Vehículo #' + id))}</option>`;
              }).join('');

              return `
                <div class="border rounded-4 p-3 mb-3 bg-white">
                  <div class="d-flex justify-content-between align-items-center">
                    <div class="fw-semibold">${esc(nombre)}</div>
                    <div class="text-muted small">Vehículos: ${items.length}</div>
                  </div>

                  <select class="form-select mt-2" data-veh-select="1" data-us="${usIdIng}">
                    <option value="">— Ninguno —</option>
                    ${options}
                  </select>

                  ${items.length ? '' : '<div class="small-muted mt-1">No hay vehículos cargados en catálogo.</div>'}
                </div>
              `;
            }).join('');

            // handler selección
            cont.querySelectorAll('[data-veh-select="1"]').forEach(sel => {
              sel.addEventListener('change', () => {
                const usId = Number(sel.dataset.us);
                const viId = Number(sel.value || 0);
                state.selectedVehiculos = state.selectedVehiculos || {};
                state.selectedVehiculos[usId] = viId || 0;
              });
            });
          }

          function renderEquipos() {
            const cont = document.getElementById('equiposContainer');
            const selected = getSelectedIngIds();

            if (!selected.length) {
              cont.innerHTML = '<div class="text-muted">Selecciona ingeniero(s) primero.</div>';
              return;
            }

            const equipos = (state.catalogos && Array.isArray(state.catalogos.equipos)) ? state.catalogos.equipos : [];

            state.selectedEquipos = state.selectedEquipos || {}; // { usIdIng: { ieId: cantidad } }

            cont.innerHTML = selected.map(usIdIng => {
              const e = (state.ingenierosDisponibles || []).find(x => Number(x.usIdIng) === Number(usIdIng));
              const nombre = e ? `${e.usNombre||''} ${e.usAPaterno||''}`.trim() : `Ingeniero #${usIdIng}`;

              const items = equipos.filter(eq => Number(eq.usIdIng) === Number(usIdIng));
              const selMap = state.selectedEquipos[usIdIng] || {};

              const rows = items.map(eq => {
                const ieId = Number(eq.ieId);
                const checked = selMap[ieId] ? 'checked' : '';
                const qty = selMap[ieId] ? Number(selMap[ieId]) : 1;

                const title = `${eq.ieTipo || 'Equipo'} · ${(eq.ieMarca||'')} ${(eq.ieModelo||'')}`.replace(/\s+/g, ' ').trim();
                const sub = (eq.ieSerie || '').trim();

                return `
        <div class="d-flex align-items-center gap-2 border rounded-3 p-2">
          <input type="checkbox" class="form-check-input m-0" data-eq-check="1" data-us="${usIdIng}" data-ie="${ieId}" ${checked}>
          <div class="flex-grow-1">
            <div class="fw-semibold">${esc(title || ('Equipo #' + ieId))}</div>
            ${sub ? `<div class="small-muted">Serie: ${esc(sub)}</div>` : `<div class="small-muted">—</div>`}
          </div>
          <div style="width:90px">
            <input type="number" min="1" class="form-control form-control-sm" data-eq-qty="1"
              data-us="${usIdIng}" data-ie="${ieId}" value="${qty}" ${checked ? '' : 'disabled'}>
            <div class="form-text text-center m-0">Cantidad</div>
          </div>
        </div>
      `;
              }).join('');

              return `
      <div class="border rounded-4 p-3 mb-3 bg-white">
        <div class="d-flex justify-content-between align-items-center">
          <div class="fw-semibold">${esc(nombre)}</div>
          <div class="text-muted small">Equipos: ${items.length}</div>
        </div>

        <div class="d-grid gap-2 mt-2">
          ${rows || '<div class="small-muted">No hay equipos cargados en catálogo.</div>'}
        </div>
      </div>
    `;
            }).join('');

            // handlers checkbox
            cont.querySelectorAll('[data-eq-check="1"]').forEach(chk => {
              chk.addEventListener('change', () => {
                const usId = Number(chk.dataset.us);
                const ieId = Number(chk.dataset.ie);

                state.selectedEquipos[usId] = state.selectedEquipos[usId] || {};

                const qtyInput = cont.querySelector(`[data-eq-qty="1"][data-us="${usId}"][data-ie="${ieId}"]`);

                if (chk.checked) {
                  state.selectedEquipos[usId][ieId] = Number(qtyInput?.value || 1);
                  if (qtyInput) qtyInput.disabled = false;
                } else {
                  delete state.selectedEquipos[usId][ieId];
                  if (qtyInput) qtyInput.disabled = true;
                }
              });
            });

            // handlers qty
            cont.querySelectorAll('[data-eq-qty="1"]').forEach(inp => {
              inp.addEventListener('input', () => {
                const usId = Number(inp.dataset.us);
                const ieId = Number(inp.dataset.ie);
                const val = Math.max(1, Number(inp.value || 1));

                state.selectedEquipos[usId] = state.selectedEquipos[usId] || {};
                if (state.selectedEquipos[usId][ieId] != null) {
                  state.selectedEquipos[usId][ieId] = val;
                }
              });
            });
          }

          function renderTools() {
            const cont = document.getElementById('toolsContainer');

            if (!state.selectedIng.length) {
              cont.innerHTML = `<div class="text-muted">Selecciona ingeniero(s) para ver catálogo.</div>`;
              return;
            }

            const tools = (state.catalogos.herramientas || []).filter(v => state.selectedIng.includes(Number(v.usIdIng)));
            const byIng = groupBy(tools, v => Number(v.usIdIng));

            // epp/vestimenta (si vienen)
            const epp = (state.catalogos.epp || []);
            const vest = (state.catalogos.vestimenta || []);

            let html = '';
            state.selectedIng.forEach(usId => {
              const e = state.allEngineers.find(x => Number(x.usIdIng) === usId);
              const name = e ? `${e.usNombre||''} ${e.usAPaterno||''} ${e.usAMaterno||''}` : `Ingeniero #${usId}`;
              const list = byIng.get(usId) || [];
              if (!state.selectedTools[usId]) state.selectedTools[usId] = new Set();

              const eppRow = epp.find(x => Number(x.usIdIng) === usId) || null;
              const vestRow = vest.find(x => Number(x.usIdIng) === usId) || null;

              html += `
      <div class="border rounded-4 p-3 mb-3 bg-white">
        <div class="fw-semibold mb-2">${esc(name)}</div>

        <div class="row g-2">
          <div class="col-12 col-lg-6">
            <div class="fw-semibold">Herramientas</div>
            <div class="mt-2 d-grid gap-2">
              ${list.map(x=>{
                const id = Number(x.ihtId);
                const checked = state.selectedTools[usId].has(id) ? 'checked' : '';
                const label = `${x.nombre||'Herramienta'}${x.detalle?(' · '+x.detalle):''}`;
                return `
                  <label class="d-flex align-items-center gap-2">
                    <input type="checkbox" class="form-check-input m-0" data-tool="1" data-us="${usId}" value="${id}" ${checked}>
                    <span>${esc(label)}</span>
                  </label>
                `;
              }).join('')}
              ${list.length ? '' : `<div class="small-muted">—</div>`}
            </div>
          </div>

          <div class="col-12 col-lg-6">
            <div class="fw-semibold">EPP / Vestimenta</div>
            <div class="small-muted mt-1">Default: mezclilla · polo · botas</div>

            <div class="mt-2">
              <div class="small-muted">EPP (catálogo)</div>
              <div class="d-flex flex-wrap gap-2 mt-1">
                <span class="badge text-bg-light border">Casco: ${eppRow ? (Number(eppRow.casco||0)===1?'Sí':'No') : '—'}</span>
                <span class="badge text-bg-light border">Chaleco: ${eppRow ? (Number(eppRow.chaleco||0)===1?'Sí':'No') : '—'}</span>
                <span class="badge text-bg-light border">Botas: ${eppRow ? (Number(eppRow.botas||0)===1?'Sí':'No') : '—'}</span>
              </div>

              <div class="small-muted mt-2">Vestimenta (catálogo)</div>
              <div class="d-flex flex-wrap gap-2 mt-1">
                <span class="badge text-bg-light border">${esc((vestRow && vestRow.pantalon) ? vestRow.pantalon : 'Mezclilla')}</span>
                <span class="badge text-bg-light border">${esc((vestRow && vestRow.camisa) ? vestRow.camisa : 'Camisa/Polo')}</span>
                <span class="badge text-bg-light border">${esc((vestRow && vestRow.calzado) ? vestRow.calzado : 'Botas/Zapatos')}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
            });

            cont.innerHTML = html;

            cont.querySelectorAll('[data-tool]').forEach(cb => {
              cb.addEventListener('change', () => {
                const us = Number(cb.dataset.us);
                const id = Number(cb.value);
                if (!state.selectedTools[us]) state.selectedTools[us] = new Set();
                if (cb.checked) state.selectedTools[us].add(id);
                else state.selectedTools[us].delete(id);
              });
            });
          }

          function renderPiezas() {
            const tb = document.getElementById('piezasTbody');
            if (!state.piezas.length) {
              tb.innerHTML = `<tr><td colspan="5" class="text-muted">Sin piezas.</td></tr>`;
              return;
            }
            tb.innerHTML = state.piezas.map((p, idx) => `
    <tr>
      <td><input class="form-control form-control-sm" value="${esc(p.tipo_pieza||'')}" data-pz="tipo" data-i="${idx}"></td>
      <td><input class="form-control form-control-sm" value="${esc(p.partNumber||'')}" data-pz="pn" data-i="${idx}"></td>
      <td><input class="form-control form-control-sm" value="${esc(p.serialNumber||'')}" data-pz="sn" data-i="${idx}"></td>
      <td><input class="form-control form-control-sm" value="${esc(p.notas||'')}" data-pz="nt" data-i="${idx}"></td>
      <td>
        <button class="btn btn-sm btn-outline-danger" data-pz-del="${idx}" title="Quitar">
          <i class="bi bi-x-lg"></i>
        </button>
      </td>
    </tr>
  `).join('');

            tb.querySelectorAll('[data-pz]').forEach(inp => {
              inp.addEventListener('input', () => {
                const i = Number(inp.dataset.i);
                const k = inp.dataset.pz;
                if (!state.piezas[i]) return;
                if (k === 'tipo') state.piezas[i].tipo_pieza = inp.value;
                if (k === 'pn') state.piezas[i].partNumber = inp.value;
                if (k === 'sn') state.piezas[i].serialNumber = inp.value;
                if (k === 'nt') state.piezas[i].notas = inp.value;
              });
            });

            tb.querySelectorAll('[data-pz-del]').forEach(btn => {
              btn.addEventListener('click', () => {
                const i = Number(btn.dataset.pzDel);
                state.piezas.splice(i, 1);
                renderPiezas();
              });
            });
          }

          document.getElementById('btnAddPieza').addEventListener('click', () => {
            if (!state.piezas.length) {
              state.piezas = [];
            }
            state.piezas.push({
              tipo_pieza: '',
              partNumber: '',
              serialNumber: '',
              notas: '',
              invId: null
            });
            renderPiezas();
          });

          document.getElementById('vpSearchIng').addEventListener('input', renderEngineers);

          document.getElementById('vpNotasAcceso').addEventListener('input', (e) => {
            state.notas = e.target.value || '';
          });

          document.getElementById('btnGuardarPaquete').onclick = async function() {

            const selected = getSelectedIngIds();
            if (selected.length === 0) {
              alert('Selecciona al menos un ingeniero.');
              return;
            }

            // ====== PIEZAS payload (como lo traías) ======
            const piezasPayload = (state.piezas || [])
              .map(p => ({
                tipo_pieza: (p.tipo_pieza || '').trim(),
                partNumber: (p.partNumber || '').trim(),
                serialNumber: (p.serialNumber || '').trim(),
                notas: (p.notas || '').trim(),
                invId: (p.invId ?? null)
              }))
              // opcional: evita guardar filas totalmente vacías
              .filter(p => p.tipo_pieza || p.partNumber || p.serialNumber || p.notas || p.invId);
            console.log('Piezas payload:', piezasPayload);

            // ====== INGENIEROS payload ======
            const ingenierosPayload = selected.map((id, idx) => ({
              usIdIng: Number(id),
              rol: idx === 0 ? 'principal' : 'apoyo'
            }));

            // ====== DOCS payload (lo que ya manejas en selects) ======
            // state.selectedDocs debe existir del módulo Documentos:
            // state.selectedDocs[usIdIng].credencial_trabajo / INE / NSS / OTRO
            const docsPayload = [];
            if (state.selectedDocs) {
              Object.keys(state.selectedDocs).forEach(usId => {
                const byTipo = state.selectedDocs[usId] || {};

                // single selects
                ['credencial_trabajo', 'INE', 'NSS'].forEach(tipo => {
                  const idocId = Number(byTipo[tipo] || 0);
                  if (idocId) {
                    docsPayload.push({
                      usIdIng: Number(usId),
                      tipo,
                      idocId
                    });
                  }
                });

                // multi otros
                if (Array.isArray(byTipo.OTRO)) {
                  byTipo.OTRO.forEach(id => {
                    const idocId = Number(id || 0);
                    if (idocId) {
                      docsPayload.push({
                        usIdIng: Number(usId),
                        tipo: 'OTRO',
                        idocId
                      });
                    }
                  });
                }
              });
            }

            // ====== VEHICULOS payload (1 por ing, opcional) ======
            const vehiculosPayload = [];
            if (state.selectedVehiculos) {
              Object.keys(state.selectedVehiculos).forEach(usId => {
                const viId = Number(state.selectedVehiculos[usId] || 0);
                if (viId) {
                  vehiculosPayload.push({
                    usIdIng: Number(usId),
                    viId
                  });
                }
              });
            }

            // ====== EQUIPOS payload (MULTI + cantidad) ======
            const equiposPayload = [];
            const selEq = state.selectedEquipos || {}; // { usIdIng: { ieId: cantidad } }

            Object.keys(selEq).forEach(usId => {
              const map = selEq[usId] || {};
              Object.keys(map).forEach(ieId => {
                const cantidad = Math.max(1, Number(map[ieId] || 1));
                equiposPayload.push({
                  usIdIng: Number(usId),
                  ieId: Number(ieId),
                  cantidad
                });
              });
            });


            // ====== HERRAMIENTAS payload (multi) ======
            const herramientasPayload = [];
            const selTools = state.selectedTools || {}; // { usIdIng: Set(ihtId) }

            Object.keys(selTools).forEach(usId => {
              const set = selTools[usId];
              if (!set) return;
              const arr = Array.from(set);
              arr.forEach(ihtId => {
                herramientasPayload.push({
                  usIdIng: Number(usId),
                  ihtId: Number(ihtId)
                });
              });
            });
            // ====== payload FINAL ======
            const payload = {
              tiId,
              ingenieros: ingenierosPayload,
              docs: docsPayload,
              vehiculos: vehiculosPayload,
              equipos: equiposPayload,
              herramientas: herramientasPayload,
              piezas: piezasPayload,
              notas_acceso: document.getElementById('vpNotasAcceso')?.value || ''
            };

            const r = await fetch(`../api/visita_paquete_save.php`, {
              method: 'POST',
              credentials: 'include',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
              },
              body: JSON.stringify({
                ...payload,
                csrf_token: csrfToken
              })
            });

            const j = await r.json().catch(() => null);
            if (!j || !j.success) {
              alert((j && j.error) ? j.error : 'Error');
              return;
            }

            // alert('Paquete guardado correctamente.');
            // window.close();
          };

          let vpModalDoc;

          function vpOpenAddDoc() {
            const selected = getSelectedIngIds();

            if (selected.length === 0) {
              alert('Selecciona al menos un ingeniero.');
              return;
            }

            // llenar select con seleccionados
            const sel = document.getElementById('vpDocIng');
            sel.innerHTML = '';

            selected.forEach(usId => {
              const id = Number(usId);
              const e =
                (state.ingenierosDisponibles || state.allEngineers || []).find(x => Number(x.usIdIng) === id);

              const name = e ?
                `${e.usNombre||''} ${e.usAPaterno||''} ${e.usAMaterno||''}`.trim() :
                `#${id}`;

              const opt = document.createElement('option');
              opt.value = String(id);
              opt.textContent = name;
              sel.appendChild(opt);
            });

            // defaults
            document.getElementById('vpDocTipo').value = 'credencial_trabajo';
            document.getElementById('vpDocLabel').value = 'Credencial de trabajo';
            document.getElementById('vpDocFile').value = '';
            document.getElementById('vpDocErr').classList.add('d-none');

            vpModalDoc = vpModalDoc || new bootstrap.Modal(document.getElementById('vpModalDoc'));
            vpModalDoc.show();
          }

          async function vpSaveDoc() {
            const err = document.getElementById('vpDocErr');
            err.classList.add('d-none');

            const usIdIng = Number(document.getElementById('vpDocIng').value || 0);
            const tipo = document.getElementById('vpDocTipo').value;
            let label = (document.getElementById('vpDocLabel').value || '').trim();
            const fileEl = document.getElementById('vpDocFile');
            const file = fileEl.files && fileEl.files[0] ? fileEl.files[0] : null;

            if (!usIdIng) {
              err.textContent = 'Ingeniero inválido.';
              err.classList.remove('d-none');
              return;
            }
            if (!file) {
              err.textContent = 'Selecciona un PDF.';
              err.classList.remove('d-none');
              return;
            }
            if (file.type !== 'application/pdf') {
              err.textContent = 'El archivo debe ser PDF.';
              err.classList.remove('d-none');
              return;
            }

            if (!label) {
              label = (tipo === 'credencial_trabajo') ? 'Credencial de trabajo' : (tipo === 'INE' ? 'INE' : (tipo === 'NSS' ? 'NSS' : 'Documento'));
            }

            const fd = new FormData();
            fd.append('usIdIng', String(usIdIng));
            fd.append('tipo', tipo);
            fd.append('label', label);
            fd.append('file', file);

            const btn = document.getElementById('vpDocSave');
            btn.disabled = true;

            try {
              const selected = getSelectedIngIds(); // la función que ya hicimos
              const selParam = selected.length ? `&sel=${encodeURIComponent(selected.join(','))}` : '';

              const r = await fetch('api/ingeniero_documento_add.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                  'X-CSRF-Token': csrfToken
                },
                body: fd
              });
              const j = await r.json().catch(() => null);
              if (!j || j.success === false) throw new Error((j && j.error) ? j.error : 'No se pudo guardar');

              // refresca paquete (para que ya aparezca en catálogo y se pueda seleccionar)
              await load(); // o tu función load()

              vpModalDoc.hide();
            } catch (e) {
              err.textContent = e.message || String(e);
              err.classList.remove('d-none');
            } finally {
              btn.disabled = false;
            }
          }
          let vpModalVeh;

          function vpOpenAddVeh() {
            const selected = getSelectedIngIds();
            if (!selected.length) {
              alert('Selecciona al menos un ingeniero.');
              return;
            }

            const sel = document.getElementById('vpVehIng');
            sel.innerHTML = '';
            selected.forEach(usId => {
              const e = (state.ingenierosDisponibles || []).find(x => Number(x.usIdIng) === Number(usId));
              const name = e ? `${e.usNombre||''} ${e.usAPaterno||''} ${e.usAMaterno||''}`.trim() : `#${usId}`;
              const opt = document.createElement('option');
              opt.value = String(usId);
              opt.textContent = name;
              sel.appendChild(opt);
            });

            document.getElementById('vpVehPlacas').value = '';
            document.getElementById('vpVehMarca').value = '';
            document.getElementById('vpVehModelo').value = '';
            document.getElementById('vpVehColor').value = '';
            document.getElementById('vpVehAnio').value = '';
            document.getElementById('vpVehErr').classList.add('d-none');

            vpModalVeh = vpModalVeh || new bootstrap.Modal(document.getElementById('vpModalVeh'));
            vpModalVeh.show();
          }

          async function vpSaveVeh() {
            const err = document.getElementById('vpVehErr');
            err.classList.add('d-none');

            const usIdIng = Number(document.getElementById('vpVehIng').value || 0);
            const placas = (document.getElementById('vpVehPlacas').value || '').trim();
            const marca = (document.getElementById('vpVehMarca').value || '').trim();
            const modelo = (document.getElementById('vpVehModelo').value || '').trim();
            const color = (document.getElementById('vpVehColor').value || '').trim();
            const anio = (document.getElementById('vpVehAnio').value || '').trim();

            if (!usIdIng) {
              err.textContent = 'Ingeniero inválido.';
              err.classList.remove('d-none');
              return;
            }
            if (!placas) {
              err.textContent = 'Placas son obligatorias.';
              err.classList.remove('d-none');
              return;
            }

            const btn = document.getElementById('vpVehSave');
            btn.disabled = true;

            try {
              const r = await fetch('api/ingeniero_vehiculo_add.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                  usIdIng,
                  placas,
                  marca,
                  modelo,
                  color,
                  anio
                })
              });

              const j = await r.json().catch(() => null);
              if (!j || j.success === false) throw new Error((j && j.error) ? j.error : 'No se pudo guardar');

              // refrescar catálogos (para que aparezca como opción)
              if (typeof refreshCatalogosOnly === 'function') await refreshCatalogosOnly();
              else if (typeof load === 'function') await load();
              else if (typeof loadData === 'function') await loadData();

              vpModalVeh.hide();
            } catch (e) {
              err.textContent = e.message || String(e);
              err.classList.remove('d-none');
            } finally {
              btn.disabled = false;
            }
          }

          // handlers
          document.getElementById('vpBtnAddVeh').addEventListener('click', vpOpenAddVeh);
          document.getElementById('vpVehSave').addEventListener('click', vpSaveVeh);
          // handlers
          document.getElementById('vpBtnAddDoc').addEventListener('click', vpOpenAddDoc);
          document.getElementById('vpDocSave').addEventListener('click', vpSaveDoc);

          // habilita botón cuando ya hay ingeniero(s)
          function getSelectedIngIds() {
            // soporta ambos nombres para no romper
            if (Array.isArray(state.selectedIngenieros)) return state.selectedIngenieros;
            if (Array.isArray(state.selectedIng)) return state.selectedIng;
            return [];
          }

          let vpModalEq;

          function vpOpenAddEq() {
            const selected = getSelectedIngIds();
            if (!selected.length) {
              alert('Selecciona al menos un ingeniero.');
              return;
            }

            const sel = document.getElementById('vpEqIng');
            sel.innerHTML = '';
            selected.forEach(usId => {
              const e = (state.ingenierosDisponibles || []).find(x => Number(x.usIdIng) === Number(usId));
              const name = e ? `${e.usNombre||''} ${e.usAPaterno||''} ${e.usAMaterno||''}`.trim() : `#${usId}`;
              const opt = document.createElement('option');
              opt.value = String(usId);
              opt.textContent = name;
              sel.appendChild(opt);
            });

            document.getElementById('vpEqTipo').value = '';
            document.getElementById('vpEqMarca').value = '';
            document.getElementById('vpEqModelo').value = '';
            document.getElementById('vpEqSerie').value = '';
            document.getElementById('vpEqDesc').value = '';
            document.getElementById('vpEqErr').classList.add('d-none');

            vpModalEq = vpModalEq || new bootstrap.Modal(document.getElementById('vpModalEq'));
            vpModalEq.show();
          }

          async function vpSaveEq() {
            const err = document.getElementById('vpEqErr');
            err.classList.add('d-none');

            const usIdIng = Number(document.getElementById('vpEqIng').value || 0);
            const ieTipo = (document.getElementById('vpEqTipo').value || '').trim();
            const ieMarca = (document.getElementById('vpEqMarca').value || '').trim();
            const ieModelo = (document.getElementById('vpEqModelo').value || '').trim();
            const ieSerie = (document.getElementById('vpEqSerie').value || '').trim();
            const ieDescripcion = (document.getElementById('vpEqDesc').value || '').trim();

            if (!usIdIng) {
              err.textContent = 'Ingeniero inválido.';
              err.classList.remove('d-none');
              return;
            }
            if (!ieTipo) {
              err.textContent = 'Tipo es obligatorio.';
              err.classList.remove('d-none');
              return;
            }

            const btn = document.getElementById('vpEqSave');
            btn.disabled = true;

            try {
              const r = await fetch('api/ingeniero_equipo_add.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                  usIdIng,
                  ieTipo,
                  ieMarca,
                  ieModelo,
                  ieSerie,
                  ieDescripcion
                })
              });

              const j = await r.json().catch(() => null);
              if (!j || j.success === false) throw new Error((j && j.error) ? j.error : 'No se pudo guardar');

              // refresca catálogos y repinta
              if (typeof refreshCatalogosOnly === 'function') await refreshCatalogosOnly();
              else if (typeof load === 'function') await load();

              renderEquipos();
              vpModalEq.hide();
            } catch (e) {
              err.textContent = e.message || String(e);
              err.classList.remove('d-none');
            } finally {
              btn.disabled = false;
            }
          }

          document.getElementById('vpBtnAddEq').addEventListener('click', vpOpenAddEq);
          document.getElementById('vpEqSave').addEventListener('click', vpSaveEq);

          function vpSyncEppBtn() {
            const btn = document.getElementById('vpBtnEpp');
            if (!btn) return;
            btn.disabled = (getSelectedIngIds().length === 0);
          }

          let vpModalEpp;

          function vpOpenEpp() {
            const selected = getSelectedIngIds();
            if (!selected.length) {
              alert('Selecciona al menos un ingeniero.');
              return;
            }

            const sel = document.getElementById('vpEppIng');
            sel.innerHTML = '';
            selected.forEach(usId => {
              const e = (state.allEngineers || state.ingenierosDisponibles || []).find(x => Number(x.usIdIng) === Number(usId));
              const name = e ? `${e.usNombre||''} ${e.usAPaterno||''} ${e.usAMaterno||''}`.trim() : `#${usId}`;
              const opt = document.createElement('option');
              opt.value = String(usId);
              opt.textContent = name;
              sel.appendChild(opt);
            });

            // precarga EPP del primer ingeniero
            vpLoadEppFromCatalog(Number(sel.value || 0));

            document.getElementById('vpEppErr').classList.add('d-none');

            vpModalEpp = vpModalEpp || new bootstrap.Modal(document.getElementById('vpModalEpp'));
            vpModalEpp.show();
          }

          function vpLoadEppFromCatalog(usIdIng) {
            const row = (state.catalogos?.epp || []).find(x => Number(x.usIdIng) === Number(usIdIng)) || null;

            document.getElementById('vpEppCasco').checked = row ? Number(row.casco || 0) === 1 : false;
            document.getElementById('vpEppChaleco').checked = row ? Number(row.chaleco || 0) === 1 : false;
            document.getElementById('vpEppBotas').checked = row ? Number(row.botas || 0) === 1 : false;
            document.getElementById('vpEppNotas').value = row ? (row.notas || '') : '';
          }

          async function vpSaveEpp() {
            const err = document.getElementById('vpEppErr');
            err.classList.add('d-none');

            const usIdIng = Number(document.getElementById('vpEppIng').value || 0);
            if (!usIdIng) {
              err.textContent = 'Ingeniero inválido.';
              err.classList.remove('d-none');
              return;
            }

            const casco = document.getElementById('vpEppCasco').checked ? 1 : 0;
            const chaleco = document.getElementById('vpEppChaleco').checked ? 1 : 0;
            const botas = document.getElementById('vpEppBotas').checked ? 1 : 0;
            const notas = (document.getElementById('vpEppNotas').value || '').trim();

            const btn = document.getElementById('vpEppSave');
            btn.disabled = true;

            try {
              const r = await fetch('api/ingeniero_epp_save.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                  usIdIng,
                  casco,
                  chaleco,
                  botas,
                  notas
                })
              });
              const j = await r.json().catch(() => null);
              if (!j || j.success === false) throw new Error((j && j.error) ? j.error : 'No se pudo guardar');

              // refresca catálogo y repinta tools (para que ya se vea)
              if (typeof refreshCatalogosOnly === 'function') await refreshCatalogosOnly();
              else await load();

              renderTools();
              vpModalEpp.hide();
            } catch (e) {
              err.textContent = e.message || String(e);
              err.classList.remove('d-none');
            } finally {
              btn.disabled = false;
            }
          }

          // binds
          document.getElementById('vpBtnEpp').addEventListener('click', vpOpenEpp);
          document.getElementById('vpEppSave').addEventListener('click', vpSaveEpp);

          document.getElementById('vpEppIng').addEventListener('change', (e) => {
            vpLoadEppFromCatalog(Number(e.target.value || 0));
          });

          function vpSyncAddToolBtn() {
            const btn = document.getElementById('vpBtnAddTool');
            if (!btn) return;
            btn.disabled = (getSelectedIngIds().length === 0);
          }

          let vpModalTool;

          function vpOpenAddTool() {
            const selected = getSelectedIngIds();
            if (!selected.length) {
              alert('Selecciona al menos un ingeniero.');
              return;
            }

            const sel = document.getElementById('vpToolIng');
            sel.innerHTML = '';
            selected.forEach(usId => {
              const e = (state.allEngineers || state.ingenierosDisponibles || []).find(x => Number(x.usIdIng) === Number(usId));
              const name = e ? `${e.usNombre||''} ${e.usAPaterno||''} ${e.usAMaterno||''}`.trim() : `#${usId}`;
              const opt = document.createElement('option');
              opt.value = String(usId);
              opt.textContent = name;
              sel.appendChild(opt);
            });

            document.getElementById('vpToolNombre').value = '';
            document.getElementById('vpToolDetalle').value = '';
            document.getElementById('vpToolErr').classList.add('d-none');

            vpModalTool = vpModalTool || new bootstrap.Modal(document.getElementById('vpModalTool'));
            vpModalTool.show();
          }

          async function vpSaveTool() {
            const err = document.getElementById('vpToolErr');
            err.classList.add('d-none');

            const usIdIng = Number(document.getElementById('vpToolIng').value || 0);
            const nombre = (document.getElementById('vpToolNombre').value || '').trim();
            const detalle = (document.getElementById('vpToolDetalle').value || '').trim();

            if (!usIdIng) {
              err.textContent = 'Ingeniero inválido.';
              err.classList.remove('d-none');
              return;
            }
            if (!nombre) {
              err.textContent = 'Nombre es obligatorio.';
              err.classList.remove('d-none');
              return;
            }

            const btn = document.getElementById('vpToolSave');
            btn.disabled = true;

            try {
              const r = await fetch('api/ingeniero_herramienta_add.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                  usIdIng,
                  nombre,
                  detalle
                })
              });

              const j = await r.json().catch(() => null);
              if (!j || j.success === false) throw new Error((j && j.error) ? j.error : 'No se pudo guardar');

              // refresca catálogo y repinta tools
              if (typeof refreshCatalogosOnly === 'function') await refreshCatalogosOnly();
              else await load();

              renderTools();
              vpModalTool.hide();
            } catch (e) {
              err.textContent = e.message || String(e);
              err.classList.remove('d-none');
            } finally {
              btn.disabled = false;
            }
          }

          // binds
          document.getElementById('vpBtnAddTool').addEventListener('click', vpOpenAddTool);
          document.getElementById('vpToolSave').addEventListener('click', vpSaveTool);



          function vpSyncAddEqBtn() {
            const btn = document.getElementById('vpBtnAddEq');
            if (!btn) return;
            btn.disabled = (getSelectedIngIds().length === 0);
          }

          function vpSyncAddDocBtn() {
            const btn = document.getElementById('vpBtnAddDoc');
            if (!btn) return;
            btn.disabled = (getSelectedIngIds().length === 0);
          }

          function vpSyncAddVehBtn() {
            const btn = document.getElementById('vpBtnAddVeh');
            if (!btn) return;
            btn.disabled = (getSelectedIngIds().length === 0);
          }
          load();
        </script>
      </main>
    </div>
  </div>

</body>

</html>