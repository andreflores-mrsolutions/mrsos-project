<?php session_start();
if (empty($_SESSION['clId'])) {
    header('Location: ../login/login.php');
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


    <!-- Bootstrap core CSS -->
    <link href="assets/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Ajax - JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- /Ajax - JQuery -->

    <!-- JS -->
    <script src="js/main.js"></script>
    <!-- /JS -->
    <style>

    </style>


    <!-- Custom styles for this template -->
    <link href="pricing.css" rel="stylesheet">
</head>

<body>
    <?php
    if (!empty($_SESSION['uNombre'])) {
    ?>

        <!-- Ventana muestra -->
    <?php } else { ?>
    <?php } ?>

    <div class="container py-3">
        <header>
            <div class="d-flex flex-column flex-md-row align-items-center py-2 px-5 border-bottom fixed-top bg-white shadow">

                <nav class="nav-masthead navbar-expand-sm navbar d-flex justify-content-between mt-2 mt-md-0 container-fluid">

                    <a class="navbar-brand">
                        <img src="img/MRlogo.png" alt="Avatar Logo" style="width:200px;" class="rounded-pill">
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="collapsibleNavbar" style="flex-grow:unset;">
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="me-3 py-2 text-dark text-decoration-none nav-link active" href="#">Inicio</a>
                            </li>
                            <li class="nav-item">
                                <a class="me-3 py-2 text-dark text-decoration-none nav-link" href="#">Mi Póliza</a>
                            </li>
                            <li class="nav-item">
                                <a class="me-3 py-2 text-dark text-decoration-none nav-link" href="dashboard/home.php">Dashboard</a>
                            </li>
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
                                    <li><a class="dropdown-item" href="#">Cerrar Sesión</a></li>
                                </ul>
                            </li>

                        </ul>
                    </div>
                </nav>
            </div>
        </header>

        <main class="mx-auto">
            <div class="px-4 py-5 my-5 text-center">
                <img class="d-block mx-auto mb-4" src="img/Logo MR 25.png" alt="" width="150">
                <h1 class="display-5 fw-bold" style="color: var(--base-color);">¡Bienvenido!</h1>
                <div class="col-lg-6 mx-auto">
                    <p class="lead mb-4">MRSolutions tiene el objetivo de brindar tranquilidad y seguridad, y su elección de la póliza Platinum permite cumplir con este compromiso de una manera aún más excepcional.</p>
                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    </div>
                </div>
                <div class="row row-cols-1 row-cols-lg-3 align-items-stretch g-4 py-5">
                    <div class="col">
                        <div class="card card-cover h-100 overflow-hidden rounded-5 shadow-lg" style="background-image: url('unsplash-photo-1.jpg');">
                            <div class="d-flex flex-column h-100 p-5 pb-3 text-shadow-1">
                                <h3 class="pt-5 mb-4 lh-1 fw-bold">¡En hora buena, el 100% de tus tickets han sido resueltos con éxito!</h3>
                                <ul class="d-flex list-unstyled mt-auto">
                                    <li class="me-auto">
                                        <button type="button" class="btn w-100 btn-card">Ver más..</button>
                                    </li>

                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="card card-cover h-100 overflow-hidden text-white rounded-5 shadow-lg" style="background-image: url('unsplash-photo-2.jpg'); background-color:var(--base-color);">
                            <div class="d-flex flex-column h-100 p-5 pb-3 text-white text-shadow-1">
                                <h2 class="pt-5 display-6 lh-1 fw-bold" id="totalTickets">0</h2>
                                <h2 class=" mb-4  lh-1 fw-bold">Tickets abiertos actualmente</h2>
                                <ul class="d-flex list-unstyled mt-auto">
                                    <li class="me-auto">
                                        <button type="button" class="btn w-100 btn-card-bg-dark">Ver más..</button>

                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="card card-cover h-100 overflow-hidden rounded-5 shadow-lg" style="background-image: url('unsplash-photo-3.jpg');">
                            <div class="d-flex flex-column h-100 p-5 pb-3 text-shadow-1">
                                <h2 class="pt-5 display-6 lh-1 fw-bold" id="equiposPoliza">0</h2>
                                <h2 class=" mb-4  lh-1 fw-bold">Actualmente tienes <p id="equiposPoliza">0</p> equipos en poliza</h2>
                                <ul class="d-flex list-unstyled mt-auto">
                                    <li class="me-auto">
                                        <button type="button" class="btn w-100 btn-card">Ver más..</button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="b-example-divider"></div>

            <div id="bloqueTickets" class="mt-5 d-none">
                <div class="pricing-header p-3 pb-md-4 mx-auto">
                    <h1 class="fw-normal border-bottom">Mis Tickets</h1>
                </div>

                <div class="text-end position-relative">
                    <a href="#" id="dropdownTickets" data-bs-toggle="dropdown" aria-expanded="false" class="text-dark text-decoration-none nav-link active">
                        <h5 class="fw-normal dropdown-toggle">Ver Todos</h5>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownTickets">
                        <li><a class="dropdown-item" href="#">Todos los Tickets</a></li>
                        <li><a class="dropdown-item" href="#">Tickets Activos</a></li>
                        <li><a class="dropdown-item" href="#">Tickets Finalizados</a></li>
                    </ul>
                </div>


                <div class="container row row-cols-1 row-cols-md-3 mb-3 text-center px-2 mx-auto" id="ticketCardsContainer">
                    <!-- Aquí se insertarán las tarjetas con JS -->

                </div>
            </div>

        </main>

        <footer class="pt-4 my-md-5 pt-md-5 border-top">
            <div class="row">
                <div class="col-12 col-md">
                    <img class="mb-2" src="img/Logo MR 25.png" alt="" width="90">
                    <small class="d-block mb-3 text-muted">&copy; 2025</small>
                </div>
                <div class="col-6 col-md">
                </div>
                <div class="col-6 col-md">
                    <h5></h5>
                    <ul class="list-unstyled text-small">
                        <li class="mb-1"><a class="link-secondary text-decoration-none" href="#">ventas@mrsolutions.com.mx</a></li>
                        <li class="mb-1"><a class="link-secondary text-decoration-none" href="#">+52 5523 319918</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md">
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
    </div>



</body>

</html>