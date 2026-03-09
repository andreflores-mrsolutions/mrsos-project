<?php

declare(strict_types=1);

$activeMenu = $activeMenu ?? '';

function isActive(string $key, string $activeMenu): string
{
    return $key === $activeMenu ? 'active' : '';
}


$theme = $_COOKIE['mrs_theme'] ?? 'light';

?>
<script>
    const CL_ID = <?= (int)($_SESSION['clId'] ?? 0) ?>;
</script>
<!-- SIDEBAR DESKTOP -->



<nav id="sidebar" class="col-12 col-md-3 col-lg-2 d-none d-lg-block p-3 mr-side">
    <div class="brand mb-3 px-2">
        <a class="navbar-brand" href="#">
            <img src="../img/image.png" alt="Logo" class="rounded-pill" style="max-width:120px;">
        </a>
    </div>

    <div class="section-title px-2">Operación</div>
    <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item">
            <a class="nav-link <?= isActive('dashboard', $activeMenu) ?>" href="home.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <div class="section-subtitle ">Tickets</div>
        <ul class="nav nav-pills flex-column gap-1">
            <li class="nav-item"><a class="nav-link <?= isActive('ticket', $activeMenu) ?>" href="nuevo_ticket.php"><i class="bi bi-plus-circle"></i> Nuevo Ticket</a></li>
            <li class="nav-item"><a class="nav-link <?= isActive('health', $activeMenu) ?>" href="nuevo_health.php"><i class="bi bi-plus-circle"></i> Nuevo Health Check</a></li>
        </ul>
        <div class="section-subtitle ">Documentos</div>
        <ul class="nav nav-pills flex-column gap-1">
            <li class="nav-item"><a class="nav-link <?= isActive('poliza', $activeMenu) ?>" href="poliza.php"><i class="bi bi-file-binary"></i> Poliza</a></li>
            <li class="nav-item"><a class="nav-link <?= isActive('hs', $activeMenu) ?>" href="hojas_de_servicio.php"><i class="bi bi-plus-circle"></i> Mis Hojas de Servicio</a></li>
        </ul>
        <div class="section-subtitle ">Más</div>
        <li class="nav-item">
            <a class="nav-link <?= isActive('clientes', $activeMenu) ?>" href="configuracion.php">
                <i class="bi bi-buildings"></i> Mis datos/Configuración
            </a>
        </li>
        
        <div class="section-subtitle ">Mis usuarios</div>

        <li class="nav-item">
            <a class="nav-link <?= isActive('panel', $activeMenu) ?>" href="admin_usuarios.php">
                <i class="bi bi-hdd-rack"></i> Panel Administrador
            </a>
        </li>

    </ul>
</nav>

<!-- OFFCANVAS MOBILE/TABLET -->
<div class="offcanvas offcanvas-start mr-side" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
    <div class="p-3 d-flex align-items-center justify-content-between border-bottom border-light border-opacity-10">
        <div class="brand">
            <a class="navbar-brand" href="#">
                <img src="../img/image.png" alt="Logo" class="rounded-pill" style="max-width:120px;">
            </a>
        </div>

        <button type="button" class="btn btn-outline-light close-btn" data-bs-dismiss="offcanvas" aria-label="Close">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <div class="offcanvas-body p-3">
        <div class="section-title px-2">Operación</div>
        <ul class="nav nav-pills flex-column gap-1">
            <li class="nav-item">
                <a class="nav-link <?= isActive('dashboard', $activeMenu) ?>" href="home.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <div class="section-subtitle ">Tickets</div>
            <ul class="nav nav-pills flex-column gap-1">
                <li class="nav-item"><a class="nav-link <?= isActive('ticket', $activeMenu) ?>" href="nuevo_ticket.php"><i class="bi bi-plus-circle"></i> Nuevo Ticket</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive('health', $activeMenu) ?>" href="nuevo_health.php"><i class="bi bi-plus-circle"></i> Nuevo Health Check</a></li>
            </ul>
            <div class="section-subtitle ">Tickets</div>
            <ul class="nav nav-pills flex-column gap-1">
                <li class="nav-item"><a class="nav-link <?= isActive('poliza', $activeMenu) ?>" href="poliza.php"><i class="bi bi-file-binary"></i> Poliza</a></li>

            </ul>
            <div class="section-subtitle ">Más</div>
            <li class="nav-item">
                <a class="nav-link <?= isActive('clientes', $activeMenu) ?>" href="configuracion.php">
                    <i class="bi bi-buildings"></i> Mis datos/Configuración
                </a>
            </li>
            <div class="section-subtitle ">Mis archivos</div>
            <ul class="nav nav-pills flex-column gap-1">
                <li class="nav-item"><a class="nav-link <?= isActive('hs', $activeMenu) ?>" href="hojas_de_servicio.php"><i class="bi bi-plus-circle"></i> Mis Hojas de Servicio</a></li>
                <li class="nav-item"><a class="nav-link <?= isActive('poliza', $activeMenu) ?>" href="poliza.php"><i class="bi bi-plus-circle"></i> Mi(s) Poliza(s)</a></li>
            </ul>

            <li class="nav-item">
                <a class="nav-link <?= isActive('panel', $activeMenu) ?>" href="admin_usuarios.php">
                    <i class="bi bi-hdd-rack"></i> Panel Administrador
                </a>
            </li>

        </ul>
    </div>
</div>