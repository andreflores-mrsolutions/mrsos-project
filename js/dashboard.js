
//TODO: Estadisticas Mes
let areaChartRef, donutChartRef, barChartRef;

$(document).ready(function () {
  // cargarTickets();
  cargarEstadisticas();

  $("#filtroEstado, #filtroMarca, #filtroProceso, #filtroTipoEquipo").change(
    function () {
      // cargarTickets();
      cargarEstadisticas(); // si quieres que también afecte estadísticas según filtros, quita esto si no
    }
  );

  $("#resetFiltros").on("click", function () {
    $("#filtro-form")[0].reset();
    // cargarTickets();
    cargarEstadisticas();
  });

  $("#btnRecargar").on("click", function () {
    // cargarTickets();
    cargarEstadisticas();
  });
});

// Cargar por defecto: mes actual

let areaChart, donutTipo, donutEstatus;

function initCharts() {
  const areaCtx = document.getElementById('areaChart').getContext('2d');
  areaChart = new Chart(areaCtx, {
    type: 'line',
    data: {
      labels: [], datasets: [{
        data: [], fill: true,
        backgroundColor: 'rgba(115,96,255,0.2)',
        borderColor: 'rgba(115,96,255,1)',
        tension: 0.35, pointRadius: 0
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { x: { display: true }, y: { display: true } } }
  });

  const tipoCtx = document.getElementById('donutTipo').getContext('2d');
  donutTipo = new Chart(tipoCtx, {
    type: 'doughnut',
    data: { labels: [], datasets: [{ data: [], backgroundColor: ['#7360ff', '#a29bfe', '#b2bec3', '#dfe6e9'] }] },
    options: { cutout: '65%', plugins: { legend: { display: false } } }
  });

  const estCtx = document.getElementById('donutEstatus').getContext('2d');
  donutEstatus = new Chart(estCtx, {
    type: 'doughnut',
    data: { labels: [], datasets: [{ data: [], backgroundColor: ['#28a745', '#6c757d', '#0d6efd', '#adb5bd'] }] },
    options: { cutout: '65%', plugins: { legend: { display: false } } }
  });
}

function updateArea(labels, data) {
  areaChart.data.labels = labels || [];
  areaChart.data.datasets[0].data = data || [];
  areaChart.update();
}

function updateDonutTipo(map) {
  const labels = ['Servicio', 'Preventivo', 'Extra', 'Otros'];
  donutTipo.data.labels = labels;
  donutTipo.data.datasets[0].data = labels.map(l => (map && map[l]) ? map[l] : 0);
  donutTipo.update();
}

function updateDonutEstatus(map) {
  const labels = ['Abierto', 'Cancelado', 'Finalizado', 'Otro'];
  donutEstatus.data.labels = labels;
  donutEstatus.data.datasets[0].data = labels.map(l => (map && map[l]) ? map[l] : 0);
  donutEstatus.update();
}

function poblarSelectSedes(lista, csIdActual = null) {
  const sel = document.getElementById('selSede');
  if (!sel) return;
  sel.innerHTML = '<option value="">Todas las sedes</option>';
  (lista || []).forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.csId;
    opt.textContent = s.csNombre;
    sel.appendChild(opt);
  });
  if (csIdActual) sel.value = String(csIdActual);
}

function cargarEstadisticas({ ym = null, lastDays = 30, csId = null, clId = null } = {}) {
  const qs = new URLSearchParams();
  if (ym) qs.set('ym', ym);
  if (lastDays) qs.set('lastDays', lastDays);
  if (csId) qs.set('csId', csId);
  if (clId) qs.set('clId', clId); // para MRA

  fetch(`../php/estadisticas_mes.php${qs.toString() ? '?' + qs.toString() : ''}`)
    .then(r => r.json())
    .then(res => {
      if (!res?.success) throw new Error(res?.error || 'Error');

      // charts
      updateArea(res.labels, res.data);
      updateDonutTipo(res.porTipo);
      updateDonutEstatus(res.porEstatus);

      // sedes accesibles (para el select)
      poblarSelectSedes(res.sedes, res.csId || null);
    })
    .catch(err => {
      console.error(err);
      updateArea([], []);
      updateDonutTipo({ Servicio: 0, Preventivo: 0, Extra: 0, Otros: 0 });
      updateDonutEstatus({ Abierto: 0, Cancelado: 0, Finalizado: 0, Otro: 0 });
      poblarSelectSedes([]);
    });
}

document.addEventListener('DOMContentLoaded', () => {
  initCharts();
  // Carga por defecto últimos 30 días
  cargarEstadisticas({ lastDays: 30 });

  document.getElementById('btnUlt30')?.addEventListener('click', () => {
    const csId = document.getElementById('selSede')?.value || '';
    cargarEstadisticas({ lastDays: 30, csId: csId || null });
  });

  document.getElementById('btnMesAplicar')?.addEventListener('click', () => {
    const ym = document.getElementById('mesFiltro')?.value || null;
    const csId = document.getElementById('selSede')?.value || '';
    cargarEstadisticas({ ym, lastDays: null, csId: csId || null });
  });

  document.getElementById('selSede')?.addEventListener('change', (e) => {
    // Mantén el mismo rango actual (si prefieres 30 días al cambiar sede, cambia esta lógica)
    const ym = document.getElementById('mesFiltro')?.value || null;
    const csId = e.target.value || null;
    cargarEstadisticas({ ym, lastDays: ym ? null : 30, csId });
  });
});
