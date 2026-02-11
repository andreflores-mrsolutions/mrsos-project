<?php
declare(strict_types=1);

/* =========================
 *  Sesión y dependencias
 * ========================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../php/conexion.php';
require_once __DIR__ . '/../php/csrf.php';
require_once __DIR__ . '/../php/json.php';
require_once __DIR__ . '/../php/admin_bootstrap.php';


/* =========================
 *  Seguridad básica
 * ========================= */
$ROL   = $_SESSION['usRol'] ?? null;
$US_ID = $_SESSION['usId']  ?? null;
$CL_ID = $_SESSION['clId']  ?? null;

$ROLES_PERMITIDOS = ['CLI','MRV','MRA','MRSA'];

if (!$US_ID || !$ROL || !in_array($ROL, $ROLES_PERMITIDOS, true)) {
    http_response_code(403);
    exit('Acceso no autorizado');
}

/* =========================
 *  CSRF para JS
 * ========================= */
$csrf = csrf_token();

/* =========================
 *  Flags por rol
 * ========================= */
$isMR   = in_array($ROL, ['MRV','MRA','MRSA'], true);
$isMRSA = ($ROL === 'MRSA');
$isMRA  = ($ROL === 'MRA');
$isMRV  = ($ROL === 'MRV');

/* =========================
 *  Permisos UI
 * ========================= */
$CAN_CREATE_TICKET = in_array($ROL, ['CLI','MRA','MRSA'], true);

/* =========================
 *  Tema
 * ========================= */
$theme = $_COOKIE['mrs_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>MR Solutions | Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- CSRF global -->
<script>
window.MRS_CSRF = <?= json_encode($csrf) ?>;
window.SESSION = {
    usId: <?= (int)$US_ID ?>,
    clId: <?= json_encode($CL_ID) ?>,
    rol: <?= json_encode($ROL) ?>,
    isMR: <?= $isMR ? 'true' : 'false' ?>,
    canCreateTicket: <?= $CAN_CREATE_TICKET ? 'true' : 'false' ?>
};
</script>

<!-- Librerías -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="stylesheet" href="../css/style.css">
</head>

<body class="<?= $theme === 'dark' ? 'dark-mode' : '' ?>">

<!-- =========================
     Layout
========================= -->
<div class="container-fluid">
<div class="row">

<!-- =========================
     Sidebar
========================= -->
<nav class="col-lg-2 d-none d-lg-block sidebar p-3">
    <img src="../img/image.png" class="img-fluid mb-4 rounded-pill" alt="MR Solutions">

    <ul class="nav nav-pills flex-column gap-2">
        <li class="nav-item">
            <a class="nav-link active" href="home.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>

        <?php if ($CAN_CREATE_TICKET): ?>
        <li class="nav-item">
            <a class="nav-link" href="nuevo_ticket.php">
                <i class="bi bi-plus-circle"></i> Nuevo Ticket
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item">
            <a class="nav-link" href="misequipos.php">
                <i class="bi bi-cpu"></i> Mis equipos
            </a>
        </li>

        <?php if ($isMR): ?>
        <li class="nav-item">
            <a class="nav-link" href="admin_usuarios.php">
                <i class="bi bi-shield-lock"></i> Administración
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item mt-4">
            <a class="nav-link text-danger" href="../php/logout.php">
                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
            </a>
        </li>
    </ul>
</nav>

<!-- =========================
     Main
========================= -->
<main class="col-lg-10 px-4">

<!-- Header -->
<div class="d-flex justify-content-between align-items-center py-3">
    <h4 class="mb-0">Dashboard</h4>
    <div>
        <span class="badge bg-secondary"><?= htmlspecialchars($ROL) ?></span>
    </div>
</div>

<!-- =========================
     Tickets por sede (API)
========================= -->
<div class="card mrs-card mb-4">
    <div class="card-body">
        <h5 class="mb-2">Tickets por sede</h5>
        <small class="text-muted">
            Información en tiempo real de tickets abiertos.
        </small>

        <div id="wrapTicketsSedes" class="mt-3">
            <div class="text-muted">Cargando tickets…</div>
        </div>
    </div>
</div>

<!-- =========================
     Estadísticas
========================= -->
<div class="card mrs-card">
    <div class="card-body">
        <h5 class="mb-3">Estadísticas</h5>
        <canvas id="chartTickets"></canvas>
    </div>
</div>

</main>
</div>
</div>

<script src="../js/main.js"></script>
<script src="../js/tickets.js"></script>

<script>
/* =========================
   Tema claro / oscuro
========================= */
(function () {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
    document.body.dataset.theme = theme || (prefersDark ? 'dark' : 'light');
})();
</script>

</body>
</html>
