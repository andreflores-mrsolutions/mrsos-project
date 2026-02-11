<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../php/conexion.php';
require_once __DIR__ . '/../php/auth_guard.php';
require_once __DIR__ . '/../php/csrf.php';
require_once __DIR__ . '/../php/json.php';
require_once __DIR__ . '/../php/admin_bootstrap.php';

$ROL = $_SESSION['usRol'] ?? null;

$ROLES_PERMITIDOS = ['CLI', 'MRV', 'MRA', 'MRSA'];

if (!$ROL || !in_array($ROL, $ROLES_PERMITIDOS, true)) {
    http_response_code(403);
    exit('Acceso no autorizado');
}


// CSRF para JS
$csrf = csrf_token();

// Tema
$theme = $_COOKIE['mrs_theme'] ?? 'light';

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../php/conexion.php"; // ajusta la ruta si aplica

// Permite fijar la póliza activa desde ?pcId= y guardarla en sesión
if (isset($_GET['pcId'])) {
  $_SESSION['pcId'] = (int)$_GET['pcId'];
}

$clId  = $_SESSION['clId'] ?? null;      // cliente del usuario logueado
$pcId  = $_SESSION['pcId'] ?? null;      // póliza activa (opcional)

// Helper: localizar la foto de usuario con varias extensiones
function findUserAvatarUrl(string $username): string
{
  // Ajusta las rutas base según tu estructura
  $urlBase = "../img/Usuario/";                         // para el src en <img>
  $fsBase  = realpath(__DIR__ . "/../img/Usuario");     // en disco

  if (!$fsBase) return $urlBase . "user.webp";

  $exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  foreach ($exts as $ext) {
    $fs = $fsBase . DIRECTORY_SEPARATOR . $username . "." . $ext;
    if (file_exists($fs)) {
      return $urlBase . $username . "." . $ext;
    }
  }
  return $urlBase . "user.webp";
}

// Buscar vendedor (usId) para el cliente (y póliza si está definida)
$vend = null;
if ($clId) {
  if ($pcId) {
    $sql = "SELECT u.usId, u.usNombre, u.usAPaterno, u.usUsername
            FROM cuentas c
            JOIN polizascliente pc ON pc.pcId = c.pcId
            JOIN usuarios u ON u.usId = c.usId
            WHERE c.clId = ? AND c.pcId = ?
            LIMIT 1";
    $stmt = $conectar->prepare($sql);
    $stmt->bind_param("ii", $clId, $pcId);
  } else {
    // Si no hay póliza activa, toma cualquiera del cliente (la más reciente)
    $sql = "SELECT u.usId, u.usNombre, u.usAPaterno, u.usUsername
            FROM cuentas c
            JOIN polizascliente pc ON pc.pcId = c.pcId
            JOIN usuarios u ON u.usId = c.usId
            WHERE c.clId = ?
            ORDER BY c.cuId DESC
            LIMIT 1";
    $stmt = $conectar->prepare($sql);
    $stmt->bind_param("i", $clId);
  }

  if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    $vend = $res->fetch_assoc() ?: null;
  }
  if (isset($stmt) && $stmt) $stmt->close();
}

$vendNombre = $vend ? trim(($vend['usNombre'] ?? '') . ' ' . ($vend['usAPaterno'] ?? '')) : 'Responsable del proyecto';
$vendAvatar = $vend ? findUserAvatarUrl($vend['usUsername'] ?? '') : '../img/Usuario/user.webp';

if (empty($_SESSION['clId'])) {
  header('Location: ../login/login.php');
  exit;
}

// ... tu lógica de sesión previa
$ROL   = $_SESSION['usRol']  ?? null;   // 'AC' | 'UC' | 'EC' | 'MRA'
$CL_ID = $_SESSION['clId'] ?? null;
$US_ID = $_SESSION['usId'] ?? null;

$CAN_CREATE = ($ROL === 'AC' || $ROL === 'UC' || $ROL === 'MRA'); // EC no crea
?>
<script>
  window.SESSION = {
    rol: <?= json_encode($ROL) ?>,
    clId: <?= json_encode((int)$CL_ID) ?>,
    usId: <?= json_encode((int)$US_ID) ?>,
    canCreateTicket: <?= $CAN_CREATE ? 'true' : 'false' ?>
  };
</script>

<!DOCTYPE html>
<html lang="es">

<head>
  <title>MRSolutions</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Ajax - JQuery -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- /Ajax - JQuery -->

  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <link rel="manifest" href="../manifest.json">
  <meta name="theme-color" content="#0e1525">


  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
  <script src="https://kit.fontawesome.com/04af9e068b.js" crossorigin="anonymous"></script>
  <!-- /Bootstrap -->
  <!-- Bootstrap Icons -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Primero el objeto Meet -->
  <script src="../js/tickets/modal_meet.js"></script>
  <script src="../js/tickets/modal_visita.js"></script>

  <!-- Luego tus otros JS que lo usan -->
  <script src="../js/tickets.js"></script>
  <script src="../js/main.js"></script>
  <script src="../js/logout.js"></script>

  <!-- css -->
  <link href="../css/style.css" rel="stylesheet">

<script>
  window.MRS_CSRF = <?= json_encode($csrf) ?>;
</script>

</head>

<body class="<?php echo ($theme === 'dark') ? 'dark-mode' : ''; ?>">

  <div class="container-fluid">
    <div class="row gx-0">
      <!-- SIDEBAR -->
      <!-- SIDEBAR FIJO (md+) -->
      <nav id="sidebar" class="col-12 col-md-3 col-lg-2 d-none d-lg-block p-3 mr-side">
        <div class="brand mb-3 px-2">
          <a class="navbar-brand" href="#">
            <img src="../img/image.png" alt="Logo" class="rounded-pill">
          </a>
        </div>

        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item">
            <a class="nav-link active" href="home.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
          </li>

          <li class="nav-item" id="btnNuevoTicket">
            <a class="nav-link" href="nuevo_ticket.php"><i class="bi bi-plus-circle"></i> Ticket Nuevo</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="configuracion.php"><i class="bi bi-gear"></i> Configuración</a>
          </li>


          <!-- Dropdown -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="reportesMenu"
              data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-upload"></i> Exportar Reportes
            </a>
            <ul class="dropdown-menu" aria-labelledby="reportesMenu">
              <li><a class="dropdown-item" href="hojas_de_servicio.php">
                  <i class="bi bi-archive"></i> Hojas de Servicio
                </a>
              </li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li>
                <a id="btnLogout2" class="dropdown-item" href="../dashboard/poliza.php">
                    <i class="bi bi-file-text"></i> Póliza
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-item">
            <a class="nav-link" href="admin_usuarios.php"><i class="bi bi-shield-lock"></i> Panel Administrador</a>
          </li>
        </ul>

        <div class="section-title px-2">MÁS</div>
        <ul class="nav nav-pills flex-column gap-1">
          <li class="nav-item">
            <a class="nav-link" href="misequipos.php"><i class="bi bi-cpu"></i> Mis equipos</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="configuracion.php"><i class="bi bi-person"></i> Mis datos</a>
          </li>
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

        <div class="offcanvas-body pt-0">
          <ul class="nav nav-pills flex-column gap-1">
            <li class="nav-item">
              <a class="nav-link active" href="home.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            </li>
            
            <li class="nav-item">
              <a class="nav-link" href="nuevo_ticket.php"><i class="bi bi-plus-circle"></i> Ticket Nuevo</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="configuracion.php" modalAsignacion><i class="bi bi-gear"></i> Configuración</a>
            </li>

            <!-- Dropdown (mismo contenido que el sidebar) -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="reportesMenuOff"
                data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-upload"></i> Reportes
              </a>
              <ul class="dropdown-menu" aria-labelledby="reportesMenuOff">
                <li><a class="dropdown-item" href="hojas_de_servicio.php">
                    <i class="bi bi-archive"></i> Hojas de Servicio
                  </a>
                </li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li>
                  <a id="btnLogout2" class="dropdown-item" href="../dashboard/poliza.php">
                    <i class="bi bi-file-text"></i> Póliza
                  </a>
                </li>
              </ul>
            </li>

            <li class="nav-item">
              <a class="nav-link" href="admin_usuarios.php"><i class="bi bi-shield-lock"></i> Panel Administrador</a>
            </li>
          </ul>

          <div class="section-title px-1">MÁS</div>
          <ul class="nav nav-pills flex-column gap-1 mb-4">
            <li class="nav-item"><a class="nav-link" href="misequipos.php"><i class="bi bi-cpu"></i> Mis equipos</a></li>
            <li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-person"></i> Mis datos</a></li>
          </ul>
        </div>
      </div>



      <!-- MAIN -->
      <main class="col-md-12 ms-sm-auto col-lg-10 px-lg-4">
        <!-- Top bar -->
        <!-- Contenedor general -->
        <div class="d-flex align-items-center justify-content-between py-3 px-2 px-md-4 ">
          <!-- Lado izquierdo -->
          <div class="d-flex align-items-center">
            <!-- Botón hamburguesa solo en sm y xs -->
            <button class="btn btn-outline-secondary d-lg-none me-2"
              data-bs-toggle="offcanvas"
              data-bs-target="#offcanvasSidebar"
              aria-controls="offcanvasSidebar">
              <i class="bi bi-list"></i>
            </button>

            <!-- Logo cliente siempre visible -->
            <span class="badge bg-light me-3 p-2">
              <img src="../img/Clientes/enel.svg" style="height:30px;" alt="cliente">
            </span>

            <!-- Estado: en mobiles pequeño badge -->
            <span class="badge bg-success me-3 d-none d-sm-inline-block">Activo</span>

            <!-- Responsable: solo avatar en xs & sm -->
            <!-- Responsable: avatar + nombre del vendedor desde BD -->
            <img src="<?= htmlspecialchars($vendAvatar) ?>"
              class="rounded-circle me-2 d-inline-block"
              alt="<?= htmlspecialchars($vendNombre) ?>"
              style="width: 40px; height: 40px; object-fit: cover; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
            <span class="d-none d-sm-inline">
              <?= htmlspecialchars($vendNombre) ?>
            </span>

          </div>

          <!-- Lado derecho -->
          <div class="d-flex align-items-center">
            <!-- Íconos grandes: solo md+ -->
            <div class="d-none d-md-flex align-items-center top-icons me-3">
              <i class="bi bi-search mx-2"></i>
              <a id="btnRecargar"><i class="bi bi-arrow-clockwise mx-2"></i></a>
              <i class="bi bi-bell mx-2"></i>
              <i class="bi bi-question-circle mx-2"></i>

              <!-- NUEVO: Botón tema (luna) -->
              <button id="btnThemeDesktop" class="btn btn-outline-secondary btn-icon mx-2" title="Modo oscuro">
                <i class="bi bi-moon"></i>
              </button>
            </div>


            <!-- Dropdown general en sm- -->
            <div class="dropdown d-md-none me-2">
              <button class="btn btn-outline-secondary" type="button" id="moreActions" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots-vertical"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="moreActions">
                <li><a class="dropdown-item" href="#"><i class="bi bi-search me-2"></i>Buscar</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-arrow-clockwise me-2"></i>Refrescar</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-bell me-2"></i>Notificaciones</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-question-circle me-2"></i>Ayuda</a></li>

                <!-- NUEVO: Tema -->
                <li>
                  <a id="btnThemeMobile" class="dropdown-item" href="#" role="button">
                    <i class="bi bi-moon me-2"></i><span>Cambiar a modo oscuro</span>
                  </a>
                </li>
              </ul>
            </div>


            <!-- Perfil -->
            <div class="nav-item dropdown">
              <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle"
                id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <?php

                // Ajusta si tu archivo está en otra carpeta
                $usuario  = $_SESSION['usUsername'] ?? 'default';
                $usuario  = preg_replace('/[^A-Za-z0-9_\-]/', '', $usuario); // sanitiza por seguridad

                $dirFS  = __DIR__ . '/../img/Usuario/'; // ruta en el sistema de archivos
                $dirURL = '../img/Usuario/';            // ruta pública (para el src)

                $extsPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $src = $dirURL . 'user.webp'; // fallback por si no hay avatar

                foreach ($extsPermitidas as $ext) {
                  $fs = $dirFS . $usuario . '.' . $ext;
                  if (is_file($fs)) {
                    // Evita caché del navegador cuando cambie la imagen
                    $src = $dirURL . $usuario . '.' . $ext . '?v=' . filemtime($fs);
                    break;
                  }
                }
                ?>
                <img
                  src="<?= htmlspecialchars($src, ENT_QUOTES) ?>"
                  class="rounded-circle me-2"
                  alt="Usuario"
                  style="width: 40px; height: 40px; object-fit: cover; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"
                  onerror="this.onerror=null;this.src='../img/Usuario/user.webp';" />

                <span class="d-none d-md-inline"><strong><?php echo $_SESSION['usUsername']; ?></strong></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                <li><a class="dropdown-item" href="configuracion.php"><i class="bi bi-person me-2"></i>Mis datos</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a id="btnLogout"
                    class="dropdown-item"
                    href="../php/logout.php"
                    data-href="../php/logout.php?ajax=1"
                    data-redirect="../login/login.php">
                    <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                  </a></li>

              </ul>
            </div>
          </div>
        </div>
        <!-- Recent Incidents -->
        <div class="main mb-4">
          <div class="row">
            <main class="col-md-12 ms-sm-auto col-lg-12 px-lg-4">
              <div class="card mrs-card mt-4">
                <div class="card-body" style="padding:0.5rem;">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <div>
                      <h5 class="mb-0">Tickets por sede</h5>
                      <small class="text-muted">
                        Visualiza los tickets abiertos agrupados por cada sede de tu organización.
                      </small>
                    </div>
                  </div>
                  <!-- Toggle Tabla / Cards -->
                  <div class="btn-group btn-group-sm my-2" id="vistaTicketsToggle" role="group">
                    <button type="button"
                      class="btn btn-outline-secondary active"
                      data-vista="tabla">
                      <i class="bi bi-table"></i>
                      <span class="d-none d-md-inline ms-1">Tabla</span>
                    </button>
                    <button type="button"
                      class="btn btn-outline-secondary"
                      data-vista="cards">
                      <i class="bi bi-grid-3x3-gap"></i>
                      <span class="d-none d-md-inline ms-1">Cards</span>
                    </button>
                    <button id="btnRecargar1" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-arrow-clockwise"></i> Recargar Tickets
                    </button>
                  </div>

                  <!-- Filtros rápidos + buscador -->
                  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                    <!-- Filtros por estado/criticidad -->
                    <div id="filtrosTickets" class="d-flex flex-wrap gap-2">
                      <button type="button" class="btn btn-sm btn-outline-secondary active" data-filter="all">
                        Todos
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-success" data-filter="abierto">
                        Abiertos
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger" data-filter="alta">
                        Críticos
                      </button>
                    </div>

                    <!-- Buscador por código o SN -->
                    <div class="input-group input-group-sm" style="max-width:260px;">
                      <span class="input-group-text"><i class="bi bi-search"></i></span>
                      <input type="text" id="searchTickets" class="form-control"
                        placeholder="Buscar por código, SN o modelo">
                    </div>
                  </div>


                  <div id="wrapTicketsSedes">
                    <p class="text-muted mb-0">Cargando tickets...</p>
                  </div>
                </div>
              </div>



          </div>
          <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasTicket" aria-labelledby="offcanvasTicket"
            data-bs-scroll="true" aria-labelledby="offcanvasWithBackdropLabel">
            <div class="offcanvas-header bg-light" style="background: #f8f9fb!important;">
              <h5 class="offcanvas-title bg-light">Detalles del Ticket</h5>
              <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body" id="offcanvasContent">
              <p>Cargando información...</p>
            </div>
          </div>


          <!-- Menu -->
          <div class="d-flex gap-3 mb-4 mx-0 row">
            <div class="col-2 menu-card active"><i class="bi bi-exclamation-triangle"></i><br>Tickets</div>
            <div class="col-2 menu-card"><a href="hojas_de_servicio.php"><i class="bi bi-file-earmark-text"></i><br>Hojas de servicio</a></div>
            <div class="col-2 menu-card"><a href="hojas_de_servicio.php"><i class="bi bi-journal"></i><br>Póliza</a></div>
            <div class="col-2 menu-card"><a href="hojas_de_servicio.php"><i class="bi bi-sliders"></i><br>Ajustes</a></div>

          </div>

          <!-- === Estadísticas de tickets === -->
          <div class="card mrs-card mt-4">
            <div class="card-body">
              <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                  <h5 class="mb-0">Estadísticas de tickets</h5>
                  <small class="text-muted">Incidentes y distribuciones en el rango seleccionado.</small>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <input type="month" id="mesFiltro"
                    class="form-control form-control-sm"
                    style="max-width: 180px;"
                    value="<?= date('Y-m'); ?>">

                  <select id="selSede" class="form-select form-select-sm" style="max-width: 220px;">
                    <option value="">Todas las sedes</option>
                  </select>

                  <button id="btnMesAplicar" class="btn btn-sm btn-primary">
                    Aplicar mes
                  </button>
                  <button id="btnUlt30" class="btn btn-sm btn-outline-secondary">
                    Últimos 30 días
                  </button>
                </div>
              </div>

              <div class="row">
                <div class="col-lg-6">
                  <div class="stat-card">
                    <h6 class="mb-2">Incidentes (rango)</h6>
                    <canvas id="areaChart"></canvas>
                  </div>
                </div>

                <div class="col-md-3">
                  <div class="stat-card text-center">
                    <h6>Tipo de ticket<br><small>en rango</small></h6>
                    <canvas id="donutTipo"
                      style="max-width:140px; display:initial!important;"></canvas>
                  </div>
                </div>

                <div class="col-md-3">
                  <div class="stat-card text-center">
                    <h6>Estatus de ticket<br><small>en rango</small></h6>
                    <canvas id="donutEstatus"
                      style="max-width:140px; display:initial!important;"></canvas>
                  </div>
                </div>
              </div>
            </div>
          </div>
      </main>


      <div class="modal fade" id="modalMeet" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <div class="modal-header border-0">
              <div class="d-flex align-items-center gap-2">
                <img src="../img/icon-meet.svg" alt="" style="width:42px;height:42px;">
                <div>
                  <h5 class="modal-title mb-0">Meet</h5>
                  <small class="text-muted" id="meetSubtitulo">Fecha propuesta</small>
                </div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <!-- vista propuesta -->
              <div id="meetVistaPropuesta">
                <div class="mb-3 row g-2">
                  <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" id="meetFechaProp" class="form-control">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Hora</label>
                    <input type="time" id="meetHoraProp" class="form-control">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Plataforma</label>
                    <select id="meetPlataformaProp" class="form-select">
                      <option value="teams">Teams</option>
                      <option value="google_meet">Google Meet</option>
                      <option value="zoom">Zoom</option>
                      <option value="otro">Otro</option>
                    </select>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Enlace de reunión (opcional)</label>
                  <input type="url" id="meetLinkProp" class="form-control"
                    placeholder="https://...">
                </div>
              </div>

              <!-- vista asignación -->
              <div id="meetVistaAsignacion" class="d-none">
                <div class="mb-3 row g-2">
                  <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" id="meetFechaAsig" class="form-control">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Hora</label>
                    <input type="time" id="meetHoraAsig" class="form-control">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Plataforma</label>
                    <select id="meetPlataformaAsig" class="form-select">
                      <option value="google_meet">Google Meet</option>
                      <option value="teams">Teams</option>
                      <option value="zoom">Zoom</option>
                      <option value="otro">Otro</option>
                    </select>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Enlace de reunión</label>
                  <input type="url" id="meetLinkAsig" class="form-control"
                    placeholder="https://...">
                  <small class="text-muted">
                    Si eliges “Google Meet” y lo asigna MR Solutions, el enlace lo puede generar el ingeniero.
                  </small>
                </div>
              </div>
            </div>

            <div class="modal-footer border-0 justify-content-between">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                Cancelar
              </button>
              <div class="d-flex gap-2">
                <!-- para modo propuesta -->
                <button type="button" class="btn btn-outline-primary d-none" id="btnMeetNuevaPropuesta">
                  Nueva propuesta
                </button>
                <button type="button" class="btn btn-primary" id="btnMeetConfirmar">
                  Confirmar
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>


      <!-- Bootstrap JS Bundle -->




</body>




<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.28.0/dist/feather.min.js" integrity="sha384-uO3SXW5IuS1ZpFPKugNNWqTZRRglnUJK6UAZ/gxOX80nxEkN9NcGZTftn6RzhGWE" crossorigin="anonymous"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js" integrity="sha384-zNy6FEbO50N+Cg5wap8IKA4M/ZnLJgzc6w2NqACZaK0u0FXfOWRRJOnQtpZun8ha" crossorigin="anonymous"></script> -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

</html>

<script>
  // --- Helpers de cookie ---
  function setCookie(name, value, days = 365) {
    const d = new Date();
    d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value};expires=${d.toUTCString()};path=/;SameSite=Lax`;
  }

  function getCookie(name) {
    const m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return m ? decodeURIComponent(m[2]) : null;
  }

  // --- Estado inicial: cookie > prefers-color-scheme > light ---
  (function initTheme() {
    const cookieTheme = getCookie('mrs_theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const initial = cookieTheme || (prefersDark ? 'dark' : 'light');
    applyTheme(initial, {
      save: false
    });
  })();

  function applyTheme(mode, {
    save = true
  } = {}) {
    const isDark = (mode === 'dark');
    document.body.classList.toggle('dark-mode', isDark);
    if (save) setCookie('mrs_theme', isDark ? 'dark' : 'light');

    // Sincroniza iconos Desktop + Mobile
    const deskBtn = document.getElementById('btnThemeDesktop');
    const mobBtn = document.getElementById('btnThemeMobile');

    if (deskBtn) {
      const i = deskBtn.querySelector('i');
      if (i) {
        i.classList.remove('bi-moon', 'bi-moon-fill');
        i.classList.add(isDark ? 'bi-moon-fill' : 'bi-moon');
      }
      deskBtn.title = isDark ? 'Modo claro' : 'Modo oscuro';
    }

    if (mobBtn) {
      const i = mobBtn.querySelector('i');
      const label = mobBtn.querySelector('span');
      if (i) {
        i.classList.remove('bi-moon', 'bi-moon-fill');
        i.classList.add(isDark ? 'bi-moon-fill' : 'bi-moon');
      }
      if (label) {
        label.textContent = isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
      }
    }
  }

  // --- Eventos de toggle ---
  document.addEventListener('DOMContentLoaded', () => {
    const deskBtn = document.getElementById('btnThemeDesktop');
    const mobBtn = document.getElementById('btnThemeMobile');

    const current = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
    applyTheme(current, {
      save: false
    });

    if (deskBtn) {
      deskBtn.addEventListener('click', () => {
        const next = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
        applyTheme(next);
      });
    }

    if (mobBtn) {
      mobBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const next = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
        applyTheme(next);

        // Cerrar el dropdown en móviles si está abierto (Bootstrap)
        const dropdownEl = document.getElementById('moreActions');
        if (dropdownEl) {
          const bsDropdown = bootstrap.Dropdown.getInstance(dropdownEl);
          if (bsDropdown) bsDropdown.hide();
        }
      });
    }
  });
</script>

<!-- ========== MODAL VISITA · PROPUESTA ========== -->
<div class="modal fade" id="visitaModalPropuesta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="visitaFormPropuesta">
      <div class="modal-header border-0 pb-0">
        <div class="d-flex align-items-center gap-2">
          <img src="../img/icon-visita.svg" alt="Visita" style="width:40px;height:40px;">
          <div>
            <h5 class="modal-title mb-0">Ventana/Visita</h5>
            <small class="text-muted">Fecha propuesta</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="visitaProp_tiId" name="tiId">

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" id="visitaProp_fecha" required>
          </div>
          <div class="col-6">
            <label class="form-label">Hora</label>
            <input type="time" class="form-control" id="visitaProp_hora" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Tiempo estimado</label>
          <div class="input-group mb-1">
            <input type="number" min="0" class="form-control"
              id="visitaProp_duracionHoras" placeholder="Horas">
            <span class="input-group-text">:</span>
            <input type="number" min="0" max="59" class="form-control"
              id="visitaProp_duracionMinutos" placeholder="Minutos">
          </div>
          <div class="form-text">
            Las ventanas de visita ayudan a organizar mejor las agendas de los ingenieros.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label d-block">¿Cómo se gestionará la invitación?</label>
          <div class="form-check">
            <input class="form-check-input" type="radio"
              name="visitaProp_envio" id="visitaProp_envio_correo"
              value="correo">
            <label class="form-check-label" for="visitaProp_envio_correo">
              Enviaré la invitación por correo electrónico
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio"
              name="visitaProp_envio" id="visitaProp_envio_ingeniero"
              value="ingeniero">
            <label class="form-check-label" for="visitaProp_envio_ingeniero">
              Que el ingeniero genere la invitación
            </label>
          </div>
        </div>

      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Confirmar propuesta</button>
      </div>
    </form>
  </div>
</div>

<!-- ========== MODAL VISITA · ASIGNACIÓN ========== -->
<div class="modal fade" id="visitaModalAsignar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="visitaFormAsignar">
      <div class="modal-header border-0 pb-0">
        <div class="d-flex align-items-center gap-2">
          <img src="../img/icon-visita.svg" alt="Visita" style="width:40px;height:40px;">
          <div>
            <h5 class="modal-title mb-0">Ventana/Visita</h5>
            <small class="text-muted">Asignación de una ventana/visita</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="visitaAsig_tiId" name="tiId">

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" id="visitaAsig_fecha" required>
          </div>
          <div class="col-6">
            <label class="form-label">Hora</label>
            <input type="time" class="form-control" id="visitaAsig_hora" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Tiempo estimado</label>
          <div class="input-group mb-1">
            <input type="number" min="0" class="form-control"
              id="visitaAsig_duracionHoras" placeholder="Horas">
            <span class="input-group-text">:</span>
            <input type="number" min="0" max="59" class="form-control"
              id="visitaAsig_duracionMinutos" placeholder="Minutos">
          </div>
        </div>
        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input"
              type="checkbox"
              id="visitaAsig_reqAcceso">
            <label class="form-check-label" for="visitaAsig_reqAcceso">
              El ingeniero enviará esta información para la creación de folio de entrada
            </label>
            <div class="form-text">
              Nombre, Email, Teléfono, CURP, NSS (Num de Seguro Social), Auto ( placas, marca, modelo y color), Celular (marca, modelo, serie), Laptop (Marca, Modelo y serie).
            </div>
          </div>
          <label class="form-label mt-2">
            ¿Se necesita algo más para el folio de entrada?
          </label>
          <textarea class="form-control"
            id="visitaAsig_extraAcceso"
            rows="2"
            placeholder="Ej. Enviar copia de credencial, licencia vigente, etc."></textarea>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success">Guardar visita</button>
      </div>
    </form>
  </div>
</div>





<!-- ========== MODAL MEET · PROPUESTA ==========
     El cliente propone fecha/horario/plataforma, puede mandar link o decir
     que mandará invitación por correo.
-->
<div class="modal fade" id="meetModalPropuesta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="meetFormPropuesta">
      <div class="modal-header border-0 pb-0">
        <div class="d-flex align-items-center gap-2">
          <img src="../img/icon-meet.svg" alt="Meet" style="width:40px;height:40px;">
          <div>
            <h5 class="modal-title mb-0">Meet</h5>
            <small class="text-muted">Fecha propuesta</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="meetProp_tiId" name="tiId">

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" id="meetProp_fecha" required>
          </div>
          <div class="col-6">
            <label class="form-label">Hora</label>
            <input type="time" class="form-control" id="meetProp_hora" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Plataforma</label>
          <select class="form-select" id="meetProp_plataforma" required>
            <option value="">Selecciona…</option>
            <option value="Google Meet">Google Meet</option>
            <option value="Teams">Microsoft Teams</option>
            <option value="Zoom">Zoom</option>
            <option value="Otro">Otro</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Enlace (si ya lo tienes)</label>
          <input type="url" class="form-control" id="meetProp_link"
            placeholder="https://…">
          <div class="form-text">
            Si aún no tienes el link, puedes elegir la opción de enviar invitación por correo.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label d-block">¿Cómo se gestionará la invitación?</label>
          <div class="form-check">
            <input class="form-check-input" type="radio"
              name="meetProp_envio" id="meetProp_envio_link"
              value="link" checked>
            <label class="form-check-label" for="meetProp_envio_link">
              Ya tengo / tendré el enlace del Meet
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio"
              name="meetProp_envio" id="meetProp_envio_correo"
              value="correo">
            <label class="form-check-label" for="meetProp_envio_correo">
              Enviaré invitación por correo electrónico
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio"
              name="meetProp_envio" id="meetProp_envio_ingeniero"
              value="ingeniero">
            <label class="form-check-label" for="meetProp_envio_ingeniero">
              Que el ingeniero genere la invitación
            </label>
          </div>
        </div>

      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Confirmar propuesta</button>
      </div>
    </form>
  </div>
</div>

<!-- ========== MODAL MEET · ASIGNACIÓN ==========
     El cliente deja YA firme la fecha y el enlace.
-->
<div class="modal fade" id="meetModalAsignar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="meetFormAsignar">
      <div class="modal-header border-0 pb-0">
        <div class="d-flex align-items-center gap-2">
          <img src="../img/icon-meet.svg" alt="Meet" style="width:40px;height:40px;">
          <div>
            <h5 class="modal-title mb-0">Meet</h5>
            <small class="text-muted">Asignación de un meet</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="meetAsig_tiId" name="tiId">

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" id="meetAsig_fecha" required>
          </div>
          <div class="col-6">
            <label class="form-label">Hora</label>
            <input type="time" class="form-control" id="meetAsig_hora" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Plataforma</label>
          <select class="form-select" id="meetAsig_plataforma" required>
            <option value="">Selecciona…</option>
            <option value="Google Meet">Google Meet</option>
            <option value="Teams">Microsoft Teams</option>
            <option value="Zoom">Zoom</option>
            <option value="Otro">Otro</option>
          </select>
          <div class="form-text">
            Los ingenieros de MR normalmente generan la reunión en Google Meet.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Enlace de la reunión</label>
          <input type="url" class="form-control" id="meetAsig_link"
            placeholder="https://…">
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success">Guardar meet</button>
      </div>
    </form>
  </div>
</div>
<a href="nuevo_ticket.php" class="float-wa">
  <div class="position-absolute top-50 start-50 translate-middle">
    <i class="bi bi-plus mx-auto my-auto"></i>
  </div>
</a>


</script>