const ctx = window.MRS_CTX || {};
const csrf = window.MRS_CSRF?.csrf || '';

const state = {
  sedes: [],
  equipos: [],
  selectedMap: new Map()
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

function selectedItems() {
  return Array.from(state.selectedMap.values());
}

function renderSelected() {
  const list = document.getElementById('selList');
  const count = document.getElementById('selCountText');
  const items = selectedItems();

  count.textContent = `${items.length} equipo(s)`;

  if (!items.length) {
    list.innerHTML = `<div class="small text-muted">Aún no seleccionas equipos.</div>`;
    document.getElementById('items_json').value = '[]';
    document.querySelectorAll('.eq-card').forEach(c => c.classList.remove('selected'));
    return;
  }

  list.innerHTML = items.map(it => `
    <div class="border rounded-3 p-2 mb-2 bg-white">
      <div class="fw-semibold">${esc(it.modelo)}</div>
      <div class="small text-muted">${esc(it.marca)} · SN: ${esc(it.sn)}</div>
    </div>
  `).join('');

  document.getElementById('items_json').value = JSON.stringify(items.map(it => ({
    peId: it.peId,
    eqId: it.eqId
  })));

  document.querySelectorAll('.eq-card').forEach(c => {
    c.classList.toggle('selected', state.selectedMap.has(Number(c.dataset.peid)));
  });
}

function renderEquipos() {
  const q = (document.getElementById('txtBuscarEquipo').value || '').trim().toLowerCase();
  const grid = document.getElementById('equiposGrid');

  const rows = state.equipos.filter(e => {
    if (q) {
      const hay = [e.modelo, e.tipoEquipo, e.sn, e.marca, e.polizaTipo]
        .some(v => String(v || '').toLowerCase().includes(q));
      if (!hay) return false;
    }
    return true;
  });

  grid.innerHTML = rows.map(e => `
    <div class="col-12 col-md-6 col-xl-4 mb-3">
      <div class="eq-card h-100 p-3 ${state.selectedMap.has(Number(e.peId)) ? 'selected' : ''}"
           data-peid="${e.peId}">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="fw-bold">${esc(e.modelo)}</div>
          <span class="badge text-bg-light border">${esc(e.polizaTipo || '—')}</span>
        </div>
        <div class="text-center mb-2">
          <img src="${esc(e.img)}" class="img-fluid" style="max-height:90px;object-fit:contain"
               onerror="this.src='../img/Equipos/default.png'">
        </div>
        <div class="small text-muted">${esc(e.marca)} · ${esc(e.tipoEquipo)}</div>
        <div class="small">SN: ${esc(e.sn)}</div>
      </div>
    </div>
  `).join('');

  grid.querySelectorAll('.eq-card').forEach(card => {
    card.addEventListener('click', () => {
      const peId = Number(card.dataset.peid);
      const eq = state.equipos.find(x => Number(x.peId) === peId);
      if (!eq) return;

      if (state.selectedMap.has(peId)) state.selectedMap.delete(peId);
      else state.selectedMap.set(peId, eq);

      renderSelected();
      renderEquipos();
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
    const eq = state.equipos.find(x => Number(x.peId) === prefPeId);
    if (eq) state.selectedMap.set(eq.peId, eq);
  } else if (prefEqId) {
    const eq = state.equipos.find(x => Number(x.eqId) === prefEqId);
    if (eq) state.selectedMap.set(eq.peId, eq);
  }

  renderEquipos();
  renderSelected();
}

function bindUI() {
  document.getElementById('txtBuscarEquipo').addEventListener('input', renderEquipos);

  document.getElementById('csId').addEventListener('change', async () => {
    state.selectedMap.clear();
    renderSelected();
    await loadEquipos();
  });

  document.getElementById('btnClearSel').addEventListener('click', () => {
    state.selectedMap.clear();
    renderSelected();
    renderEquipos();
  });

  document.getElementById('btnSelectAll').addEventListener('click', () => {
    const q = (document.getElementById('txtBuscarEquipo').value || '').trim().toLowerCase();
    const visibles = state.equipos.filter(e => {
      if (!q) return true;
      return [e.modelo, e.tipoEquipo, e.sn, e.marca, e.polizaTipo]
        .some(v => String(v || '').toLowerCase().includes(q));
    });
    visibles.forEach(e => state.selectedMap.set(e.peId, e));
    renderSelected();
    renderEquipos();
  });

  document.getElementById('btnReload').addEventListener('click', async () => {
    await loadSedes();
    await loadEquipos();
    toastOk('Catálogos recargados.');
  });

  document.getElementById('frmHealth').addEventListener('submit', async (e) => {
    e.preventDefault();

    const items = selectedItems().map(it => ({ peId: it.peId, eqId: it.eqId }));
    if (!items.length) {
      toastErr('Selecciona al menos un equipo.');
      return;
    }

    try {
      const body = {
        csId: Number(document.getElementById('csId').value || 0),
        hcFechaHora: document.getElementById('hcFechaHora').value,
        hcDuracionMins: Number(document.getElementById('hcDuracionMins').value || 240),
        hcNombreContacto: document.getElementById('hcNombreContacto').value,
        hcNumeroContacto: document.getElementById('hcNumeroContacto').value,
        hcCorreoContacto: document.getElementById('hcCorreoContacto').value,
        items
      };

      const json = await apiFetch('api/health_create.php', { method: 'POST', body });
      toastOk(`Health Check #${json.hcId} programado correctamente.`);
    } catch (err) {
      toastErr(err.message || 'No se pudo programar el Health Check.');
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