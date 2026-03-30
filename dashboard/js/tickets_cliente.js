/* ===========================
   MRSOS Cliente - tickets_cliente.js (COMPLETO)
   - Listado tabla/cards
   - Agrupación + paginación por sede
   - Offcanvas detalle (offTicket)
   - Offcanvas LOGS (offLogs) + subir logs (cambia proceso a "revision especial" vía API)
   - Offcanvas MEET (offMeet) + proponer 3 horarios + aceptar
   =========================== */

const state = {
  vista: 'tabla',
  scope: 'todo',
  estado: 'all',
  search: '',
  tickets: [],
  selected: null,

  // paginación por sede
  perPage: 20,
  pagesBySede: {},
};

const STEPS = [
  'asignacion',
  'revision inicial',
  'logs',
  'meet',
  'revision especial',
  'espera refaccion',
  'visita',
  'fecha asignada',
  'espera ventana',
  'espera visita',
  'en camino',
  'espera documentacion',
  'encuesta satisfaccion',
  'finalizado',
  'cancelado',
  'fuera de alcance',
  'servicio por evento'
];

function getCsrf() {
  return window.MRS_CSRF?.csrf || '';
}

async function apiFetch(url, { method = 'GET', body = null, headers = {} } = {}) {
  const csrf = getCsrf();
  const h = new Headers(headers);
  h.set('X-CSRF-Token', csrf);

  let realBody = body;
  if (method !== 'GET' && body && !(body instanceof FormData)) {
    h.set('Content-Type', 'application/json');
    realBody = JSON.stringify({ ...body, csrf_token: csrf });
  }

  const res = await fetch(url, {
    method,
    credentials: 'include',
    headers: h,
    body: method === 'GET' ? null : realBody,
    cache: 'no-store'
  });

  const json = await res.json().catch(() => ({}));
  if (!res.ok || json.success === false) {
    throw new Error(json.error || json.message || 'Error de API');
  }
  return json;
}

async function apiFetchForm(url, formData) {
  const csrf = getCsrf();
  formData.append('csrf_token', csrf);

  const res = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers: { 'X-CSRF-Token': csrf },
    body: formData,
    cache: 'no-store'
  });

  const json = await res.json().catch(() => ({}));
  if (!res.ok || json.success === false) {
    throw new Error(json.error || json.message || 'Error de API');
  }
  return json;
}

function toastOk(msg) {
  const el = document.getElementById('toastOk');
  if (!el) return;
  el.querySelector('.toast-body').textContent = msg;
  bootstrap.Toast.getOrCreateInstance(el).show();
}

function toastErr(msg) {
  const el = document.getElementById('toastErr');
  if (!el) return;
  el.querySelector('.toast-body').textContent = msg;
  bootstrap.Toast.getOrCreateInstance(el).show();
}

function normalizeStep(raw) {
  const s = String(raw || '').trim().toLowerCase();
  if (!s) return 'asignacion';
  if (s.includes('asignac')) return 'asignacion';
  if (s.includes('rev') && s.includes('inicial')) return 'revision inicial';
  if (s.includes('log')) return 'logs';
  if (s.includes('meet')) return 'meet';
  if (s.includes('rev') && s.includes('especial')) return 'revision especial';
  if (s.includes('refac')) return 'espera refaccion';
  if (s.includes('fecha') && s.includes('asign')) return 'fecha asignada';
  if (s.includes('ventana')) return 'espera ventana';
  if (s.includes('visita') && s.includes('espera')) return 'espera visita';
  if (s.includes('visita')) return 'visita';
  if (s.includes('camino')) return 'en camino';
  if (s.includes('doc')) return 'espera documentacion';
  if (s.includes('encuesta')) return 'encuesta satisfaccion';
  if (s.includes('final')) return 'finalizado';
  if (s.includes('cancel')) return 'cancelado';
  if (s.includes('fuera')) return 'fuera de alcance';
  if (s.includes('evento')) return 'servicio por evento';
  return s;
}

function stepProgress(step) {
  const idx = Math.max(0, STEPS.indexOf(step));
  const pct = Math.round((idx / Math.max(1, STEPS.length - 1)) * 100);
  return { idx, pct, total: STEPS.length };
}

function esc(str) {
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function bytesFmt(n) {
  const v = Number(n || 0);
  if (v < 1024) return `${v} B`;
  if (v < 1024 * 1024) return `${(v / 1024).toFixed(1)} KB`;
  if (v < 1024 * 1024 * 1024) return `${(v / (1024 * 1024)).toFixed(1)} MB`;
  return `${(v / (1024 * 1024 * 1024)).toFixed(1)} GB`;
}

function criticidadBadge(v) {
  const n = Number(v || 0);
  if (n === 3) return `<span class="badge rounded-pill" style="background:#7a8591;color:#fff;">Nivel 3</span>`;
  if (n === 2) return `<span class="badge rounded-pill" style="background:#f2c318;color:#111;">Nivel 2</span>`;
  if (n <= 1) return `<span class="badge rounded-pill" style="background:#dc3545;color:#fff;">Nivel 1</span>`;
  return `<span class="badge rounded-pill text-bg-secondary">—</span>`;
}

function estadoBadge(v) {
  const s = String(v || '—');
  if (s === 'Abierto') return `<span class="badge rounded-pill" style="background:#cfe7d8;color:#123c2e;">Abierto</span>`;
  if (s === 'Pospuesto') return `<span class="badge rounded-pill" style="background:#fff3cd;color:#7a4b00;">Pospuesto</span>`;
  if (s === 'Cerrado') return `<span class="badge rounded-pill" style="background:#d1e7dd;color:#0f5132;">Cerrado</span>`;
  return `<span class="badge rounded-pill text-bg-secondary">${esc(s)}</span>`;
}

function procesoBadge(v) {
  return `
    <span class="badge rounded-pill"
      style="background:#e9e7fb;color:#3e37b6;border:1px solid rgba(62,55,182,.10);">
      ${esc(v || '—')}
    </span>
  `;
}

/* ===========================
   Acción cliente por proceso
   =========================== */

function humanAction(ticket) {
  const step = normalizeStep(ticket.tiProceso);

  if (step === 'logs') {
    return {
      title: 'Hay que validar o enviar logs',
      desc: 'MR Solutions solicitó archivos de diagnóstico para continuar la revisión.',
      cta: 'Gestionar logs',
      key: 'logs',
      btnClass: 'btn-outline-primary',
      icon: 'bi-file-earmark-arrow-up'
    };
  }

  if (step === 'meet') {
    if (String(ticket.tiMeetEstado || '').toLowerCase() === 'confirmado') {
      return {
        title: 'Meet confirmado',
        desc: 'La sesión remota ya fue confirmada.',
        cta: 'Ver Meet',
        key: 'meet',
        btnClass: 'btn-outline-primary',
        icon: 'bi-camera-video'
      };
    }
    return {
      title: 'Hay que coordinar un Meet',
      desc: 'Puedes aceptar una propuesta o enviar 3 horarios disponibles.',
      cta: 'Gestionar Meet',
      key: 'meet',
      btnClass: 'btn-outline-primary',
      icon: 'bi-camera-video'
    };
  }

  if (step === 'visita') {
    if (Number(ticket.tiVisitaConfirmada || 0) === 1 && !ticket.tiFolioEntrada) {
      return {
        title: 'Hay que completar acceso para la visita',
        desc: 'La ventana ya fue confirmada, pero aún falta folio o autorización.',
        cta: 'Gestionar visita',
        key: 'visita',
        btnClass: 'btn-outline-primary',
        icon: 'bi-car-front'
      };
    }
    return {
      title: 'Hay que coordinar la visita',
      desc: 'Debes revisar o proponer ventanas y completar acceso si aplica.',
      cta: 'Gestionar visita',
      key: 'visita',
      btnClass: 'btn-outline-primary',
      icon: 'bi-car-front'
    };
  }

  if (step === 'encuesta satisfaccion') {
    return {
      title: 'Encuesta disponible',
      desc: 'Tu opinión nos ayuda a cerrar la experiencia del servicio.',
      cta: 'Responder encuesta',
      key: 'encuesta',
      btnClass: 'btn-outline-primary',
      icon: 'bi-stars'
    };
  }


  return {
    title: 'MR Solutions está trabajando en tu caso',
    desc: 'Por ahora no necesitas hacer ninguna acción adicional.',
    cta: 'Ver detalle',
    key: 'none',
    btnClass: 'btn-outline-secondary',
    icon: 'bi-eye'
  };
}

function actionButtonHtml(ticket) {
  const action = humanAction(ticket);

  // if (action.key === 'none') {
  //   return `
  //     <button type="button" class="btn btn-sm ${action.btnClass}" data-open-ticket="${ticket.tiId}">
  //       <i class="bi ${action.icon}"></i> ${esc(action.cta)}
  //     </button>
  //   `;
  // }

  if (action.key === 'logs') {
    return `
      <button type="button" class="btn btn-sm ${action.btnClass}" data-open-logs="${ticket.tiId}">
        <i class="bi ${action.icon}"></i> ${esc(action.cta)}
      </button>
    `;
  }

  if (action.key === 'meet') {
    return `
      <button type="button" class="btn btn-sm ${action.btnClass}" data-open-meet="${ticket.tiId}">
        <i class="bi ${action.icon}"></i> ${esc(action.cta)}
      </button>
    `;
  }

  if (action.key === 'visita') {
    return `
      <button type="button" class="btn btn-sm ${action.btnClass}" data-open-visita="${ticket.tiId}">
        <i class="bi ${action.icon}"></i> ${esc(action.cta)}
      </button>
    `;
  }
  if (action.key === 'encuesta') {
    return `
    <button type="button" class="btn btn-sm ${action.btnClass}" data-open-encuesta="${ticket.tiId}">
      <i class="bi ${action.icon}"></i> ${esc(action.cta)}
    </button>
  `;
  }

  return ``;
}

function guidanceMessage(ticket) {
  const step = normalizeStep(ticket.tiProceso);

  switch (step) {
    case 'logs':
      return 'Estamos esperando que nos compartas los archivos de diagnóstico para continuar con una revisión más precisa.';
    case 'meet':
      return ticket.tiMeetEstado === 'confirmado'
        ? 'El Meet ya fue confirmado. Solo falta seguir la fecha y hora acordada.'
        : 'Estamos coordinando una sesión remota para revisar tu caso con mayor detalle.';
    case 'visita':
      return ticket.tiVisitaConfirmada
        ? 'La visita ya tiene una ventana confirmada. Ahora es importante validar acceso y folio si aplica.'
        : 'Estamos coordinando una visita técnica. Necesitamos alinear una ventana viable contigo.';
    case 'encuesta satisfaccion':
      return 'Tu ticket está en la etapa final de experiencia. La encuesta no complica el flujo y nos ayuda a mejorar.';
    case 'revision especial':
      return 'Tu caso está siendo revisado internamente por el equipo de ingeniería.';
    case 'espera documentacion':
      return 'Estamos integrando documentación o cierre técnico antes de concluir el caso.';
    case 'en camino':
      return 'El ingeniero ya se encuentra en trayecto o en fase final previa a la atención en sitio.';
    default:
      return 'MR Solutions está avanzando tu caso. Cuando necesitemos algo de tu lado, lo verás claramente aquí.';
  }
}

function formatDateTime(s) {
  if (!s) return '—';
  const d = new Date(String(s).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return s;
  return d.toLocaleString('es-MX', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  });
}

/* ===========================
   Filtros, agrupación y paginación por sede
   =========================== */

function filteredTickets() {
  const q = state.search.trim().toLowerCase();

  return state.tickets.filter(t => {
    if (state.scope === 'accion' && !t.requiereAccionCliente) return false;
    if (state.scope === 'abiertos' && t.tiEstatus !== 'Abierto') return false;
    if (state.estado !== 'all' && t.tiEstatus !== state.estado) return false;

    if (q) {
      const hay = [
        t.folio,
        t.eqModelo,
        t.eqVersion,
        t.maNombre,
        t.peSN,
        t.tiProceso,
        t.csNombre
      ].some(v => String(v || '').toLowerCase().includes(q));
      if (!hay) return false;
    }

    return true;
  });
}

function getSedeName(ticket) {
  return String(ticket.csNombre || 'Principal').trim() || 'Principal';
}

function groupTicketsBySede(rows) {
  const groups = new Map();
  rows.forEach(t => {
    const sede = getSedeName(t);
    if (!groups.has(sede)) groups.set(sede, []);
    groups.get(sede).push(t);
  });
  return Array.from(groups.entries()).map(([sede, tickets]) => ({ sede, tickets }));
}

function getSedePage(sede) {
  return Number(state.pagesBySede[sede] || 1);
}

function setSedePage(sede, page) {
  state.pagesBySede[sede] = Math.max(1, Number(page || 1));
}

function resetAllSedePages() {
  state.pagesBySede = {};
}

function paginateSedeTickets(sede, rows) {
  const total = rows.length;
  const perPage = Math.max(1, Number(state.perPage || 20));
  const totalPages = Math.max(1, Math.ceil(total / perPage));

  let page = getSedePage(sede);
  if (page > totalPages) page = totalPages;
  if (page < 1) page = 1;
  setSedePage(sede, page);

  const startIndex = (page - 1) * perPage;
  const endIndex = startIndex + perPage;

  return {
    rows: rows.slice(startIndex, endIndex),
    total,
    totalPages,
    page,
    start: total ? startIndex + 1 : 0,
    end: Math.min(endIndex, total),
    perPage
  };
}

/* ===========================
   Render UI
   =========================== */

function renderStats(meta = {}) {
  const statTotal = document.getElementById('statTotal');
  const statAbiertos = document.getElementById('statAbiertos');
  const statAccion = document.getElementById('statAccion');
  const statCurso = document.getElementById('statCurso');

  if (statTotal) statTotal.textContent = meta.total ?? 0;
  if (statAbiertos) statAbiertos.textContent = meta.abiertos ?? 0;
  if (statAccion) statAccion.textContent = meta.accion ?? 0;
  if (statCurso) statCurso.textContent = meta.curso ?? 0;
}

function renderSummary(totalRows, totalSedes) {
  const el = document.getElementById('ticketsSummary');
  if (!el) return;

  const total = Number(totalRows || 0);
  const sedes = Number(totalSedes || 0);
  if (!total) {
    el.textContent = 'Sin tickets para mostrar';
    return;
  }
  el.textContent = `${total} ticket(s) en ${sedes} sede(s)`;
}

function renderSedeHeader(sede, meta) {
  return `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 mt-4">
      <div><div class="fw-bold fs-5">${esc(sede)}</div></div>
      <div class="small muted">${meta.start}-${meta.end} de ${meta.total} ticket(s)</div>
    </div>
  `;
}

function renderTableRows(rows) {
  return rows.map(t => {
    const action = humanAction(t);
    return `
      <tr class="ticket-row" data-id="${t.tiId}">
        <td style="min-width:140px;">
          <div class="fw-bold fs-5">${esc(t.folio)}</div>
          <div class="small muted">${esc(t.csNombre || 'Principal')}</div>
        </td>

        <td style="min-width:140px;">
          <img src="${esc(t.eqImgPath || '../img/Equipos/default.png')}"
            alt="${esc(t.eqModelo || 'default.png')}"
            class="img-fluid"
            style="max-height:70px; object-fit:contain;">
        </td>

        <td style="min-width:240px;">
          <div class="fw-bold fs-5">${esc(t.eqModelo || 'Equipo')}</div>
          <div class="small muted">
            ${esc(t.maNombre || 'Marca')}
            ${t.eqVersion ? ' · ' + esc(t.eqVersion) : ''}
          </div>
        </td>

        <td style="min-width:220px;">
          <div>${esc(t.peSN || '—')}</div>
        </td>

        <td style="min-width:160px;">${procesoBadge(t.tiProceso)}</td>
        <td style="min-width:110px;">${estadoBadge(t.tiEstatus)}</td>
        <td style="min-width:140px;">${criticidadBadge(t.tiNivelCriticidad)}</td>

        <td style="min-width:240px;">
          <div class="fw-semibold">${esc(action.title)}</div>
          <div class="small muted mb-2">${esc(action.cta)}</div>
          ${actionButtonHtml(t)}
        </td>

        <td style="min-width:160px;">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-open-ticket="${t.tiId}">
            <i class="bi bi-eye"></i> Ver detalle
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

function renderTableGroup(sede, rows, meta) {
  return `
    <section class="mb-4" data-sede="${esc(sede)}">
      ${renderSedeHeader(sede, meta)}
      <div class="table-responsive mb-2">
        <table class="table align-middle table-hover mrs-ticket-table">
          <thead>
            <tr>
              <th>Folio</th>
              <th>Imagen</th>
              <th>Equipo</th>
              <th>SN</th>
              <th>Proceso</th>
              <th>Estado</th>
              <th>Criticidad</th>
              <th>Acción</th>
              <th>Detalle</th>
            </tr>
          </thead>
          <tbody>${renderTableRows(rows)}</tbody>
        </table>
      </div>
      ${renderSedePagination(sede, meta)}
    </section>
  `;
}

function renderCardsGroup(sede, rows, meta) {
  return `
    <section class="mb-4" data-sede="${esc(sede)}">
      ${renderSedeHeader(sede, meta)}
      <div class="row g-3 mb-2">
        ${rows.map(t => {
    const progress = stepProgress(normalizeStep(t.tiProceso));
    return `
            <div class="col-12 col-md-6 col-xl-4">
              <div class="ticket-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                  <div class="d-flex flex-wrap gap-2">
                    ${estadoBadge(t.tiEstatus)}
                    ${criticidadBadge(t.tiNivelCriticidad)}
                  </div>
                  <div class="fw-bold fs-6">${esc(t.folio)}</div>
                </div>

                <div class="row align-items-center">
                  <div class="col-4">
                    <img src="${esc(t.eqImgPath || '../img/Equipos/default.png')}"
                      alt="${esc(t.eqModelo || 'default.png')}"
                      class="img-fluid"
                      style="max-height:90px; object-fit:contain;">
                  </div>
                  <div class="col-8">
                    <div class="fw-bold fs-5 mb-1">${esc(t.eqModelo || 'Equipo')}</div>
                    <div class="muted mb-2">
                      ${esc(t.maNombre || 'Marca')}
                      ${t.eqVersion ? ' ' + esc(t.eqVersion) : ''}
                      · SN: ${esc(t.peSN || '—')}
                    </div>

                    <div class="mb-2">
                      <span class="fw-semibold">Paso actual:</span>
                      <span class="muted">${esc(t.tiProceso || '—')}</span>
                    </div>
                  </div>
                </div>

                <div class="progress progress-thin mb-1 mt-2">
                  <div class="progress-bar" style="width:${progress.pct}%"></div>
                </div>
                <div class="small muted mb-3">${progress.idx + 1}/${progress.total}</div>

                <div class="d-flex flex-wrap gap-2">
                  ${actionButtonHtml(t)}
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-open-ticket="${t.tiId}">
                    <i class="bi bi-eye"></i> Ver detalle
                  </button>
                </div>
              </div>
            </div>
          `;
  }).join('')}
      </div>
      ${renderSedePagination(sede, meta)}
    </section>
  `;
}

function renderSedePagination(sede, meta) {
  if (!meta || meta.totalPages <= 1) return '';

  const page = meta.page;
  const totalPages = meta.totalPages;

  const start = Math.max(1, page - 2);
  const end = Math.min(totalPages, page + 2);

  let pagesHtml = `
    <li class="page-item ${page === 1 ? 'disabled' : ''}">
      <button class="page-link" data-sede-page="${esc(sede)}" data-page-action="prev">Anterior</button>
    </li>
  `;

  for (let i = start; i <= end; i++) {
    pagesHtml += `
      <li class="page-item ${i === page ? 'active' : ''}">
        <button class="page-link" data-sede-page="${esc(sede)}" data-page-action="${i}">${i}</button>
      </li>
    `;
  }

  pagesHtml += `
    <li class="page-item ${page === totalPages ? 'disabled' : ''}">
      <button class="page-link" data-sede-page="${esc(sede)}" data-page-action="next">Siguiente</button>
    </li>
  `;

  return `
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
      <div class="small muted">Página ${page} de ${totalPages}</div>
      <nav>
        <ul class="pagination pagination-sm mb-0">${pagesHtml}</ul>
      </nav>
    </div>
  `;
}

function bindRenderEvents(container) {
  if (!container) return;

  container.querySelectorAll('[data-open-help]').forEach(el => {
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      openHelp(Number(el.dataset.openHelp));
    });
  });

  container.querySelectorAll('[data-open-logs]').forEach(el => {
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      openLogs(Number(el.dataset.openLogs));
    });
  });

  container.querySelectorAll('[data-open-meet]').forEach(el => {
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      openMeet(Number(el.dataset.openMeet));
    });
  });

  container.querySelectorAll('[data-open-ticket]').forEach(el => {
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      openTicket(Number(el.dataset.openTicket));
    });
  });

  container.querySelectorAll('[data-sede-page]').forEach(el => {
    el.addEventListener('click', (ev) => {
      ev.preventDefault();

      const sede = el.dataset.sedePage;
      const action = el.dataset.pageAction;

      const groups = groupTicketsBySede(filteredTickets());
      const group = groups.find(g => g.sede === sede);
      if (!group) return;

      const totalPages = Math.max(1, Math.ceil(group.tickets.length / state.perPage));
      let page = getSedePage(sede);

      if (action === 'prev' && page > 1) page--;
      else if (action === 'next' && page < totalPages) page++;
      else if (!Number.isNaN(Number(action))) page = Number(action);

      setSedePage(sede, page);
      renderTickets();
    });
  });

  container.querySelectorAll('[data-open-visita]').forEach(el => {
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      openVisita(Number(el.dataset.openVisita));
    });
  });

  container.querySelectorAll('[data-open-encuesta]').forEach(el => {
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      openEncuesta(Number(el.dataset.openEncuesta));
    });
  });
}

function renderTickets() {
  const allRows = filteredTickets();
  const wrap = document.getElementById('wrapTickets');
  const empty = document.getElementById('emptyState');

  if (!wrap || !empty) return;

  renderSummary(allRows.length, groupTicketsBySede(allRows).length);

  if (!allRows.length) {
    wrap.innerHTML = '';
    empty.classList.remove('d-none');
    return;
  }

  empty.classList.add('d-none');

  const groups = groupTicketsBySede(allRows);

  wrap.innerHTML = state.vista === 'tabla'
    ? groups.map(g => {
      const meta = paginateSedeTickets(g.sede, g.tickets);
      return renderTableGroup(g.sede, meta.rows, meta);
    }).join('')
    : groups.map(g => {
      const meta = paginateSedeTickets(g.sede, g.tickets);
      return renderCardsGroup(g.sede, meta.rows, meta);
    }).join('');

  bindRenderEvents(wrap);
}

function formatVisitSlot(inicio, fin) {
  try {
    const di = new Date(String(inicio).replace(' ', 'T'));
    const df = new Date(String(fin).replace(' ', 'T'));

    const fecha = di.toLocaleDateString('es-MX', {
      year: 'numeric',
      month: 'short',
      day: '2-digit'
    });

    const hi = di.toLocaleTimeString('es-MX', {
      hour: '2-digit',
      minute: '2-digit'
    });

    const hf = df.toLocaleTimeString('es-MX', {
      hour: '2-digit',
      minute: '2-digit'
    });

    return `${fecha} · ${hi} - ${hf}`;
  } catch {
    return `${inicio} - ${fin}`;
  }
}

function addMinutesToVisit(datetimeLocal, minutes) {
  const value = String(datetimeLocal || '').trim();
  const mins = Number(minutes || 0);
  if (!value || Number.isNaN(mins) || mins <= 0) return '';

  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';

  d.setMinutes(d.getMinutes() + mins);

  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
}

function normalizeVisitStart(datetimeLocal) {
  const value = String(datetimeLocal || '').trim();
  if (!value) return '';

  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';

  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
}

async function openVisita(tiId) {
  try {
    document.getElementById('offVisitaTitle').textContent = 'Visita en sitio';
    document.getElementById('offVisitaSub').textContent = 'Coordina acceso, ventana y folio de entrada';
    document.getElementById('offVisitaBody').innerHTML = `<div class="muted">Cargando...</div>`;

    const data = await apiFetch(`api/visita_get.php?tiId=${encodeURIComponent(tiId)}`);
    const visita = data.visita || {};
    const accepted = data.accepted || null;
    const propuestas = Array.isArray(data.propuestas) ? data.propuestas : [];
    const autorTipo = String(data.autorTipo || '').toLowerCase();
    const ingenieros = Array.isArray(data.ingenieros) ? data.ingenieros : [];
    const vehiculos = Array.isArray(data.vehiculos) ? data.vehiculos : [];
    const piezas = Array.isArray(data.piezas) ? data.piezas : [];

    const confirmada = Number(visita.tiVisitaConfirmada || 0) === 1 || String(visita.estado || '') === 'confirmada';

    document.getElementById('offVisitaBody').innerHTML = `
      <div class="off-section">
        <div class="fw-bold mb-1">Estado actual</div>
        ${confirmada ? `
          <div class="small muted mb-2">La visita ya está confirmada.</div>
          <div class="fw-semibold">${esc(formatVisitSlot(accepted?.vpInicio || visita.confirmada_inicio, accepted?.vpFin || visita.confirmada_fin))}</div>
          <div class="small muted mt-1">Estado: ${esc(visita.tiVisitaEstado || visita.estado || 'confirmada')}</div>
          ${Number(visita.lock_cancel || 0) === 1 ? `
            <div class="small text-danger mt-2">La visita ya compromete recursos y no debe cancelarse libremente.</div>
          ` : ''}
        ` : `
          <div class="muted">Aún no hay una visita confirmada.</div>
        `}
      </div>

      <div class="off-section">
        <div class="fw-bold mb-2">Ingenieros asignados</div>
        ${ingenieros.length ? `
          <div class="list-group mb-2">
            ${ingenieros.map(i => `
              <div class="list-group-item">
                <div class="fw-semibold">${esc(i.nombre || 'Ingeniero')}</div>
                <div class="small muted">${esc(i.rol || '')}</div>
                <div class="small muted">${esc(i.usTelefono || '—')} · ${esc(i.usCorreo || '—')}</div>
              </div>
            `).join('')}
          </div>
        ` : `<div class="muted">Aún no hay ingenieros visibles para esta visita.</div>`}

        ${vehiculos.length ? `
          <div class="mt-3">
            <div class="fw-semibold mb-2">Vehículo(s)</div>
            <div class="list-group">
              ${vehiculos.map(v => `
                <div class="list-group-item">
                  <div class="fw-semibold">${esc(v.marca || '')} ${esc(v.modelo || '')}</div>
                  <div class="small muted">Color: ${esc(v.color || '—')} · Placas: ${esc(v.placas || '—')}</div>
                </div>
              `).join('')}
            </div>
          </div>
        ` : ''}

        ${piezas.length ? `
          <div class="mt-3">
            <div class="fw-semibold mb-2">Piezas / equipo de apoyo</div>
            <div class="list-group">
              ${piezas.map(p => `
                <div class="list-group-item">
                  <div class="fw-semibold">${esc(p.tipo_pieza || 'Pieza')}</div>
                  <div class="small muted">PN: ${esc(p.partNumber || '—')} · SN: ${esc(p.serialNumber || '—')}</div>
                  ${p.notas ? `<div class="small muted">Notas: ${esc(p.notas)}</div>` : ''}
                </div>
              `).join('')}
            </div>
          </div>
        ` : ''}
      </div>

      <div class="off-section">
        <div class="fw-bold mb-2">Opciones propuestas</div>

        ${propuestas.length ? `
          <div class="list-group mb-2">
            ${propuestas.map(p => `
              <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
                <div class="me-auto">
                  <div class="fw-semibold">Opción ${esc(p.vpOpcion)} · ${esc(formatVisitSlot(p.vpInicio, p.vpFin))}</div>
                  <div class="small muted">Estado: ${esc(p.vpEstado || '')}</div>
                </div>
                ${(String(p.vpEstado || '') === 'pendiente' && !confirmada && autorTipo === 'ingeniero') ? `
                  <button class="btn btn-sm btn-primary" data-accept-visita="${p.vpId}">
                    <i class="bi bi-check2-circle"></i> Aceptar
                  </button>
                ` : ''}
              </div>
            `).join('')}
          </div>
        ` : `<div class="muted">Aún no hay opciones registradas.</div>`}
      </div>

      <div class="off-section">
        <div class="fw-bold mb-2">Proponer 3 ventanas</div>
        <div class="small muted mb-3">Proponlas cuando necesites coordinar acceso o una nueva fecha de visita.</div>

        <div class="row g-3">
          <div class="col-12">
            <label class="small muted">Opción 1</label>
            <div class="row g-2">
              <div class="col-8"><input class="form-control" type="datetime-local" id="v1i"></div>
              <div class="col-4">
                <select class="form-select" id="v1d">
                  <option value="60">60 min</option>
                  <option value="90">90 min</option>
                  <option value="120" selected>120 min</option>
                  <option value="180">180 min</option>
                  <option value="240">240 min</option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-12">
            <label class="small muted">Opción 2</label>
            <div class="row g-2">
              <div class="col-8"><input class="form-control" type="datetime-local" id="v2i"></div>
              <div class="col-4">
                <select class="form-select" id="v2d">
                  <option value="60">60 min</option>
                  <option value="90">90 min</option>
                  <option value="120" selected>120 min</option>
                  <option value="180">180 min</option>
                  <option value="240">240 min</option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-12">
            <label class="small muted">Opción 3</label>
            <div class="row g-2">
              <div class="col-8"><input class="form-control" type="datetime-local" id="v3i"></div>
              <div class="col-4">
                <select class="form-select" id="v3d">
                  <option value="60">60 min</option>
                  <option value="90">90 min</option>
                  <option value="120" selected>120 min</option>
                  <option value="180">180 min</option>
                  <option value="240">240 min</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <button class="btn btn-primary w-100 mt-3" id="btnSendVisita">
          <i class="bi bi-calendar-plus"></i> Enviar 3 opciones
        </button>

        <div class="small muted mt-2" id="visitaHint"></div>
      </div>

      <div class="off-section">
        <div class="fw-bold mb-2">Folio / autorización de entrada</div>
        <div class="small muted mb-2">
          ${Number(visita.tiAccesoRequiereDatos || 0) === 1 ? 'Este dato es obligatorio para el acceso del ingeniero.' : 'Puedes registrar el folio y adjuntar evidencia si aplica.'}
        </div>

        ${visita.tiAccesoExtraTexto ? `
          <div class="small muted mb-2">Indicaciones: ${esc(visita.tiAccesoExtraTexto)}</div>
        ` : ''}

        <input class="form-control mb-2" id="tiFolioEntradaVisita" placeholder="Folio / autorización" value="${esc(visita.tiFolioEntrada || '')}">
        <input class="form-control mb-2" type="file" id="folioFileVisita" accept=".pdf,.jpg,.jpeg,.png,.webp">

        <button class="btn btn-primary w-100" id="btnSaveFolioVisita">
          <i class="bi bi-upload"></i> Guardar folio
        </button>

        <div class="small muted mt-2" id="folioHintVisita"></div>

        ${visita.tiFolioArchivo ? `
          <div class="mt-3">
            <a class="btn btn-sm btn-outline-primary" href="../${esc(visita.tiFolioArchivo)}" target="_blank">
              <i class="bi bi-file-earmark"></i> Ver archivo actual
            </a>
          </div>
        ` : ''}
      </div>

      <div class="d-grid gap-2">
        <button class="btn btn-outline-secondary" type="button" id="btnReloadVisita">
          <i class="bi bi-arrow-clockwise"></i> Recargar
        </button>
        <button class="btn btn-dark" type="button" data-bs-dismiss="offcanvas">Cerrar</button>
      </div>
    `;

    document.querySelectorAll('[data-accept-visita]').forEach(btn => {
      btn.addEventListener('click', async () => {
        try {
          const vpId = Number(btn.dataset.acceptVisita);
          await apiFetch('api/visita_accept.php', {
            method: 'POST',
            body: { vpId }
          });
          toastOk('Visita confirmada.');
          await openVisita(tiId);
          await loadTickets();
        } catch (e) {
          toastErr(e.message || 'No se pudo confirmar la visita.');
        }
      });
    });

    document.getElementById('btnSendVisita').addEventListener('click', async () => {
      const hint = document.getElementById('visitaHint');

      const i1 = document.getElementById('v1i').value;
      const i2 = document.getElementById('v2i').value;
      const i3 = document.getElementById('v3i').value;

      const d1 = Number(document.getElementById('v1d').value || 120);
      const d2 = Number(document.getElementById('v2d').value || 120);
      const d3 = Number(document.getElementById('v3d').value || 120);

      const slots = [
        { inicio: normalizeVisitStart(i1), fin: addMinutesToVisit(i1, d1) },
        { inicio: normalizeVisitStart(i2), fin: addMinutesToVisit(i2, d2) },
        { inicio: normalizeVisitStart(i3), fin: addMinutesToVisit(i3, d3) }
      ];

      if (!i1 || !i2 || !i3) {
        toastErr('Debes capturar las 3 opciones de visita.');
        return;
      }

      if (slots.some(s => !s.inicio || !s.fin)) {
        toastErr('No se pudieron calcular correctamente las ventanas.');
        return;
      }

      try {
        hint.textContent = 'Enviando propuestas...';

        await apiFetch('api/visita_create.php', {
          method: 'POST',
          body: { tiId, slots }
        });

        hint.textContent = '';
        toastOk('Propuestas de visita enviadas.');
        await openVisita(tiId);
        await loadTickets();
      } catch (e) {
        hint.textContent = '';
        toastErr(e.message || 'No se pudieron enviar las propuestas.');
      }
    });

    document.getElementById('btnSaveFolioVisita').addEventListener('click', async () => {
      const hint = document.getElementById('folioHintVisita');
      const folio = document.getElementById('tiFolioEntradaVisita').value || '';
      const file = document.getElementById('folioFileVisita').files?.[0] || null;

      if (!folio.trim()) {
        toastErr('Debes capturar el folio o autorización.');
        return;
      }

      try {
        hint.textContent = 'Guardando folio...';

        const fd = new FormData();
        fd.append('tiId', String(tiId));
        fd.append('tiFolioEntrada', folio);
        if (file) fd.append('folioFile', file);

        await apiFetchForm('api/visita_folio_upload.php', fd);

        hint.textContent = '';
        toastOk('Folio guardado correctamente.');
        await openVisita(tiId);
        await loadTickets();
      } catch (e) {
        hint.textContent = '';
        toastErr(e.message || 'No se pudo guardar el folio.');
      }
    });

    document.getElementById('btnReloadVisita').addEventListener('click', async () => {
      await openVisita(tiId);
    });

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offVisita')).show();
  } catch (e) {
    toastErr(e.message || 'No se pudo abrir la visita.');
  }
}

/* ===========================
   Data loaders
   =========================== */

async function loadTickets() {
  try {
    const json = await apiFetch('api/tickets_list.php');
    state.tickets = Array.isArray(json.tickets) ? json.tickets : [];
    resetAllSedePages();
    renderStats(json.meta || {});
    renderTickets();
  } catch (e) {
    toastErr(e.message || 'No se pudieron cargar los tickets.');
  }
}

async function loadHistorial(tiId) {
  const json = await apiFetch(`api/ticket_historial_short.php?tiId=${encodeURIComponent(tiId)}`);
  return Array.isArray(json.items) ? json.items : [];
}

/* ===========================
   Offcanvas Ticket (detalle)
   =========================== */

function buildFooterActions(ticket) {
  // mantenemos un footer simple (puedes refinar luego)
  return `
    <button class="btn btn-outline-secondary w-100" type="button" data-bs-dismiss="offcanvas">
      <i class="bi bi-x-circle"></i> Cerrar
    </button>
  `;
}

function helpTypeFromTicket(ticket) {
  const step = normalizeStep(ticket?.tiProceso);

  if (step === 'logs') return 'logs';
  if (step === 'meet') return 'meet';
  if (step === 'visita' || step === 'espera visita' || step === 'espera ventana') return 'visita';
  if (step === 'espera documentacion') return 'documentacion';
  return 'general';
}

function helpTypeLabel(tipo) {
  switch (String(tipo || '').toLowerCase()) {
    case 'logs': return 'Apoyo con logs';
    case 'meet': return 'Apoyo con Meet';
    case 'visita': return 'Apoyo con visita';
    case 'documentacion': return 'Apoyo con documentación';
    default: return 'Ayuda general';
  }
}

async function openHelp(tiId) {
  try {
    const ticket = state.tickets.find(t => Number(t.tiId) === Number(tiId)) || state.selected || { tiId };
    const tipoDefault = helpTypeFromTicket(ticket);

    const title = document.getElementById('offHelpTitle');
    const sub = document.getElementById('offHelpSub');
    const body = document.getElementById('offHelpBody');

    if (title) title.textContent = `Ayuda · ${ticket.folio || ('Ticket ' + tiId)}`;
    if (sub) sub.textContent = `${ticket.eqModelo || 'Equipo'} · ${ticket.maNombre || 'Marca'} · SN: ${ticket.peSN || '—'}`;
    if (body) body.innerHTML = `<div class="muted">Cargando...</div>`;

    const data = await apiFetch(`api/help_list.php?tiId=${encodeURIComponent(tiId)}`);
    const items = Array.isArray(data.items) ? data.items : [];

    if (body) {
      body.innerHTML = `
        <div class="off-section">
          <div class="fw-bold mb-2">Solicitar apoyo</div>
          <div class="small muted mb-3">
            Este mensaje le llegará al equipo de MR Solutions para ayudarte a continuar con el ticket.
          </div>

          <div class="mb-3">
            <label class="form-label">Tipo de ayuda</label>
            <select class="form-select" id="helpTipo">
              <option value="general" ${tipoDefault === 'general' ? 'selected' : ''}>Ayuda general</option>
              <option value="logs" ${tipoDefault === 'logs' ? 'selected' : ''}>Apoyo con logs</option>
              <option value="meet" ${tipoDefault === 'meet' ? 'selected' : ''}>Apoyo con Meet</option>
              <option value="visita" ${tipoDefault === 'visita' ? 'selected' : ''}>Apoyo con visita</option>
              <option value="documentacion" ${tipoDefault === 'documentacion' ? 'selected' : ''}>Apoyo con documentación</option>
              <option value="otro">Otro</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Mensaje</label>
            <textarea
              class="form-control"
              id="helpMensaje"
              rows="5"
              maxlength="2000"
              placeholder="Ej: No logro subir los logs correctos, necesito apoyo para validar qué archivo corresponde al equipo."></textarea>
            <div class="small muted mt-1">Sé lo más claro posible. Esto acelera la atención.</div>
          </div>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="helpRequiereMeet">
            <label class="form-check-label" for="helpRequiereMeet">También necesito apoyo por Meet</label>
          </div>

          <div class="mb-3">
            <label class="form-label">Plataforma preferida para Meet (opcional)</label>
            <select class="form-select" id="helpPlataforma">
              <option value="">Sin preferencia</option>
              <option value="Google Meet">Google Meet</option>
              <option value="Microsoft Teams">Microsoft Teams</option>
              <option value="Zoom">Zoom</option>
              <option value="Llamada telefónica">Llamada telefónica</option>
            </select>
          </div>

          <button class="btn btn-primary w-100" id="btnSendHelp">
            <i class="bi bi-send"></i> Enviar solicitud de ayuda
          </button>
          <div class="small muted mt-2" id="helpHint"></div>
        </div>

        <div class="off-section">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-bold">Solicitudes recientes</div>
            <div class="small muted">${items.length}</div>
          </div>

          ${items.length
          ? `<div class="timeline-mini">
                  ${items.map(item => {
            const respuestas = Array.isArray(item.respuestas) ? item.respuestas : [];
            const estado = String(item.taEstado || '').toLowerCase();
            const badgeClass =
              estado === 'pendiente' ? 'text-bg-warning'
                : estado === 'atendida' ? 'text-bg-success'
                  : 'text-bg-secondary';

            return `
    <li>
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div class="flex-grow-1">
          <div class="small fw-semibold">${esc(helpTypeLabel(item.taTipo))}</div>
          <div class="small">${esc(item.taMensaje || '')}</div>

          ${Number(item.taRequiereMeet || 0) === 1
                ? `<div class="small muted mt-1"><i class="bi bi-camera-video"></i> Solicitó apoyo por Meet</div>`
                : ''}

          <div class="small muted mt-1">
            Enviado el ${esc(formatDateTime(item.taCreadoEn || item.fecha || ''))}
          </div>

          ${respuestas.length
                ? `
                <div class="mt-2 ps-2 border-start">
                  <div class="small fw-semibold mb-1">Respuesta de MR Solutions</div>
                  ${respuestas.map(r => `
                    <div class="rounded p-2 mb-2" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);">
                      <div class="small">${esc(r.tarMensaje || '')}</div>
                      <div class="small muted mt-1">
                        ${esc(r.usuarioNombre || 'MR Solutions')} · ${esc(formatDateTime(r.tarCreadoEn || ''))}
                      </div>
                    </div>
                  `).join('')}
                </div>
              `
                : `
                <div class="small muted mt-2">
                  Aún no hay respuesta de MR Solutions.
                </div>
              `

              }
          ${estado !== 'cerrada'
                ? `
      <div class="mt-2">
        <textarea
          class="form-control form-control-sm help-reply-text"
          rows="2"
          data-ta-id="${Number(item.taId)}"
          placeholder="Escribe una respuesta o comentario adicional..."></textarea>
        <button
          class="btn btn-sm btn-outline-primary mt-2 help-reply-send"
          data-ta-id="${Number(item.taId)}">
          <i class="bi bi-reply"></i> Responder
        </button>
      </div>
    `
                : `
      <div class="small muted mt-2">Esta solicitud ya fue cerrada.</div>
    `
              }
        </div>

        <span class="badge rounded-pill ${badgeClass}">
          ${esc(item.taEstado || 'pendiente')}
        </span>
      </div>
    </li>
  `;
          }).join('')}
                </div>`
          : `<div class="muted">Todavía no has enviado solicitudes de ayuda para este ticket.</div>`
        }
        </div>
      `;
    }
    document.querySelectorAll('.help-reply-send').forEach(btn => {
    btn.addEventListener('click', async () => {
      const taId = Number(btn.dataset.taId || 0);
      const box = document.querySelector(`.help-reply-text[data-ta-id="${taId}"]`);
      const tarMensaje = (box?.value || '').trim();

      if (!taId || !tarMensaje) {
        toastErr('Escribe un mensaje antes de responder.');
        return;
      }

      try {
        btn.disabled = true;

        await apiFetch('api/help_reply.php', {
          method: 'POST',
          body: { taId, tarMensaje }
        });

        toastOk('Mensaje enviado.');
        await openHelp(tiId);
      } catch (e) {
        toastErr(e.message || 'No se pudo enviar el mensaje.');
      } finally {
        btn.disabled = false;
      }
    });
  });

    const btnSend = document.getElementById('btnSendHelp');
    if (btnSend) {
      btnSend.addEventListener('click', async () => {
        const tipo = document.getElementById('helpTipo')?.value || 'general';
        const mensaje = (document.getElementById('helpMensaje')?.value || '').trim();
        const requiereMeet = document.getElementById('helpRequiereMeet')?.checked ? 1 : 0;
        const plataforma = document.getElementById('helpPlataforma')?.value || '';
        const hint = document.getElementById('helpHint');

        if (!mensaje) {
          toastErr('Debes escribir el mensaje de ayuda.');
          return;
        }

        try {
          btnSend.disabled = true;
          if (hint) hint.textContent = 'Enviando solicitud...';

          await apiFetch('api/help_create.php', {
            method: 'POST',
            body: {
              tiId: Number(tiId),
              taTipo: tipo,
              taMensaje: mensaje,
              taRequiereMeet: requiereMeet,
              taPlataformaPreferida: plataforma
            }
          });

          if (hint) hint.textContent = '';
          toastOk('Tu solicitud de ayuda fue enviada.');
          await openHelp(tiId);
          await loadTickets();
        } catch (e) {
          if (hint) hint.textContent = '';
          toastErr(e.message || 'No se pudo enviar la solicitud de ayuda.');
        } finally {
          btnSend.disabled = false;
        }
      });
    }

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offHelp')).show();
  } catch (e) {
    toastErr(e.message || 'No se pudo abrir ayuda.');
  }
}


async function openTicket(tiId) {
  try {
    const [detail, historial] = await Promise.all([
      apiFetch(`api/ticket_detail.php?tiId=${encodeURIComponent(tiId)}`),
      loadHistorial(tiId)
    ]);

    const t = detail.ticket;
    state.selected = t;

    const action = humanAction(t);
    const progress = stepProgress(normalizeStep(t.tiProceso));

    const offCodigo = document.getElementById('offCodigo');
    const offHeaderSub = document.getElementById('offHeaderSub');
    const offEquipo = document.getElementById('offEquipo');
    const offMarcaSn = document.getElementById('offMarcaSn');
    const offCriticidad = document.getElementById('offCriticidad');
    const offEstado = document.getElementById('offEstado');
    const offAccionActual = document.getElementById('offAccionActual');
    const offPasoActual = document.getElementById('offPasoActual');
    const offProgressBar = document.getElementById('offProgressBar');
    const offProgresoTexto = document.getElementById('offProgresoTexto');
    const offMensajeClaro = document.getElementById('offMensajeClaro');
    const offHistorial = document.getElementById('offHistorial');
    const offFooterActions = document.getElementById('offFooterActions');

    if (offCodigo) offCodigo.textContent = t.folio;
    if (offHeaderSub) offHeaderSub.textContent = t.csNombre || 'Detalle del ticket';
    if (offEquipo) offEquipo.textContent = t.eqModelo || 'Equipo';
    if (offMarcaSn) offMarcaSn.textContent = `${t.maNombre || 'Marca'} · SN: ${t.peSN || '—'}`;
    if (offCriticidad) offCriticidad.innerHTML = criticidadBadge(t.tiNivelCriticidad);
    if (offEstado) offEstado.innerHTML = estadoBadge(t.tiEstatus);

    if (offAccionActual) {
      offAccionActual.innerHTML = `
        <div class="fw-bold fs-5 mb-1">${esc(action.title)}</div>
        <div class="muted mb-3">${esc(action.desc)}</div>
        <div class="d-flex flex-wrap gap-2">
          ${actionButtonHtml(t)}
          <button class="btn btn-sm btn-outline-secondary" type="button" data-open-help="${t.tiId}">
            <i class="bi bi-question-circle"></i> Ayuda
          </button>
        </div>
      `;
      offAccionActual?.querySelectorAll('[data-open-help]').forEach(el => {
        el.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          openHelp(Number(el.dataset.openHelp));
        });
      });

      offAccionActual?.querySelectorAll('[data-open-logs]').forEach(el => {
        el.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          openLogs(Number(el.dataset.openLogs));
        });
      });

      offAccionActual?.querySelectorAll('[data-open-meet]').forEach(el => {
        el.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          openMeet(Number(el.dataset.openMeet));
        });
      });

      offAccionActual?.querySelectorAll('[data-open-visita]').forEach(el => {
        el.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          openVisita(Number(el.dataset.openVisita));
        });
      });

      offAccionActual?.querySelectorAll('[data-open-encuesta]').forEach(el => {
        el.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          openEncuesta(Number(el.dataset.openEncuesta));
        });
      });
    }

    if (offPasoActual) offPasoActual.textContent = t.tiProceso || '—';
    if (offProgressBar) offProgressBar.style.width = `${progress.pct}%`;
    if (offProgresoTexto) offProgresoTexto.textContent = `Paso ${progress.idx + 1} de ${progress.total} del flujo actual.`;
    if (offMensajeClaro) offMensajeClaro.textContent = guidanceMessage(t);

    if (offHistorial) {
      offHistorial.innerHTML = historial.length
        ? `<ul class="timeline-mini">${historial.map(i => `
            <li>
              <div class="small fw-semibold">${esc(i.descripcion)}</div>
              <div class="small muted">${esc(formatDateTime(i.fecha))}</div>
            </li>
          `).join('')}</ul>`
        : `<div class="muted small">Todavía no hay movimientos visibles para mostrar aquí.</div>`;
    }

    if (offFooterActions) offFooterActions.innerHTML = buildFooterActions(t);

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offTicket')).show();
  } catch (e) {
    toastErr(e.message || 'No se pudo abrir el ticket.');
  }
}

/* ===========================

   Offcanvas LOGS (offLogs)
   =========================== */


async function openLogs(tiId) {
  try {
    const title = document.getElementById('offLogsTitle');
    const body = document.getElementById('offLogsBody');
    if (title) title.textContent = `Logs · Ticket ${tiId}`;
    if (body) body.innerHTML = `<div class="muted">Cargando...</div>`;

    const data = await apiFetch(`api/logs_list.php?tiId=${encodeURIComponent(tiId)}`);
    const logs = Array.isArray(data.logs) ? data.logs : [];
    const motivo = data.logs_request?.motivo ? String(data.logs_request.motivo) : '';

    if (body) {
      body.innerHTML = `
        <div class="off-section">
          <div class="fw-bold mb-2">¿Qué se requiere?</div>
          ${motivo
          ? `<pre class="p-2 mb-0" style="white-space:pre-wrap;background:#f8fafc;border:1px solid rgba(15,23,42,.08);border-radius:.75rem;">${esc(motivo)}</pre>`
          : `<div class="muted">No hay motivo registrado. Puedes subir los archivos solicitados.</div>`}
        </div>

        <div class="off-section">
          <div class="fw-bold mb-2">Subir logs</div>
          <div class="small muted mb-2">.txt .log .zip .7z .rar (máx 25MB por archivo)</div>
          <input class="form-control mb-2" type="file" id="logsFilesOff" multiple>
          <button class="btn btn-primary w-100" id="btnUploadLogsOff">
            <i class="bi bi-upload"></i> Subir y enviar a revisión
          </button>
          <div class="small muted mt-2" id="logsHintOff"></div>
        </div>

        <div class="off-section">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-bold">Archivos cargados</div>
            <div class="small muted">${logs.length}</div>
          </div>
          ${logs.length ? `
            <div class="list-group">
              ${logs.map(f => `
                <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <div class="fw-semibold">${esc(f.taNombreOriginal)}</div>
                    <div class="small muted">${bytesFmt(f.taTamano)} · ${esc(f.taMime || '—')}</div>
                  </div>
                  <a class="btn btn-sm btn-outline-primary" href="api/logs_download.php?taId=${f.taId}">
                    <i class="bi bi-download"></i>
                  </a>
                </div>
              `).join('')}
            </div>
          ` : `<div class="muted">Aún no hay archivos cargados.</div>`}
        </div>
      `;
    }

    const btn = document.getElementById('btnUploadLogsOff');
    if (btn) {
      btn.addEventListener('click', async () => {
        const input = document.getElementById('logsFilesOff');
        const hint = document.getElementById('logsHintOff');
        const files = input?.files ? Array.from(input.files) : [];

        if (!files.length) { toastErr('Selecciona al menos un archivo.'); return; }

        const allowed = ['txt', 'log', 'zip', '7z', 'rar', 'gz', 'tar', 'json', 'csv'];
        const maxBytes = 25 * 1024 * 1024;

        for (const f of files) {
          const ext = (f.name.split('.').pop() || '').toLowerCase();
          if (!allowed.includes(ext)) { toastErr(`Formato no permitido: ${f.name}`); return; }
          if (f.size > maxBytes) { toastErr(`Archivo demasiado grande: ${f.name}`); return; }
        }

        try {
          if (hint) hint.textContent = 'Subiendo...';
          btn.disabled = true;

          const fd = new FormData();
          fd.append('tiId', String(tiId));
          files.forEach(f => fd.append('files[]', f));

          await apiFetchForm('api/logs_upload.php', fd);

          toastOk('Logs enviados. El ticket pasó a revisión especial.');
          if (hint) hint.textContent = '';

          await loadTickets();

          bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offLogs')).hide();
        } catch (e) {
          if (hint) hint.textContent = '';
          toastErr(e.message || 'Error al subir logs.');
        } finally {
          btn.disabled = false;
        }
      });
    }

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offLogs')).show();
  } catch (e) {
    toastErr(e.message || 'No se pudo abrir logs.');
  }
}

/* ===========================
   Offcanvas MEET (offMeet)
   =========================== */

function formatMeetSlot(inicio, fin) {
  try {
    const di = new Date(String(inicio).replace(' ', 'T'));
    const df = new Date(String(fin).replace(' ', 'T'));

    const fecha = di.toLocaleDateString('es-MX', {
      year: 'numeric',
      month: 'short',
      day: '2-digit'
    });

    const hi = di.toLocaleTimeString('es-MX', {
      hour: '2-digit',
      minute: '2-digit'
    });

    const hf = df.toLocaleTimeString('es-MX', {
      hour: '2-digit',
      minute: '2-digit'
    });

    return `${fecha} · ${hi} - ${hf}`;
  } catch {
    return `${inicio} - ${fin}`;
  }
}

function normalizeDateTimeLocal(datetimeLocal) {
  const value = String(datetimeLocal || '').trim();
  if (!value) return '';

  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';

  const pad = (n) => String(n).padStart(2, '0');

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
}

function addMinutesToDateTime(datetimeLocal, minutes) {
  const value = String(datetimeLocal || '').trim();
  const mins = Number(minutes || 0);

  if (!value || Number.isNaN(mins) || mins < 0) return '';

  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';

  d.setMinutes(d.getMinutes() + mins);

  const pad = (n) => String(n).padStart(2, '0');

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
}

function normalizeUrl(url) {
  let u = String(url || '').trim();

  if (!u) return '';

  if (!/^https?:\/\//i.test(u)) {
    u = 'https://' + u;
  }

  return u;
}

function renderStars(value = 0) {
  const current = Number(value || 0);
  return `
    <div class="d-flex gap-2 flex-wrap" id="encuestaStars">
      ${[1, 2, 3, 4, 5].map(n => `
        <button type="button"
          class="btn ${current === n ? 'btn-warning' : 'btn-outline-warning'}"
          data-star-value="${n}">
          <i class="bi bi-star-fill"></i> ${n}
        </button>
      `).join('')}
    </div>
  `;
}

async function openEncuesta(tiId) {
  try {
    document.getElementById('offEncuestaTitle').textContent = 'Encuesta de satisfacción';
    document.getElementById('offEncuestaSub').textContent = 'Tu experiencia con el servicio';
    document.getElementById('offEncuestaBody').innerHTML = `<div class="muted">Cargando...</div>`;

    const data = await apiFetch(`api/encuesta_get.php?tiId=${encodeURIComponent(tiId)}`);
    const encuesta = data.encuesta || null;

    const calificacionInicial = Number(encuesta?.calificacion || 0);
    const comentarioInicial = String(encuesta?.comentario || '');

    document.getElementById('offEncuestaBody').innerHTML = `
      <div class="off-section">
        <div class="fw-bold mb-2">¿Cómo calificarías la atención recibida?</div>
        <div class="small muted mb-3">
          Tu respuesta nos ayuda a mejorar el servicio. La encuesta representa el cierre de experiencia.
        </div>

        <div id="encuestaStarsWrap">
          ${renderStars(calificacionInicial)}
        </div>

        <div class="small muted mt-2" id="encuestaCalificacionLabel">
          ${calificacionInicial ? `Calificación seleccionada: ${calificacionInicial}/5` : 'Selecciona una calificación del 1 al 5'}
        </div>
      </div>

      <div class="off-section">
        <div class="fw-bold mb-2">Comentario (opcional)</div>
        <textarea class="form-control" id="encuestaComentario" rows="5" placeholder="Cuéntanos brevemente cómo fue tu experiencia...">${esc(comentarioInicial)}</textarea>
      </div>

      <div class="d-grid gap-2">
        <button class="btn btn-primary" type="button" id="btnSaveEncuesta">
          <i class="bi bi-send-check"></i> Enviar encuesta
        </button>
        <button class="btn btn-outline-secondary" type="button" id="btnReloadEncuesta">
          <i class="bi bi-arrow-clockwise"></i> Recargar
        </button>
        <button class="btn btn-dark" type="button" data-bs-dismiss="offcanvas">
          Cerrar
        </button>
      </div>
    `;

    let selectedScore = calificacionInicial;

    function bindStars() {
      document.querySelectorAll('[data-star-value]').forEach(btn => {
        btn.addEventListener('click', () => {
          selectedScore = Number(btn.dataset.starValue || 0);
          document.getElementById('encuestaStarsWrap').innerHTML = renderStars(selectedScore);
          document.getElementById('encuestaCalificacionLabel').textContent = `Calificación seleccionada: ${selectedScore}/5`;
          bindStars();
        });
      });
    }

    bindStars();

    document.getElementById('btnSaveEncuesta').addEventListener('click', async () => {
      try {
        if (!selectedScore || selectedScore < 1 || selectedScore > 5) {
          toastErr('Selecciona una calificación del 1 al 5.');
          return;
        }

        const comentario = document.getElementById('encuestaComentario').value || '';

        await apiFetch('api/encuesta_save.php', {
          method: 'POST',
          body: {
            tiId,
            calificacion: selectedScore,
            comentario
          }
        });

        toastOk('Encuesta enviada correctamente.');
        await loadTickets();
        bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offEncuesta')).hide();
      } catch (e) {
        toastErr(e.message || 'No se pudo guardar la encuesta.');
      }
    });

    document.getElementById('btnReloadEncuesta').addEventListener('click', async () => {
      await openEncuesta(tiId);
    });

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offEncuesta')).show();
  } catch (e) {
    toastErr(e.message || 'No se pudo abrir la encuesta.');
  }
}

function formatMeetSlot(inicio, fin) {
  try {
    const di = new Date(String(inicio).replace(' ', 'T'));
    const df = new Date(String(fin).replace(' ', 'T'));

    if (Number.isNaN(di.getTime()) || Number.isNaN(df.getTime())) {
      return `${inicio} - ${fin}`;
    }

    const fecha = di.toLocaleDateString('es-MX', {
      year: 'numeric',
      month: 'short',
      day: '2-digit'
    });

    const hi = di.toLocaleTimeString('es-MX', {
      hour: '2-digit',
      minute: '2-digit'
    });

    const hf = df.toLocaleTimeString('es-MX', {
      hour: '2-digit',
      minute: '2-digit'
    });

    return `${fecha} · ${hi} - ${hf}`;
  } catch {
    return `${inicio} - ${fin}`;
  }
}

async function openMeet(tiId) {
  try {
    document.getElementById('offMeetTitle').textContent = 'Meet de apoyo';
    document.getElementById('offMeetSub').textContent = 'El cliente propone 3 horarios. El ingeniero/admin confirma 1.';
    document.getElementById('offMeetBody').innerHTML = `<div class="muted">Cargando...</div>`;

    const data = await apiFetch(`api/meet_get.php?tiId=${encodeURIComponent(tiId)}`);
    const meet = data.meet || {};
    const accepted = data.accepted || null;
    const propuestas = Array.isArray(data.propuestas) ? data.propuestas : [];
    const autorTipo = String(data.autorTipo || '').toLowerCase();
    const confirmado = String(meet.estado || '').toLowerCase() === 'confirmado';

    document.getElementById('offMeetBody').innerHTML = `
      <div class="off-section">
        <div class="fw-bold mb-1">Estado actual</div>
        ${confirmado ? `
          <div class="small muted mb-2">El Meet ya está confirmado.</div>

          <div class="fw-semibold">
            ${accepted ? esc(formatMeetSlot(accepted.mpInicio, accepted.mpFin)) : `${esc(meet.fecha || '')} · ${esc(meet.hora || '')}`}
          </div>

          <div class="small muted mt-1">
            Plataforma: ${esc(accepted?.mpPlataforma || meet.plataforma || '—')}
          </div>

          <div class="small muted mt-1">
            Modalidad: ${esc(accepted?.mpModo || '—')}
          </div>

          <div class="small muted mt-1">
            Motivo: ${esc(accepted?.mpMotivo || 'Sin motivo registrado')}
          </div>

          ${(accepted?.mpLink || meet.enlace) ? `
            <div class="mt-2">
              <a class="btn btn-sm btn-outline-primary" href="${esc(normalizeUrl(accepted?.mpLink || meet.enlace))}" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> Abrir enlace
              </a>
            </div>
          ` : ''}
        ` : `
          <div class="muted">Aún no hay un Meet confirmado.</div>
        `}
      </div>

      <div class="off-section">
        <div class="fw-bold mb-2">Opciones propuestas</div>

        ${propuestas.length ? `
          <div class="list-group mb-2">
            ${propuestas.map(p => `
              <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
                <div class="me-auto">
                  <div class="fw-semibold">${esc(formatMeetSlot(p.mpInicio, p.mpFin))}</div>
                  <div class="small muted">${esc(p.mpPlataforma || '')}</div>
                  <div class="small muted">Estado: ${esc(p.mpEstado || '')}</div>
                  ${p.mpMotivo ? `<div class="small muted">Motivo: ${esc(p.mpMotivo)}</div>` : ''}
                </div>
                ${(String(p.mpEstado || '') === 'pendiente' && !confirmado && autorTipo === 'ingeniero') ? `
                  <button class="btn btn-sm btn-primary" data-accept-meet="${p.mpId}">
                    <i class="bi bi-check2-circle"></i> Aceptar
                  </button>
                ` : ''}
              </div>
            `).join('')}
          </div>
        ` : `
          <div class="muted">Aún no hay opciones registradas.</div>
        `}

        <div class="small muted mt-2">
          Al aceptar una opción, el Meet queda confirmado y el resto se rechaza.
        </div>
      </div>

      <div class="off-section">
        <div class="fw-bold mb-2">Proponer 3 horarios</div>
        <div class="small muted mb-3">
          Úsalo cuando necesites apoyo remoto para revisar logs, validaciones o seguimiento técnico.
        </div>

        <div class="row g-3">
          <div class="col-12">
            <label class="small muted">Opción 1</label>
            <div class="row g-2">
              <div class="col-8">
                <input class="form-control" type="datetime-local" id="m1i">
              </div>
              <div class="col-4">
                <select class="form-select" id="m1d">
                  <option value="30">30 min</option>
                  <option value="45">45 min</option>
                  <option value="60" selected>60 min</option>
                  <option value="90">90 min</option>
                  <option value="120">120 min</option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-12">
            <label class="small muted">Opción 2</label>
            <div class="row g-2">
              <div class="col-8">
                <input class="form-control" type="datetime-local" id="m2i">
              </div>
              <div class="col-4">
                <select class="form-select" id="m2d">
                  <option value="30">30 min</option>
                  <option value="45">45 min</option>
                  <option value="60" selected>60 min</option>
                  <option value="90">90 min</option>
                  <option value="120">120 min</option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-12">
            <label class="small muted">Opción 3</label>
            <div class="row g-2">
              <div class="col-8">
                <input class="form-control" type="datetime-local" id="m3i">
              </div>
              <div class="col-4">
                <select class="form-select" id="m3d">
                  <option value="30">30 min</option>
                  <option value="45">45 min</option>
                  <option value="60" selected>60 min</option>
                  <option value="90">90 min</option>
                  <option value="120">120 min</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <label class="small muted">Plataforma </label>
          <input class="form-control" id="meetPlat" placeholder="Teams / Zoom / Google Meet" required>
        </div>

        <div class="mt-2">
          <label class="small muted">Motivo </label>
          <input class="form-control" id="meetMotivo" placeholder="Ej: apoyo extracción de logs" required>
        </div>

        <div class="mt-2">
          <label class="small muted">Enlace</label>
          <input class="form-control" id="meetLink" placeholder="https://..." required>
        </div>

        <button class="btn btn-primary w-100 mt-3" id="btnSendMeet">
          <i class="bi bi-calendar-plus"></i> Enviar 3 opciones
        </button>

        <div class="small muted mt-2" id="meetHint"></div>
      </div>

      <div class="d-grid gap-2">
        <button class="btn btn-outline-secondary" type="button" id="btnReloadMeet">
          <i class="bi bi-arrow-clockwise"></i> Recargar
        </button>
        <button class="btn btn-dark" type="button" data-bs-dismiss="offcanvas">
          Cerrar
        </button>
      </div>
    `;

    document.querySelectorAll('[data-accept-meet]').forEach(btn => {
      btn.addEventListener('click', async () => {
        try {
          const mpId = Number(btn.dataset.acceptMeet);

          await apiFetch('api/meet_accept.php', {
            method: 'POST',
            body: { mpId }
          });

          toastOk('Meet confirmado.');
          await openMeet(tiId);
          await loadTickets();
        } catch (e) {
          toastErr(e.message || 'No se pudo confirmar meet.');
        }
      });
    });

    document.getElementById('btnSendMeet').addEventListener('click', async () => {
      const hint = document.getElementById('meetHint');

      const i1 = document.getElementById('m1i').value;
      const i2 = document.getElementById('m2i').value;
      const i3 = document.getElementById('m3i').value;

      const d1 = Number(document.getElementById('m1d').value || 60);
      const d2 = Number(document.getElementById('m2d').value || 60);
      const d3 = Number(document.getElementById('m3d').value || 60);

      const plat = document.getElementById('meetPlat').value.trim();
      const motivo = document.getElementById('meetMotivo').value.trim();
      const enlace = document.getElementById('meetLink').value.trim();

      const slots = [
        {
          inicio: normalizeDateTimeLocal(i1),
          fin: addMinutesToDateTime(i1, d1)
        },
        {
          inicio: normalizeDateTimeLocal(i2),
          fin: addMinutesToDateTime(i2, d2)
        },
        {
          inicio: normalizeDateTimeLocal(i3),
          fin: addMinutesToDateTime(i3, d3)
        }
      ];

      if (!i1 || !i2 || !i3) {
        toastErr('Debes capturar las 3 fechas de inicio.');
        return;
      }

      if (slots.some(s => !s.inicio || !s.fin)) {
        toastErr('No se pudieron calcular correctamente las fechas del Meet.');
        return;
      }

      if (!plat) {
        toastErr('Captura la plataforma del Meet.');
        return;
      }
      if (!motivo) {
        toastErr('Captura el motivo del Meet.');
        return;
      }
      if (!enlace) {
        toastErr('Captura el enlace del Meet.');
        return;
      }

      try {
        hint.textContent = 'Enviando propuestas...';

        await apiFetch('api/meet_create.php', {
          method: 'POST',
          body: {
            tiId,
            modo: 'remoto',
            plataforma: document.getElementById('meetPlat').value || '',
            enlace: normalizeUrl(document.getElementById('meetLink').value),
            motivo: document.getElementById('meetMotivo').value || '',
            slots
          }
        });

        hint.textContent = '';
        toastOk('Propuestas enviadas.');
        await openMeet(tiId);
        await loadTickets();
      } catch (e) {
        hint.textContent = '';
        toastErr(e.message || 'No se pudieron enviar las propuestas.');
      }
    });

    document.getElementById('btnReloadMeet').addEventListener('click', async () => {
      await openMeet(tiId);
    });

    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offMeet')).show();
  } catch (e) {
    toastErr(e.message || 'No se pudo abrir meet.');
  }
}

/* ===========================
   Bind UI superior
   =========================== */

function bindUI() {
  document.querySelectorAll('#vistaToggle [data-vista]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#vistaToggle [data-vista]').forEach(x => x.classList.remove('active'));
      btn.classList.add('active');
      state.vista = btn.dataset.vista;
      renderTickets();
    });
  });

  document.querySelectorAll('#scopeToggle [data-scope]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#scopeToggle [data-scope]').forEach(x => x.classList.remove('active'));
      btn.classList.add('active');
      state.scope = btn.dataset.scope;
      resetAllSedePages();
      renderTickets();
    });
  });

  document.querySelectorAll('#estadoToggle [data-estado]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#estadoToggle [data-estado]').forEach(x => x.classList.remove('active'));
      btn.classList.add('active');
      state.estado = btn.dataset.estado;
      resetAllSedePages();
      renderTickets();
    });
  });
  
  const searchInput = document.getElementById('searchTickets');
  if (searchInput) {
    searchInput.addEventListener('input', e => {
      state.search = e.target.value || '';
      resetAllSedePages();
      renderTickets();
    });
  }

  const btnClear = document.getElementById('btnClear');
  if (btnClear) {
    btnClear.addEventListener('click', () => {
      state.search = '';
      const input = document.getElementById('searchTickets');
      if (input) input.value = '';
      resetAllSedePages();
      renderTickets();
    });
  }

  const btnReload = document.getElementById('btnReload');
  if (btnReload) {
    btnReload.addEventListener('click', loadTickets);
  }

  const btnResetFilters = document.getElementById('btnResetFilters');
  if (btnResetFilters) {
    btnResetFilters.addEventListener('click', () => {
      state.vista = 'tabla';
      state.scope = 'todo';
      state.estado = 'all';
      state.search = '';
      state.perPage = 20;
      resetAllSedePages();

      const search = document.getElementById('searchTickets');
      if (search) search.value = '';

      const perPageSelect = document.getElementById('perPageSelect');
      if (perPageSelect) perPageSelect.value = '20';

      document.querySelectorAll('#vistaToggle [data-vista]').forEach(x => x.classList.remove('active'));
      document.querySelector('#vistaToggle [data-vista="tabla"]')?.classList.add('active');

      document.querySelectorAll('#scopeToggle [data-scope]').forEach(x => x.classList.remove('active'));
      document.querySelector('#scopeToggle [data-scope="todo"]')?.classList.add('active');

      document.querySelectorAll('#estadoToggle [data-estado]').forEach(x => x.classList.remove('active'));
      document.querySelector('#estadoToggle [data-estado="all"]')?.classList.add('active');

      renderTickets();
    });
  }

  const perPageSelect = document.getElementById('perPageSelect');
  if (perPageSelect) {
    perPageSelect.value = String(state.perPage);
    perPageSelect.addEventListener('change', (e) => {
      const value = Number(e.target.value || 20);
      state.perPage = value > 0 ? value : 20;
      resetAllSedePages();
      renderTickets();
    });
  }
}

/* ===========================
   Init
   =========================== */

document.addEventListener('DOMContentLoaded', async () => {
  bindUI();
  await loadTickets();
});