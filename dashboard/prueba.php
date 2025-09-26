<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Clone</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
  <style>
    body {
      background: #ede7f6;
    }
    /* Sidebar */
    #sidebar {
      min-height: 100vh;
      background: #1e1e2f;
      color: #fff;
    }
    #sidebar .nav-link {
      color: #bbb;
    }
    #sidebar .nav-link.active, #sidebar .nav-link:hover {
      background: rgba(255,255,255,0.1);
      color: #fff;
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
    .menu-card.active {
      background: #7360ff;
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
    <nav id="sidebar" class="col-2 d-none d-md-block p-3">
      <h5 class="mb-4"><i class="bi bi-circle-fill me-2"></i>Tech System</h5>
      <ul class="nav nav-pills flex-column">
        <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-tree me-2"></i>Plants</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-plus-circle me-2"></i>Add New Machine</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-gear me-2"></i>Platform Settings</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-upload me-2"></i>Import Incidents</a></li>
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-shield-lock me-2"></i>Admin Panel</a></li>
      </ul>
      <div class="mt-5 text-muted small">Workspace</div>
      <ul class="nav nav-pills flex-column">
        <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-cpu me-2"></i>FFT</a></li>
      </ul>
    </nav>

    <!-- MAIN -->
    <main class="col-md-10 offset-md-2 px-4">
      <!-- Top bar -->
      <div class="d-flex align-items-center justify-content-between py-3">
        <div class="d-flex align-items-center">
          <button class="btn btn-outline-secondary d-lg-none me-2"><i class="bi bi-list"></i></button>
          <h4 class="me-4">ASS1 <i class="bi bi-star ms-1"></i></h4>
          <span class="badge bg-danger me-3">Offline</span>
          <span class="me-2">Responsible Personnel:</span>
          <img src="https://via.placeholder.com/32" class="rounded-circle" alt="">
        </div>
        <div class="d-flex align-items-center top-icons">
          <i class="bi bi-search"></i>
          <i class="bi bi-arrow-clockwise"></i>
          <i class="bi bi-bell"></i>
          <i class="bi bi-question-circle"></i>
          <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown">
              <img src="https://via.placeholder.com/32" class="rounded-circle me-2" alt="">
              <strong>Name Surname</strong>
            </a>
          </div>
        </div>
      </div>

      <!-- Recent Incidents -->
      <div class="main mb-4">
        <h5>Recent Incidents</h5>
        <table class="table table-borderless mb-0">
          <thead>
            <tr><th>Flag</th><th>Message</th><th>Origin</th><th>Cycle Date</th><th>Start Time</th><th>End Time</th><th>Length</th></tr>
          </thead>
          <tbody>
            <tr><td><i class="bi bi-flag"></i></td><td>Test</td><td>Video Page</td><td>2.9.2022</td><td>10:39:10</td><td>10:38:23</td><td>12.06s</td></tr>
            <tr><td><i class="bi bi-flag"></i></td><td>Gehange Problem</td><td>Video Page</td><td>2.9.2022</td><td>10:38:49</td><td>10:39:49</td><td>19.27s</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Menu -->
      <div class="d-flex gap-3 mb-4">
        <div class="menu-card active"><i class="bi bi-exclamation-triangle"></i><br>Incidents</div>
        <div class="menu-card"><i class="bi bi-arrow-repeat"></i><br>Cycles</div>
        <div class="menu-card"><i class="bi bi-wifi"></i><br>Sensor Data</div>
        <div class="menu-card"><i class="bi bi-file-earmark-text"></i><br>Exports</div>
        <div class="menu-card"><i class="bi bi-sliders"></i><br>Settings</div>
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
            <canvas id="donutChart" style="max-width:120px;"></canvas>
          </div>
        </div>
        <!-- Bar chart -->
        <div class="col-md-3">
          <div class="stat-card text-center">
            <h6>Incident Messages<br><small>Last 7 days</small></h6>
            <canvas id="barChart" style="max-width:120px;"></canvas>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Area chart
  new Chart(document.getElementById('areaChart'), {
    type: 'line',
    data: {
      labels: ['03.09','04.09','05.09','06.09','07.09','08.09','09.09'],
      datasets: [{
        data: [1,2,3,4,3,5,4],
        fill: true,
        backgroundColor: 'rgba(115,96,255,0.2)',
        borderColor: 'rgba(115,96,255,1)',
        tension: 0.4,
        pointRadius: 0
      }]
    },
    options: { 
      plugins: { legend: { display: false } },
      scales: { x: { display: false }, y: { display: false } }
    }
  });

  // Donut chart
  new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
      labels: ['',''],
      datasets: [{
        data: [80,20],
        backgroundColor: ['#7360ff','#eee']
      }]
    },
    options: {
      cutout: '75%',
      plugins: {
        tooltip: { enabled: false },
        legend: { display: false },
        doughnutlabel: { labels: [{ text: '80%', font: { size: '20' } }] }
      }
    }
  });

  // Bar chart
  new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
      labels: ['M','T','W','T','F','S','S'],
      datasets: [{
        data: [3,4,2,5,4,6,3],
        backgroundColor: '#7360ff',
        barPercentage: 0.6
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { x: { display: false }, y: { display: false } }
    }
  });
</script>
</body>
</html>
