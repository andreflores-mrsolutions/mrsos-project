<?php

declare(strict_types=1);

$MR_ALLOWED_ROLES = ['MRSA', 'MRA', 'MRV']; // admin/ingeniero MR
require_once __DIR__ . '/../../php/admin_bootstrap.php';

$tiId = isset($_GET['tiId']) ? (int)$_GET['tiId'] : 0;
if ($tiId <= 0) {
  http_response_code(400);
  echo "tiId inválido";
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MR SOS | Hoja de Servicio</title>

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
      --mr-shadow: 0 10px 30px rgba(16, 24, 40, .08);
      --mr-radius: 16px;
      --mr-font: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
    }

    body {
      background: var(--mr-bg);
      color: var(--mr-text);
      font-family: var(--mr-font);
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Dark mode: NO negro total (más legible) */
    body.dark-mode {
      background: #0f172a;
      color: #e5e7eb;
    }

    .admin-topbar {
      background: rgba(255, 255, 255, .90);
      border-bottom: 1px solid var(--mr-border);
      backdrop-filter: blur(10px);
    }

    body.dark-mode .admin-topbar {
      background: rgba(15, 23, 42, .72);
      border-bottom: 1px solid rgba(148, 163, 184, .18);
    }

    /* “Hoja” */
    .doc-wrap {
      padding: 16px;
      background: var(--mr-card);
      border: 1px solid var(--mr-border);
      border-radius: var(--mr-radius);
      box-shadow: var(--mr-shadow);
    }

    body.dark-mode .doc-wrap {
      background: rgba(255, 255, 255, .04);
      border-color: rgba(148, 163, 184, .18);
      box-shadow: none;
    }

    .muted {
      color: var(--mr-muted);
    }

    body.dark-mode .muted {
      color: rgba(226, 232, 240, .72);
    }

    .section-title {
      font-weight: 800;
      letter-spacing: .2px;
      color: var(--mr-text);
    }

    body.dark-mode .section-title {
      color: #e5e7eb;
    }

    .divider {
      height: 1px;
      background: rgba(15, 23, 42, .10);
    }

    body.dark-mode .divider {
      background: rgba(148, 163, 184, .18);
    }

    /* Inputs SIEMPRE legibles */
    label {
      font-weight: 700;
    }

    .form-control,
    .form-select,
    textarea {
      border-radius: 12px;
      border: 1px solid rgba(15, 23, 42, .14);
      background: #fff;
      color: var(--mr-text);
    }

    .form-control::placeholder,
    textarea::placeholder {
      color: rgba(15, 23, 42, .45);
    }

    .form-control:focus,
    .form-select:focus,
    textarea:focus {
      border-color: rgba(13, 110, 253, .55);
      box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .12);
    }

    body.dark-mode .form-control,
    body.dark-mode .form-select,
    body.dark-mode textarea {
      background: rgba(255, 255, 255, .06);
      border-color: rgba(148, 163, 184, .22);
      color: #e5e7eb;
    }

    body.dark-mode .form-control::placeholder,
    body.dark-mode textarea::placeholder {
      color: rgba(226, 232, 240, .60);
    }

    /* Badges/pills */
    .badge-soft {
      background: rgba(13, 110, 253, .10);
      border: 1px solid rgba(13, 110, 253, .22);
      color: #0b3d91;
    }

    body.dark-mode .badge-soft {
      background: rgba(13, 110, 253, .16);
      border-color: rgba(13, 110, 253, .28);
      color: #dbeafe;
    }
  </style>
</head>

<body class="<?php echo ($theme === 'dark') ? 'dark-mode' : ''; ?>">
  <?php admin_print_js_bootstrap(); ?>
  <script>
    window.__TI_ID = <?= (int)$tiId ?>;
  </script>

  <div class="container-fluid">
    <div class="row gx-0">

      <!-- SIDEBAR (tu base) -->
      <nav id="sidebar" class="col-12 col-md-3 col-lg-2 d-none d-lg-block p-3 mr-side">
        <div class="brand mb-3 px-2">
          <a class="navbar-brand" href="#">
            <img src="../../img/image.png" alt="Logo" class="rounded-pill" style="max-width: 120px;">
          </a>
        </div>

        <div class="section-title px-2">Operación</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
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

      <!-- OFFCANVAS SIDEBAR (tu base, corregido cierres) -->
      <div class="offcanvas offcanvas-start offcanvas-xl mr-side" tabindex="-1" id="offcanvasSidebar">
        <div class="p-3 d-flex align-items-center justify-content-between">
          <div class="brand">
            <a class="navbar-brand" href="#">
              <img src="../../img/image.png" alt="Logo" class="rounded-pill" style="max-width:120px;">
            </a>
          </div>
          <button type="button" class="btn btn-outline-light close-btn" data-bs-dismiss="offcanvas" aria-label="Close">
            <i class="bi bi-chevron-left"></i>
          </button>
        </div>

        <div class="p-3 pt-0">
          <div class="section-title px-2">Operación</div>
          <ul class="nav nav-pills flex-column gap-1">
            <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
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
      </div>

      <!-- MAIN -->
      <main class="col-12 col-lg-10 ms-auto">

        <!-- TOPBAR (tu base) -->
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

        <!-- CONTENT -->
        <div class="container py-3">

          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
              <div class="h4 mb-0">Hoja de Servicio</div>
              <div class="muted">Ticket <span class="badge badge-soft">tiId: <?= (int)$tiId ?></span></div>
            </div>
            <div class="d-flex gap-2">
              <a class="btn btn-outline-secondary" href="tickets.php"><i class="bi bi-arrow-left"></i> Volver</a>
              <a class="btn btn-outline-primary d-none" id="btnOpenLastPdf" target="_blank" href="#"><i class="bi bi-file-earmark-pdf"></i> Ver último PDF</a>
            </div>
          </div>

          <div class="doc-wrap">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
              <div>
                <div class="section-title">Captura (UI basada en el PDF)</div>
                <div class="muted">Todo visible/legible en claro y en oscuro ✅</div>
              </div>
              <div class="text-end">
                <div class="muted small">HS ID (preview)</div>
                <div class="fw-bold" id="hsPreview">HS-<?= (int)$tiId ?>-<?= date('YmdHis') ?></div>
              </div>
            </div>

            <div class="my-3 divider"></div>

            <!-- FORM -->
            <form id="hsForm" novalidate>
              <input type="hidden" name="tiId" value="<?= (int)$tiId ?>">

              <!-- Header -->
              <div class="row g-3">
                <div class="col-md-3">
                  <label>Fecha</label>
                  <input type="date" class="form-control" name="fecha" required>
                </div>
                <div class="col-md-3">
                  <label>No. de Caso</label>
                  <input type="text" class="form-control" name="no_caso" placeholder="Folio / Caso" required>
                </div>
                <div class="col-md-6">
                  <label>HS ID (auto)</label>
                  <input type="text" class="form-control" name="hs_folio_preview" readonly>
                </div>
              </div>

              <div class="my-4 divider"></div>

              <!-- Cliente -->
              <div class="section-title mb-2">Información del Cliente</div>
              <div class="row g-3">
                <div class="col-md-4">
                  <label>Contacto</label>
                  <input type="text" class="form-control" name="cliente_contacto" required>
                </div>
                <div class="col-md-4">
                  <label>Puesto / Área</label>
                  <input type="text" class="form-control" name="cliente_puesto_area">
                </div>
                <div class="col-md-4">
                  <label>Razón Social</label>
                  <input type="text" class="form-control" name="cliente_razon_social" required>
                </div>

                <div class="col-md-6">
                  <label>Dirección</label>
                  <input type="text" class="form-control" name="cliente_direccion">
                </div>
                <div class="col-md-3">
                  <label>Ciudad o Estado</label>
                  <input type="text" class="form-control" name="cliente_ciudad_estado">
                </div>
                <div class="col-md-3">
                  <label>Teléfono</label>
                  <input type="text" class="form-control" name="cliente_telefono">
                </div>

                <div class="col-md-3">
                  <label>Fax</label>
                  <input type="text" class="form-control" name="cliente_fax">
                </div>
                <div class="col-md-5">
                  <label>E-mail</label>
                  <input type="email" class="form-control" name="cliente_email">
                </div>

                <div class="col-md-4">
                  <label>Tipo</label>
                  <div class="row g-2 pt-1">
                    <?php
                    $tipos = [
                      'garantia' => 'Garantía',
                      'evaluacion' => 'Evaluación de equipo',
                      'proyecto' => 'Proyecto',
                      'mantenimiento' => 'Mantenimiento',
                      'reparacion' => 'Reparación',
                      'software_demo' => 'Software demo'
                    ];
                    foreach ($tipos as $k => $lbl) {
                      echo '<div class="col-6">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tipo_' . $k . '" value="1" id="tipo_' . $k . '">
                        <label class="form-check-label" for="tipo_' . $k . '">' . $lbl . '</label>
                      </div>
                    </div>';
                    }
                    ?>
                  </div>
                </div>
              </div>

              <div class="my-4 divider"></div>

              <!-- Equipo -->
              <div class="section-title mb-2">Datos del equipo</div>
              <div class="row g-3">
                <div class="col-md-4">
                  <label>Sistema Operativo</label>
                  <input type="text" class="form-control" name="equipo_so">
                </div>
                <div class="col-md-4">
                  <label>Software Respaldo</label>
                  <input type="text" class="form-control" name="equipo_software_respaldo">
                </div>
                <div class="col-md-4">
                  <label>Número Serie</label>
                  <input type="text" class="form-control" name="equipo_sn">
                </div>
                <div class="col-md-6">
                  <label>Modelo Unidad / Librería</label>
                  <input type="text" class="form-control" name="equipo_modelo_unidad">
                </div>
                <div class="col-md-6">
                  <label>Marca Unidad / Librería</label>
                  <input type="text" class="form-control" name="equipo_marca_unidad">
                </div>
              </div>

              <div class="my-4 divider"></div>

              <!-- Visita -->
              <div class="section-title mb-2">Primera Visita</div>
              <div class="row g-3">
                <div class="col-md-3">
                  <label>Fecha</label>
                  <input type="date" class="form-control" name="visita_fecha">
                </div>
                <div class="col-md-3">
                  <label>Hora</label>
                  <input type="time" class="form-control" name="visita_hora">
                </div>
                <div class="col-md-6">
                  <label>Ingeniero(s)</label>
                  <select class="form-select" name="ingenieros[]" id="ingenierosSelect" multiple required>
                    <!-- se llena por JS -->
                  </select>
                  <div class="muted small mt-1">Tip: puedes seleccionar más de uno.</div>
                </div>
              </div>

              <div class="my-4 divider"></div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label>Descripción del Problema</label>
                  <textarea class="form-control" name="problema" rows="6" required></textarea>
                </div>
                <div class="col-md-6">
                  <label>Actividades Realizadas</label>
                  <textarea class="form-control" name="actividades" rows="6" required></textarea>
                </div>
              </div>

              <div class="my-4 divider"></div>

              <!-- Status -->
              <div class="section-title mb-2">Estatus</div>
              <div class="row g-2">
                <?php
                $st = [
                  'cerrado' => 'Reporte Cerrado',
                  'pendiente' => 'Reporte Pendiente',
                  'cancelado' => 'Reporte Cancelado',
                  'reasignado' => 'Reasignado'
                ];
                foreach ($st as $k => $lbl) {
                  echo '<div class="col-md-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="status" value="' . $k . '" id="status_' . $k . '" ' . ($k === 'cerrado' ? 'checked' : '') . '>
                    <label class="form-check-label" for="status_' . $k . '">' . $lbl . '</label>
                  </div>
                </div>';
                }
                ?>
              </div>

              <div class="my-4 divider"></div>

              <!-- Resultado/Acciones -->
              <div class="section-title mb-2">Resultado / Acciones</div>
              <div class="row g-2">
                <?php
                $chk = [
                  'reemplazo_refaccion' => 'Reemplazo de Refacción',
                  'config_hw' => 'Configuración de HW',
                  'config_sw' => 'Configuración de SW',
                  'reinstalacion' => 'Reinstalación',
                  'reparacion_sitio' => 'Reparación en sitio',
                  'pendiente_partes' => 'Pendiente por partes',
                  'software_respaldo' => 'Software de respaldo',
                  'otros' => 'Otros'
                ];
                foreach ($chk as $k => $lbl) {
                  $checked = ($k === 'reemplazo_refaccion') ? 'checked' : '';
                  echo '<div class="col-md-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="res_' . $k . '" value="1" id="res_' . $k . '" ' . $checked . '>
                    <label class="form-check-label" for="res_' . $k . '">' . $lbl . '</label>
                  </div>
                </div>';
                }
                ?>
              </div>

              <div class="mt-3">
                <label>Comentarios Adicionales</label>
                <textarea class="form-control" name="comentarios" rows="4"></textarea>
              </div>

              <div class="my-4 divider"></div>

              <!-- Firmas -->
              <div class="my-4 divider"></div>

              <div class="section-title mb-2">Firmas</div>

              <!-- hidden donde se guarda la firma -->
              <input type="hidden" name="sig_ing_base64" id="sigIngBase64">
              <input type="hidden" name="sig_cli_base64" id="sigCliBase64">

              <div class="row g-3">
                <div class="col-md-6">
                  <label>Firma del Ingeniero (dibuja)</label>
                  <div class="border rounded-3 p-2 bg-white" style="border-color: rgba(15,23,42,.14)!important;">
                    <canvas id="sigIng" style="width:100%; height:140px; display:block;"></canvas>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                      <div class="muted small">Desde móvil o laptop</div>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearIng">
                        <i class="bi bi-eraser"></i> Limpiar
                      </button>
                    </div>
                  </div>
                </div>

                <div class="col-md-6">
                  <label>Firma del Cliente (dibuja)</label>
                  <div class="border rounded-3 p-2 bg-white" style="border-color: rgba(15,23,42,.14)!important;">
                    <canvas id="sigCli" style="width:100%; height:140px; display:block;"></canvas>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                      <div class="muted small">El cliente firma en tu dispositivo</div>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearCli">
                        <i class="bi bi-eraser"></i> Limpiar
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="my-4 divider"></div>

              <!-- Acciones -->
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="enviar_correo" value="1" id="enviar_correo">
                  <label class="form-check-label" for="enviar_correo">
                    Enviar por correo al contacto del ticket (stub)
                  </label>
                </div>
                <div class="d-flex gap-2 align-items-center mt-3">
                  <button id="btnGenerarPDF" class="btn btn-primary">
                    <i class="bi bi-file-earmark-pdf"></i> Generar PDF
                  </button>

                  <button id="btnContinuar" class="btn btn-success" disabled>
                    <i class="bi bi-arrow-right-circle"></i> Continuar
                  </button>

                  <div class="ms-auto d-flex align-items-center gap-2">
                    <label class="small text-muted m-0">Siguiente paso</label>
                    <select id="nextProceso" class="form-select form-select-sm" style="min-width:240px;">
                      <option value="encuesta satisfaccion" selected>Encuesta de satisfacción</option>
                      <option value="finalizado">Finalizado</option>
                      <option value="espera documentacion">Espera documentación</option>
                      <!-- agrega los que te interesen -->
                    </select>
                  </div>
                </div>

                <!-- hidden state -->
                <input type="hidden" id="hsId" value="">
                <div id="hsResult" class="mt-2 small"></div>
              </div>

              <div class="mt-3 muted small" id="uiResult"></div>
            </form>
          </div>
        </div>
      </main>

    </div>
  </div>


  <script>
    async function apiGet(url) {
      const r = await fetch(url, {
        credentials: 'include'
      });
      return r.json();
    }

    function clientePrefix(nombre) {
      if (!nombre) return 'UNK';
      return nombre.normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^A-Za-z]/g, "")
        .substring(0, 3).toUpperCase();
    }

    function setVal(name, v) {
      const el = document.querySelector(`[name="${name}"]`);
      if (!el) return;
      el.value = (v ?? '').toString();
    }


    function setCheck(idOrName, on) {
      // intenta por name o por id
      let el = document.querySelector(`[name="${idOrName}"]`);
      if (!el) el = document.getElementById(idOrName);
      if (!el) return;
      el.checked = !!on;
    }

    function pad2(n) {
      return String(n).padStart(2, '0');
    }

    function nowDate() {
      return new Date().toISOString().slice(0, 10);
    }

    function nowTime() {
      const d = new Date();
      return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
    }

    // --- SIGN PAD (simple, sin libs) ---
    function setupSignature(canvasId, hiddenId, clearBtnId) {
      const canvas = document.getElementById(canvasId);
      const hidden = document.getElementById(hiddenId);
      const btnClear = document.getElementById(clearBtnId);

      const ctx = canvas.getContext('2d');
      let drawing = false;
      let last = null;

      function resize() {
        // Mantener nitidez
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.floor(rect.width * ratio);
        canvas.height = Math.floor(rect.height * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111827';
        // no borramos para no perder firma en resize; si quieres, guardamos y redibujamos (opcional)
      }

      function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches && e.touches[0];
        const clientX = touch ? touch.clientX : e.clientX;
        const clientY = touch ? touch.clientY : e.clientY;
        return {
          x: clientX - rect.left,
          y: clientY - rect.top
        };
      }

      function start(e) {
        drawing = true;
        last = getPos(e);
        e.preventDefault();
      }

      function move(e) {
        if (!drawing) return;
        const p = getPos(e);
        ctx.beginPath();
        ctx.moveTo(last.x, last.y);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        last = p;
        e.preventDefault();
      }

      function end() {
        if (!drawing) return;
        drawing = false;
        last = null;
        // Guardar base64 (PNG)
        hidden.value = canvas.toDataURL('image/png');
      }

      function clear() {
        const rect = canvas.getBoundingClientRect();
        ctx.clearRect(0, 0, rect.width, rect.height);
        hidden.value = '';
      }

      window.addEventListener('resize', resize);
      resize();

      canvas.addEventListener('mousedown', start);
      canvas.addEventListener('mousemove', move);
      window.addEventListener('mouseup', end);

      canvas.addEventListener('touchstart', start, {
        passive: false
      });
      canvas.addEventListener('touchmove', move, {
        passive: false
      });
      canvas.addEventListener('touchend', end);

      btnClear.addEventListener('click', clear);

      return {
        canvas,
        hidden,
        clear
      };
    }

    // --- Fill engineers multi-select ---
    function fillEngineers(selectId, engineers, preselectUsId) {
      const sel = document.getElementById(selectId);
      sel.innerHTML = '';
      (engineers || []).forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.usId;
        opt.textContent = u.nombre;
        if (preselectUsId && String(u.usId) === String(preselectUsId)) opt.selected = true;
        sel.appendChild(opt);
      });
    }

    (async function init() {
      const tiId = window.__TI_ID;

      // Defaults “por regla”
      setVal('fecha', nowDate());
      setVal('visita_fecha', nowDate());
      setVal('visita_hora', nowTime());

      // Tipo: mantenimiento + reparación
      setCheck('tipo_mantenimiento', true);
      setCheck('tipo_reparacion', true);

      // Estatus: Reporte Cerrado default (radio)
      const stCerrado = document.getElementById('status_cerrado');
      if (stCerrado) stCerrado.checked = true;

      // Resultado: reemplazo refacción default
      setCheck('res_reemplazo_refaccion', true);

      // Descripción / actividades vacías
      setVal('problema', '');
      setVal('actividades', '');

      // Preview HS ID
      const stamp = new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14);
      setVal('hs_folio_preview', 'HS-' + tiId + '-' + stamp);
      document.getElementById('hsPreview').textContent = 'HS-' + tiId + '-' + stamp;

      // Firma pads
      setupSignature('sigIng', 'sigIngBase64', 'btnClearIng');
      setupSignature('sigCli', 'sigCliBase64', 'btnClearCli');

      // Traer contexto del ticket
      const ctx = await apiGet('api/hoja_servicio_get_context.php?tiId=' + encodeURIComponent(tiId));
      if (!ctx.success) {
        $('#uiResult').text('Error cargando contexto: ' + (ctx.error || ''));
        return;
      }

      const t = ctx.ticket || {};

      // No. de caso: ideal tu folio real
      // después de recibir ctx.ticket
      const clienteNombre = t.clNombre || t.cliente || '';
      const pref = clientePrefix(clienteNombre);

      // ENE-19

      setVal('no_caso', (t.noCaso || (pref + '-' + tiId)));


      /**
       * REGLAS DE CLIENTE:
       * - contacto/email/teléfono: del que levantó el caso (tiNombreContacto/tiCorreoContacto/tiNumeroContacto)
       * - razón social: empresa (clNombre)
       * - dirección: sede del ticket si aplica; si no, fallback (zona -> sede principal -> dirección cliente)
       *
       * Nota: hoy tu endpoint solo trae csDireccion/clDireccion. Para “zona/sede principal” vamos a:
       * - usar csDireccion si existe
       * - si no, clDireccion
       * Si luego agregas zona/sede principal al endpoint, ya está listo para extender.
       */
      setVal('cliente_contacto', t.contactoNombre || '');
      setVal('cliente_email', t.contactoCorreo || '');
      setVal('cliente_telefono', t.contactoTelefono || '');
      setVal('cliente_fax', ''); // ya no se usa

      setVal('cliente_razon_social', t.clNombre || t.cliente || '');

      const dir = (t.csDireccion && t.csDireccion.trim()) ?
        t.csDireccion :
        (t.zonaDireccion && t.zonaDireccion.trim()) ?
        t.zonaDireccion :
        (t.sedePrincipalDireccion && t.sedePrincipalDireccion.trim()) ?
        t.sedePrincipalDireccion :
        (t.clDireccion || '');

      setVal('cliente_direccion', dir);

      // Ciudad/estado: si no lo tienes, se puede dejar vacío o derivar de sede nombre
      setVal('cliente_ciudad_estado', t.ciudadEstado || (t.csNombre ? t.csNombre : ''));

      /**
       * REGLAS EQUIPO:
       * - SN: de la póliza del equipo (peSN)
       * - Modelo/Marca: del equipo + marca
       * - SO y Software respaldo: opcionales (vacío por default). SO solo si aplica.
       */
      setVal('equipo_sn', t.peSN || '');
      setVal('equipo_modelo_unidad', ((t.eqModelo || '') + (t.eqVersion ? (' ' + t.eqVersion) : '')).trim());
      setVal('equipo_marca_unidad', t.maNombre || '');

      setVal('equipo_so', t.peSO || ''); // si aplica, si no se queda vacío
      setVal('equipo_software_respaldo', ''); // opcional

      /**
       * PRIMERA VISITA:
       * - fecha/hora ya vienen con now()
       * - ingenieros: lista + preselección del asignado
       */
      // ctx.engineers debe venir del endpoint; si no existe, no rompe.
      fillEngineers('ingenierosSelect', ctx.engineers || [], t.usIdIng || null);

      // Botón “ver último PDF” si existe
      if (ctx.last?.downloadUrl) {
        const a = document.getElementById('btnOpenLastPdf');
        a.href = ctx.last.downloadUrl;
        a.classList.remove('d-none');
      }

      // Tema (si tu proyecto ya maneja theme por backend, aquí solo dejamos el botón)
      $('#btnTheme').on('click', function() {
        document.body.classList.toggle('dark-mode');
      });

      // Submit por ahora lo dejamos como estaba (luego conectamos create)
      $('#hsForm').on('submit', async function(e) {
        e.preventDefault();

        // Validación HTML5
        const form = this;
        if (!form.checkValidity()) {
          form.classList.add('was-validated');
          $('#uiResult').html('⚠️ Revisa campos obligatorios.');
          return;
        }

        // Construir JSON desde form
        const fd = new FormData(form);
        const payload = {};
        for (const [k, v] of fd.entries()) {
          if (k.endsWith('[]')) continue;
          payload[k] = v;
        }

        // Multi ingenieros
        const ings = $('#ingenierosSelect').val() || [];
        payload['ingenieros'] = ings.map(x => parseInt(x, 10)).filter(n => Number.isFinite(n));

        // Checkboxes (FormData ya incluye sólo los marcados si tienen value)
        // Aseguramos bools para tipo_ y res_
        $('input[type="checkbox"]').each(function() {
          const name = this.name;
          if (!name) return;
          payload[name] = this.checked ? 1 : 0;
        });

        // Radios
        const st = $('input[name="status"]:checked').val();
        payload['status'] = st || 'cerrado';

        // Firmas base64 (hidden ya tiene el dataURL)
        payload['sig_ing_base64'] = $('#sigIngBase64').val() || '';
        payload['sig_cli_base64'] = $('#sigCliBase64').val() || '';

        // CSRF
        payload['csrf_token'] = window.MRS_CSRF;

        $('#btnGen').prop('disabled', true);
        $('#uiResult').html('⏳ Generando PDF...');

        try {
          const r = await fetch('api/hoja_servicio_create.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': window.MRS_CSRF
            },
            credentials: 'include',
            body: JSON.stringify(payload)
          });
          const j = await r.json();

          if (!j.success) {
            $('#uiResult').html('❌ ' + (j.error || 'Error'));
            $('#btnGen').prop('disabled', false);
            return;
          }

          $('#uiResult').html('✅ PDF generado: <b>' + j.hsFolio + '</b>');
          // Abrir descarga en nueva pestaña
          window.open(j.downloadUrl, '_blank');

          // Mostrar botón “ver último”
          const a = document.getElementById('btnOpenLastPdf');
          if (a) {
            a.href = j.downloadUrl;
            a.classList.remove('d-none');
          }

        } catch (err) {
          $('#uiResult').html('❌ Error de red / servidor.');
        } finally {
          $('#btnGen').prop('disabled', false);
        }
      });

    })();
  </script>
</body>

</html>