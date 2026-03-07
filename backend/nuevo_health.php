<?php
// admin/nuevo_health.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../php/auth_guard.php';
require_login();

$rol = $_SESSION['usRol'] ?? ($_SESSION['rol'] ?? '');
if (!in_array($rol, ['MRA', 'MRSA', 'ADMIN'], true)) {
  http_response_code(403);
  exit('Sin permisos');
}

require_once __DIR__ . '/../php/csrf.php';
$csrf = csrf_token();

if (empty($_SESSION['usId'])) {
  header('Location: ../login/login.php');
  exit;
}

$clId = isset($_GET['clId']) ? (int)$_GET['clId'] : 0;
if ($clId <= 0) {
  http_response_code(400);
  echo "Falta clId";
  exit;
}

$theme = $_COOKIE['mrs_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <script>
  window.MRS_CSRF = <?= json_encode(['csrf' => $csrf], JSON_UNESCAPED_UNICODE) ?>;
  window.MRS_CTX = <?= json_encode([
    'clId' => $clId,
    'baseApi' => 'api',
    'NOTIFY_URL' => '../php/notify.php'
  ], JSON_UNESCAPED_UNICODE) ?>;
</script>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MR SOS | Nuevo Health Check</title>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <link href="css/css.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">
  <link href="css/nuevo_health.css" rel="stylesheet">
</head>

<body class="<?php echo ($theme === 'dark') ? 'dark-mode' : ''; ?>">
  <div class="container-fluid">
    <div class="row gx-0">

      <!-- SIDEBAR (TU BASE) -->
      <nav id="sidebar" class="col-12 col-md-3 col-lg-2 d-none d-lg-block p-3 mr-side">
        <div class="brand mb-3 px-2">
          <a class="navbar-brand" href="#">
            <img src="../img/image.png" alt="Logo" class="rounded-pill" style="max-width: 120px;">
          </a>
        </div>

        <div class="section-title px-2">Operación</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_ticket.php?clId=<?= (int)$clId ?>"><i class="bi bi-shield-check"></i> Health Checks</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nuevo Cliente</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_usuario.php"><i class="bi bi-plus-circle"></i> Nuevo Usuario</a></li>
        </ul>

        <div class="section-title pt-2">Creación</div>
        <div class="section-subtitle ">Tickets</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="nuevo_ticket.php?clId=<?= (int)$clId ?>"><i class="bi bi-plus-circle"></i> Nuevo Ticket</a></li>
          <li class="nav-item"><a class="nav-link active" href="nuevo_health.php?clId=<?= (int)$clId ?>"><i class="bi bi-plus-circle"></i> Nuevo Health Check</a></li>
        </ul>

        <div class="section-subtitle ">Cliente</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nuevo Cliente</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nueva Zona</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nueva Sede</a></li>
        </ul>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item"><a class="nav-link" href="nuevo_usuario.php"><i class="bi bi-plus-circle"></i> Nuevo Usuario</a></li>
          <li class="nav-item"><a class="nav-link" href="nuevo_cliente.php"><i class="bi bi-plus-circle"></i> Nuevo Ingeniero</a></li>
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

      <main class="col-12 col-lg-10">

        <div class="admin-topbar px-3 py-2 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i></a>
            <span class="badge text-bg-success rounded-pill px-3">Activo</span>
            <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['usUsername'] ?? 'Admin'); ?></span>
          </div>

          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="btnTheme" type="button" title="Tema">
              <i class="bi bi-moon"></i>
            </button>
            <a class="btn btn-sm btn-outline-danger" href="../dashboard/logout.php" title="Salir">
              <i class="bi bi-box-arrow-right"></i>
            </a>
          </div>
        </div>

        <div class="px-3 py-3">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
              <h4 class="mb-0">Nuevo Health Check</h4>
              <div class="text-muted small">Cliente ID: <span class="badge text-bg-light border"><?= (int)$clId ?></span></div>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-secondary btn-sm" id="btnReload">
                <i class="bi bi-arrow-clockwise"></i> Recargar catálogos
              </button>
            </div>
          </div>

          <div class="row g-3">
            <!-- FORM -->
            <div class="col-12 col-xl-5">
              <div class="card mrs-card">
                <div class="card-body">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold"><i class="bi bi-clipboard2-pulse"></i> Datos del Health Check</div>
                    <span class="badge text-bg-primary-subtle border">Paso 1</span>
                  </div>

                  <div id="alertBox" class="alert d-none" role="alert"></div>

                  <form id="frmHealth" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="clId" value="<?= (int)$clId ?>">
                    <input type="hidden" name="items_json" id="items_json" value="[]">

                    <div class="mb-3">
                      <label class="form-label">Sede <span class="text-danger">*</span></label>
                      <select class="form-select" name="csId" id="csId" required>
                        <option value="">Cargando...</option>
                      </select>
                      <div class="form-text">El Health Check se programa para una sede específica.</div>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Cliente responsable <span class="text-danger">*</span></label>
                      <select class="form-select" name="usIdCliente" id="usIdCliente" required>
                        <option value="">Selecciona una sede primero...</option>
                      </select>
                      <div class="form-text">
                        Este usuario se guardará en <code>health_check.usId</code> como responsable y se usa para autollenar contacto.
                      </div>
                    </div>

                    <div class="row g-2">
                      <div class="col-12 col-md-7">
                        <label class="form-label">Fecha y hora <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="hcFechaHora" name="hcFechaHora" required>
                      </div>
                      <div class="col-12 col-md-5">
                        <label class="form-label">Duración</label>
                        <select class="form-select" id="hcDuracionMins" name="hcDuracionMins">
                          <option value="60">60 min</option>
                          <option value="120">120 min</option>
                          <option value="240" selected>240 min</option>
                          <option value="480">480 min</option>
                        </select>
                      </div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-3">
                      <label class="form-label">Contacto (nombre) <span class="text-danger">*</span></label>
                      <input class="form-control" type="text" name="hcNombreContacto" id="hcNombreContacto"
                        required maxlength="120" placeholder="Ej. Nailea Fragoso">
                    </div>

                    <div class="row g-2">
                      <div class="col-12 col-md-6">
                        <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="hcNumeroContacto" id="hcNumeroContacto"
                          required maxlength="25" placeholder="55XXXXXXXX">
                      </div>
                      <div class="col-12 col-md-6">
                        <label class="form-label">Correo <span class="text-danger">*</span></label>
                        <input class="form-control" type="email" name="hcCorreoContacto" id="hcCorreoContacto"
                          required maxlength="120" placeholder="correo@dominio.com">
                      </div>
                    </div>

                    <div class="hc-selected mt-3">
                      <div class="d-flex align-items-center justify-content-between">
                        <div class="fw-semibold"><i class="bi bi-list-check"></i> Equipos seleccionados</div>
                        <button class="btn btn-sm btn-outline-secondary" id="btnClearSel" type="button">Limpiar</button>
                      </div>
                      <div class="small text-muted mt-1" id="selCountText">0 equipos</div>
                      <div class="mt-2" id="selList"></div>
                    </div>

                    <div class="d-grid mt-3">
                      <button class="btn btn-primary" id="btnCrear" type="submit">
                        <i class="bi bi-check2-circle"></i> Programar Health Check
                      </button>
                    </div>

                    <div class="small text-muted mt-2">
                      Se guarda en <code>health_check</code> (Programado) y los equipos en <code>health_check_items</code>.
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- EQUIPOS -->
            <div class="col-12 col-xl-7">
              <div class="card mrs-card">
                <div class="card-body">
                  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <div class="fw-semibold"><i class="bi bi-grid-3x3-gap"></i> Selecciona equipos</div>
                    <span class="badge text-bg-primary-subtle border">Paso 2</span>
                  </div>

                  <div class="row g-2 align-items-center mb-3">
                    <div class="col-12 col-md-8">
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="txtBuscarEquipo" placeholder="Buscar por modelo, tipo, SN, póliza...">
                      </div>
                    </div>
                    <div class="col-12 col-md-4">
                      <button class="btn btn-outline-dark w-100" id="btnSelectAll" type="button">
                        <i class="bi bi-check2-square"></i> Seleccionar visibles
                      </button>
                    </div>
                  </div>

                  <div id="equiposSkeleton" class="mrs-skeleton-grid">
                    <div class="mrs-skel"></div>
                    <div class="mrs-skel"></div>
                    <div class="mrs-skel"></div>
                    <div class="mrs-skel"></div>
                    <div class="mrs-skel"></div>
                    <div class="mrs-skel"></div>
                  </div>

                  <div class="row" id="equiposGrid" style="display:none;"></div>

                  <div class="text-muted small mt-2">
                    Tip: clic para agregar/quitar del Health Check. (Multi-selección)
                  </div>

                </div>
              </div>
            </div>

          </div><!-- row -->
        </div><!-- px -->

      </main>
    </div>
  </div>
<!-- TOASTS -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
  <div id="toastSuccess" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">OK</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
    </div>
  </div>

  <div id="toastError" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">Error</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
    </div>
  </div>
</div>
  <script src="js/nuevo_health.js"></script>
</body>

</html>