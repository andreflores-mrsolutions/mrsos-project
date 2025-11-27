<?php
// headers.php (parte superior)
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
  <link rel="apple-touch-icon" href="/img/icon-192.png">
  <meta name="apple-mobile-web-app-capable" content="yes">


  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
  <script src="https://kit.fontawesome.com/04af9e068b.js" crossorigin="anonymous"></script>
  <!-- /Bootstrap -->
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://kit.fontawesome.com/04af9e068b.js" crossorigin="anonymous"></script>

  <!-- Chart.js -->
  <!-- <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script> -->

  <!-- css -->
  <link href="../css/style.css" rel="stylesheet">
  <!-- JS -->
  <script src="../js/main.js"></script>
  <script src="../js/dashboard.js"></script>
  <script src="../js/tickets/modal_meet.js"></script>
  <script src="../js/logout.js"></script>
  <!-- /JS -->
  <style>

  </style>
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
          <li class="nav-item">
            <a class="nav-link" href="tickets_abiertos.php"><i class="bi bi-tree"></i> Tickets Abiertos</a>
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
                  <i class="bi bi-person"></i> Hojas de Servicio
                </a>
              </li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li>
                <a id="btnLogout2" class="dropdown-item" href="../php/polizas.php">
                  <i class="bi bi-box-arrow-right"></i> Póliza
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
            <a class="nav-link" href="#"><i class="bi bi-person"></i> Mis datos</a>
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
              <a class="nav-link" href="tickets_abiertos.php"><i class="bi bi-tree"></i> Tickets Abiertos</a>
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
                    <i class="bi bi-person"></i> Hojas de Servicio
                  </a>
                </li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li>
                  <a id="btnLogout2" class="dropdown-item" href="../php/polizas.php">
                    <i class="bi bi-box-arrow-right"></i> Póliza
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
            <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-person"></i> Mis datos</a></li>
          </ul>
        </div>
      </div>



      <!-- MAIN -->
      <main class="col-md-10  px-4">
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
                <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Mis datos</a></li>
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
            <main class="col-md-9 ms-sm-auto col-lg-12 px-md-4">
              <button id="btnRecargar" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-clockwise"></i> Recargar Tickets
              </button>
              <!-- <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAnalisis">
                Launch demo modal
              </button>
              <div class="d-flex gap-2 my-2">
                <button class="btn btn-outline-primary btn-sm" onclick="enableWebPush()">Activar notificaciones</button>
                <button class="btn btn-outline-success btn-sm" onclick="probarPush()">Probar push</button>
              </div> -->
              <script>
                function probarPush() {
                  const usIdActual = window.USUARIO_ACTUAL_ID || 2001;
                  if (!usIdActual) {
                    alert('Define USUARIO_ACTUAL_ID');
                    return;
                  }
                  fetch('../php/send_push_test.php?usId=' + usIdActual)
                    .then(r => r.json())
                    .then(j => alert(JSON.stringify(j)))
                    .catch(e => alert('Error: ' + e.message));
                }
              </script>

              <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">

              </div>
          </div>
          <h5>Incidentes Recientes</h5>
          <div class="d-flex align-items-center gap-2 mb-3">
            <div class="btn-group" id="viewToggle">
              <button type="button" class="btn btn-outline-secondary btn-sm active" data-mode="table">
                <i class="bi bi-table"></i> Tabla
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" data-mode="cards">
                <i class="bi bi-grid-3x3-gap"></i> Cards
              </button>
            </div>
          </div>

          <!-- Aquí pintamos todo (clientes -> sedes -> tickets) -->
          <div id="wrapTicketsClientes"></div>

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

        <!-- Statistics -->
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
          <input type="month" id="mesFiltro" class="form-control form-control-sm" style="max-width: 180px;" value="<?= date('Y-m'); ?>">
          <select id="selSede" class="form-select form-select-sm" style="max-width: 220px;">
            <option value="">Todas las sedes</option>
          </select>
          <button id="btnMesAplicar" class="btn btn-sm btn-primary">Aplicar mes</button>
          <button id="btnUlt30" class="btn btn-sm btn-outline-secondary">Últimos 30 días</button>
        </div>

        <div class="row">
          <div class="col-lg-6">
            <div class="stat-card">
              <h6>Incidentes (rango)</h6>
              <canvas id="areaChart"></canvas>
            </div>
          </div>

          <div class="col-md-3">
            <div class="stat-card text-center">
              <h6>Tipo de ticket<br><small>en rango</small></h6>
              <canvas id="donutTipo" style="max-width:140px; display:initial!important;"></canvas>
            </div>
          </div>

          <div class="col-md-3">
            <div class="stat-card text-center">
              <h6>Estatus de ticket<br><small>en rango</small></h6>
              <canvas id="donutEstatus" style="max-width:140px; display:initial!important;"></canvas>
            </div>
          </div>
        </div>

        <!-- Modal: Meet -->
        <div class="modal fade" id="modalMeet" aria-hidden="true">
          <div class="modal-dialog">
            <form id="formMeet" class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Reunión (Meet)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
              </div>

              <div class="modal-body">
                <input type="hidden" id="meet_ticketId" name="ticketId">
                <input type="hidden" id="meet_modo" name="modo">

                <div class="mb-3">
                  <label for="meet_plataforma" class="form-label">Plataforma</label>
                  <select id="meet_plataforma" name="plataforma" class="form-select">
                    <option value="">Selecciona…</option>
                    <option value="Google">Google</option>
                    <option value="Teams">Teams</option>
                    <option value="Zoom">Zoom</option>
                    <option value="Otro">Otro</option>
                  </select>
                </div>

                <div class="mb-3">
                  <label for="meet_link" class="form-label">Enlace</label>
                  <input id="meet_link" name="link" type="url" class="form-control" placeholder="https://…">
                  <div class="form-text">Pega el enlace completo. (Opcional al solicitar)</div>
                </div>
                <!-- dentro del form del modalMeet -->
                <div class="row g-2">
                  <div class="col-12 col-md-6">
                    <!-- <label class="form-label mb-1">Fecha del meet</label> -->
                    <input type="date" id="meet_fecha" name="fecha" class="form-control">
                  </div>
                  <div class="col-12 col-md-6">
                    <!-- <label class="form-label mb-1">Hora</label> -->
                    <input type="time" id="meet_hora" name="hora" class="form-control">
                  </div>
                </div>

                <!-- Mensajes según rol -->
                <div class="alert alert-info d-none" data-rol="cliente">
                  Se enviará una solicitud de reunión al ingeniero.
                </div>
                <div class="alert alert-success d-none" data-rol="ingeniero">
                  Estás estableciendo una reunión activa para el cliente.
                </div>
              </div>

              <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <!-- Botón visible cuando es flujo de cliente -->
                <button type="submit" class="btn btn-primary" data-rol="cliente">Solicitar</button>
                <!-- Botón visible cuando es flujo de ingeniero -->
                <button type="submit" class="btn btn-success d-none" data-rol="ingeniero">Establecer</button>
              </div>
            </form>
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

<!-- Modal: Análisis de ingeniero -->
<div class="modal fade justify-content-center" id="modalAnalisis" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body justify-content-center">
        <img src="../img/asignacion.png" class="d-flex mx-auto mb-2" style="height: 150px;" alt="Análisis">
        <h1 class="modal-title text-center"><b>Análisis</b></h1>
        <h6 class="text-muted text-center mb-4">
          Añade un análisis al ticket. (Si faltan datos, escribe “Faltan datos”).
        </h6>

        <div class="mb-3">
          <label for="tiAnalisisDesc" class="form-label">Descripción del análisis</label>
          <textarea class="form-control" id="tiAnalisisDesc"
            placeholder="Describe el análisis preliminar/final del problema"
            rows="4"></textarea>
        </div>
      </div>

      <div class="modal-footer justify-content-center border-0">
        <button class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" id="btnGuardarAnalisis"
          data-next-proceso="logs">Continuar</button>
      </div>
    </div>
  </div>
</div>



<!-- Modal: Asignación de ingeniero -->
<div class="modal fade" id="modalAsignacion" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <h1 class="modal-title text-center"><b>Asignación</b></h1>
        <h5 class="text-muted text-center mb-3">Es momento de asignar un ingeniero, elige la mejor opción</h5>

        <!-- Grupos por Tier -->
        <div id="wrapIngenieros">
          <!-- Aquí se pintan los tiers y las cards -->
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-danger" data-bs-dismiss="modal">Cancelar</button>
        <button id="btnContinuarAsignacion" class="btn btn-success d-none">Continuar</button>
      </div>
    </div>
  </div>
</div>



