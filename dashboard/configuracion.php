<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../php/conexion.php";

$theme = $_COOKIE['mrs_theme'] ?? ($_SESSION['usTheme'] ?? 'light');

// Permite fijar la póliza activa desde ?pcId= y guardarla en sesión
if (isset($_GET['pcId'])) {
  $_SESSION['pcId'] = (int)$_GET['pcId'];
}

$clId        = $_SESSION['clId'] ?? null;
$pcId        = $_SESSION['pcId'] ?? null;
$usNombre    = $_SESSION['usNombre'] ?? ($_SESSION['usUsuario'] ?? 'Usuario');
$usCorreo    = $_SESSION['usCorreo'] ?? '';
$usTelefono  = $_SESSION['usTelefono'] ?? '';
$usRol       = $_SESSION['usRol'] ?? '';
$clNombre    = $_SESSION['clNombre'] ?? '';

function findUserAvatarUrl(string $username): string
{
  $urlBase = "../img/Usuario/";
  $fsBase  = realpath(__DIR__ . "/../img/Usuario");

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

// Buscar vendedor
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
    if ($stmt) {
      $stmt->bind_param("ii", $clId, $pcId);
    }
  } else {
    $sql = "SELECT u.usId, u.usNombre, u.usAPaterno, u.usUsername
            FROM cuentas c
            JOIN polizascliente pc ON pc.pcId = c.pcId
            JOIN usuarios u ON u.usId = c.usId
            WHERE c.clId = ?
            ORDER BY c.cuId DESC
            LIMIT 1";
    $stmt = $conectar->prepare($sql);
    if ($stmt) {
      $stmt->bind_param("i", $clId);
    }
  }

  if (isset($stmt) && $stmt && $stmt->execute()) {
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

$ROL   = $_SESSION['usRol'] ?? null;
$CL_ID = $_SESSION['clId'] ?? null;
$US_ID = $_SESSION['usId'] ?? null;

$prefTheme             = 'light';
$prefNotifInApp        = 1;
$prefNotifMail         = 1;
$prefNotifTicketCambio = 1;
$prefNotifMeet         = 1;
$prefNotifVisita       = 1;
$prefNotifFolio        = 1;

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
        $prefTheme             = $rowP['usTheme'] ?? 'light';
        $prefNotifInApp        = (int)($rowP['usNotifInApp'] ?? 1);
        $prefNotifMail         = (int)($rowP['usNotifMail'] ?? 1);
        $prefNotifTicketCambio = (int)($rowP['usNotifTicketCambio'] ?? 1);
        $prefNotifMeet         = (int)($rowP['usNotifMeet'] ?? 1);
        $prefNotifVisita       = (int)($rowP['usNotifVisita'] ?? 1);
        $prefNotifFolio        = (int)($rowP['usNotifFolio'] ?? 1);
      }
    }
    $stmtP->close();
  }
}

$CAN_CREATE = ($ROL === 'AC' || $ROL === 'UC' || $ROL === 'MRA');

$usAPaterno = $_SESSION['usAPaterno'] ?? '';
$usAMaterno = $_SESSION['usAMaterno'] ?? '';
$usUsername = $_SESSION['usUsername'] ?? '';

$usuarioSan  = preg_replace('/[^A-Za-z0-9_\-]/', '', $usUsername !== '' ? $usUsername : 'default');
$dirFS       = __DIR__ . '/../img/Usuario/';
$dirURL      = '../img/Usuario/';
$extsPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

$src = $dirURL . 'user.webp';
foreach ($extsPermitidas as $ext) {
  $fs = $dirFS . $usuarioSan . '.' . $ext;
  if (is_file($fs)) {
    $src = $dirURL . $usuarioSan . '.' . $ext . '?v=' . filemtime($fs);
    break;
  }
}

$avatarSrc = $dirURL . 'user.webp';
foreach ($extsPermitidas as $ext) {
  $fs = $dirFS . $usuarioSan . '.' . $ext;
  if (is_file($fs)) {
    $avatarSrc = $dirURL . $usuarioSan . '.' . $ext . '?v=' . filemtime($fs);
    break;
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>MR SOS | Configuración</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">

  <style>
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
  </style>

  <script>
    window.SESSION = {
      rol: <?= json_encode($ROL) ?>,
      clId: <?= json_encode((int)$CL_ID) ?>,
      usId: <?= json_encode((int)$US_ID) ?>,
      canCreateTicket: <?= $CAN_CREATE ? 'true' : 'false' ?>
    };

    window.USER_PREFS = {
      theme: <?= json_encode($prefTheme) ?>,
      notifInApp: <?= json_encode((bool)$prefNotifInApp) ?>,
      notifMail: <?= json_encode((bool)$prefNotifMail) ?>,
      notifTicketCambio: <?= json_encode((bool)$prefNotifTicketCambio) ?>,
      notifMeet: <?= json_encode((bool)$prefNotifMeet) ?>,
      notifVisita: <?= json_encode((bool)$prefNotifVisita) ?>,
      notifFolio: <?= json_encode((bool)$prefNotifFolio) ?>
    };
  </script>
</head>

<body class="<?= ($theme === 'dark') ? 'dark-mode' : '' ?>">
  <div class="container-fluid">
    <div class="row gx-0">
      <?php $activeMenu = 'clientes'; ?>
      <?php require_once __DIR__ . '/partials/sidebar_cliente.php'; ?>

      <main class="col-md-10">
        <div class="topbar px-3 py-2 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="home.php">
              <i class="bi bi-arrow-left"></i>
            </a>
            <span class="badge text-bg-success rounded-pill px-3">Activo</span>
            <span class="fw-bold"><?= htmlspecialchars($_SESSION['usUsername'] ?? 'Admin') ?></span>
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

        <main class="container py-4">
          <div class="row g-4">
            <div class="col-12 col-lg-4">
              <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                  <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0 me-3">
                      <img
                        src="<?= htmlspecialchars($src, ENT_QUOTES) ?>"
                        class="rounded-circle me-2"
                        alt="Usuario"
                        style="width:40px;height:40px;object-fit:cover;box-shadow:0 1px 3px rgba(0,0,0,0.2);"
                        onerror="this.onerror=null;this.src='../img/Usuario/user.webp';">
                    </div>
                    <div>
                      <div class="fw-semibold"><?= htmlspecialchars($usNombre, ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="small text-muted"><?= htmlspecialchars($usCorreo, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </div>

                  <?php if ($clNombre): ?>
                    <div class="small text-muted mb-2">
                      Cliente: <span class="fw-semibold"><?= htmlspecialchars($clNombre, ENT_QUOTES, 'UTF-8') ?></span>
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

                  <form id="formPerfil" autocomplete="off" enctype="multipart/form-data">
                    <div class="row g-3">
                      <div class="col-md-4 text-center">
                        <img id="previewAvatar"
                          src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES) ?>"
                          class="rounded-circle mb-2"
                          alt="Avatar"
                          style="width:96px;height:96px;object-fit:cover;box-shadow:0 1px 4px rgba(0,0,0,0.2);">
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
                              value="<?= htmlspecialchars($usNombre, ENT_QUOTES, 'UTF-8') ?>" disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Apellido paterno</label>
                            <input type="text" class="form-control" name="usAPaterno"
                              value="<?= htmlspecialchars($usAPaterno, ENT_QUOTES, 'UTF-8') ?>" disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Apellido materno</label>
                            <input type="text" class="form-control" name="usAMaterno"
                              value="<?= htmlspecialchars($usAMaterno, ENT_QUOTES, 'UTF-8') ?>" disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Usuario</label>
                            <input type="text" class="form-control" name="usUsername"
                              value="<?= htmlspecialchars($usUsername, ENT_QUOTES, 'UTF-8') ?>" disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Correo</label>
                            <input type="email" class="form-control" name="usCorreo"
                              value="<?= htmlspecialchars($usCorreo, ENT_QUOTES, 'UTF-8') ?>" disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Teléfono</label>
                            <input type="text" class="form-control" name="usTelefono"
                              value="<?= htmlspecialchars($usTelefono, ENT_QUOTES, 'UTF-8') ?>" disabled>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label small">Rol</label>
                            <input type="text" class="form-control"
                              value="<?= htmlspecialchars($usRol ?: '—', ENT_QUOTES, 'UTF-8') ?>" disabled>
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
                      <input class="form-check-input" type="checkbox" role="switch" id="switchDarkMode">
                      <label class="form-check-label small ms-2" for="switchDarkMode">
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
      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    const THEME_COOKIE = 'mrs_theme';
    const DARK_KEY = 'mrsos_dark_mode';

    function setCookie(name, value, days = 365) {
      const d = new Date();
      d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
      document.cookie = `${name}=${encodeURIComponent(value)};expires=${d.toUTCString()};path=/;SameSite=Lax`;
    }

    function getCookie(name) {
      const m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]+)'));
      return m ? decodeURIComponent(m[1]) : null;
    }

    function applyTheme(mode, { saveCookie = true, saveStorage = true } = {}) {
      const isDark = mode === 'dark';

      document.body.classList.toggle('dark-mode', isDark);
      document.body.classList.toggle('mrsos-dark', isDark);

      if (saveCookie) {
        setCookie(THEME_COOKIE, isDark ? 'dark' : 'light');
      }
      if (saveStorage) {
        localStorage.setItem(DARK_KEY, isDark ? '1' : '0');
      }

      const btnThemeDesktop = document.getElementById('btnThemeDesktop');
      const switchDark = document.getElementById('switchDarkMode');
      const labelDark = document.getElementById('labelDarkMode');

      if (btnThemeDesktop) {
        const icon = btnThemeDesktop.querySelector('i');
        if (icon) {
          icon.classList.remove('bi-moon', 'bi-moon-fill');
          icon.classList.add(isDark ? 'bi-moon-fill' : 'bi-moon');
        }
        btnThemeDesktop.title = isDark ? 'Modo claro' : 'Modo oscuro';
      }

      if (switchDark) switchDark.checked = isDark;
      if (labelDark) labelDark.textContent = isDark ? 'Modo oscuro' : 'Modo claro';
    }

    function initThemeFromPrefs() {
      const prefs = window.USER_PREFS || {};
      let initial = null;

      if (prefs.theme === 'dark' || prefs.theme === 'light') {
        initial = prefs.theme;
      }

      if (!initial) {
        const cookieTheme = getCookie(THEME_COOKIE);
        if (cookieTheme === 'dark' || cookieTheme === 'light') {
          initial = cookieTheme;
        }
      }

      if (!initial) {
        const savedDark = localStorage.getItem(DARK_KEY);
        if (savedDark === '1') initial = 'dark';
        else if (savedDark === '0') initial = 'light';
      }

      if (!initial) {
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        initial = prefersDark ? 'dark' : 'light';
      }

      applyTheme(initial, { saveCookie: false, saveStorage: false });
    }

    function initNotifCheckbox(el, value, def = true) {
      if (!el) return;
      if (typeof value === 'boolean') el.checked = value;
      else el.checked = def;
    }

    function getProfileEditableInputs(formPerfil) {
      return formPerfil.querySelectorAll(
        'input[name="usNombre"], input[name="usAPaterno"], input[name="usAMaterno"], input[name="usCorreo"], input[name="usTelefono"], input[name="usUsername"]'
      );
    }

    document.addEventListener('DOMContentLoaded', () => {
      const body = document.body;

      const switchDark = document.getElementById('switchDarkMode');
      const btnThemeDesktop = document.getElementById('btnThemeDesktop');

      initThemeFromPrefs();

      if (switchDark) {
        switchDark.addEventListener('change', () => {
          applyTheme(switchDark.checked ? 'dark' : 'light');
        });
      }

      if (btnThemeDesktop) {
        btnThemeDesktop.addEventListener('click', () => {
          const next = body.classList.contains('dark-mode') ? 'light' : 'dark';
          applyTheme(next);
        });
      }

      const formPerfil = document.getElementById('formPerfil');
      const btnEditar = document.getElementById('btnEditarPerfil');
      const btnGuardar = document.getElementById('btnGuardarPerfil');
      const btnCancelar = document.getElementById('btnCancelarPerfil');
      const btnCambiarPass = document.getElementById('btnCambiarPass');
      const inputAvatar = document.getElementById('usAvatar');
      const imgPreview = document.getElementById('previewAvatar');

      let initialAvatarSrc = imgPreview ? imgPreview.src : '';
      let initialValues = {};

      if (formPerfil) {
        const editableInputs = getProfileEditableInputs(formPerfil);
        editableInputs.forEach(input => {
          initialValues[input.name] = input.value;
        });
      }

      if (btnEditar && formPerfil) {
        btnEditar.addEventListener('click', () => {
          const editableInputs = getProfileEditableInputs(formPerfil);
          editableInputs.forEach(el => el.removeAttribute('disabled'));

          if (inputAvatar) inputAvatar.removeAttribute('disabled');

          if (btnGuardar) btnGuardar.disabled = false;
          if (btnCancelar) btnCancelar.disabled = false;
        });
      }

      if (btnCancelar && formPerfil) {
        btnCancelar.addEventListener('click', () => {
          const editableInputs = getProfileEditableInputs(formPerfil);

          editableInputs.forEach(el => {
            el.value = initialValues[el.name] ?? '';
            el.setAttribute('disabled', 'disabled');
          });

          if (inputAvatar) {
            inputAvatar.value = '';
            inputAvatar.setAttribute('disabled', 'disabled');
          }

          if (imgPreview && initialAvatarSrc) {
            imgPreview.src = initialAvatarSrc;
          }

          if (btnGuardar) btnGuardar.disabled = true;
          if (btnCancelar) btnCancelar.disabled = true;
        });
      }

      if (inputAvatar && imgPreview) {
        inputAvatar.addEventListener('change', () => {
          const file = inputAvatar.files && inputAvatar.files[0];
          if (!file) return;

          const reader = new FileReader();
          reader.onload = e => {
            imgPreview.src = e.target.result;
          };
          reader.readAsDataURL(file);
        });
      }

      if (btnGuardar && formPerfil) {
        btnGuardar.addEventListener('click', async () => {
          try {
            const fd = new FormData(formPerfil);

            const res = await fetch('../php/actualizar_perfil.php', {
              method: 'POST',
              body: fd
            });

            const json = await res.json();

            if (!res.ok || !json.success) {
              throw new Error(json.error || 'No fue posible guardar tu perfil.');
            }

            const editableInputs = getProfileEditableInputs(formPerfil);
            editableInputs.forEach(input => {
              initialValues[input.name] = input.value;
              input.setAttribute('disabled', 'disabled');
            });

            if (inputAvatar) {
              inputAvatar.value = '';
              inputAvatar.setAttribute('disabled', 'disabled');
            }

            if (imgPreview) {
              initialAvatarSrc = imgPreview.src;
            }

            btnGuardar.disabled = true;
            btnCancelar.disabled = true;

            await Swal.fire({
              title: 'Perfil actualizado',
              text: 'Tus datos se han guardado correctamente.',
              icon: 'success'
            });

          } catch (err) {
            Swal.fire({
              title: 'Error',
              text: err.message || 'Error de red al intentar guardar tu perfil.',
              icon: 'error'
            });
          }
        });
      }

      if (btnCambiarPass) {
        btnCambiarPass.addEventListener('click', () => {
          window.location.href = 'cambiar_password.php';
        });
      }

      const elInApp = document.getElementById('notifInApp');
      const elCorreo = document.getElementById('notifCorreo');
      const elCambio = document.getElementById('notifTicketCambio');
      const elMeet = document.getElementById('notifMeet');
      const elVisita = document.getElementById('notifVisita');
      const elFolio = document.getElementById('notifFolio');
      const btnSaveN = document.getElementById('btnGuardarNotifs');

      initNotifCheckbox(elInApp, window.USER_PREFS?.notifInApp, true);
      initNotifCheckbox(elCorreo, window.USER_PREFS?.notifMail, true);
      initNotifCheckbox(elCambio, window.USER_PREFS?.notifTicketCambio, true);
      initNotifCheckbox(elMeet, window.USER_PREFS?.notifMeet, true);
      initNotifCheckbox(elVisita, window.USER_PREFS?.notifVisita, true);
      initNotifCheckbox(elFolio, window.USER_PREFS?.notifFolio, true);

      if (btnSaveN) {
        btnSaveN.addEventListener('click', async () => {
          const themeIsDark = document.body.classList.contains('dark-mode');

          const payload = {
            theme: themeIsDark ? 'dark' : 'light',
            notifInApp: !!elInApp?.checked,
            notifMail: !!elCorreo?.checked,
            notifTicketCambio: !!elCambio?.checked,
            notifMeet: !!elMeet?.checked,
            notifVisita: !!elVisita?.checked,
            notifFolio: !!elFolio?.checked
          };

          try {
            const res = await fetch('../php/guardar_preferencias.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify(payload)
            });

            const json = await res.json();

            if (!res.ok || !json.success) {
              throw new Error(json.error || 'No se pudieron guardar las preferencias.');
            }

            setCookie(THEME_COOKIE, payload.theme);
            localStorage.setItem(DARK_KEY, payload.theme === 'dark' ? '1' : '0');

            window.USER_PREFS = {
              ...window.USER_PREFS,
              ...payload
            };

            Swal.fire({
              icon: 'success',
              title: 'Preferencias guardadas',
              text: 'Tus cambios fueron guardados correctamente.'
            });
          } catch (err) {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: err.message || 'No se pudieron guardar las preferencias.'
            });
          }
        });
      }
    });
  </script>
</body>
</html>