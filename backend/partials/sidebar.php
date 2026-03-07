<?php
declare(strict_types=1);

$activeMenu = $activeMenu ?? '';

function isActive(string $key, string $activeMenu): string {
    return $key === $activeMenu ? 'active' : '';
}
?>

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
      <a class="nav-link <?= isActive('dashboard', $activeMenu) ?>" href="index.php">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </li>
    <!-- <li class="nav-item">
      <a class="nav-link <?= isActive('crear', $activeMenu) ?>" href="crear_index.php">
        <i class="bi bi-plus"></i> Nuevo
      </a>
    </li> -->
    <li class="nav-item">
      <a class="nav-link <?= isActive('clientes', $activeMenu) ?>" href="clientes_index.php">
        <i class="bi bi-buildings"></i> Clientes
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= isActive('marcas', $activeMenu) ?>" href="marcas_index.php">
        <i class="bi bi-bookmark-star"></i> Marcas
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= isActive('equipos', $activeMenu) ?>" href="equipos_index.php">
        <i class="bi bi-hdd-rack"></i> Equipos
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= isActive('refacciones', $activeMenu) ?>" href="refacciones_index.php">
        <i class="bi bi-cpu"></i> Refacciones
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= isActive('inventario', $activeMenu) ?>" href="inventario_index.php">
        <i class="bi bi-box-seam"></i> Inventario
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
        <a class="nav-link <?= isActive('dashboard', $activeMenu) ?>" href="index.php">
          <i class="bi bi-speedometer2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isActive('clientes', $activeMenu) ?>" href="clientes_index.php">
          <i class="bi bi-buildings"></i> Clientes
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isActive('marcas', $activeMenu) ?>" href="marcas_index.php">
          <i class="bi bi-bookmark-star"></i> Marcas
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isActive('equipos', $activeMenu) ?>" href="equipos_index.php">
          <i class="bi bi-hdd-rack"></i> Equipos
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isActive('refacciones', $activeMenu) ?>" href="refacciones_index.php">
          <i class="bi bi-cpu"></i> Refacciones
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isActive('inventario', $activeMenu) ?>" href="inventario_index.php">
          <i class="bi bi-box-seam"></i> Inventario
        </a>
      </li>
    </ul>
  </div>
</div>