<?php
session_start();
if (empty($_SESSION['usId'] || $_SESSION['usEstatus'] !== 'Activo' || empty($_SESSION['usEstatus']))) {
    header('Location: login/login.php');
    exit;
}
if ($_SESSION['usEstatus'] === 'NewPass' || empty($_SESSION['usEstatus'])) {
    header('Location: login/cambiar_password.php');
    exit;
}
?>
<!doctype html>
<html lang="en">

<head>
    <title>MRSolutions</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/04af9e068b.js" crossorigin="anonymous"></script>

    <!-- /Bootstrap -->
    <link href="css/style.css" rel="stylesheet">

    <!-- Ajax - JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- /Ajax - JQuery -->

    <!-- JS -->
    <script src="js/sos.js"></script>
    <!-- <script src="js/main.js"></script> -->
    <!-- /JS -->
    <style>

    </style>
</head>

<body>
    <div class="container py-3">
        <header>
            <div class="d-flex flex-column flex-md-row align-items-center py-2 px-5 border-bottom fixed-top bg-white shadow">

                <nav class="nav-masthead navbar-expand-sm navbar d-flex justify-content-between mt-2 mt-md-0 container-fluid">

                    <a class="navbar-brand">
                        <img id="logoMR" src="img/MRlogo.png" alt="Logo MR" style="width:200px;" class="rounded-pill">
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="collapsibleNavbar" style="flex-grow:unset;">
                        <ul class="navbar-nav me-auto">
                            <!-- 🔘 SWITCH DE MODO OSCURO -->
                            <li class="nav-item d-flex align-items-center mx-3">
                                <div class="theme-switch-wrapper">
                                    <label class="theme-switch" for="darkModeToggle">
                                        <input type="checkbox" id="darkModeToggle">
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                            </li>

                            <li class="nav-item">
                                <a class="me-3 py-2 text-dark text-decoration-none nav-link active" href="#">Inicio</a>
                            </li>
                            <?php
                            if ($_SESSION['clId'] === 1) {
                            ?>
                                <li class="nav-item">
                                    <a class="me-3 py-2 text-dark text-decoration-none nav-link" href="backend/">Dashboard Admin</a>
                                </li>
                            <?php
                            } else {
                            ?>
                                <li class="nav-item">
                                    <a class="me-3 py-2 text-dark text-decoration-none nav-link" href="dashboard/poliza.php">Mi Póliza</a>
                                </li>
                                <li class="nav-item">
                                    <a class="me-3 py-2 text-dark text-decoration-none nav-link" href="dashboard/home.php">Dashboard</a>
                                </li>
                            <?php
                            }
                            ?>
                            <li class="nav-item dropdown">
                                <a class="me-3 py-2 text-dark text-decoration-none nav-link" href="#" id="dropdown09" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="d-flex align-items-center">
                                        <div class="me-2 text-end">
                                            <div class="fw-bold">¡Hola <span id="nombreUsuario"><?php echo $_SESSION['usUsername']; ?></span>!</div>
                                            <div class="text-muted dropdown-toggle" id="tipoPoliza" style="color:var(--base-color); text-shadow: 0 0 1px rgb(109, 109, 255);"></div>
                                        </div>
                                        <?php

                                        // Ajusta si tu archivo está en otra carpeta
                                        $usuario  = $_SESSION['usUsername'] ?? 'default';
                                        $usuario  = preg_replace('/[^A-Za-z0-9_\-]/', '', $usuario); // sanitiza por seguridad

                                        $dirFS  = __DIR__ . '/img/Usuario/'; // ruta en el sistema de archivos
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

                                        <div class="position-relative" style="width: 50px; height: 50px;">
                                            <img
                                                src="<?= htmlspecialchars($src, ENT_QUOTES) ?>"
                                                class="rounded-circle me-2"
                                                alt="Usuario"
                                                style="width: 40px; height: 40px; object-fit: cover; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"
                                                onerror="this.onerror=null;this.src='../img/Usuario/user.webp';" />
                                        </div>
                                    </div>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="dropdown09">
                                    <li><a class="dropdown-item" href="#">Mis datos</a></li>
                                    <li><a class="dropdown-item" href="../php/logout.php"
                                            data-href="../php/logout.php?ajax=1"
                                            data-redirect="../login/login.php">Cerrar Sesión</a></li>
                                </ul>
                            </li>

                        </ul>
                    </div>
                </nav>
            </div>
        </header>

        <main class="mx-auto" style="margin-top: 150px;">
            <!-- ========== MR SoS: Landing Cliente ========== -->
            <!-- ========== MR SoS · Landing (paleta propia + dark-mode) ========== -->
            <section class="mrs-hero rounded-4 p-5 mb-4 text-center">
                <div class="container">
                    <span class="badge mrs-hero-badge mb-3">MRSolutions · Support One Service</span>
                    <h1 class="display-6 fw-bold mb-2">
                        Soporte inteligente, <span class="fw-bolder">sin fricción</span>.
                    </h1>
                    <p class="lead m-0 m-auto mb-4" style="max-width: 60ch;">
                        Centraliza tickets, seguimiento por procesos, reuniones y carga guiada de logs.
                        Todo en una sola vista clara.
                    </p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a href="dashboard/home.php" class="btn btn-primary btn-lg px-4 shadow-sm">
                            <i class="bi bi-ticket-perforated me-2"></i> Ir a mis tickets
                        </a>
                        <a href="dashboard/nuevo_ticket.php" class="btn btn-outline-primary btn-lg px-4">
                            <i class="bi bi-camera-video me-2"></i> Crear Tikcet
                        </a>
                    </div>
                </div>
            </section>
            <section class="container mb-5">
                <!-- Métricas -->
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-3">
                        <div class="mrs-kpi">
                            <div class="mrs-kpi-title">Tickets activos</div>
                            <div id="kpiTickets" class="mrs-kpi-value">—</div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="mrs-kpi">
                            <div class="mrs-kpi-title">Equipos en póliza</div>
                            <div id="kpiEquipos" class="mrs-kpi-value">—</div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="mrs-kpi">
                            <div class="mrs-kpi-title">Tiempo de respuesta</div>
                            <div class="mrs-kpi-value">≤ 4 hrs</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="mrs-kpi">
                            <div class="mrs-kpi-title">Satisfacción</div>
                            <div class="mrs-kpi-value">★ ★ ★ ★ ★</div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- ========== Sección corporativa: Soluciones TI Integrales ========== -->
            <section class="container mb-5 mrs-hero-corp">
                <div class="row align-items-center g-4">
                    <!-- Texto -->
                    <div class="col-12 col-lg-6">
                        <p class="text-uppercase small fw-semibold text-primary mb-2">
                            MR Solutions · Integrador TI
                        </p>
                        <h2 class="display-6 fw-bold mb-3">
                            Soluciones TI <span class="text-primary">Integrales</span> para tu Empresa
                        </h2>
                        <p class="lead text-muted mb-4">
                            Más de 25 años de experiencia como empresa 100% mexicana,
                            especializada en almacenamiento, respaldo, virtualización
                            y soluciones cloud empresariales.
                        </p>

                        <div class="d-flex flex-wrap gap-3">
                            <div class="d-inline-flex align-items-center gap-2 mrs-pill">
                                <i class="bi bi-clock-history mrs-pill-icon"></i>
                                <span class="small">+25 años de experiencia</span>
                            </div>
                            <div class="d-inline-flex align-items-center gap-2 mrs-pill">
                                <i class="bi bi-flag mrs-pill-icon"></i>
                                <span class="small">100% empresa mexicana</span>
                            </div>
                            <div class="d-inline-flex align-items-center gap-2 mrs-pill">
                                <i class="bi bi-patch-check mrs-pill-icon"></i>
                                <span class="small">Partners certificados</span>
                            </div>
                        </div>
                    </div>

                    <!-- Imagen -->
                    <div class="col-12 col-lg-6">
                        <div class="position-relative mrs-hero-corp-img-wrap">
                            <img
                                src="../img/landing/rack-mrsolutions.jpg"
                                alt="Infraestructura de servidores MR Solutions"
                                class="img-fluid rounded-4 shadow-lg mrs-hero-corp-img">

                            <!-- Tarjeta flotante 500+ -->
                            <div class="mrs-stat-floating shadow-lg">
                                <div class="fw-bold fs-4">500+</div>
                                <small class="text-muted d-block">Proyectos Exitosos</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>



            <!-- ========== Sección: Quiénes Somos ========== -->
            <section class="container mb-5 mrs-about">
                <div class="text-center mb-4">
                    <h2 class="h3 fw-bold mb-2">Quiénes <span class="text-primary">Somos</span></h2>
                    <p class="text-muted mb-0">
                        Una empresa 100% mexicana con más de 25 años de trayectoria,
                        especializada en brindar soluciones tecnológicas de clase mundial.
                    </p>
                </div>

                <div class="row g-4 align-items-center mb-4">
                    <!-- Imagen -->
                    <div class="col-12 col-lg-5">
                        <img
                            src="../img/landing/equipo-mrsolutions.jpg"
                            alt="Equipo de trabajo MR Solutions"
                            class="img-fluid rounded-4 shadow mrs-about-img">
                    </div>

                    <!-- Texto historia -->
                    <div class="col-12 col-lg-7">
                        <h3 class="h5 fw-semibold mb-3">Nuestra Historia</h3>
                        <p class="text-muted">
                            Desde nuestros inicios hace más de 25 años, MR Solutions se ha consolidado
                            como un integrador y consultor TI de confianza, enfocado en proporcionar
                            soluciones de almacenamiento, respaldo, virtualización y cloud computing.
                        </p>
                        <p class="text-muted mb-3">
                            Como empresa 100% mexicana, entendemos las necesidades específicas del mercado
                            nacional y ofrecemos soluciones adaptadas que impulsan la transformación digital
                            de nuestros clientes.
                        </p>

                        <figure class="mrs-quote p-3 rounded-4 mb-0">
                            <blockquote class="mb-1 small">
                                “Nuestro compromiso es ser el socio tecnológico que acompañe a las empresas
                                en su crecimiento y evolución digital.”
                            </blockquote>
                            <figcaption class="small text-muted mb-0">
                                — MR Solutions
                            </figcaption>
                        </figure>
                    </div>
                </div>

                <!-- Misión, Visión, Valores, Equipo -->
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="mrs-info-card h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-bullseye mrs-info-icon"></i>
                                <h4 class="h6 fw-bold m-0">Misión</h4>
                            </div>
                            <p class="small mb-0">
                                Brindar soluciones tecnológicas integrales que impulsen el crecimiento
                                y competitividad de nuestros clientes.
                            </p>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="mrs-info-card h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-eye mrs-info-icon"></i>
                                <h4 class="h6 fw-bold m-0">Visión</h4>
                            </div>
                            <p class="small mb-0">
                                Ser el partner tecnológico líder en México, reconocido por nuestra
                                excelencia e innovación.
                            </p>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="mrs-info-card h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-heart mrs-info-icon"></i>
                                <h4 class="h6 fw-bold m-0">Valores</h4>
                            </div>
                            <p class="small mb-0">
                                Compromiso, calidad, innovación y servicio al cliente como los pilares
                                de nuestra organización.
                            </p>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="mrs-info-card h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-people mrs-info-icon"></i>
                                <h4 class="h6 fw-bold m-0">Equipo</h4>
                            </div>
                            <p class="small mb-0">
                                Profesionales certificados con amplia experiencia en las mejores
                                prácticas de la industria.
                            </p>
                        </div>
                    </div>
                </div>
            </section>


    </div>

    <section class="mrs-band-why py-5">
        <div class="container py-3">
            <section class="container mb-5">
                <!-- Beneficios -->
                <h2 class="h4 fw-semibold mb-3">¿Por qué usar MR SoS?</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="mrs-card h-100">
                            <i class="bi bi-diagram-3 mrs-icon"></i>
                            <h3 class="h6 fw-bold">Flujo por procesos</h3>
                            <p class="mb-0">Estados claros: asignación, logs, meet, visita y encuesta.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="mrs-card h-100">
                            <i class="bi bi-cloud-arrow-up mrs-icon"></i>
                            <h3 class="h6 fw-bold">Carga guiada de logs</h3>
                            <p class="mb-0">Guías por marca/modelo para evidencias correctas.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="mrs-card h-100">
                            <i class="bi bi-camera-video mrs-icon"></i>
                            <h3 class="h6 fw-bold">Meet en un clic</h3>
                            <p class="mb-0">Agenda y confirma reuniones con recordatorios.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="mrs-card h-100">
                            <i class="bi bi-bell mrs-icon"></i>
                            <h3 class="h6 fw-bold">Notificaciones</h3>
                            <p class="mb-0">Activa WebPush y entérate al instante.</p>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="mrs-card h-100">
                            <i class="bi bi-shield-check mrs-icon"></i>
                            <h3 class="h6 fw-bold">Orientado a SLA</h3>
                            <p class="mb-0">Ventanas y prioridades según tu póliza.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="container mb-5">
                <!-- Cómo funciona -->
                <div class="row g-4 align-items-center">
                    <div class="col-12 col-lg-5">
                        <h2 class="h4 fw-semibold mb-3">¿Cómo funciona?</h2>
                        <p class="text-muted mb-3">De la apertura a la resolución, con visibilidad en cada paso.</p>
                        <ul class="list-unstyled m-0">
                            <li class="d-flex align-items-start mb-2">
                                <span class="mrs-step">1</span>
                                <div><b>Abrir ticket</b><br><small>Describe el incidente o servicio.</small></div>
                            </li>
                            <li class="d-flex align-items-start mb-2">
                                <span class="mrs-step">2</span>
                                <div><b>Enviar logs/evidencia</b><br><small>Guías por marca/modelo.</small></div>
                            </li>
                            <li class="d-flex align-items-start mb-2">
                                <span class="mrs-step">3</span>
                                <div><b>Análisis de la falla</b><br><small>Los expertos de MRSolutions diagnostican la falla.</small></div>
                            </li>
                            <li class="d-flex align-items-start mb-2">
                                <span class="mrs-step">4</span>
                                <div><b>Agendar Meet/Visita</b><br><small>Confirmación rápida.</small></div>
                            </li>
                            <li class="d-flex align-items-start">
                                <span class="mrs-step">5</span>
                                <div><b>Resolución y encuesta</b><br><small>Cierre y calificación.</small></div>
                            </li>
                        </ul>
                    </div>
                    <div class="col-12 col-lg-7">
                        <div class="mrs-progress px-3 py-4 rounded-4">
                            <div class="d-flex justify-content-between small text-muted mb-2">
                                <span>Asignación</span><span>Logs</span><span>Análisis</span><span>Visita</span><span>Encuesta</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" style="width: 65%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="container mb-5">
                <!-- CTA final + Aviso -->
                <div class="row g-3">
                    <div class="col-12 col-lg-8">
                        <div class="mrs-cta p-4 rounded-4 d-flex flex-wrap align-items-center justify-content-between">
                            <div class="me-3">
                                <h3 class="h5 m-0">¿Necesitas levantar un ticket ahora?</h3>
                                <small class="text-muted">Te tomará menos de 2 minutos.</small>
                            </div>
                            <div class="d-flex gap-2 mt-3 mt-lg-0">
                                <a href="dashboard/nuevo_ticket.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Nuevo ticket
                                </a>
                                <a href="dashboard/home.php" class="btn btn-outline-primary">
                                    <i class="bi bi-calendar-check me-2"></i>Ver mis tickets
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="mrs-news p-4 rounded-4 h-100">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-megaphone me-2"></i><b>Novedades</b>
                            </div>
                            <ul class="small m-0 ps-3">
                                <li>Nuevo módulo de encuesta de satisfacción.</li>
                                <li>Asistente para extracción de logs por marca.</li>
                                <li>Vista de estados mejorada en detalle de ticket.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
            <!-- ========== /MR SoS · Landing ========== -->

            <script>
                // KPIs dinámicos (opcional)
                (function() {
                    if (!window.jQuery) return;
                    $.getJSON("../php/getIndexData.php").done(function(d) {
                        if (d?.clientes) $("#kpiClientes").text(d.clientes);
                        if (d?.totalEquipos) $("#kpiEquipos").text(d.totalEquipos);
                    });
                })();
            </script>



        </div>
    </section>
    </main>

    <footer class="pt-4 my-md-5 pt-md-5 border-top">
        <div class="row">
            <div class="col-12 col-md">
                <img class="mb-2" src="img/Logo MR 25.png" alt="" width="90">
                <small class="d-block mb-3 text-muted">&copy; 2025</small>
            </div>
            <div class="col-12 col-md">
            </div>
            <div class="col-12 col-md">
                <h5></h5>
                <ul class="list-unstyled text-small">
                    <li class="mb-1"><a class="link-secondary text-decoration-none" href="#">ventas@mrsolutions.com.mx</a></li>
                    <li class="mb-1"><a class="link-secondary text-decoration-none" href="#">+52 5523 319918</a></li>
                </ul>
            </div>
            <div class="col-12 col-md">
                <h5>About</h5>
                <ul class="list-unstyled text-small">
                    <li class="mb-1"><a class="link-secondary text-decoration-none" href="#">México CDMX</a></li>
                    <li class="mb-1"><a class="link-secondary text-decoration-none" href="#">Alhambra 813 Bis</a></li>
                    <li class="mb-1"><a class="link-secondary text-decoration-none" href="#">Portales Sur</a></li>
                    <li class="mb-1"><a class="link-secondary text-decoration-none" href="#">Benito Juárez, 03300</a></li>
                </ul>
            </div>
        </div>
    </footer>




</body>

</html>
<script>
    (function() {
        if (!window.jQuery) return;

        $.getJSON("../php/getIndexData.php").done(function(d) {
            if (!d || !d.success) return;

            if (typeof d.ticketsAbiertos !== "undefined") {
                $("#kpiTickets").text(d.ticketsAbiertos); // aquí tu card de "Tickets act."
            }

            if (typeof d.equipos !== "undefined") {
                $("#kpiEquipos").text(d.equipos); // card "Equipos en póliza"
            }

            if (d.poliza) {
                $("#tipoPoliza").text(d.poliza);
            }
        });
    })();
</script>