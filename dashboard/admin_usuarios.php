<?php
// admin_usuarios.php
declare(strict_types=1);
session_start();

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

if (empty($_SESSION['usId']) || empty($_SESSION['clId'])) {
  header('Location: ../login/login.php');
  exit;
}

$US_ID      = (int)$_SESSION['usId'];
$CL_ID      = (int)$_SESSION['clId'];
$US_NOMBRE  = $_SESSION['usNombre']   ?? 'Usuario';
$US_CORREO  = $_SESSION['usCorreo']   ?? '';
$US_USERNAME = $_SESSION['usUsername'] ?? '';
$US_ROL_SYS = $_SESSION['usRol']      ?? 'CLI'; // CLI | MRA | MRV | MRSA

// Cargar nombre del cliente (solo para encabezado)
$clNombre = '';
if ($stmt = $conectar->prepare("SELECT clNombre FROM clientes WHERE clId = ?")) {
  $stmt->bind_param("i", $CL_ID);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $clNombre = $row['clNombre'];
  }
  $stmt->close();
}
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <title>Administración de usuarios · MRSoS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Tu CSS principal -->
  <link href="../css/style.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">


</head>

<body class="<?php echo ($theme === 'dark') ? 'dark-mode' : ''; ?>">

  <div class="container-fluid">
    <div class="row gx-0">
      <!-- SIDEBAR -->
      <!-- SIDEBAR FIJO (md+) -->
      <?php $activeMenu = 'panel'; ?>
      <?php require_once __DIR__ . '/partials/sidebar_cliente.php'; ?>



      <!-- MAIN -->
      <main class="col-md-12 ms-sm-auto col-lg-10 px-lg-4">
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

        <div class="px-3 py-3">
          <!-- Row principal -->
          <div class="row g-3">
            <!-- Col filtros -->
            <div class="col-12 col-lg-3">
              <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                  <button type="button" class="btn btn-primary btn-sm" id="btnCrearUsuario">
                    <i class="bi bi-person-plus me-1"></i>Nuevo usuario
                  </button>
                  <div class="mb-3">
                    <div class="filtro-label mb-1">Búsqueda</div>
                    <div class="input-group input-group-sm">
                      <span class="input-group-text"><i class="bi bi-search"></i></span>
                      <input type="text" id="txtBuscarUsuario" class="form-control" placeholder="Nombre, correo o usuario">
                    </div>
                  </div>

                  <div class="mb-3">
                    <div class="filtro-label mb-1">Rol cliente</div>
                    <select id="filtroRolCliente" class="form-select form-select-sm">
                      <option value="">Todos</option>
                      <option value="ADMIN_GLOBAL">Admin global</option>
                      <option value="ADMIN_ZONA">Admin zona</option>
                      <option value="ADMIN_SEDE">Admin sede</option>
                      <option value="USUARIO">Usuario</option>
                      <option value="VISOR">Visor</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <div class="filtro-label mb-1">Zona</div>
                    <select id="filtroZona" class="form-select form-select-sm">
                      <option value="">Todas</option>
                    </select>
                  </div>

                  <div class="mb-3">
                    <div class="filtro-label mb-1">Sede</div>
                    <select id="filtroSede" class="form-select form-select-sm">
                      <option value="">Todas</option>
                    </select>
                  </div>

                  <hr>
                  <div class="filtro-label mb-1">Notificaciones</div>
                  <div class="form-check form-check-sm mb-1">
                    <input class="form-check-input" type="checkbox" id="filtroNotifMail">
                    <label class="form-check-label small" for="filtroNotifMail">
                      Solo quienes reciben correo
                    </label>
                  </div>
                  <div class="form-check form-check-sm mb-1">
                    <input class="form-check-input" type="checkbox" id="filtroNotifInApp">
                    <label class="form-check-label small" for="filtroNotifInApp">
                      Solo quienes reciben in-app
                    </label>
                  </div>

                  <hr>
                  <div class="d-flex justify-content-between align-items-center">
                    <button class="btn btn-light btn-sm" id="btnLimpiarFiltros">
                      Limpiar filtros
                    </button>
                    <span class="small text-muted">
                      <span id="countUsuariosFiltrados">0</span> de <span id="countUsuariosTotal">0</span>
                    </span>
                  </div>
                </div>
              </div>

              <div class="card border-0 bg-light-subtle">
                <div class="card-body small text-muted">
                  <strong><i class="bi bi-info-circle me-1"></i>Tip</strong><br>
                  Los permisos para ver/editar usuarios se basan en los roles:
                  <span class="text-nowrap">Admin global / zona / sede.</span>
                </div>
              </div>
            </div>

            <!-- Col listado -->
            <div class="col-12 col-lg-9">
              <div class="card border-0 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <div>
                    <span class="filtro-label d-block mb-1">Resumen</span>
                    <div class="small">
                      <span class="badge bg-primary-subtle text-primary me-1" id="badgeScope"></span>
                      <span class="badge bg-secondary-subtle text-secondary me-1" id="badgeZonas"></span>
                      <span class="badge bg-secondary-subtle text-secondary" id="badgeSedes"></span>
                    </div>
                  </div>
                </div>
                <div class="card-body p-0">
                  <!-- Tabla / tarjetas -->
                  <div class="table-responsive">
                    <table class="table align-middle mb-0" id="tablaUsuarios">
                      <thead class="table-light">
                        <tr>
                          <th style="width: 40px;"></th>
                          <th>Usuario</th>
                          <th class="d-none d-md-table-cell">Correo</th>
                          <th class="d-none d-lg-table-cell">Rol cliente</th>
                          <th class="d-none d-lg-table-cell">Ubicación</th>
                          <th style="width: 48px;"></th>
                        </tr>
                      </thead>
                      <tbody id="tbodyUsuarios">
                        <!-- Se llena por JS -->
                      </tbody>
                    </table>
                  </div>

                  <div class="p-3 text-center text-muted small d-none" id="estadoSinUsuarios">
                    <i class="bi bi-inboxes mb-1" style="font-size:1.5rem;"></i><br>
                    No se encontraron usuarios con los filtros actuales.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- ========== MODAL · CREAR USUARIO ========== -->
        <div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="formCrearUsuario">
              <div class="modal-header border-0 pb-0">
                <div>
                  <h5 class="modal-title mb-0">
                    <i class="bi bi-person-plus me-2"></i> Crear usuario
                  </h5>
                  <small class="text-muted">Da de alta un nuevo usuario para esta cuenta / póliza.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label small" for="uNombre">Nombre(s)</label>
                    <input type="text" class="form-control" id="uNombre" name="nombre" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small" for="uAPaterno">Apellido paterno</label>
                    <input type="text" class="form-control" id="uAPaterno" name="apaterno" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small" for="uAMaterno">Apellido materno</label>
                    <input type="text" class="form-control" id="uAMaterno" name="amaterno">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label small" for="uCorreo">Correo electrónico</label>
                    <input type="email" class="form-control" id="uCorreo" name="correo" required>
                    <div class="form-text">Usaremos este correo para notificaciones y acceso.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small" for="uTelefono">Teléfono</label>
                    <input type="text" class="form-control" id="uTelefono" name="telefono">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label small" for="uUsername">Usuario (login)</label>
                    <input type="text" class="form-control" id="uUsername" name="username" required>
                    <div class="form-text">
                      De 3 a 20 caracteres, sólo letras, números, guiones y guión bajo.
                    </div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label small" for="uNivel">Nivel dentro del cliente</label>
                    <select class="form-select" id="uNivel" name="nivel" required>
                      <option value="">Selecciona un nivel…</option>
                      <option value="ADMIN_GLOBAL">Administrador global del cliente</option>
                      <option value="ADMIN_ZONA">Administrador por zona</option>
                      <option value="ADMIN_SEDE">Administrador de sede</option>
                      <option value="USUARIO">Usuario (operativo)</option>
                      <option value="VISOR">Visor (solo lectura)</option>
                    </select>
                    <div class="form-text">
                      Estos niveles definen la autoridad <code>del usuario</code>.
                      El tipo del usuario en cuestion <code>podrá ver los equipos</code> según <code>la sede o zona</code>.
                    </div>
                  </div>

                  <!-- Zona -->
                  <div class="col-md-6">
                    <label class="form-label small" for="uZona">Zona (opcional)</label>
                    <select class="form-select" id="uZona" name="zonaId">
                      <option value="">Sin zona específica</option>
                      <!-- Se llenará via JS con STATE.zonas -->
                    </select>
                    <div class="form-text">
                      Obligatoria sólo si el rol requiere nivel por zona (ej. Administrador por zona).
                    </div>
                  </div>

                  <!-- Sede -->
                  <div class="col-md-6">
                    <label class="form-label small" for="uSede">Sede (opcional)</label>
                    <select class="form-select" id="uSede" name="sedeId">
                      <option value="">Sin sede específica</option>
                      <!-- Se llenará via JS con STATE.sedes filtradas por zona -->
                    </select>
                    <div class="form-text">
                      Obligatoria sólo si el rol requiere nivel por sede (ej. Administrador de sede).
                    </div>
                  </div>

                  <div class="col-12">
                    <label class="form-label small" for="uNota">Notas internas (opcional)</label>
                    <textarea class="form-control" id="uNota" name="nota" rows="2"></textarea>
                  </div>

                  <div class="col-12">
                    <div class="alert alert-info py-2 px-3 small mb-0">
                      El usuario se creará con estado <strong>Nuevo Usuario</strong> y deberá
                      cambiar su contraseña al ingresar por primera vez.
                    </div>
                  </div>
                </div>
              </div>

              <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check2 me-1"></i> Crear usuario
                </button>
              </div>
            </form>
          </div>
        </div>
        <!-- ========== MODAL · EDITAR USUARIO ========== -->
        <div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" id="formEditarUsuario">
              <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center">
                  <div id="editAvatarPreview" class="user-avatar-circle bg-primary text-white me-2">
                    U
                  </div>
                  <div>
                    <h5 class="modal-title mb-0">
                      <i class="bi bi-pencil-square me-2"></i> Editar usuario
                    </h5>
                    <small class="text-muted" id="editUsernameLabel"></small>
                  </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>

              <div class="modal-body">
                <input type="hidden" name="usId" id="editUsId">

                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label small">Nombre(s)</label>
                    <input type="text" class="form-control" name="nombre" id="editNombre" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small">Apellido paterno</label>
                    <input type="text" class="form-control" name="apaterno" id="editApaterno" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small">Apellido materno</label>
                    <input type="text" class="form-control" name="amaterno" id="editAmaterno">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label small">Correo electrónico</label>
                    <input type="email" class="form-control" name="correo" id="editCorreo" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Teléfono</label>
                    <input type="text" class="form-control" name="telefono" id="editTelefono">
                  </div>

                  <div class="col-md-6">
                    <label class="form-label small">Usuario (login)</label>
                    <input type="text" class="form-control" name="username" id="editUsername" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label small">Nivel dentro del cliente</label>
                    <select class="form-select" name="nivel" id="editNivel" required>
                      <option value="">Selecciona un nivel…</option>
                      <option value="ADMIN_GLOBAL">Administrador global del cliente</option>
                      <option value="ADMIN_ZONA">Administrador por zona</option>
                      <option value="ADMIN_SEDE">Administrador de sede</option>
                      <option value="USUARIO">Usuario (operativo)</option>
                      <option value="VISOR">Visor (solo lectura)</option>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label small">Sede</label>
                    <select class="form-select" name="sedeId" id="editSedeId">
                      <option value="">Sin sede específica</option>
                      <!-- Se llenará con las mismas sedes que el modal de crear -->
                    </select>
                    <div class="form-text">
                      Solo aplica para niveles de zona / sede / usuario si manejan sedes.
                    </div>
                  </div>

                  <div class="col-12">
                    <hr class="my-3">
                    <div class="form-check mb-2">
                      <input class="form-check-input" type="checkbox" id="chkCambiarPass">
                      <label class="form-check-label small" for="chkCambiarPass">
                        Cambiar contraseña de este usuario
                      </label>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label small">Nueva contraseña</label>
                    <input type="password" class="form-control" id="editPass1" disabled>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Confirmar contraseña</label>
                    <input type="password" class="form-control" id="editPass2" disabled>
                    <div class="form-text small">
                      Mínimo 8 caracteres, mayúsculas, minúsculas, números y símbolo.
                    </div>
                  </div>
                </div>
              </div>

              <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check2 me-1"></i> Guardar cambios
                </button>
              </div>
            </form>
          </div>
        </div>




        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Datos de sesión básicos para JS -->
        <script>
          window.SESSION = {
            usId: <?= json_encode($US_ID) ?>,
            clId: <?= json_encode($CL_ID) ?>,
            usRolSistema: <?= json_encode($US_ROL_SYS) ?>,
            usNombre: <?= json_encode($US_NOMBRE) ?>,
            usUsername: <?= json_encode($US_USERNAME) ?>
          };
        </script>

        <!-- Lógica del panel de usuarios -->
        <script src="../js/admin_usuarios.js"></script>
        <script src="js/theme.js"></script>
</body>

</html>