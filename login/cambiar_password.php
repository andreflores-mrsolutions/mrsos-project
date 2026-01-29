<?php
session_start();

if (empty($_SESSION['usId']) || empty($_SESSION['forzarCambioPass'])) {
    header('Location: login.php');
    exit;
}

$usId = (int)$_SESSION['usId'];

require '../php/conexion.php';

// Traer info actual del usuario para los pasos 4 y 5
$stmt = $conectar->prepare("
    SELECT usNombre, usAPaterno, usAMaterno, usCorreo, usTelefono, usUsername 
    FROM usuarios 
    WHERE usId = ?
");
$stmt->bind_param("i", $usId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('Usuario no encontrado.');
}
$user = $result->fetch_assoc();
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Bienvenido · MR SoS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <!-- Estilos MR SoS -->
    <link rel="stylesheet" href="../css/style.css">

    <style>
        /* Wizard básico, usando la misma paleta que sos.php */

        .onb-step {
            display: none;
            /* 🔹 ya NO ocupan espacio cuando no están activos */
            opacity: 0;
            transform: translateY(8px);
            pointer-events: none;
        }

        .onb-step.active {
            display: block;
            pointer-events: auto;
            animation: fadeSlideIn .25s ease forwards;
            /* 🔹 animación de entrada */
        }

        @keyframes fadeSlideIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }



        .onb-card {
            border-radius: 1.5rem;
            border: 1px solid rgba(0, 0, 0, .06);
            padding: 2rem;
        }

        body.dark-mode .onb-card {
            background: var(--bg-secondary);
            border-color: var(--border-light);
        }

        .onb-progress {
            display: flex;
            gap: .5rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .onb-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #d1d5db;
            transition: .2s;
        }

        /* etiquetas de los pasos */
        .onb-step-label-item {
            margin: 0 .35rem;
            opacity: .45;
        }

        .onb-step-label-item.active {
            opacity: 1;
            color: var(--base-color);
        }

        .onb-dot.active {
            background: var(--base-color);
            transform: scale(1.2);
        }

        .onb-hero-img {
            max-width: 480px;
            width: 100%;
            border-radius: 1.2rem;
            object-fit: cover;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .18);
        }

        .onb-nav-btn {
            min-width: 140px;
        }

        /* Unificar tamaño de KPIs dentro del onboarding */
        .onb-card .mrs-kpi-title {
            font-size: 0.8rem;
        }

        .onb-card .mrs-kpi-value {
            font-size: 1.4rem;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div class="container py-5" style="max-width: 980px;">
        <div class="onb-card mrs-hero shadow-sm">

            <!-- Progreso superior -->
            <div class="onb-progress mb-2">
                <div class="onb-dot active" data-step-dot="1"></div>
                <div class="onb-dot" data-step-dot="2"></div>
                <div class="onb-dot" data-step-dot="3"></div>
                <div class="onb-dot" data-step-dot="4"></div>
                <div class="onb-dot" data-step-dot="5"></div>
                <div class="onb-dot" data-step-dot="6"></div>
            </div>

            <div class="text-center mb-3 small fw-semibold onb-steps-labels">
                <span class="onb-step-label-item active" data-step-label="1">Bienvenida</span>
                <span class="onb-step-label-item" data-step-label="2">Tu plataforma</span>
                <span class="onb-step-label-item" data-step-label="3">Seguridad</span>
                <span class="onb-step-label-item" data-step-label="4">Tus datos</span>
                <span class="onb-step-label-item" data-step-label="5">Usuario</span>
                <span class="onb-step-label-item" data-step-label="6">Listo</span>
            </div>

            <form id="onboardingForm" method="post" action="../php/guardar_onboarding.php" enctype="multipart/form-data">
                <input type="hidden" name="usId"
                    value="<?php echo htmlspecialchars($usId, ENT_QUOTES); ?>">

                <!-- PASO 1: Bienvenida (usa el hero de sos.php) -->
                <div class="onb-step active" data-step="1">
                    <section class=" rounded-4 p-4 p-md-5 text-center mb-0">
                        <div class="container-fluid">
                            <span class="badge mrs-hero-badge mb-3">
                                MRSolutions · Support One Service
                            </span>
                            <h1 class="h3 h-md-2 fw-bold mb-2">
                                Soporte inteligente, <span class="fw-bolder">sin fricción.</span>
                            </h1>
                            <p class="lead m-0 m-auto mb-4" style="max-width: 60ch;">
                                Centraliza tickets, seguimiento por procesos, reuniones y carga guiada de logs.
                                Todo en una sola vista clara.
                            </p>
                            <div class="row g-3 justify-content-center mb-4">
                                <div class="col-6 col-md-3">
                                    <div class="mrs-kpi">
                                        <div class="mrs-kpi-title">Experiencia</div>
                                        <div class="mrs-kpi-value">+25 años</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="mrs-kpi">
                                        <div class="mrs-kpi-title">Clientes</div>
                                        <div class="mrs-kpi-value">100+</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="mrs-kpi">
                                        <div class="mrs-kpi-title">Proyectos</div>
                                        <div class="mrs-kpi-value">500+</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-center">
                                <button type="button" class="btn btn-primary btn-lg px-4 onb-nav-btn" data-next>
                                    Comenzar
                                </button>
                                <button type="button" href="../php/logout.php"
                                    data-href="../php/logout.php?ajax=1"
                                    data-redirect="../login/login.php" class="btn btn-outline-secondary btn-lg px-4 onb-nav-btn ms-1" id="btnLogout">
                                    En otro momento
                                </button>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- PASO 2: Tus tickets, tu póliza, tu control -->
                <div class="onb-step" data-step="2">
                    <div class="row g-4 align-items-center">
                        <div class="col-12 col-md-6 order-md-2">
                            <p class="text-uppercase small fw-semibold text-primary mb-1">
                                Visibilidad total
                            </p>
                            <h2 class="h4 fw-bold mb-2">Tus tickets, tu póliza, tu control.</h2>
                            <p class="text-muted mb-3">
                                Consulta el estado de tus casos, la información de tus equipos
                                y los detalles de tu póliza en un solo panel. Todo lo que necesitas
                                para tomar decisiones rápidas, al alcance de tu mano.
                            </p>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary onb-nav-btn" data-prev>
                                    Atrás
                                </button>
                                <button type="button" class="btn btn-primary onb-nav-btn" data-next>
                                    Continuar
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 order-md-1 text-center">
                            <!-- Usa una captura del dashboard de sos.php -->
                            <img src="../img/dashboard-mrsos.svg" alt="Dashboard MR SoS" class="onb-hero-img">
                        </div>
                    </div>
                </div>

                <!-- PASO 3: Cambio de contraseña -->
                <div class="onb-step" data-step="3">
                    <h2 class="h4 fw-bold mb-2">Protejamos tu acceso</h2>
                    <p class="text-muted mb-3">
                        Vamos a cambiar tu contraseña. Debe contener al menos: una letra mayúscula,
                        una minúscula, un número y un carácter especial.
                    </p>

                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña</label>
                        <input type="password" name="pass1" id="pass1" class="form-control" required>
                        <div class="form-text">
                            Usa una contraseña fuerte para mantener segura tu cuenta.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar contraseña</label>
                        <input type="password" name="pass2" id="pass2" class="form-control" required>
                    </div>

                    <ul class="small text-muted mb-3 ps-3">
                        <li>Mínimo 8 caracteres.</li>
                        <li>Al menos una mayúscula (A-Z) y una minúscula (a-z).</li>
                        <li>Al menos un número (0-9).</li>
                        <li>Al menos un carácter especial (!@#$%^&* etc.).</li>
                    </ul>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary onb-nav-btn" data-prev>
                            Atrás
                        </button>
                        <button type="button" class="btn btn-primary onb-nav-btn" data-next data-validate-pass>
                            Continuar
                        </button>
                    </div>
                </div>

                <!-- PASO 4: Confirmación de datos -->
                <div class="onb-step" data-step="4">
                    <h2 class="h4 fw-bold mb-2">Verifiquemos tus datos</h2>
                    <p class="text-muted mb-3">
                        Ayúdanos a confirmar tu información de contacto. Esto facilitará la
                        comunicación con nuestro equipo de soporte.
                    </p>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Foto de perfil (opcional)</label>
                            <input type="file" name="usAvatar" id="usAvatar"
                                class="form-control" accept="image/*">
                            <div class="mt-2 d-flex align-items-center gap-2">
                                <img id="avatarPreview"
                                    src="img/Usuario/user.webp"
                                    alt="Vista previa"
                                    style="width:56px;height:56px;border-radius:50%;object-fit:cover;">
                                <small class="text-muted">
                                    Se mostrará en tu perfil y encabezado.
                                </small>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="usNombre" class="form-control"
                                value="<?php echo htmlspecialchars($user['usNombre'] ?? '', ENT_QUOTES); ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Apellido paterno</label>
                            <input type="text" name="usAPaterno" class="form-control"
                                value="<?php echo htmlspecialchars($user['usAPaterno'] ?? '', ENT_QUOTES); ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Apellido materno</label>
                            <input type="text" name="usAMaterno" class="form-control"
                                value="<?php echo htmlspecialchars($user['usAMaterno'] ?? '', ENT_QUOTES); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" name="usCorreo" class="form-control"
                                value="<?php echo htmlspecialchars($user['usCorreo'] ?? '', ENT_QUOTES); ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="usTelefono" class="form-control"
                                value="<?php echo htmlspecialchars($user['usTelefono'] ?? '', ENT_QUOTES); ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary onb-nav-btn" data-prev>
                            Atrás
                        </button>
                        <button type="button" class="btn btn-primary onb-nav-btn" data-next>
                            Continuar
                        </button>
                    </div>
                </div>

                <!-- PASO 5: Username -->
                <div class="onb-step" data-step="5">
                    <h2 class="h4 fw-bold mb-2">Elige tu nombre de usuario</h2>
                    <p class="text-muted mb-3">
                        Es momento de elegir tu nombre de usuario. Será único y no podrás cambiarlo
                        hasta más adelante, así que elígelo con cuidado.
                    </p>

                    <div class="mb-3">
                        <label class="form-label">Nombre de usuario</label>
                        <input type="text" name="usUsername" id="usUsername" class="form-control"
                            value="<?php echo htmlspecialchars($user['usUsername'] ?? '', ENT_QUOTES); ?>" required>
                        <div class="form-text">
                            Evita palabras ofensivas. Usa letras, números, guion (-) o guion bajo (_).
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary onb-nav-btn" data-prev>
                            Atrás
                        </button>
                        <button type="button" class="btn btn-primary onb-nav-btn" data-next
                            data-validate-username>
                            Continuar
                        </button>
                    </div>
                </div>

                <!-- PASO 6: Final -->
                <div class="onb-step" data-step="6">
                    <h2 class="h4 fw-bold mb-2">¡Todo listo!</h2>
                    <p class="text-muted mb-3">
                        Tu cuenta ha sido configurada. A partir de ahora podrás levantar tickets,
                        consultar el estado de tus equipos y coordinar con nuestro equipo de soporte
                        desde MR SoS.
                    </p>

                    <p class="mb-4">
                        Cuando pulses <strong>Finalizar</strong>, guardaremos tus cambios y te
                        llevaremos directamente a la plataforma.
                    </p>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary onb-nav-btn" data-prev>
                            Atrás
                        </button>
                        <button type="submit" class="btn btn-primary onb-nav-btn">
                            Finalizar
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <script>
        (function() {
            let currentStep = 1;
            const totalSteps = 6;

            function showStep(n) {
                currentStep = n;

                document.querySelectorAll('.onb-step').forEach(step => {
                    step.classList.toggle('active', parseInt(step.dataset.step, 10) === n);
                });

                document.querySelectorAll('.onb-dot').forEach(dot => {
                    dot.classList.toggle('active', parseInt(dot.dataset.stepDot, 10) === n);
                });

                document.querySelectorAll('.onb-step-label-item').forEach(lbl => {
                    lbl.classList.toggle('active', parseInt(lbl.dataset.stepLabel, 10) === n);
                });
            }



            function validarPassword() {
                const p1 = document.getElementById('pass1').value;
                const p2 = document.getElementById('pass2').value;

                if (p1.length < 8) {
                    Swal.fire('Contraseña débil', 'Debe tener al menos 8 caracteres.', 'warning');
                    return false;
                }
                if (!/[A-Z]/.test(p1) || !/[a-z]/.test(p1)) {
                    Swal.fire('Contraseña débil', 'Debe incluir mayúsculas y minúsculas.', 'warning');
                    return false;
                }
                if (!/[0-9]/.test(p1)) {
                    Swal.fire('Contraseña débil', 'Debe incluir al menos un número.', 'warning');
                    return false;
                }
                if (!/[!@#$%^&*()_\-+={}[\]:;"'<>,.?/~`\\|]/.test(p1)) {
                    Swal.fire('Contraseña débil', 'Debe incluir al menos un carácter especial.', 'warning');
                    return false;
                }
                if (p1 !== p2) {
                    Swal.fire('Atención', 'Las contraseñas no coinciden.', 'warning');
                    return false;
                }
                return true;
            }

            function validarUsername() {
                const input = document.getElementById('usUsername');
                const val = (input.value || '').trim();

                if (!val) {
                    Swal.fire('Campo requerido', 'Ingresa un nombre de usuario.', 'warning');
                    return false;
                }
                if (!/^[A-Za-z0-9_-]{3,20}$/.test(val)) {
                    Swal.fire(
                        'Formato inválido',
                        'Usa entre 3 y 20 caracteres: letras, números, guion (-) o guion bajo (_).',
                        'warning'
                    );
                    return false;
                }

                const malas = ['puta', 'puto', 'mierda', 'pendejo', 'pendeja', 'idiota'];
                const lower = val.toLowerCase();
                if (malas.some(m => lower.includes(m))) {
                    Swal.fire(
                        'Nombre no permitido',
                        'El nombre de usuario contiene palabras no permitidas.',
                        'error'
                    );
                    return false;
                }
                return true;
            }

            document.querySelectorAll('[data-next]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const step = parseInt(this.closest('.onb-step').dataset.step, 10);

                    if (this.hasAttribute('data-validate-pass') && !validarPassword()) {
                        return;
                    }
                    if (this.hasAttribute('data-validate-username') && !validarUsername()) {
                        return;
                    }

                    if (step < totalSteps) {
                        showStep(step + 1);
                    }
                });
            });

            document.querySelectorAll('[data-prev]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const step = parseInt(this.closest('.onb-step').dataset.step, 10);
                    if (step > 1) {
                        showStep(step - 1);
                    }
                });
            });
            // Previsualización de avatar
            // Previsualización de avatar + validación de tamaño/formato
            const avatarInput = document.getElementById('usAvatar');
            const avatarPreview = document.getElementById('avatarPreview');

            if (avatarInput && avatarPreview) {
                avatarInput.addEventListener('change', function() {
                    const file = this.files && this.files[0];
                    if (!file) return;

                    const maxSize = 2 * 1024 * 1024; // 2MB
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

                    // Tamaño
                    if (file.size > maxSize) {
                        Swal.fire(
                            'Imagen muy pesada',
                            'La imagen de perfil no debe superar los 2MB.',
                            'warning'
                        );
                        this.value = '';
                        // Regresar a la imagen por defecto
                        avatarPreview.src = 'img/Usuario/user.webp';
                        return;
                    }

                    // Tipo
                    if (!allowedTypes.includes(file.type)) {
                        Swal.fire(
                            'Formato no permitido',
                            'Usa una imagen JPG, PNG o WEBP.',
                            'warning'
                        );
                        this.value = '';
                        avatarPreview.src = 'img/Usuario/user.webp';
                        return;
                    }

                    // Si pasa las validaciones → previsualizar
                    const url = URL.createObjectURL(file);
                    avatarPreview.src = url;
                });
            }

        })();
        document.addEventListener('click', function(e) {
            const a = e.target.closest('#btnLogout');
            if (!a) return;

            e.preventDefault();

            const hrefAjax = a.dataset.href || (a.getAttribute('href') + '?ajax=1');
            const redirect = a.dataset.redirect || '../login/login.php';

            fetch(hrefAjax, {
                    method: 'GET', // si prefieres, usa 'POST' y ajusta logout.php
                    credentials: 'same-origin'
                })
                .catch(() => {}) // aunque falle, intentamos redirigir
                .finally(() => {
                    window.location.href = redirect;
                });
        });
    </script>
</body>

</html>