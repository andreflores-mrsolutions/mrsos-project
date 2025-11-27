window.openAnalisis = openAnalisis;

// ========= Proceso: ANALISIS =========
let _tiIdAnalisis = null;

// Muestra el modal y precarga (si hubiera) diagnóstico previo
function openAnalisis(tiId, diagnosticoActual = '') {
  _tiIdAnalisis = Number(tiId);

  const $txt = document.getElementById('tiAnalisisDesc');
  if ($txt) {
    $txt.value = (diagnosticoActual || '').trim();
    setTimeout(() => $txt.focus(), 150);
  }

  const el = document.getElementById('modalAnalisis');
  const modal = bootstrap.Modal.getOrCreateInstance(el);
  modal.show();
}

function guardarAnalisis() {
  if (!_tiIdAnalisis) return;

  const $btn = document.getElementById('btnGuardarAnalisis');
  const nextProceso = $btn?.dataset?.nextProceso || 'logs';

  const $txt = document.getElementById('tiAnalisisDesc');
  let desc = ($txt?.value || '').trim();
  if (!desc) desc = 'Faltan datos';

  // Evitar doble submit
  if ($btn) {
    $btn.disabled = true;
    $btn.innerText = 'Guardando...';
  }

  const payload = new URLSearchParams();
  payload.set('tiId', _tiIdAnalisis);
  payload.set('tiDiagnostico', desc);
  payload.set('nextProceso', nextProceso);

  fetch('php/guardar_analisis.php', {
    method: 'POST',
    headers: { 'Content-Type':'application/x-www-form-urlencoded' },
    body: payload.toString()
  })
  .then(r => r.json())
  .then(json => {
    if (!json?.success) throw new Error(json?.error || 'No se pudo guardar el análisis');

    // Cierra modal
    const modalEl = document.getElementById('modalAnalisis');
    const modal = bootstrap.Modal.getInstance(modalEl);
    modal?.hide();

    // Limpia y refresca
    if ($txt) $txt.value = '';
    cargarTodosTickets();
  })
  .catch(err => {
    alert('Error: ' + err.message);
  })
  .finally(() => {
    if ($btn) {
      $btn.disabled = false;
      $btn.innerText = 'Continuar';
    }
  });
}

// Wire del botón
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btnGuardarAnalisis')?.addEventListener('click', guardarAnalisis);
});

// ====== Render de acciones para proceso 'analisis' ======
function renderAccionesPorProceso(t) {
  const proceso = (t.tiProceso || '').toLowerCase();
  const btnVerMas = `
    <button class="btn btn-primary btn-sm"
      onclick="abrirDetalle(${Number(t.tiId)})"
      data-bs-toggle="offcanvas" data-bs-target="#offcanvasTicket">Ver más</button>`;

  if (proceso === 'asignacion') {
    return `
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-success btn-sm" onclick="openAsignacion(${Number(t.tiId)})">Siguiente</button>
        ${btnVerMas}
      </div>
    `;
  }

  if (proceso === 'revision inicial') {
    return `
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-warning btn-sm" disabled>Anterior</button>
      <button class="btn btn-success btn-sm btn-open-analisis"
              data-ti-id="${Number(t.tiId)}"
              data-diag="${encodeURIComponent(t.tiDiagnostico || '')}">
        Siguiente
      </button>
      ${btnVerMas}
    </div>
  `;
}

  // Default mientras definimos los demás procesos
  return `
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-warning btn-sm" disabled>Anterior</button>
      <button class="btn btn-success btn-sm" disabled>Siguiente</button>
      ${btnVerMas}
    </div>
  `;
}
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-open-analisis');
  if (!btn) return;

  const tiId = Number(btn.dataset.tiId || 0);
  const diag = decodeURIComponent(btn.dataset.diag || '');
  openAnalisis(tiId, diag);
});