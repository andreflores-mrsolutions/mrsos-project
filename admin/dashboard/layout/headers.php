<?php session_start(); 
if (!empty($_SESSION['uNombre'])) {
    
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

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

    <!-- css -->
    <link href="../../css/style.css" rel="stylesheet">


    <!-- Bootstrap core CSS -->
    <link href="../../assets/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Ajax - JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- /Ajax - JQuery -->

    <!-- JS -->
    <script src="../../js/main.js"></script>
    <script src="../../js/perfil.js"></script>
    <!-- /JS -->
    <style></style>


    <style>
        .btn-menu {
            width: 100%;
        }

        .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            user-select: none;
        }

        @media (min-width: 768px) {
            .bd-placeholder-img-lg {
                font-size: 3.5rem;
            }
        }

        /* Sidebar normal visible solo en >=600px */
        @media (max-width: 600px) {
            #sidebar {
                display: none !important;
            }
        }

        /* Bottom nav solo en <600px */
        #bottomNav {
            display: none;
        }

        @media (max-width: 600px) {
            #bottomNav {
                display: flex;
                justify-content: space-around;
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                background-color: #f8f9fa;
                padding: 10px 0;
                border-top: 1px solid #ddd;
                z-index: 1050;
            }
        }

        /* Para ajustar contenido según tamaño */
        #main-content {
            margin-left: 250px;
            padding-top: 60px;
        }

        @media (max-width: 600px) {
            #main-content {
                margin-left: 0;
                padding-bottom: 60px;
            }
        }

        header {
            position: fixed;
            width: 100%;
            z-index: 1050;
        }
    </style>


    <!-- Custom styles for this template -->
    <link href="../dashboard.css" rel="stylesheet">
</head>

<body>

    <header class="navbar navbar-light sticky-top bg-light flex-md-nowrap p-0 shadow ">
        <a class="navbar-brand col-md-2 col-lg-2 me-0 px-3 bg-light" href="#"><img src="../../img/MRlogo.png" alt="Avatar Logo" style="width:150px;" class="rounded-pill"></a>
        <input class="form-control form-control-dark w-100" type="text" placeholder="Search" aria-label="Search">
    </header>


    <!-- 🔹 Sidebar con animación (pantallas >=600px) -->
  <div id="sidebar" class="sidebar d-flex flex-column">
    <!-- Imagen de perfil del usuario -->
    <div class="text-center p-2">
    <img id="imagenPerfil" src="../../img/Usuario/chilaquil.jpg" alt="Perfil" class="rounded-circle" style="
        width: 40px;   /* Tamaño reducido */
        height: 40px;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    ">
    <h6 id="nombreUsuario" class="mt-1 mb-0" style="font-size: 0.5rem;"><?php echo $_SESSION['usUsername']; ?></h6>
</div>

    <a href="#" class="nav-link" onclick="toggleSidebar()">
      <i class="bi bi-list"></i><span></span>
    </a>
    <a href="../index.php" class="nav-link"><i class="bi bi-house"></i><span>Inicio</span></a>
    <a href="perfil.php" class="nav-link"><i class="bi bi-person"></i><span>Perfil</span></a>
    <a href="#" class="nav-link"><i class="bi bi-plus"></i><span>Nuevo Ticket</span></a>
    <a href="#" class="nav-link"><i class="bi bi-file-earmark"></i><span>Poliza</span></a>
    <a href="#" class="nav-link"><i class="bi bi-clipboard"></i><span>Hojas de Servicio</span></a>
    <a href="#" class="nav-link"><i class="bi bi-gear"></i><span>Configuración</span></a>
  </div>


<script>
    function toggleSidebar() {
      document.getElementById("sidebar").classList.toggle("expand");
    }
  </script>

  <!-- 🔸 Bottom nav solo en móviles -->
  <div id="bottomNav" class="d-md-none d-flex justify-content-around fixed-bottom py-2 bg-light" style="z-index: 1030;">
    <button class="btn btn-light btn-menu"><i class="bi bi-house"></i></button>
    <button class="btn btn-light btn-menu">
        <img id="imagenPerfilMini" src="../../img/Usuario/chilaquil.jpg" alt="Perfil" class="rounded-circle" style="width: 30px; height: 30px; object-fit: cover;">
    </button>
    <button class="btn btn-success btn-menu"><i class="bi bi-plus"></i></button>
    <button class="btn btn-light btn-menu"><i class="bi bi-file-earmark"></i></button>
    <button class="btn btn-light btn-menu"><i class="bi bi-gear"></i></button>
  </div>