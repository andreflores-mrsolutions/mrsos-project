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
$usNombre  = $_SESSION['usNombre']  ?? ($_SESSION['usUsuario'] ?? 'Usuario');
$usCorreo  = $_SESSION['usCorreo']  ?? '';
$usTelefono = $_SESSION['usTelefono'] ?? '';
$usRol     = $_SESSION['usRol']     ?? '';
$clNombre  = $_SESSION['clNombre']  ?? '';

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

$prefTheme = 'light';
$prefNotifInApp = 1;
$prefNotifMail = 1;
$prefNotifTicketCambio = 1;
$prefNotifMeet = 1;
$prefNotifVisita = 1;
$prefNotifFolio = 1;

if ($US_ID) {
  $sqlPref = "SELECT usTheme, usNotifInApp, usNotifMail,
                     usNotifTicketCambio, usNotifMeet,
                     usNotifVisita, usNotifFolio
              FROM usuarios
              WHERE usId = ?";
  if ($stmtP = $conectar->prepare($sqlPref)) {
    $stmtP->bind_param("i", $US_ID);
    if ($stmtP->execute()) {
      $resP = $stmtP->get_result();
      if ($rowP = $resP->fetch_assoc()) {
        $prefTheme             = $rowP['usTheme']             ?? 'light';
        $prefNotifInApp        = (int)($rowP['usNotifInApp']        ?? 1);
        $prefNotifMail         = (int)($rowP['usNotifMail']         ?? 1);
        $prefNotifTicketCambio = (int)($rowP['usNotifTicketCambio'] ?? 1);
        $prefNotifMeet         = (int)($rowP['usNotifMeet']         ?? 1);
        $prefNotifVisita       = (int)($rowP['usNotifVisita']       ?? 1);
        $prefNotifFolio        = (int)($rowP['usNotifFolio']        ?? 1);
      }
    }
    $stmtP->close();
  }
}


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
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <title>Configuración · MRSoS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Tu CSS principal si aplica -->
  <link rel="stylesheet" href="../css/style.css">
  <style>
    body.mrsos-dark {
      background-color: #111827;
      color: #e5e7eb;
    }

    body.mrsos-dark .card {
      background-color: #1f2937;
      border-color: #374151;
    }

    body.mrsos-dark .form-control,
    body.mrsos-dark .form-select {
      background-color: #111827;
      border-color: #4b5563;
      color: #e5e7eb;
    }

    body.mrsos-dark .form-control:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 .2rem rgba(99, 102, 241, .35);
    }

    body.mrsos-dark .list-group-item {
      background-color: #111827;
      color: #e5e7eb;
      border-color: #1f2937;
    }

    .config-section-title {
      font-size: .8rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #9ca3af;
    }

    .config-badge-role {
      font-size: .7rem;
      text-transform: uppercase;
      letter-spacing: .06em;
    }
  </style>
</head>
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

        <!-- CONTENIDO PRINCIPAL -->
        <main class="container py-4">
          <div class="row g-4">
            <!-- Columna lateral (secciones) -->
            <div class="col-12 col-lg-4">
              <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                  <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0 me-3">
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
                    </div>
                    <div>
                      <div class="fw-semibold">
                        <?php echo htmlspecialchars($usNombre, ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                      <div class="small text-muted">
                        <?php echo htmlspecialchars($usCorreo, ENT_QUOTES, 'UTF-8'); ?>
                      </div>
                      <?php if ($usRol): ?>
                        <div class="mt-1">
                          <span class="badge bg-primary-subtle text-primary-emphasis config-badge-role">
                            <?php echo htmlspecialchars($usRol, ENT_QUOTES, 'UTF-8'); ?>
                          </span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <?php if ($clNombre): ?>
                    <div class="small text-muted mb-2">
                      Cliente: <span class="fw-semibold"><?php echo htmlspecialchars($clNombre, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                  <?php endif; ?>

                  <hr>

                  <div class="config-section-title mb-2">Secciones</div>
                  <div class="list-group list-group-flush">
                    <a href="#sec-perfil" class="list-group-item list-group-item-action small">
                      <i class="bi bi-person-badge me-2"></i> Perfil
                    </a>
                    <a href="#sec-tema" class="list-group-item list-group-item-action small">
                      <i class="bi bi-moon-stars me-2"></i> Modo oscuro
                    </a>
                    <a href="#sec-notificaciones" class="list-group-item list-group-item-action small">
                      <i class="bi bi-bell me-2"></i> Notificaciones
                    </a>
                  </div>
                </div>
              </div>

              <div class="card border-0 bg-light-subtle">
                <div class="card-body small text-muted">
                  <div class="fw-semibold mb-1">
                    <i class="bi bi-info-circle me-1"></i> Tip
                  </div>
                  Personaliza tu experiencia en MRSoS. Estos cambios sólo afectan tu usuario.
                </div>
              </div>
            </div>

            <!-- Columna principal -->
            <div class="col-12 col-lg-8">
              <div class="card shadow-sm border-0 mb-4" id="sec-perfil">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Perfil</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnEditarPerfil">
                      <i class="bi bi-pencil me-1"></i>Editar
                    </button>
                  </div>
                  <p class="text-muted small mb-3">
                    Estos datos identifican tu cuenta dentro de MRSoS.
                  </p>

                  <?php
                  $usAPaterno = $_SESSION['usAPaterno'] ?? '';
                  $usAMaterno = $_SESSION['usAMaterno'] ?? '';
                  $usUsername = $_SESSION['usUsername'] ?? '';
                  ?>

                  <form id="formPerfil" autocomplete="off" enctype="multipart/form-data">
                    <div class="row g-3">
                      <div class="col-md-4 text-center">
                        <?php
                        // Reutilizamos la lógica de avatar
                        $usuario  = $usUsername !== '' ? $usUsername : ($_SESSION['usUsername'] ?? 'default');
                        $usuario  = preg_replace('/[^A-Za-z0-9_\-]/', '', $usuario);
                        $dirFS  = __DIR__ . '/../img/Usuario/';
                        $dirURL = '../img/Usuario/';
                        $extsPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        $avatarSrc = $dirURL . 'user.webp';
                        foreach ($extsPermitidas as $ext) {
                          $fs = $dirFS . $usuario . '.' . $ext;
                          if (is_file($fs)) {
                            $avatarSrc = $dirURL . $usuario . '.' . $ext . '?v=' . filemtime($fs);
                            break;
                          }
                        }
                        ?>
                        <img id="previewAvatar"
                          src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES) ?>"
                          class="rounded-circle mb-2"
                          alt="Avatar"
                          style="width:96px; height:96px; object-fit:cover; box-shadow:0 1px 4px rgba(0,0,0,0.2);">
                        <div class="small text-muted mb-1">Imagen de perfil</div>
                        <input type="file"
                          class="form-control form-control-sm"
                          id="usAvatar"
                          name="usAvatar"
                          accept="image/*"
                          disabled>
                        <div class="form-text small">
                          Máx. 2MB · JPG, PNG o WEBP
                        </div>
                      </div>

                      <div class="col-md-8">
                        <div class="row g-3">
                          <div class="col-md-6">
                            <label class="form-label small">Nombre</label>
                            <input type="text" class="form-control" name="usNombre"
                              value="<?php echo htmlspecialchars($usNombre, ENT_QUOTES, 'UTF-8'); ?>"
                              disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Apellido paterno</label>
                            <input type="text" class="form-control" name="usAPaterno"
                              value="<?php echo htmlspecialchars($usAPaterno, ENT_QUOTES, 'UTF-8'); ?>"
                              disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Apellido materno</label>
                            <input type="text" class="form-control" name="usAMaterno"
                              value="<?php echo htmlspecialchars($usAMaterno, ENT_QUOTES, 'UTF-8'); ?>"
                              disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Usuario</label>
                            <input type="text" class="form-control" name="usUsername"
                              value="<?php echo htmlspecialchars($usUsername, ENT_QUOTES, 'UTF-8'); ?>"
                              disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Correo</label>
                            <input type="email" class="form-control" name="usCorreo"
                              value="<?php echo htmlspecialchars($usCorreo, ENT_QUOTES, 'UTF-8'); ?>"
                              disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Teléfono</label>
                            <input type="text" class="form-control" name="usTelefono"
                              value="<?php echo htmlspecialchars($usTelefono, ENT_QUOTES, 'UTF-8'); ?>"
                              disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Rol</label>
                            <input type="text" class="form-control"
                              value="<?php echo htmlspecialchars($usRol ?: '—', ENT_QUOTES, 'UTF-8'); ?>"
                              disabled>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2">
                      <button type="button" class="btn btn-primary btn-sm" id="btnGuardarPerfil" disabled>
                        <i class="bi bi-check2 me-1"></i>Guardar cambios
                      </button>
                      <button type="button" class="btn btn-light btn-sm" id="btnCancelarPerfil" disabled>
                        Cancelar
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCambiarPass">
                        <i class="bi bi-shield-lock me-1"></i>Cambiar contraseña
                      </button>
                    </div>
                    <div class="form-text mt-2">
                      Estos cambios afectan únicamente tu usuario. La contraseña se gestiona en la opción "Cambiar contraseña".
                    </div>
                  </form>
                </div>
              </div>


              <div class="card shadow-sm border-0 mb-4" id="sec-tema">
                <div class="card-body">
                  <h5 class="mb-2"><i class="bi bi-moon-stars me-2"></i>Modo oscuro</h5>
                  <p class="text-muted small mb-3">
                    Cambia el tema de la plataforma entre claro y oscuro. Esta preferencia se guarda en tu navegador.
                  </p>

                  <div class="d-flex align-items-center justify-content-between">
                    <div>
                      <div class="fw-semibold mb-1">Tema de la interfaz</div>
                      <div class="text-muted small">
                        Activa el modo oscuro para trabajar más cómodo en ambientes con poca luz.
                      </div>
                    </div>
                    <div class="form-check form-switch fs-5 mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="switchDarkMode1">
                      <label class="form-check-label small ms-2" for="switchDarkMode1">
                        <span id="labelDarkMode">Modo claro</span>
                      </label>
                    </div>
                  </div>

                </div>
              </div>

              <div class="card shadow-sm border-0" id="sec-notificaciones">
                <div class="card-body">
                  <h5 class="mb-2"><i class="bi bi-bell me-2"></i>Notificaciones</h5>
                  <p class="text-muted small mb-3">
                    Decide cómo quieres que MRSoS te notifique sobre cambios en tus tickets.
                  </p>

                  <div class="config-section-title mb-2">Canales</div>

                  <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <div class="fw-semibold">Notificaciones dentro de la plataforma</div>
                        <div class="text-muted small">
                          Mensajes en el dashboard cuando haya cambios importantes.
                        </div>
                      </div>
                      <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="notifInApp">
                      </div>
                    </div>
                  </div>

                  <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <div class="fw-semibold">Notificaciones por correo</div>
                        <div class="text-muted small">
                          Te enviaremos correos cuando haya cambios relevantes en tus tickets.
                        </div>
                      </div>
                      <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="notifCorreo">
                      </div>
                    </div>
                  </div>

                  <hr>

                  <div class="config-section-title mb-2">Eventos</div>

                  <div class="form-check small mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="notifTicketCambio">
                    <label class="form-check-label" for="notifTicketCambio">
                      Cambios de estado en mis tickets
                    </label>
                  </div>

                  <div class="form-check small mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="notifMeet">
                    <label class="form-check-label" for="notifMeet">
                      Asignación / cambios de Meet
                    </label>
                  </div>

                  <div class="form-check small mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="notifVisita">
                    <label class="form-check-label" for="notifVisita">
                      Asignación / cambios de ventana de visita
                    </label>
                  </div>

                  <div class="form-check small mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="notifFolio">
                    <label class="form-check-label" for="notifFolio">
                      Generación de folio de entrada
                    </label>
                  </div>

                  <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnGuardarNotifs">
                      <i class="bi bi-save me-1"></i>Guardar preferencias
                    </button>
                  </div>

                  <div class="form-text mt-2">
                    Estas preferencias se guardan actualmente en tu navegador (localStorage). Más adelante podemos enlazarlas a tu usuario en base de datos.
                  </div>
                </div>
              </div>

            </div>
          </div>
        </main>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

        <script>
          // ==== SESIÓN Y PREFERENCIAS INYECTADAS DESDE PHP ====
          window.SESSION = {
            rol: <?= json_encode($ROL) ?>,
            clId: <?= json_encode((int)$CL_ID) ?>,
            usId: <?= json_encode((int)$US_ID) ?>,
            canCreateTicket: <?= $CAN_CREATE ? 'true' : 'false' ?>
          };

          window.USER_PREFS = {
            theme: <?= json_encode($prefTheme) ?>, // 'light' | 'dark'
            notifInApp: <?= json_encode((bool)$prefNotifInApp) ?>,
            notifMail: <?= json_encode((bool)$prefNotifMail) ?>,
            notifTicketCambio: <?= json_encode((bool)$prefNotifTicketCambio) ?>,
            notifMeet: <?= json_encode((bool)$prefNotifMeet) ?>,
            notifVisita: <?= json_encode((bool)$prefNotifVisita) ?>,
            notifFolio: <?= json_encode((bool)$prefNotifFolio) ?>
          };

          // ==== HELPERS DE COOKIES PARA EL TEMA ====
          const THEME_COOKIE = 'mrs_theme';
          const DARK_KEY = 'mrsos_dark_mode'; // para compat con lo que ya usabas

          function setCookie(name, value, days = 365) {
            const d = new Date();
            d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = `${name}=${encodeURIComponent(value)};expires=${d.toUTCString()};path=/;SameSite=Lax`;
          }

          function getCookie(name) {
            const m = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return m ? decodeURIComponent(m[2]) : null;
          }

          // ==== TEMA UNIFICADO PARA TODA LA APP ====
          function applyTheme(mode, {
            saveCookie = true,
            saveStorage = true
          } = {}) {
            const isDark = (mode === 'dark');

            // Clases en body (compatibilidad con estilos viejos y nuevos)
            document.body.classList.toggle('dark-mode', isDark);
            document.body.classList.toggle('mrsos-dark', isDark);

            // Cookie + localStorage
            if (saveCookie) {
              setCookie(THEME_COOKIE, isDark ? 'dark' : 'light');
            }
            if (saveStorage) {
              localStorage.setItem(DARK_KEY, isDark ? '1' : '0');
            }

            // Sincronizar iconos de los botones de tema (header)
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

            // Sincronizar switch de configuración
            const switchDark = document.getElementById('switchDarkMode');
            const labelDark = document.getElementById('labelDarkMode');
            if (switchDark) switchDark.checked = isDark;
            if (labelDark) labelDark.textContent = isDark ? 'Modo oscuro' : 'Modo claro';
          }

          function initThemeFromPrefs() {
            const prefs = window.USER_PREFS || {};
            let initial = (prefs.theme === 'dark' || prefs.theme === 'light') ? prefs.theme : null;

            if (!initial) {
              // 1) Cookie
              const cookieTheme = getCookie(THEME_COOKIE);
              if (cookieTheme === 'dark' || cookieTheme === 'light') {
                initial = cookieTheme;
              } else {
                // 2) localStorage viejo
                const savedDark = localStorage.getItem(DARK_KEY);
                if (savedDark === '1') initial = 'dark';
                else if (savedDark === '0') initial = 'light';
                else {
                  // 3) prefers-color-scheme
                  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                  initial = prefersDark ? 'dark' : 'light';
                }
              }
            }

            applyTheme(initial, {
              saveCookie: false,
              saveStorage: false
            });
          }

          // ==== PERFIL + NOTIFICACIONES + TEMA (TODO JUNTO) ====
          document.addEventListener('DOMContentLoaded', () => {
            const body = document.body;

            // 1) Tema inicial desde BD / cookie / localStorage
            initThemeFromPrefs();

            // Referencias de controles de tema
            const switchDark = document.getElementById('switchDarkMode');
            const labelDark = document.getElementById('labelDarkMode');
            const btnThemeDesktop = document.getElementById('btnThemeDesktop');
            const btnThemeMobile = document.getElementById('btnThemeMobile');

            // Sync inicial del switch de configuración con el body
            (function syncSwitchLabel() {
              const isDark = body.classList.contains('dark-mode');
              if (switchDark) switchDark.checked = isDark;
              if (labelDark) labelDark.textContent = isDark ? 'Modo oscuro' : 'Modo claro';
            })();

            // --- Cambio de tema desde el switch de Configuración ---
            if (switchDark) {
              switchDark.addEventListener('change', () => {
                const nextMode = switchDark.checked ? 'dark' : 'light';
                applyTheme(nextMode);
              });
            }

            // --- Botón de tema en Desktop ---
            if (btnThemeDesktop) {
              btnThemeDesktop.addEventListener('click', () => {
                const current = body.classList.contains('dark-mode') ? 'dark' : 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                applyTheme(next);
              });
            }

            // --- Botón de tema en Mobile (dentro del dropdown) ---
            if (btnThemeMobile) {
              btnThemeMobile.addEventListener('click', (e) => {
                e.preventDefault();
                const current = body.classList.contains('dark-mode') ? 'dark' : 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                applyTheme(next);

                // Cerrar dropdown en móvil si está abierto (Bootstrap)
                const dropdownEl = document.getElementById('moreActions');
                if (dropdownEl && window.bootstrap) {
                  const bsDropdown = bootstrap.Dropdown.getInstance(dropdownEl);
                  if (bsDropdown) bsDropdown.hide();
                }
              });
            }

            // ===== PERFIL: habilitar/inhabilitar edición + guardar backend =====
            const formPerfil = document.getElementById('formPerfil');
            const btnEditar = document.getElementById('btnEditarPerfil');
            const btnGuardar = document.getElementById('btnGuardarPerfil');
            const btnCancelar = document.getElementById('btnCancelarPerfil');
            const btnCambiarPass = document.getElementById('btnCambiarPass');
            const inputAvatar = document.getElementById('usAvatar');
            const imgPreview = document.getElementById('previewAvatar');

            if (btnEditar && formPerfil) {
              btnEditar.addEventListener('click', () => {
                formPerfil.querySelectorAll('input[name="usNombre"], input[name="usAPaterno"], input[name="usAMaterno"], input[name="usCorreo"], input[name="usTelefono"], input[name="usUsername"]')
                  .forEach(el => el.removeAttribute('disabled'));

                if (inputAvatar) inputAvatar.removeAttribute('disabled');

                btnGuardar.disabled = false;
                btnCancelar.disabled = false;
              });
            }

            if (btnCancelar && formPerfil) {
              btnCancelar.addEventListener('click', () => {
                formPerfil.reset();
                formPerfil.querySelectorAll('input[name="usNombre"], input[name="usAPaterno"], input[name="usAMaterno"], input[name="usCorreo"], input[name="usTelefono"], input[name="usUsername"]')
                  .forEach(el => el.setAttribute('disabled', 'disabled'));

                if (inputAvatar) {
                  inputAvatar.value = '';
                  inputAvatar.setAttribute('disabled', 'disabled');
                }

                btnGuardar.disabled = true;
                btnCancelar.disabled = true;
              });
            }

            // Preview simple de avatar
            if (inputAvatar && imgPreview) {
              inputAvatar.addEventListener('change', () => {
                const file = inputAvatar.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = e => {
                  imgPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
              });
            }

            if (btnGuardar && formPerfil) {
              btnGuardar.addEventListener('click', () => {
                const fd = new FormData(formPerfil);

                fetch('../php/actualizar_perfil.php', {
                    method: 'POST',
                    body: fd
                  })
                  .then(r => r.json())
                  .then(res => {
                    if (res.success) {
                      Swal.fire({
                        title: "Perfil actualizado",
                        text: "Tus datos se han guardado correctamente.",
                        icon: "success"
                      }).then(() => {
                        // Volver a bloquear campos
                        formPerfil.querySelectorAll('input[name="usNombre"], input[name="usAPaterno"], input[name="usAMaterno"], input[name="usCorreo"], input[name="usTelefono"], input[name="usUsername"]')
                          .forEach(el => el.setAttribute('disabled', 'disabled'));

                        if (inputAvatar) {
                          inputAvatar.setAttribute('disabled', 'disabled');
                        }

                        btnGuardar.disabled = true;
                        btnCancelar.disabled = true;

                        // Opcional: recargar para que header y otros sitios vean el nuevo nombre/avatar
                        // location.reload();
                      });
                    } else {
                      Swal.fire({
                        title: "Error",
                        text: res.error || "No fue posible guardar tu perfil.",
                        icon: "error"
                      });
                    }
                  })
                  .catch(() => {
                    Swal.fire({
                      title: "Error",
                      text: "Error de red al intentar guardar tu perfil.",
                      icon: "error"
                    });
                  });
              });
            }

            if (btnCambiarPass) {
              btnCambiarPass.addEventListener('click', () => {
                window.location.href = 'cambiar_password.php';
              });
            }


            // ===== NOTIFICACIONES (BD + localStorage) =====
            const notifKeys = {
              inApp: 'mrsos_notif_inapp',
              correo: 'mrsos_notif_mail',
              cambio: 'mrsos_notif_ticket',
              meet: 'mrsos_notif_meet',
              visita: 'mrsos_notif_visita',
              folio: 'mrsos_notif_folio'
            };

            const elInApp = document.getElementById('notifInApp');
            const elCorreo = document.getElementById('notifCorreo');
            const elCambio = document.getElementById('notifTicketCambio');
            const elMeet = document.getElementById('notifMeet');
            const elVisita = document.getElementById('notifVisita');
            const elFolio = document.getElementById('notifFolio');
            const btnSaveN = document.getElementById('btnGuardarNotifs');

            const prefs = window.USER_PREFS || {};

            function initNotifFromPrefs(el, key, bdValue, def = true) {
              if (!el) return;
              // Si hay valor en BD (ya guardado), manda eso
              if (typeof bdValue === 'boolean') {
                el.checked = bdValue;
                return;
              }
              // Si no hay BD (teórico), usamos localStorage o default
              const val = localStorage.getItem(key);
              el.checked = val === null ? def : val === '1';
            }

            // Cargar estado inicial (BD > localStorage)
            initNotifFromPrefs(elInApp, notifKeys.inApp, prefs.notifInApp);
            initNotifFromPrefs(elCorreo, notifKeys.correo, prefs.notifMail);
            initNotifFromPrefs(elCambio, notifKeys.cambio, prefs.notifTicketCambio);
            initNotifFromPrefs(elMeet, notifKeys.meet, prefs.notifMeet);
            initNotifFromPrefs(elVisita, notifKeys.visita, prefs.notifVisita);
            initNotifFromPrefs(elFolio, notifKeys.folio, prefs.notifFolio);

            function saveNotifLocal(el, key) {
              if (!el) return;
              localStorage.setItem(key, el.checked ? '1' : '0');
            }

            if (btnSaveN) {
              btnSaveN.addEventListener('click', () => {
                // 1) Guardar en localStorage (cache local)
                saveNotifLocal(elInApp, notifKeys.inApp);
                saveNotifLocal(elCorreo, notifKeys.correo);
                saveNotifLocal(elCambio, notifKeys.cambio);
                saveNotifLocal(elMeet, notifKeys.meet);
                saveNotifLocal(elVisita, notifKeys.visita);
                saveNotifLocal(elFolio, notifKeys.folio);

                // 2) Preparar payload para BD
                const themeIsDark = body.classList.contains('dark-mode');

                const payload = {
                  theme: themeIsDark ? 'dark' : 'light',
                  notifInApp: !!(elInApp?.checked),
                  notifMail: !!(elCorreo?.checked),
                  notifTicketCambio: !!(elCambio?.checked),
                  notifMeet: !!(elMeet?.checked),
                  notifVisita: !!(elVisita?.checked),
                  notifFolio: !!(elFolio?.checked)
                };

                fetch('../php/guardar_preferencias.php', {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                  })
                  .then(r => r.json())
                  .then(res => {
                    if (res.success) {
                      Swal.fire({
                        title: "Éxito",
                        text: "Preferencias guardadas con éxito.",
                        icon: "success"
                      });
                    } else {
                      Swal.fire({
                        title: "Error",
                        text: res.error || 'No se pudieron guardar las preferencias en el servidor.',
                        icon: "error"
                      });
                    }
                  })
                  .catch(() => {
                    Swal.fire({
                      title: "Error",
                      text: "Error de red al guardar preferencias.",
                      icon: "error"
                    });
                  });
              });
            }
          });
        </script>

</body>

</html>