const ctx = window.MRS_CTX || {};
const csrf = window.MRS_CSRF?.csrf || '';

const state = {
  sedes: [],
  equipos: [],
  selected: null
};


    
  

  

function toast(id, msg) {
  const el = document.getElementById(id);
  if (!el) return;
  el.querySelector('.toast-body').textContent = msg;
  bootstrap.Toast.getOrCreateInstance(el).show();
}
function toastOk(msg){ toast('toastSuccess', msg); }
function toastErr(msg){ toast('toastError', msg); }

function esc(str) {
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

async function apiFetch(url, { method = 'GET', body = null } = {}) {
  const headers = new Headers({ 'X-CSRF-Token': csrf });
  let realBody = null;

  if (method !== 'GET' && body) {
    headers.set('Content-Type', 'application/json');
    realBody = JSON.stringify({ ...body, csrf_token: csrf });
  }

  const res = await fetch(url, {
    method,
    credentials: 'include',
    headers,
    body: realBody,
    cache: 'no-store'
  });

  const json = await res.json().catch(() => ({}));
  if (!res.ok || json.success === false) {
    throw new Error(json.error || json.message || 'Error de API');
  }
  return json;
}

function renderSelected() {
  const box = document.getElementById('selEquipoText');
  const eqId = document.getElementById('eqId');
  const peId = document.getElementById('peId');

  if (!state.selected) {
    box.textContent = 'Aún no seleccionas un equipo.';
    eqId.value = '';
    peId.value = '';
    document.querySelectorAll('.eq-card').forEach(c => c.classList.remove('selected'));
    return;
  }

  eqId.value = state.selected.eqId;
  peId.value = state.selected.peId;

  box.innerHTML = `
    <div class="fw-semibold">${esc(state.selected.modelo)}</div>
    <div class="small text-muted">${esc(state.selected.marca)} · SN: ${esc(state.selected.sn)}</div>
    <div class="small text-muted">Póliza: ${esc(state.selected.polizaTipo || '—')}</div>
  `;

  document.querySelectorAll('.eq-card').forEach(c => {
    c.classList.toggle('selected', Number(c.dataset.peid) === Number(state.selected.peId));
  });
}

function renderEquipos() {
  const q = (document.getElementById('txtBuscarEquipo').value || '').trim().toLowerCase();
  const flt = document.getElementById('fltTicketActivo').value;
  const grid = document.getElementById('equiposGrid');

  const rows = state.equipos.filter(e => {
    if (flt === 'with' && Number(e.ticketsActivos || 0) <= 0) return false;
    if (flt === 'without' && Number(e.ticketsActivos || 0) > 0) return false;

    if (q) {
      const hay = [e.modelo, e.tipoEquipo, e.sn, e.marca, e.polizaTipo]
        .some(v => String(v || '').toLowerCase().includes(q));
      if (!hay) return false;
    }
    return true;
  });

  grid.innerHTML = rows.map(e => `
    <div class="col-12 col-md-6 col-xl-4 mb-3">
      <div class="eq-card h-100 p-3 ${state.selected && Number(state.selected.peId) === Number(e.peId) ? 'selected' : ''}"
           data-peid="${e.peId}">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="fw-bold">${esc(e.modelo)}</div>
          <span class="badge text-bg-light border eq-badge">${esc(e.polizaTipo || '—')}</span>
        </div>
        <div class="text-center mb-2">
          <img src="${esc(e.img)}" class="img-fluid" style="max-height:90px;object-fit:contain"
               onerror="this.src='../img/Equipos/default.png'">
        </div>
        <div class="small text-muted">${esc(e.marca)} · ${esc(e.tipoEquipo)}</div>
        <div class="small">SN: ${esc(e.sn)}</div>
        <div class="mt-2">
          <span class="badge ${Number(e.ticketsActivos||0)>0?'text-bg-warning':'text-bg-success'}">
            ${Number(e.ticketsActivos||0)} ticket(s) activo(s)
          </span>
        </div>
        ${Array.isArray(e.ticketsList) && e.ticketsList.length ? `
          <div class="small text-muted mt-2">Recientes: ${e.ticketsList.map(esc).join(', ')}</div>
        ` : ''}
      </div>
    </div>
  `).join('');

  grid.querySelectorAll('.eq-card').forEach(card => {
    card.addEventListener('click', () => {
      const peId = Number(card.dataset.peid);
      state.selected = state.equipos.find(x => Number(x.peId) === peId) || null;
      renderSelected();
    });
  });
}

async function loadSedes() {
  const json = await apiFetch(`api/ticket_catalog_sedes.php?clId=${encodeURIComponent(ctx.clId)}`);
  state.sedes = Array.isArray(json.sedes) ? json.sedes : [];

  const sel = document.getElementById('csId');
  sel.innerHTML = `<option value="">Selecciona...</option>` + state.sedes.map(s =>
    `<option value="${s.csId}">${esc(s.csNombre)}</option>`
  ).join('');

  let targetCsId = Number(ctx.pref?.csId || 0);
  if (!targetCsId && state.sedes.length === 1) targetCsId = Number(state.sedes[0].csId);
  if (!targetCsId && Number(ctx.csIdSession || 0) > 0) targetCsId = Number(ctx.csIdSession);

  if (targetCsId) sel.value = String(targetCsId);
}

async function loadEquipos() {
  const csId = Number(document.getElementById('csId').value || 0);
  if (!csId) {
    state.equipos = [];
    document.getElementById('equiposGrid').innerHTML = '';
    return;
  }

  document.getElementById('equiposSkeleton').style.display = '';
  document.getElementById('equiposGrid').style.display = 'none';

  const json = await apiFetch(`api/ticket_catalog_equipos.php?clId=${encodeURIComponent(ctx.clId)}&csId=${encodeURIComponent(csId)}`);
  state.equipos = Array.isArray(json.equipos) ? json.equipos : [];

  document.getElementById('equiposSkeleton').style.display = 'none';
  document.getElementById('equiposGrid').style.display = '';

  const prefPeId = Number(ctx.pref?.peId || 0);
  const prefEqId = Number(ctx.pref?.eqId || 0);

  if (prefPeId) {
    state.selected = state.equipos.find(x => Number(x.peId) === prefPeId) || null;
  } else if (prefEqId) {
    state.selected = state.equipos.find(x => Number(x.eqId) === prefEqId) || null;
  } else if (state.selected) {
    state.selected = state.equipos.find(x => Number(x.peId) === Number(state.selected.peId)) || null;
  }

  renderEquipos();
  renderSelected();
}

function bindUI() {
  document.getElementById('txtBuscarEquipo').addEventListener('input', renderEquipos);
  document.getElementById('fltTicketActivo').addEventListener('change', renderEquipos);

  document.getElementById('csId').addEventListener('change', async () => {
    state.selected = null;
    renderSelected();
    await loadEquipos();
  });

  document.getElementById('btnClearEquipo').addEventListener('click', () => {
    state.selected = null;
    renderSelected();
  });

  document.getElementById('btnReload').addEventListener('click', async () => {
    await loadSedes();
    await loadEquipos();
    toastOk('Catálogos recargados.');
  });

  document.getElementById('frmTicket').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!state.selected) {
      toastErr('Selecciona un equipo.');
      return;
    }

    try {
      const body = {
        csId: Number(document.getElementById('csId').value || 0),
        eqId: Number(document.getElementById('eqId').value || 0),
        peId: Number(document.getElementById('peId').value || 0),
        tiTipoTicket: document.getElementById('tiTipoTicket').value,
        tiNivelCriticidad: document.querySelector('input[name="tiNivelCriticidad"]:checked')?.value || '2',
        tiNombreContacto: document.getElementById('tiNombreContacto').value,
        tiNumeroContacto: document.getElementById('tiNumeroContacto').value,
        tiCorreoContacto: document.getElementById('tiCorreoContacto').value,
        tiDescripcion: document.getElementById('tiDescripcion').value
      };

      const json = await apiFetch('api/ticket_create.php', { method: 'POST', body });
      toastOk(`Ticket #${json.tiId} creado correctamente.`);
      document.getElementById('tiDescripcion').value = '';
    } catch (err) {
      toastErr(err.message || 'No se pudo crear el ticket.');
    }
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  try {
    bindUI();
    await loadSedes();
    await loadEquipos();
  } catch (e) {
    toastErr(e.message || 'No se pudo cargar la pantalla.');
  }
});

$(init);