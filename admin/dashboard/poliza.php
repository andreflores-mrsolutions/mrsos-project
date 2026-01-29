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
  
  <script src="../js/poliza.js"></script>
  <!-- css -->
  <link href="../css/style.css" rel="stylesheet">


</head>

  <style>
    body.dark-mode {
      background-color: #020617;
      color: #e5e7eb;
    }

    body.dark-mode .card {
      background-color: #020617;
      border-color: #1f2937;
    }

    body.dark-mode .card-header {
      background-color: #020617;
      border-bottom-color: #1f2937;
    }

    body.dark-mode .form-control,
    body.dark-mode .form-select {
      background-color: #020617;
      border-color: #4b5563;
      color: #e5e7eb;
    }

    .poliza-header-title {
      font-size: .8rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #6b7280;
    }

    .poliza-chip {
      font-size: .7rem;
    }

    .equipo-card {
      transition: transform .12s ease, box-shadow .12s ease;
    }

    .equipo-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(15, 23, 42, .18);
    }

    .badge-poliza-vigente {
      background-color: #dcfce7;
      color: #166534;
    }

    .badge-poliza-proxima {
      background-color: #fef9c3;
      color: #854d0e;
    }

    .badge-poliza-vencida {
      background-color: #fee2e2;
      color: #b91c1c;
    }
  </style>


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

      <!-- CONTENIDO PRINCIPAL -->
      <main class="col-md-12 ms-sm-auto col-lg-10 px-lg-4">
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
        <div class="row g-3 mb-3">
          <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
              <div class="card-body">
                <div class="poliza-header-title mb-2">Filtros</div>

                <div class="mb-3">
                  <label class="form-label small">Buscar equipo o póliza</label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="filtroTexto" class="form-control" placeholder="Modelo, SN, póliza...">
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label small">Tipo de póliza</label>
                  <select id="filtroTipoPoliza" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="Platinum">Platinum</option>
                    <option value="Gold">Gold</option>
                    <option value="Silver">Silver</option>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label small">Estado de póliza</label>
                  <select id="filtroEstadoPoliza" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="vigente">Vigente</option>
                    <option value="proxima">Próxima a vencer</option>
                    <option value="vencida">Vencida</option>
                  </select>
                </div>

                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-center">
                  <button class="btn btn-light btn-sm" id="btnLimpiarFiltrosPoliza">
                    Limpiar filtros
                  </button>
                  <small class="text-muted">
                    <span id="lblEquiposFiltrados">0</span> de
                    <span id="lblEquiposTotal">0</span> equipos
                  </small>
                </div>
              </div>
            </div>

            <div class="card border-0 bg-light-subtle mt-3">
              <div class="card-body small text-muted">
                <strong><i class="bi bi-info-circle me-1"></i>Tip</strong><br>
                Aquí sólo se muestran equipos en póliza que tu usuario puede ver según zona/sede.
              </div>
            </div>
          </div>

          <!-- Columna polizas/equipos -->
          <div class="col-12 col-lg-8">
            <div id="contenedorPolizas">
              <!-- Se llena vía JS -->
            </div>

            <div class="text-center text-muted small mt-3 d-none" id="estadoSinPolizas">
              <i class="bi bi-inboxes mb-1" style="font-size:1.6rem;"></i><br>
              No se encontraron equipos en póliza con los filtros actuales.
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- OFFCANVAS DETALLE DE EQUIPO -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasEquipoDetalle">
    <div class="offcanvas-header">
      <div>
        <h5 class="offcanvas-title" id="detTituloEquipo">Detalle de equipo</h5>
        <small class="text-muted" id="detSubtituloEquipo"></small>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body">
      <div class="mb-3 text-center">
        <img id="detImagenEquipo" src="../img/Equipos/default.svg"
          alt="Equipo"
          style="max-height:120px;"
          onerror="this.src='../img/Equipos/default.svg';">
      </div>

      <div class="mb-3">
        <div class="poliza-header-title mb-1">Póliza</div>
        <p class="mb-1" id="detPolizaLinea"></p>
        <p class="mb-0" id="detPolizaFechas"></p>
      </div>

      <div class="mb-3">
        <div class="poliza-header-title mb-1">Cliente y sede</div>
        <p class="mb-1" id="detCliente"></p>
        <p class="mb-0" id="detSede"></p>
      </div>

      <div class="mb-3">
        <div class="poliza-header-title mb-1">Equipo</div>
        <p class="mb-1" id="detModelo"></p>
        <p class="mb-1" id="detSN"></p>
        <p class="mb-0" id="detSO"></p>
      </div>

      <div class="mb-3">
        <div class="poliza-header-title mb-1">Tickets activos</div>
        <p class="mb-0" id="detTickets"></p>
      </div>

      <div class="mb-3">
        <div class="poliza-header-title mb-1">Descripción interna</div>
        <p class="mb-0" id="detDescripcion"></p>
      </div>
    </div>
  </div>

  <!-- Toasts (si no los tienes globales, puedes usar estos IDs) -->
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
    <div id="toastSuccess" class="toast align-items-center text-bg-success border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
    <div id="toastError" class="toast align-items-center text-bg-danger border-0 mt-2" role="alert">
      <div class="d-flex">
        <div class="toast-body"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>


  <!-- Datos de sesión para JS -->
  <script>
    window.SESSION = {
      usId: <?= json_encode($US_ID) ?>,
      clId: <?= json_encode($CL_ID) ?>,
      rol: <?= json_encode($ROL) ?>

    };
  </script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.28.0/dist/feather.min.js" integrity="sha384-uO3SXW5IuS1ZpFPKugNNWqTZRRglnUJK6UAZ/gxOX80nxEkN9NcGZTftn6RzhGWE" crossorigin="anonymous"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js" integrity="sha384-zNy6FEbO50N+Cg5wap8IKA4M/ZnLJgzc6w2NqACZaK0u0FXfOWRRJOnQtpZun8ha" crossorigin="anonymous"></script> -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
  <!-- JS específico de pólizas -->

  <!-- Botón oculto para abrir el offcanvas de equipo -->


</body>

</html>