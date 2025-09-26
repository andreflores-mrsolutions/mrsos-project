<!DOCTYPE html>
<html lang="es">

<head>
  <title>MRSolutions</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://kit.fontawesome.com/04af9e068b.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
  <!-- /Bootstrap -->
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://kit.fontawesome.com/04af9e068b.js" crossorigin="anonymous"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>

  <!-- css -->
  <link href="../css/style.css" rel="stylesheet">

  <!-- Ajax - JQuery -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- /Ajax - JQuery -->

  <!-- JS -->
  <script src="js/main.js"></script>
  <!-- /JS -->
  <style>
    body {
      background: rgb(231, 231, 246);
    }

    /* Sidebar */
    #sidebar,
    #offcanvasSidebar {
      min-height: 100vh;
      background: rgb(15, 15, 48);
      color: #fff;
    }

    #sidebar .nav-link,
    #offcanvasSidebar .nav-link {
      color: #bbb;
    }

    #sidebar .nav-link.active,
    #sidebar .nav-link:hover,
    #offcanvasSidebar .nav-link.active,
    #offcanvasSidebar .nav-link:hover {
      background: rgba(255, 255, 255, 0.1);
      color: #fff;
    }

    @media(min-width:768px) {
      .offcanvas-lg {
        display: none;
      }
    }

    /* Main container */
    .main {
      background: #fff;
      border-radius: .5rem;
      padding: 1rem;
      margin: 1rem 0;
    }

    /* Top bar icons */
    .top-icons .bi {
      font-size: 1.25rem;
      color: #555;
      margin-left: 1rem;
      cursor: pointer;
    }

    /* Menu cards */
    .menu-card {
      flex: 1;
      min-width: 120px;
      background: #f8f9fb;
      border-radius: .5rem;
      text-align: center;
      padding: .75rem .5rem;
      cursor: pointer;
    }

    .menu-card:hover {
      flex: 1;
      min-width: 120px;
      background: rgba(44, 32, 139, 0.5);
      border-radius: .5rem;
      text-align: center;
      padding: .75rem .5rem;
      cursor: pointer;
      color: #fff;
      transition: background 0.3s;
    }

    .menu-card.active {
      background: rgb(44, 32, 139);
      color: #fff;
    }

    .menu-card .bi {
      font-size: 1.5rem;
      margin-bottom: .5rem;
    }

    /* Statistic cards */
    .stat-card {
      background: #f8f9fb;
      border-radius: .5rem;
      padding: 1rem;
      margin-bottom: 1rem;
    }
  </style>
</head>

<body>

  <div class="container-fluid">
    <div class="row gx-0">
      <!-- SIDEBAR -->
      <nav id="sidebar" class="col-2 d-none d-md-block p-3 ">
        <h5 class="mb-4">
          <a class="navbar-brand col-md-2 col-lg-2 me-0 px-3" href="#"><img src="../img/image.png" alt="Avatar Logo" style="width:200px;" class="rounded-pill"></a>
        </h5>
        <ul class="nav nav-pills flex-column">
          <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tree me-2"></i>Tickets Abiertos</a></li>
          <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-plus-circle me-2"></i>Ticket Nuevo</a></li>
          <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-gear me-2"></i>Configuración</a></li>
          <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-upload me-2"></i>Exportar Reportes</a></li>
          <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-shield-lock me-2"></i>Panel Administrador</a></li>
        </ul>
        <div class="mt-5 small text-light">Más</div>
        <ul class="nav nav-pills flex-column">
          <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-cpu me-2"></i>Mis equipos</a></li>
        </ul>
      </nav>

      <!-- OFFCANVAS SIDEBAR (xs/sm) -->
      <div class="offcanvas offcanvas-start offcanvas-lg"
        tabindex="-1"
        id="offcanvasSidebar"
        data-bs-backdrop="false"
        data-bs-scroll="true"
        aria-labelledby="offcanvasWithBackdropLabel">
        <div class="">
          <h5 class="mb-4">
            <h5 class="offcanvas-title" id="offcanvasWithBackdropLabel"><a class="navbar-brand col-md-2 col-lg-2 me-0 px-3" href="#"><img src="../img/image.png" alt="Avatar Logo" style="width:200px;" class="rounded-pill"></a></h5>
            <button type="button" class="btn btn-outline-light text-reset text-light float-end me-3" style="color: #fff; margin-top: -50px;" data-bs-dismiss="offcanvas" aria-label="Close"><i class="bi bi-chevron-left"></i></button>
          </h5>
        </div>
        <div class="offcanvas-body">
          <ul class="nav nav-pills flex-column">
            <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tree me-2"></i>Tickets Abiertos</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-plus-circle me-2"></i>Ticket Nuevo</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-gear me-2"></i>Configuración</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-upload me-2"></i>Exportar Reportes</a></li>
            <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-shield-lock me-2"></i>Panel Administrador</a></li>
          </ul>
        </div>
      </div>


      <!-- MAIN -->
      <main class="col-md-10  px-4">
        <!-- Top bar -->
        <!-- Contenedor general -->
        <div class="d-flex align-items-center justify-content-between py-3 px-2 px-md-4 ">
          <!-- Lado izquierdo -->
          <div class="d-flex align-items-center">
            <!-- Botón hamburguesa solo en sm y xs -->
            <button class="btn btn-outline-secondary d-lg-none me-2"
              data-bs-toggle="offcanvas"
              data-bs-target="#offcanvasSidebar"
              aria-controls="offcanvasSidebar">
              <i class="bi bi-list"></i>
            </button>

            <!-- Logo cliente siempre visible -->
            <span class="badge bg-light me-3 p-2">
              <img src="../../img/Clientes/enel.svg" style="height:30px;" alt="cliente">
            </span>

            <!-- Estado: en mobiles pequeño badge -->
            <span class="badge bg-success me-3 d-none d-sm-inline-block">Activo</span>

            <!-- Responsable: solo avatar en xs & sm -->
            <img src="img/Usuario/andre.jpeg"
              class="rounded-circle me-2 d-inline-block"
              alt="Responsable"
              style="width: 40px; height: 40px; object-fit: cover; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
            <span class="d-none d-sm-inline">Responsable del proyecto</span>
          </div>

          <!-- Lado derecho -->
          <div class="d-flex align-items-center">
            <!-- Íconos grandes: solo md+ -->
            <div class="d-none d-md-flex align-items-center top-icons me-3">
              <i class="bi bi-search mx-2"></i>
              <i class="bi bi-arrow-clockwise mx-2"></i>
              <i class="bi bi-bell mx-2"></i>
              <i class="bi bi-question-circle mx-2"></i>
            </div>

            <!-- Dropdown general en sm- -->
            <div class="dropdown d-md-none me-2">
              <button class="btn btn-outline-secondary" type="button" id="moreActions" data-bs-toggle="dropdown">
                <i class="bi bi-three-dots-vertical"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="moreActions">
                <li><a class="dropdown-item" href="#"><i class="bi bi-search me-2"></i>Buscar</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-arrow-clockwise me-2"></i>Refrescar</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-bell me-2"></i>Notificaciones</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-question-circle me-2"></i>Ayuda</a></li>
              </ul>
            </div>

            <!-- Perfil -->
            <div class="nav-item dropdown">
              <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle"
                id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="../img/Usuario/chilaquil.jpg"
                  class="rounded-circle me-2"
                  alt="Usuario"
                  style="width: 40px; height: 40px; object-fit: cover; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <span class="d-none d-md-inline"><strong>André Flores</strong></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Mis datos</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
              </ul>
            </div>
          </div>
        </div>


        <!-- Recent Incidents -->
        <div class="main mb-4">
          <h5>Incidentes Recientes</h5>
          <div class="table-responsive">
            <table class="table table-borderless mb-0">
              <thead>
                <tr>
                  <th>Estado</th>
                  <th>Equipo</th>
                  <th class="d-none d-sm-table-cell">Marca</th>
                  <th class="d-none d-md-table-cell">SN</th>
                  <th class="d-none d-lg-table-cell">Estatus</th>
                  <th class="d-none d-lg-table-cell">Tipo de ticket</th>
                  <th class="d-none d-md-table-cell">Extras</th>
                </tr>
              </thead>
              <tbody id="contenedorTickets">
                <!-- <tr>
                  <td><span class="badge bg-success">Activo</span></td>
                  <td>PowerEdge R750 15G</td>
                  <td class="d-none d-sm-table-cell"><img src="../../img/Marcas/dell.png" style="height:20px;" alt="cliente"></td>
                  <td class="d-none d-md-table-cell">2106195YSAXEP2000009</td>
                  <td class="d-none d-lg-table-cell"><span class="badge bg-light text-dark">Logs</span></td>
                  <td class="d-none d-lg-table-cell"><span class="badge bg-primary">Servicio</span></td>
                  <td class="d-none d-md-table-cell">Meet</td>
                  <td><a href="#">Ver más</a></td>
                </tr>
                <tr>
                  <td><span class="badge bg-success">Activo</span></td>
                  <td>FusionServer 2288H V6</td>
                  <td class="d-none d-sm-table-cell"><img src="../../img/Marcas/xFusion.png" style="height:20px;" alt="cliente"></td>
                  <td class="d-none d-md-table-cell">2106195YSAXEP2000008</td>
                  <td class="d-none d-lg-table-cell"><span class="badge bg-light text-dark">En camino</span></td>
                  <td class="d-none d-lg-table-cell"><span class="badge bg-secondary">Preventivo</span></td>
                  <td class="d-none d-md-table-cell">--</td>
                  <td><a href="#">Ver más</a></td>
                </tr> -->
              </tbody>
            </table>
          </div>

        </div>

        <!-- Menu -->
        <div class="d-flex gap-3 mb-4 row">
          <div class="col-2 menu-card active"><i class="bi bi-exclamation-triangle"></i><br>Tickets</div>
          <!-- <div class="col-2 menu-card"><i class="bi bi-arrow-repeat"></i><br>Cycles</div>
          <div class="col-2 menu-card"><i class="bi bi-wifi"></i><br>Sensor Data</div> -->
          <div class="col-2 menu-card"><i class="bi bi-file-earmark-text"></i><br>Hojas de servicio</div>
          <div class="col-2 menu-card"><i class="bi bi-sliders"></i><br>Ajustes</div>
        </div>

        <!-- Statistics -->
        <div class="row">
          <!-- Area chart -->
          <div class="col-lg-6">
            <div class="stat-card">
              <h6>Incidents (Last 7 days)</h6>
              <canvas id="areaChart"></canvas>
            </div>
          </div>
          <!-- Donut chart -->
          <div class="col-md-3">
            <div class="stat-card text-center">
              <h6>Average Cycle Length<br><small>Last 7 days</small></h6>
              <canvas id="donutChart" style="max-width:120px; display:initial!important;"></canvas>
            </div>
          </div>
          <!-- Bar chart -->
          <div class="col-md-3">
            <div class="stat-card text-center">
              <h6>Incident Messages<br><small>Last 7 days</small></h6>
              <canvas id="barChart" style="max-width:120px; display:initial!important;"></canvas>
            </div>
          </div>
        </div>
      </main>
    </div>

  </div>

  <!-- Bootstrap JS Bundle -->

  <script>
    // Area chart
    new Chart(document.getElementById('areaChart'), {
      type: 'line',
      data: {
        labels: ['03.09', '04.09', '05.09', '06.09', '07.09', '08.09', '09.09'],
        datasets: [{
          data: [1, 2, 3, 4, 3, 5, 4],
          fill: true,
          backgroundColor: 'rgba(115,96,255,0.2)',
          borderColor: 'rgba(115,96,255,1)',
          tension: 0.4,
          pointRadius: 0
        }]
      },
      options: {
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          x: {
            display: false
          },
          y: {
            display: false
          }
        }
      }
    });

    // Donut chart
    new Chart(document.getElementById('donutChart'), {
      type: 'doughnut',
      data: {
        labels: ['', ''],
        datasets: [{
          data: [80, 20],
          backgroundColor: ['#7360ff', '#eee']
        }]
      },
      options: {
        cutout: '75%',
        plugins: {
          tooltip: {
            enabled: false
          },
          legend: {
            display: false
          },
          doughnutlabel: {
            labels: [{
              text: '80%',
              font: {
                size: '20'
              }
            }]
          }
        }
      }
    });

    // Bar chart
    new Chart(document.getElementById('barChart'), {
      type: 'bar',
      data: {
        labels: ['M', 'T', 'W', 'T', 'F', 'S', 'S'],
        datasets: [{
          data: [3, 4, 2, 5, 4, 6, 3],
          backgroundColor: '#7360ff',
          barPercentage: 0.6
        }]
      },
      options: {
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          x: {
            display: false
          },
          y: {
            display: false
          }
        }
      }
    });
  </script>
</body>

</html>