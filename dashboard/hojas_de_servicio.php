<?php
// headers.php (parte superior)
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
  <link href="css/style.css" rel="stylesheet">



  <!-- JS -->
  <script src="../js/hojas_servicio.js"></script>
  <script src="../js/logout.js"></script>
  <!-- /JS -->

</head>

<body>

  <div class="container-fluid">
    <div class="row gx-0">
      <!-- SIDEBAR -->
      <!-- SIDEBAR FIJO (md+) -->
      <?php $activeMenu = 'hs'; ?>
      <?php require_once __DIR__ . '/partials/sidebar_cliente.php'; ?>



      <!-- MAIN -->
      <main class="col-md-10">
        <!-- Top bar -->
        <!-- Contenedor general -->
        <div class="topbar px-3 py-2 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar">
              <i class="bi bi-list"></i>
            </button>
            <a class="btn btn-sm btn-outline-secondary" href="home.php"><i class="bi bi-arrow-left"></i></a>
            <span class="badge text-bg-success rounded-pill px-3">Activo</span>
            <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['usUsername'] ?? 'Admin'); ?></span>
          </div>

          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="btnThemeDesktop" type="button" title="Tema">
              <i class="bi bi-moon"></i>
            </button>
            <a class="btn btn-sm btn-outline-danger" href="logout.php" title="Salir">
              <i class="bi bi-box-arrow-right"></i>
            </a>
          </div>
        </div>
        <!-- Filtros -->
         <div class="px-3 py-3">
        <div class="card my-3">
          <div class="card-body">
            <form id="filtroHS" class="row g-2 align-items-end">
              <div class="col-12 col-md-3">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" id="hsDesde">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" id="hsHasta">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label">Tipo de equipo</label>
                <select class="form-select" id="hsTipoEquipo">
                  <option value="">Todos</option>
                  <option>Servidor</option>
                  <option>Storage</option>
                  <option>Switch</option>
                  <option>Firewall</option>
                  <option>PC</option>
                  <option>Laptop</option>
                  <option>Software</option>
                </select>
              </div>
              <div class="col-12 col-md-3 text-end">
                <button type="button" id="hsBtnFiltrar" class="btn btn-primary">Aplicar</button>
                <button type="button" id="hsBtnReset" class="btn btn-outline-secondary">Reset</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Listado -->
        <div id="wrapHojas" class="row g-3"></div>



</body>



<script src="js/theme.js"></script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons@4.28.0/dist/feather.min.js" integrity="sha384-uO3SXW5IuS1ZpFPKugNNWqTZRRglnUJK6UAZ/gxOX80nxEkN9NcGZTftn6RzhGWE" crossorigin="anonymous"></script>
<!-- <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js" integrity="sha384-zNy6FEbO50N+Cg5wap8IKA4M/ZnLJgzc6w2NqACZaK0u0FXfOWRRJOnQtpZun8ha" crossorigin="anonymous"></script> -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

</html>