<?php session_start();
if (empty($_SESSION['clId'])) {
    header('Location: ../login/login.php');
    exit;
}
?>
<!DOCTYPE html>
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
</head>

<body>

    <!-- Inicio NavBar -->
    <nav class="navbar navbar-expand-sm fixed-top mx-auto navbar-dark" style="background-color: var(--base-color)">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="img/Logo MR 25.png" alt="Avatar Logo" style="width:60px;" class="rounded-pill">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="collapsibleNavbar">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item ">
                        <a class="nav-link text-white" style="font-family: mistral; font-size:large;" href="javascript:void(0)"><i>MRSolutions</i></a>
                    </li>
                    <li class="nav-item ">
                        <a class="nav-link text-white" href="javascript:void(0)">Inicio</a>
                    </li>
                    <li class="nav-item ">
                        <a class="nav-link text-white" href="javascript:void(0)">Almacen</a>
                    </li>
                    <li class="nav-item ">
                        <a class="nav-link text-white" href="javascript:void(0)">Clientes</a>
                    </li>
                    <li class="nav-item ">
                        <a class="nav-link text-white" href="javascript:void(0)">Polizas</a>
                    </li>
                </ul>
                <form class="d-flex">
                    <input class="form-control me-1" type="text" placeholder="Buscar" style="border-radius: 20px;">
                    <button class="btn btn-white bg-white" type="submit" style="border-radius: 20px;"><i class="fa-solid fa-magnifying-glass"></i></button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Fin Navbar -->

    <!-- Clientes -->
    <div class="text-white text-center mx-auto margin-1200" style="background-color: var(--base-color); ">
        <div class="container-fluid row">
            <div class="col-12 col-md-6 my-auto">
                <h1>¿Buscas algo?</h1>
                <p>Necesitas encontrar una pieza, buscala con palabras clave</p>
                <form class="d-flex p-3">
                    <input class="form-control" type="text" placeholder="Buscar" style="border-radius: 20px;">
                    <button class="btn btn-white bg-white ms-1" type="submit" style="border-radius: 20px;"><i class="fa-solid fa-filter"></i></button>
                </form>
                <div class="mt-3 mx-5 p-3 px-5 border border-1 text-white rounded mb-3">
                    <h1>Escanear</h1>
                    <div class="row mt-3">
                        <div class="col-6 col-sm-6">
                            <a style="color: #fff;" href="#"><i class="fa-solid fa-barcode icon-init"></i>
                                <h3>Barras</h3>
                            </a>
                        </div>
                        <div class="col-6 col-sm-6">
                            <a style="color: #fff;" href="#"><i class="fa-solid fa-qrcode icon-init"></i>
                                <h3>QR</h3>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <img src="img/Inicio.png" class="img-fluid">
            </div>
        </div>
    </div>

    <!-- Fin clientes -->

    <!-- Refacciones -->
    <div class=" text-center mt-5">
        <div class="container-fluid row">
            <div class="col-12 col-md-6 my-auto">
                <h1>¿Clientes?</h1>
                <p>Encuentra la lista de algún cliente, selecciona la opción que mejor se acomode a tu necesidad</p>
                <a class="btn ms-1" type="button" style="border-radius: 20px; border-color:#200f4c!important; background-color:#200f4c!important; color:#fff;">Ver Clientes</a>
            </div>
            <div class="col-12 col-md-6 my-auto mb-3">
                <div class="mt-3 mx-5 p-3 px-5 border border-1 rounded" style="border-color:var(--base-color)!important;">
                    <h1>Ver Clientes</h1>
                    <div class="row mt-3">
                        <div class="col-6 col-sm-6">
                            <a style="color:var(--base-color);" href="#"><i class="fa-solid fa-server icon-init"></i>
                                <h3 class="title-l-s">Equipos</h3>
                            </a>
                        </div>
                        <div class="col-6 col-sm-6">
                            <a style="color: var(--base-color);" href="#"><i class="fa-solid fa-gears icon-init"></i>
                                <h3 class="title-l-s">Refacciones</h3>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <!-- Fin refacciones -->
    <div class="text-center bg-dark mt-5 text-white ">
        <div class="container-fluid row">
            <div class="col-12 col-md-6 my-auto mb-5 mt-5">
                <div class="mt-3 mx-5 p-3 px-5 border border-1 rounded">
                    <h1>Ver Polizas</h1>
                    <div class="row mt-3">
                        <div class="col-12">
                            <a style="color: #fff;" href="#"><i class="fa-solid fa-folder-open icon-init"></i>
                                <h3>Polizas</h3>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 my-auto">
                <h1>Polizas</h1>
                <p>Ve el estatus de las polizas activas y no activas</p>
                <a class="btn btn-white bg-white ms-1" type="button" style="border-radius: 20px; border-color:#200f4c!important;">Ver Polizas</a>
            </div>
        </div>
    </div>



    <footer class="bg-white text-dark py-4">
        <div class="container">
            <div class="row">
                <!-- Logo y descripción -->
                <div class="col-md-4 mb-3">
                    <img src="img/Logo MR 25.png" alt="MR Solutions" class="mb-2" width="80">
                    <p>Somos expertos en procesamiento, virtualización, almacenamiento y respaldo de información.</p>
                </div>

                <!-- Navegación -->
                <div class="col-md-4 mb-3">
                    <h5>Navegación</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class=" text-decoration-none">Inicio</a></li>
                        <li><a href="#" class=" text-decoration-none">Servicios</a></li>
                        <li><a href="#" class=" text-decoration-none">Nosotros</a></li>
                        <li><a href="#" class=" text-decoration-none">Contacto</a></li>
                    </ul>
                </div>

                <!-- Contacto -->
                <div class="col-md-4 mb-3">
                    <h5>Contacto</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-telephone"></i> +52 55 1234 5678</li>
                        <li><i class="bi bi-envelope"></i> contacto@mrsolutions.com.mx</li>
                        <li><i class="bi bi-geo-alt"></i> Ciudad de México, México</li>
                    </ul>
                    <div>
                        <a href="#" class="text-white me-2"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-white me-2"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="text-white"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
            </div>
            <hr class="bg-light">
            <div class="text-center">
                <p class="mb-0">&copy; 2025 MR Solutions. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Icons (si no están ya en el proyecto) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

    <!-- <ul class="cards">
        <li>
            <a href="" class="cardEsp">
                <i class="fa-solid fa-barcode icon-init card__image m-5"></i>
                <div class="card__overlay">
                    <div class="card__header">
                        <div class="card__header-text">
                            <h3 class="card__title">Jessica Parker</h3>
                            <span class="card__status">1 hour ago</span>
                        </div>
                    </div>
                    <p class="card__description">Lorem ipsum dolor sit amet consectetur adipisicing elit. Asperiores, blanditiis?</p>
                </div>
            </a>
        </li>
        <li>
            <a href="" class="cardEsp">
                <img src="img/Inicio.png" class="card__image" alt="" />
                <div class="card__overlay">
                    <div class="card__header">
                    <svg class="card__arc" xmlns="http://www.w3.org/2000/svg">
                            <path />
                        </svg>
                        <img class="card__thumb" src="https://i.imgur.com/sjLMNDM.png" alt="" />
                        <div class="card__header-text">
                            <h3 class="card__title">kim </h3>
                            <span class="card__status">3 hours ago</span>
                        </div>
                    </div>
                    <p class="card__description">Lorem ipsum dolor sit amet consectetur adipisicing elit. Asperiores, blanditiis?</p>
                </div>
            </a>
        </li>
        <li>
            <a href="" class="cardEsp">
                <img src="https://i.imgur.com/oYiTqum.jpg" class="card__image" alt="" />
                <div class="card__overlay">
                    <div class="card__header">
                        <svg class="card__arc" xmlns="http://www.w3.org/2000/svg">
                            <path />
                        </svg>
                        <img class="card__thumb" src="https://i.imgur.com/7D7I6dI.png" alt="" />
                        <div class="card__header-text">
                            <h3 class="card__title">Jessica Parker</h3>
                            <span class="card__tagline">Lorem ipsum dolor sit amet consectetur</span>
                            <span class="card__status">1 hour ago</span>
                        </div>
                    </div>
                    <p class="card__description">Lorem ipsum dolor sit amet consectetur adipisicing elit. Asperiores, blanditiis?</p>
                </div>
            </a>
        </li>
        <li>
            <a href="" class="cardEsp">
                <i class="fa-solid fa-barcode icon-init card__image"></i>

                <div class="card__overlay">
                    <div class="card__header">
                        <svg class="card__arc" xmlns="http://www.w3.org/2000/svg">
                            <path />
                        </svg>
                        <img class="card__thumb" src="https://i.imgur.com/sjLMNDM.png" alt="" />
                        <div class="card__header-text">
                            <h3 class="card__title">kim Cattrall</h3>
                            <span class="card__status">3 hours ago</span>
                        </div>
                    </div>
                    <p class="card__description">Lorem ipsum dolor sit amet consectetur adipisicing elit. Asperiores, blanditiis?</p>
                </div>
            </a>
        </li>
    </ul> -->
</body>

</html>