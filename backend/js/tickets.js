function getCsrf() {
    // ajusta aquí el nombre REAL que estás imprimiendo en PHP
    return (window.MRS_CSRF && window.MRS_CSRF.csrf) ? window.MRS_CSRF.csrf : '';
}

async function apiFetch(url, { method = 'GET', body = null, headers = {} } = {}) {
    const csrf = getCsrf();

    const h = new Headers(headers);

    // 🔴 mandamos CSRF por HEADER
    h.set('X-CSRF-Token', csrf);

    // 🔴 y también por body JSON como fallback (sin cambiar proyecto)
    let realBody = body;

    if (method !== 'GET' && body && typeof body === 'object' && !(body instanceof FormData)) {
        h.set('Content-Type', 'application/json');
        realBody = JSON.stringify({ ...body, csrf_token: csrf });
    }

    const res = await fetch(url, {
        method,
        credentials: 'include',
        headers: h,
        body: method === 'GET' ? null : realBody,
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) {
        throw new Error(json.error || 'Error API');
    }
    return json;
}

function csrf() {
    return (window.MRS_CSRF && window.MRS_CSRF.csrf) ? window.MRS_CSRF.csrf : '';
}

async function postForm(url, dataObj) {
    const fd = new FormData();
    fd.append('csrf_token', csrf());

    for (const [k, v] of Object.entries(dataObj || {})) {
        if (typeof v === 'object') fd.append(k, JSON.stringify(v));
        else fd.append(k, String(v ?? ''));
    }

    const res = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        body: fd
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) throw new Error(json.error || 'Error');
    return json;
}

// -------------------------
// STATE
// -------------------------
const state = {
    vista: 'tabla', // tabla | cards
    scope: 'todo', // todo | acciones | recientes
    estado: 'Abierto', // Abierto | Pospuesto | all
    search: '',
    sedes: [],
    meta: {
        clNombre: '',
        total: 0
    },
    recientes: new Set(),
};

// -------------------------
// STEPS (tu flujo real)
// -------------------------
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

// Pasos donde el cliente participa (para mostrar "esperando cliente" en Admin)
const CLIENT_STEPS = new Set(['logs', 'meet', 'visita', 'encuesta satisfaccion']);

// Pasos típicos que ejecuta el Admin (acción directa en Admin)
const ADMIN_STEPS = new Set([
    'asignacion',
    'revision inicial',
    'revision especial',
    'espera refaccion',
    'fecha asignada',
    'espera ventana',
    'espera visita',
    'en camino',
    'espera documentacion',
    'finalizado',
    'cancelado',
    'fuera de alcance',
    'servicio por evento'
]);


function normalizeStep(raw) {
    const s = (raw || '').toString().trim().toLowerCase();
    if (!s) return 'asignacion';

    if (s.includes('asign')) return 'asignacion';
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

function stepIndex(step) {
    const idx = STEPS.indexOf(step);
    return idx >= 0 ? idx : 0;
}

function stepProgress(step) {
    const idx = stepIndex(step);
    const total = STEPS.length;
    const done = Math.max(0, Math.min(idx, total));
    const pct = Math.round((done / total) * 100);
    return {
        idx,
        total,
        done,
        pct
    };
}

function currentActionForStep(step) {
    if (step === 'logs') return {
        key: 'logs',
        title: 'Subir Logs',
        required: true
    };
    if (step === 'meet') return {
        key: 'meet',
        title: 'Confirmar Meet',
        required: false
    };
    if (step === 'visita') return {
        key: 'visita',
        title: 'Confirmar Visita',
        required: true
    };
    if (step === 'encuesta satisfaccion') return {
        key: 'encuesta',
        title: 'Responder Encuesta',
        required: false
    };
    return null;
}

function currentAdminActionForStep(step, t) {
    // Puedes usar t.tiExtra / flags si luego lo requieres.
    // Por ahora: mapeo directo por paso.

    // --- pasos del cliente: el admin NO ejecuta, pero sí gestiona ---
    if (step === 'logs') {
        return {
            key: 'admin_logs',
            title: 'Notificar / recordar logs',
            required: true,
            mode: 'admin_wait_client', // usado para UI
        };
    }

    if (step === 'revision especial') {
        return {
            key: 'admin_revision_especial',
            title: 'Validar logs / solicitar corrección',
            required: true,
            mode: 'admin_wait_client', // usado para UI
        };
    }

    if (step === 'meet') {
        return {
            key: 'meet',
            title: 'Definir / confirmar Meet',
            required: false,
            mode: 'admin_action',
        };
    }

    if (step === 'visita') {
        return {
            key: 'admin_visita',
            title: 'Crear visita / confirmar preparación',
            required: true,
            mode: 'admin_action',
        };
    }

    if (step === 'encuesta satisfaccion') {
        return {
            key: 'admin_encuesta',
            title: 'Revisar encuesta / cerrar ciclo',
            required: false,
            mode: 'admin_action',
        };
    }

    // --- pasos del admin ---
    if (step === 'asignacion') {
        return {
            key: 'asignar_ingeniero',
            title: 'Asignar ingeniero',
            required: true,
            mode: 'admin_action'
        };
    }

    if (step === 'revision inicial') {
        return {
            key: 'revision_inicial',
            title: 'Registrar revisión inicial',
            required: true,
            mode: 'admin_action'
        };
    }

    if (step === 'espera refaccion') {
        return {
            key: 'refaccion',
            title: 'Gestionar refacción (estatus)',
            required: false,
            mode: 'admin_action'
        };
    }

    if (step === 'fecha asignada') {
        return {
            key: 'fecha_asignada',
            title: 'Asignar fecha de visita',
            required: true,
            mode: 'admin_action'
        };
    }

    if (step === 'espera ventana') {
        return {
            key: 'ventana',
            title: 'Asignar / proponer ventana',
            required: true,
            mode: 'admin_action'
        };
    }

    if (step === 'espera visita') {
        return {
            key: 'espera_visita',
            title: 'Confirmar que visita está lista',
            required: true,
            mode: 'admin_action'
        };
    }

    if (step === 'en camino') {
        return {
            key: 'en_camino',
            title: 'Marcar “En camino”',
            required: true,
            mode: 'admin_action'
        };
    }

    if (step === 'espera documentacion') {
        return {
            key: 'documentacion',
            title: 'Cargar / validar documentación',
            required: true,
            mode: 'admin_action'
        };
    }

    // terminales / especiales (admin)
    if (step === 'finalizado') {
        return {
            key: 'finalizado',
            title: 'Cerrar ticket (finalizado)',
            required: false,
            mode: 'admin_action'
        };
    }
    if (step === 'cancelado') {
        return {
            key: 'cancelado',
            title: 'Confirmar cancelación',
            required: false,
            mode: 'admin_action'
        };
    }
    if (step === 'fuera de alcance') {
        return {
            key: 'fuera_alcance',
            title: 'Marcar fuera de alcance',
            required: false,
            mode: 'admin_action'
        };
    }
    if (step === 'servicio por evento') {
        return {
            key: 'servicio_evento',
            title: 'Convertir a servicio por evento',
            required: false,
            mode: 'admin_action'
        };
    }

    // fallback
    return null;
}


function ownerForStep(step) {
    return ADMIN_STEPS.has(step) ? 'Cliente' : 'Administrador';
}

// -------------------------
// Recientes (localStorage)
// -------------------------
function loadRecientes() {
    try {
        const raw = localStorage.getItem('mrs_admin_recientes_' + CL_ID) || '[]';
        const arr = JSON.parse(raw);
        state.recientes = new Set(arr.map(Number).filter(Boolean));
    } catch (e) {
        state.recientes = new Set();
    }
}

function saveRecientes() {
    try {
        const arr = Array.from(state.recientes).slice(0, 80);
        localStorage.setItem('mrs_admin_recientes_' + CL_ID, JSON.stringify(arr));
    } catch (e) { }
}

function markVisto(tiId) {
    state.recientes.add(Number(tiId));
    saveRecientes();
}

// -------------------------
// Helpers UI
// -------------------------
function escapeHtml(s) {
    return (s ?? '').toString()
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
        .replaceAll("'", "&#039;");
}

function clientePrefix(nombre) {
    if (!nombre) return 'UNK';
    return nombre.normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^A-Za-z]/g, "")
        .substring(0, 3).toUpperCase();
}

function badgeEstado(estatus) {
    const e = (estatus || '').toLowerCase();
    if (e === 'abierto') return 'badge-pill-soft bg-success-subtle text-success-emphasis';
    if (e === 'pospuesto') return 'badge-pill-soft bg-warning-subtle text-warning-emphasis';
    if (e === 'cerrado') return 'badge-pill-soft bg-secondary-subtle text-secondary-emphasis';
    return 'badge-pill-soft bg-light text-body-secondary';
}

function critClass(n) {
    const c = String(n || '3');
    if (c === '1') return 'row-crit-1';
    if (c === '2') return 'row-crit-2';
    return 'row-crit-3';
}

// OJO: aquí hago match con tu screenshot (Nivel 3 rojo, 2 amarillo, 1 gris)
function critBadge(n) {
    const c = String(n || '3');
    if (c === '3') return '<span class="badge text-bg-danger">Nivel 3</span>';
    if (c === '2') return '<span class="badge text-bg-warning">Nivel 2</span>';
    return '<span class="badge text-bg-secondary">Nivel 1</span>';
}

function accionesDeTicket(t) {
    const step = normalizeStep(t.tiProceso);
    const a = currentAdminActionForStep(step, t);
    if (!a) return [];

    // Botones (1-2 máximo) para Admin
    if (a.key === 'ventana') {
        return [{
            label: 'Asignar Ventana',
            kind: 'success',
            action: 'ventana_asignar'
        },
        {
            label: 'Proponer Ventana',
            kind: 'outline',
            action: 'ventana_proponer'
        }
        ];
    }
    if (a.key === 'admin_logs') {
        return [{
            label: 'Notificar Logs',
            kind: 'primary',
            action: 'logs_revisar'
        },];
    }
    if (a.key === 'admin_revision_especial') {
        return [{
            label: 'Descargar Logs',
            kind: 'primary',
            action: 'logs_revisar'
        },
        {
            label: 'Solicitar Logs',
            kind: 'outline',
            action: 'logs_solicitar'
        },
        {
            label: 'Revisión Especial',
            kind: 'outline',
            action: 'revision_especial'
        }
        ];
    }

    // default: una sola acción principal
    return [{
        label: a.title,
        kind: 'primary',
        action: a.key
    }];
}


function procesoLabel(t) {
    const step = normalizeStep(t.tiProceso);
    return step;
}

function progresoDeProceso(t) {
    const step = normalizeStep(t.tiProceso);
    const p = stepProgress(step);
    return {
        stepName: step,
        ...p
    };
}

function formatDate(iso) {
    const s = (iso || '').toString();
    return s.length >= 10 ? s.substring(0, 10) : '—';
}

// -------------------------
// Fetch
// -------------------------
async function fetchTickets() {
    $('#wrapTickets').html('<div class="muted">Cargando tickets...</div>');
    $('#emptyState').addClass('d-none');

    const url = `api/tickets_por_sede.php?clId=${encodeURIComponent(CL_ID)}`;
    const res = await fetch(url, {
        credentials: 'include',
        cache: 'no-store'
    });

    if (!res.ok) {
        const txt = await res.text();
        $('#wrapTickets').html(`<div class="alert alert-danger">Error al cargar tickets. ${escapeHtml(txt)}</div>`);
        return;
    }

    const json = await res.json();
    if (!json.success) {
        $('#wrapTickets').html(`<div class="alert alert-danger">Error: ${escapeHtml(json.error || 'Desconocido')}</div>`);
        return;
    }

    state.sedes = json.sedes || [];
    state.meta.clNombre = json.clNombre || '';
    state.meta.total = Number(json.count || 0);

    $('#lblCliente').text(state.meta.clNombre || '—');
    $('#lblTotal').text(state.meta.total);

    applyAndRender();
}

// -------------------------
// Offcanvas: Ticket details
// -------------------------
let offTicketInstance = null;

function findTicketById(tiId) {
    for (const s of (state.sedes || [])) {
        for (const t of (s.tickets || [])) {
            if (Number(t.tiId) === Number(tiId)) return t;
        }
    }
    return null;
}

function buildMiniHistory(t) {
    try {
        const extra = t.tiExtra ? JSON.parse(t.tiExtra) : null;
        if (Array.isArray(extra?.history)) {
            return extra.history.slice(-3).reverse().map(x => ({
                title: x.title || 'Evento',
                meta: x.meta || ''
            }));
        }
    } catch (e) { }

    return [{
        title: 'Ticket creado',
        meta: formatDate(t.tiFechaCreacion)
    },
    {
        title: 'Proceso actual',
        meta: normalizeStep(t.tiProceso)
    },
    {
        title: 'Estatus',
        meta: t.tiEstatus || '—'
    }
    ].slice(0, 3);
}

function ticketCodigo(t) {
    const pref = clientePrefix(state.meta.clNombre);
    return `${pref}-${Number(t.tiId)}`;
}

function defaultEquipoImg(t) {
    // Si luego tienes imágenes por eqId o modelo, lo conectamos.
    // Por ahora: placeholder local o una imagen genérica de tu proyecto:
    return '../img/Equipos/' + t.maNombre + '/' + t.eqModelo + '.png';
}

function renderOffcanvas(t) {
    const step = normalizeStep(t.tiProceso);
    console.log('Render offcanvas for ticket', t.tiId, 'step:', step);
    const owner = ownerForStep(step);
    const action = currentAdminActionForStep(step, t);

    // Header (similar a tu mock)
    $('#offImgEquipo').attr('src', defaultEquipoImg(t));
    $('#offCodigo').text(ticketCodigo(t));
    $('#offEquipo').text((t.eqModelo || 'Equipo') + (t.eqVersion ? ' · ' + t.eqVersion : ''));
    $('#offSN').text(t.peSN ? ('SN: ' + t.peSN) : 'SN: —');

    $('#offCrit').html(critBadge(t.tiNivelCriticidad));
    $('#offEstado').html(`<span class="${badgeEstado(t.tiEstatus)}">${escapeHtml(t.tiEstatus)}</span>`);
    $('#offMarca').text(t.maNombre ? t.maNombre : '');

    // Paso/Progreso
    const prog = stepProgress(step);
    $('#offPasoActual').text(`Paso actual: ${step}`);
    $('#offProgressText').text(`${prog.done}/${prog.total}`);
    $('#offProgressBar').css('width', `${prog.pct}%`);

    // Mensaje claro
    const nextStep = STEPS[Math.min(stepIndex(step) + 1, STEPS.length - 1)];
    $('#offMensaje').html(`
      <div><b>Qué está pasando:</b> El ticket está en <b>${escapeHtml(step)}</b>.</div>
      <div><b>Quién tiene la acción:</b> <b>${escapeHtml(owner)}</b>.</div>
      <div><b>Qué sigue:</b> ${escapeHtml(nextStep || '—')}.</div>
    `);

    // Acción única, guiada y clara
    if (action) {
        const req = action.required ?
            '<span class="badge text-bg-danger ms-2">Obligatoria</span>' :
            '<span class="badge text-bg-secondary ms-2">Opcional</span>';

        // Ajuste por acción (layout “tipo screenshot”)
        if (action.key === 'logs') {
            $('#offAccionBox').html(`
          <div class="action-card">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="fw-bold">Acción requerida: ${escapeHtml(action.title)} ${req}</div>
              <div class="muted" style="font-size:.85rem;">Responsable: <b>${escapeHtml(owner)}</b></div>
            </div>

            <div class="mt-2 muted" style="font-size:.9rem;">
              Sube los logs del equipo para continuar con el diagnóstico.
            </div>

            <div class="mt-3 d-flex gap-2">
              <input type="file" id="offFileLogs" class="form-control form-control-sm" multiple>
              <button class="btn btn-primary btn-sm" id="offPrimaryAction" data-action="logs" data-ti="${t.tiId}">
                Subir Logs
              </button>
            </div>

            <div class="muted mt-2" style="font-size:.8rem;">
              Acepta .log, .txt o comprimidos (.zip/.rar). Estado: <b>Pendiente</b>
            </div>

            <div class="mt-2 d-flex gap-2">
              <button class="btn btn-outline-secondary btn-sm" id="offHelpAction" data-action="logs_help">
                ¿Cómo extraer logs?
              </button>
              <button class="btn btn-outline-secondary btn-sm" id="offMailHelp" data-action="mail_help">
                Pedir ayuda por correo
              </button>
            </div>
          </div>
        `);
        } else {
            $('#offAccionBox').html(`
          <div class="action-card">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="fw-bold">Acción requerida: ${escapeHtml(action.title)} ${req}</div>
              <div class="muted" style="font-size:.85rem;">Responsable: <b>${escapeHtml(owner)}</b></div>
            </div>

            <div class="mt-2 muted" style="font-size:.9rem;">
              Esta acción se completa desde este panel.
            </div>

            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary btn-sm flex-grow-1" id="offPrimaryAction" data-action="${escapeHtml(action.key)}" data-ti="${t.tiId}">
                ${escapeHtml(action.title)}
              </button>
              <button class="btn btn-outline-secondary btn-sm" id="offHelpAction" data-action="${escapeHtml(action.key)}_help">
                Ayuda
              </button>
            </div>

            <div class="muted mt-2" style="font-size:.8rem;">Estado: <b>Pendiente</b></div>
          </div>
        `);
        }

        $('#offBtnAccion').text('Continuar').prop('disabled', false);
    } else {
        $('#offAccionBox').html(`
        <div class="action-card">
          <div class="fw-bold">Sin acción del cliente</div>
          <div class="muted mt-2" style="font-size:.9rem;">
            Este paso corresponde al <b>Administrador</b>. El flujo está avanzando internamente.
          </div>
        </div>
      `);
        $('#offBtnAccion').text('Cerrar').prop('disabled', false);
    }

    // Historial corto
    const history = buildMiniHistory(t);
    const $ul = $('#offHistorial');
    $ul.empty();
    history.forEach(h => {
        $ul.append(`
        <li class="list-group-item d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-semibold">${escapeHtml(h.title)}</div>
            <div class="muted" style="font-size:.85rem;">${escapeHtml(h.meta)}</div>
          </div>
        </li>
      `);
    });

    // Footer buttons
    $('#offBtnAyuda').off('click').on('click', () => {
        alert('Ayuda contextual del paso: ' + step);
    });

    $('#offBtnAccion').off('click').on('click', () => {
        if (!action) {
            const el = document.getElementById('offTicket');
            bootstrap.Offcanvas.getInstance(el)?.hide();
            return;
        }
        $('#offPrimaryAction').trigger('click');
    });
}

function openTicketOffcanvasById(tiId) {
    const t = findTicketById(tiId);
    if (!t) return;

    markVisto(t.tiId);
    renderOffcanvas(t);

    const el = document.getElementById('offTicket');
    offTicketInstance = bootstrap.Offcanvas.getOrCreateInstance(el, {
        backdrop: true,
        scroll: false
    });
    offTicketInstance.show();
}

// -------------------------
// FILTROS JS
// -------------------------
function aplicarFiltros() {
    const q = (state.search || '').trim().toLowerCase();
    const prefix = clientePrefix(state.meta.clNombre);
    const sedesFiltradas = [];

    (state.sedes || []).forEach(s => {
        const tickets = (s.tickets || []).filter(t => {
            // estado
            if (state.estado !== 'all') {
                if ((t.tiEstatus || '') !== state.estado) return false;
            }

            // scope
            if (state.scope === 'acciones') {
                if (accionesDeTicket(t).length === 0) return false;
            }
            if (state.scope === 'recientes') {
                if (!state.recientes.has(Number(t.tiId))) return false;
            }

            // búsqueda
            if (q) {
                const modelo = (t.eqModelo || '').toLowerCase();
                const marca = (t.maNombre || '').toLowerCase();
                const sn = (t.peSN || '').toLowerCase();
                const codigo = `${prefix}-${Number(t.tiId) || ''}`.toLowerCase();
                if (!modelo.includes(q) && !marca.includes(q) && !sn.includes(q) && !codigo.includes(q)) return false;
            }

            return true;
        });

        if (tickets.length) sedesFiltradas.push({
            ...s,
            tickets
        });
    });

    return sedesFiltradas;
}

function applyAndRender() {
    const sedes = aplicarFiltros();
    const totalVisibles = sedes.reduce((acc, s) => acc + (s.tickets || []).length, 0);

    if (totalVisibles === 0) {
        $('#wrapTickets').html('');
        $('#emptyState').removeClass('d-none');
        return;
    }

    $('#emptyState').addClass('d-none');

    if (state.vista === 'cards') renderCards(sedes);
    else renderTabla(sedes);
}

// -------------------------
// RENDER TABLA
// -------------------------
function renderTabla(sedes) {
    const wrap = $('#wrapTickets');
    wrap.empty();

    sedes.forEach(s => {
        const sedeTitle = `${escapeHtml(state.meta.clNombre)} · ${escapeHtml(s.csNombre)}`;
        const count = (s.tickets || []).length;

        const block = $(`
        <div class="mb-4">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="fw-bold">${sedeTitle}</div>
            <div class="muted" style="font-size:.85rem;">${count} ticket(s)</div>
          </div>

          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th style="width:120px;"># Ticket</th>
                  <th style="width:120px;">Estado</th>
                  <th style="width:220px;">Proceso actual</th>
                  <th>Información del equipo</th>
                  <th style="width:120px;">Criticidad</th>
                  <th style="width:160px;">Fecha</th>
                  <th style="width:240px;">Acciones</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      `);

        const tbody = block.find('tbody');
        const prefix = clientePrefix(state.meta.clNombre);

        (s.tickets || []).forEach(t => {
            const codigo = `${prefix}-${Number(t.tiId)}`;
            const acciones = accionesDeTicket(t);
            const proc = progresoDeProceso(t);

            const btns = acciones.length ?
                acciones.map(a => {
                    const cls = a.kind === 'primary' ? 'btn btn-sm btn-outline-primary' :
                        a.kind === 'success' ? 'btn btn-sm btn-outline-success' :
                            'btn btn-sm btn-outline-secondary';
                    return `<button class="${cls} me-1 btnAccion" data-ti="${t.tiId}" data-action="${a.action}">${escapeHtml(a.label)}</button>`;
                }).join('') :
                `<span class="muted">—</span>`;

            const tr = $(`
          <tr class="${critClass(t.tiNivelCriticidad)} ticket-row" data-ti="${t.tiId}">
            <td class="fw-bold">${escapeHtml(codigo)}</td>
            <td><span class="${badgeEstado(t.tiEstatus)}">${escapeHtml(t.tiEstatus)}</span></td>
            <td class="muted">
              <div>${escapeHtml(procesoLabel(t))}</div>
              <div class="muted" style="font-size:.8rem;">${proc.done}/${proc.total}</div>
            </td>
            <td>
              <div class="fw-semibold">${escapeHtml(t.eqModelo || 'Equipo')}</div>
              <div class="muted" style="font-size:.85rem;">
                ${escapeHtml(t.maNombre || '')} ${escapeHtml(t.eqVersion || '')}
                ${t.peSN ? `· SN: ${escapeHtml(t.peSN)}` : ''}
              </div>
            </td>
            <td>${critBadge(t.tiNivelCriticidad)}</td>
            <td class="muted" style="font-size:.9rem;">${escapeHtml(formatDate(t.tiFechaCreacion))}</td>
            <td>${btns}</td>
          </tr>
        `);

            tr.css('cursor', 'pointer');
            tbody.append(tr);
        });

        wrap.append(block);
    });
}

// -------------------------
// RENDER CARDS
// -------------------------
function renderCards(sedes) {
    const wrap = $('#wrapTickets');
    wrap.empty();

    sedes.forEach(s => {
        const sedeTitle = `${escapeHtml(state.meta.clNombre)} · ${escapeHtml(s.csNombre)}`;
        const count = (s.tickets || []).length;

        wrap.append(`
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="fw-bold">${sedeTitle}</div>
          <div class="muted" style="font-size:.85rem;">${count} ticket(s)</div>
        </div>
      `);

        const row = $('<div class="row g-3 mb-4"></div>');
        const prefix = clientePrefix(state.meta.clNombre);

        (s.tickets || []).forEach(t => {
            const codigo = `${prefix}-${Number(t.tiId)}`;
            const acciones = accionesDeTicket(t);
            const proc = progresoDeProceso(t);

            const btns = acciones.length ?
                acciones.map(a => {
                    const cls = a.kind === 'primary' ? 'btn btn-sm btn-outline-primary' :
                        a.kind === 'success' ? 'btn btn-sm btn-outline-success' :
                            'btn btn-sm btn-outline-secondary';
                    return `<button class="${cls} me-1 btnAccion" data-ti="${t.tiId}" data-action="${a.action}">${escapeHtml(a.label)}</button>`;
                }).join('') :
                `<span class="muted">—</span>`;

            row.append(`
          <div class="col-12 col-md-6 col-xl-4">
            <div class="ticket-card ${critClass(t.tiNivelCriticidad)}" data-ti="${t.tiId}" style="cursor:pointer;">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div class="d-flex gap-2 flex-wrap">
                  <span class="${badgeEstado(t.tiEstatus)}">${escapeHtml(t.tiEstatus)}</span>
                  ${critBadge(t.tiNivelCriticidad)}
                </div>
                <div class="fw-bold">${escapeHtml(codigo)}</div>
              </div>

              <div class="fw-semibold">${escapeHtml(t.eqModelo || 'Equipo')}</div>
              <div class="muted" style="font-size:.85rem;">
                ${escapeHtml(t.maNombre || '')} ${escapeHtml(t.eqVersion || '')}
                ${t.peSN ? `· SN: ${escapeHtml(t.peSN)}` : ''}
              </div>

              <div class="mt-2">
                <div class="muted" style="font-size:.85rem;"><b>Paso actual:</b> ${escapeHtml(procesoLabel(t))}</div>
                <div class="progress mt-2">
                  <div class="progress-bar" style="width:${proc.pct}%"></div>
                </div>
                <div class="muted mt-1" style="font-size:.8rem;">${proc.done}/${proc.total}</div>
              </div>

              <div class="mt-3">
                ${btns}
              </div>
            </div>
          </div>
        `);
        });

        wrap.append(row);
    });
}

// -------------------------
// UI EVENTS
// -------------------------
$('#vistaToggle').on('click', 'button[data-vista]', function () {
    $('#vistaToggle button[data-vista]').removeClass('active');
    $(this).addClass('active');
    state.vista = $(this).data('vista');
    applyAndRender();
});

$('#btnReload').on('click', fetchTickets);

$('#tabScope').on('click', 'button[data-scope]', function () {
    $('#tabScope button').removeClass('active');
    $(this).addClass('active');
    state.scope = $(this).data('scope');
    applyAndRender();
});

$('#tabEstado').on('click', 'button[data-estado]', function () {
    $('#tabEstado button').removeClass('active');
    $(this).addClass('active');
    state.estado = $(this).data('estado');
    applyAndRender();
});

$('#searchTickets').on('input', function () {
    state.search = $(this).val() || '';
    applyAndRender();
});

$('#btnClear').on('click', function () {
    state.search = '';
    $('#searchTickets').val('');
    applyAndRender();
});

function resetAll() {
    state.vista = 'tabla';
    state.scope = 'todo';
    state.estado = 'Abierto';
    state.search = '';

    $('#vistaToggle button[data-vista]').removeClass('active');
    $('#vistaToggle button[data-vista="tabla"]').addClass('active');

    $('#tabScope button').removeClass('active');
    $('#tabScope button[data-scope="todo"]').addClass('active');

    $('#tabEstado button').removeClass('active');
    $('#tabEstado button[data-estado="Abierto"]').addClass('active');

    $('#searchTickets').val('');
    applyAndRender();
}
$('#btnReset, #btnReset2').on('click', resetAll);

// Click fila completa => offcanvas
$(document).on('click', '.ticket-row', function (e) {
    if ($(e.target).closest('button, a, input, label').length) return;
    const tiId = Number($(this).data('ti'));
    if (tiId) openTicketOffcanvasById(tiId);
});

// Click card completo => offcanvas
$(document).on('click', '.ticket-card', function (e) {
    if ($(e.target).closest('button, a, input, label').length) return;
    const tiId = Number($(this).data('ti'));
    if (tiId) openTicketOffcanvasById(tiId);
});

// Click botones de acción => abre offcanvas + enfoca acción
$(document).on('click', '.btnAccion', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const tiId = Number($(this).data('ti'));
    const action = String($(this).data('action') || '');

    openTicketOffcanvasById(tiId);

    setTimeout(() => {
        const btn = document.getElementById('offPrimaryAction');
        if (btn) btn.focus();
    }, 150);
});

// Acciones principales dentro del offcanvas (placeholder)
$(document).on('click', '#offPrimaryAction', function () {
    const tiId = Number($(this).data('ti'));
    const tifolio = ticketCodigo(findTicketById(tiId));
    const action = String($(this).data('action') || '');

    if (action === 'admin_logs') {

        solicitarLogs(tiId, tifolio);
        return;
    }
    if (action === 'asignar_ingeniero') {
        openAsignarIngenieroOffcanvas(tiId);
        cambiarEstadoTicket(tiId, 'Asignación en proceso');
        return;
    }

    if (action === 'revision_inicial') {
        openRevisionInicialOffcanvas(tiId);
        return;
    }


    if (action === 'meet') {
        openMeetOffcanvasByTicketId(tiId);
        return;
    }
    if (action === 'visita') {
        alert('Abrir flujo CONFIRMAR VISITA para tiId ' + tiId);
        return;
    }
    if (action === 'encuesta') {
        alert('Abrir flujo ENCUESTA para tiId ' + tiId);
        return;
    }
});

$(document).on('click', '#offHelpAction', function () {
    alert('Abrir ayuda guiada del paso actual.');
});

$(document).on('click', '#offMailHelp', function () {
    alert('Abrir “Pedir ayuda por correo” (placeholder).');
});

// ==============================
// OFFCANVAS ASIGNAR INGENIERO
// ==============================

const API_INGENIEROS = 'api/obtener_ingenieros.php';
const API_ASIGNAR = 'api/asignar_ingeniero.php';

let offAsignarIngInstance = null;
let asgContext = {
    tiId: 0,
    ticket: null,
    ingenieros: [],
    search: '',
    filtroTier: '',
    filtroExperto: ''
};

// abre el offcanvas y carga ingenieros
async function openAsignarIngenieroOffcanvas(tiId) {
    const t = findTicketById(tiId);
    if (!t) return;

    asgContext.tiId = Number(tiId);
    asgContext.ticket = t;
    asgContext.search = '';
    asgContext.filtroTier = '';
    asgContext.filtroExperto = '';

    // header
    const pref = clientePrefix(state.meta.clNombre);
    $('#asgTicketCodigo').text(`${pref}-${Number(t.tiId)}`);
    $('#asgTicketEquipo').text((t.eqModelo || 'Equipo') + (t.eqVersion ? ' · ' + t.eqVersion : ''));
    $('#asgTicketSN').text(t.peSN ? ('SN: ' + t.peSN) : 'SN: —');
    $('#asgTicketPaso').text('Paso: Asignación');
    $('#asgTicketEstado').text(t.tiEstatus || '—');

    $('#asgSearch').val('');
    $('#asgFiltroTier').val('');
    $('#asgFiltroExperto').val('');

    $('#asgWrap').html('');
    $('#asgEmpty').addClass('d-none');
    $('#asgLoading').removeClass('d-none');

    const el = document.getElementById('offAsignarIng');
    offAsignarIngInstance = bootstrap.Offcanvas.getOrCreateInstance(el, {
        backdrop: true,
        scroll: false
    });
    offAsignarIngInstance.show();

    await fetchIngenieros();
    buildExpertoOptions(asgContext.ingenieros);
    renderIngenierosAsignacion();
}

async function fetchIngenieros() {
    try {
        const res = await fetch(API_INGENIEROS, {
            cache: 'no-store',
            credentials: 'include'
        });
        if (!res.ok) {
            const txt = await res.text();
            $('#asgLoading').addClass('d-none');
            $('#asgWrap').html(`<div class="alert alert-danger">Error cargando ingenieros: ${escapeHtml(txt)}</div>`);
            return;
        }
        const json = await res.json();
        if (!json.success) {
            $('#asgLoading').addClass('d-none');
            $('#asgWrap').html(`<div class="alert alert-danger">Error: ${escapeHtml(json.error || 'Desconocido')}</div>`);
            return;
        }
        asgContext.ingenieros = Array.isArray(json.ingenieros) ? json.ingenieros : [];
        $('#asgLoading').addClass('d-none');
    } catch (e) {
        $('#asgLoading').addClass('d-none');
        $('#asgWrap').html(`<div class="alert alert-danger">Error: ${escapeHtml(e.message || e)}</div>`);
    }
}

function buildExpertoOptions(ingenieros) {
    const set = new Set();
    ingenieros.forEach(i => {
        if (i.ingExperto) set.add(String(i.ingExperto));
    });
    const arr = Array.from(set).sort((a, b) => a.localeCompare(b));

    const $sel = $('#asgFiltroExperto');
    $sel.html('<option value="">Experto: Todos</option>');
    arr.forEach(x => {
        $sel.append(`<option value="${escapeHtml(x)}">${escapeHtml(x)}</option>`);
    });
}

function normalizeText(s) {
    return (s || '').toString().trim().toLowerCase();
}

function filtrarIngenieros() {
    const q = normalizeText(asgContext.search);
    const tier = asgContext.filtroTier;
    const experto = asgContext.filtroExperto;

    return (asgContext.ingenieros || []).filter(i => {
        if (tier && i.ingTier !== tier) return false;
        if (experto && i.ingExperto !== experto) return false;

        if (q) {
            const hay = [
                i.usNombre, i.usAPaterno, i.usUsername, i.usCorreo,
                i.ingExperto, i.ingTier, i.ingDescripcion
            ].some(v => normalizeText(v).includes(q));
            if (!hay) return false;
        }
        return true;
    });
}

function groupByTier(list) {
    const map = {
        'Tier 1': [],
        'Tier 2': [],
        'Tier 3': []
    };
    list.forEach(i => {
        const t = i.ingTier || 'Tier 3';
        if (!map[t]) map[t] = [];
        map[t].push(i);
    });
    return map;
}

function ingAvatar(usId) {
    // Ajusta si tu ruta real cambia:
    // ejemplo que ya manejas: /img/Ingeniero/idUsIng.svg
    return `../img/Ingeniero/${Number(usId)}.svg`;
}

function renderIngenierosAsignacion() {
    const list = filtrarIngenieros();
    const groups = groupByTier(list);

    $('#asgWrap').empty();
    $('#asgEmpty').toggleClass('d-none', list.length !== 0);

    // Orden fijo Tier 1, Tier 2, Tier 3
    ['Tier 1', 'Tier 2', 'Tier 3'].forEach(tier => {
        const arr = groups[tier] || [];
        if (arr.length === 0) return;

        const $section = $(`
      <div class="mb-4">
        <div class="fw-bold mb-2">${escapeHtml(tier)}</div>
        <div class="row g-3" id="grid_${tier.replace(' ', '_')}"></div>
      </div>
    `);

        const $grid = $section.find('div.row');

        arr.forEach(i => {
            const fullName = `${i.usNombre || ''} ${i.usAPaterno || ''}`.trim() || 'Ingeniero';
            const experto = i.ingExperto || 'General';
            const desc = i.ingDescripcion || `Ingeniero experto en ${experto}`;
            const tel = i.usTelefono || '—';
            const user = i.usUsername || '—';
            const mail = i.usCorreo || '—';

            // badge “experto”
            const badge = `<span class="badge rounded-pill text-bg-light border">${escapeHtml(experto)}</span>`;

            const card = $(`
        <div class="col-12 col-lg-6">
          <div class="p-3 border rounded-4 h-100" style="background:#fff;">
            <div class="d-flex gap-3">
              <div style="width:86px; height:86px; border-radius:16px; overflow:hidden; background:#f1f5f9; flex:0 0 auto;">
                <img src="${ingAvatar(i.usId)}" alt="Ing" style="width:100%; height:100%; object-fit:cover;"
                  onerror="this.onerror=null;this.src='../img/avatar_default.png';">
              </div>

              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div class="fw-bold">${escapeHtml(fullName)}</div>
                  ${badge}
                </div>

                <div class="text-muted" style="font-size:.9rem;">
                  <div class="d-flex align-items-center gap-2"><i class="bi bi-person"></i> ${escapeHtml(user)}</div>
                  <div class="d-flex align-items-center gap-2"><i class="bi bi-envelope"></i> ${escapeHtml(mail)}</div>
                  <div class="d-flex align-items-center gap-2"><i class="bi bi-telephone"></i> ${escapeHtml(tel)}</div>
                  <div class="d-flex align-items-center gap-2"><i class="bi bi-shield-check"></i> ${escapeHtml(desc)}</div>
                </div>

                <div class="mt-3 d-flex gap-2">
                  <button class="btn btn-success btn-sm px-3 btnAsignarIng"
                    data-usid="${Number(i.usId)}"
                    data-name="${escapeHtml(fullName)}">
                    Asignar
                  </button>

                  <button class="btn btn-dark btn-sm px-3 btnVerMasIng"
                    data-usid="${Number(i.usId)}">
                    Ver más
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      `);

            $grid.append(card);
        });

        $('#asgWrap').append($section);
    });
}

// eventos filtros/búsqueda
$('#asgSearch').on('input', function () {
    asgContext.search = $(this).val() || '';
    renderIngenierosAsignacion();
});
$('#asgClear').on('click', function () {
    asgContext.search = '';
    $('#asgSearch').val('');
    renderIngenierosAsignacion();
});
$('#asgFiltroTier').on('change', function () {
    asgContext.filtroTier = $(this).val() || '';
    renderIngenierosAsignacion();
});
$('#asgFiltroExperto').on('change', function () {
    asgContext.filtroExperto = $(this).val() || '';
    renderIngenierosAsignacion();
});

// ver más (placeholder)
$(document).on('click', '.btnVerMasIng', function (e) {
    e.preventDefault();
    const usId = Number($(this).data('usid'));
    alert('Aquí abrimos "Ver más" del ingeniero usId=' + usId + ' (lo conectamos después).');
});

// asignar ingeniero (POST a tu php ya existente)
$(document).on('click', '.btnAsignarIng', async function (e) {
    e.preventDefault();

    const usIdIng = Number($(this).data('usid'));
    const name = String($(this).data('name') || 'Ingeniero');

    const tiId = Number(asgContext.tiId);
    if (!tiId || !usIdIng) return;

    if (!confirm(`¿Asignar a ${name} al ticket?`)) return;

    const fd = new FormData();
    fd.append('tiId', String(tiId));
    fd.append('usIdIng', String(usIdIng));
    fd.append('nextProceso', 'revision inicial'); // tu regla

    try {
        // feedback
        $(this).prop('disabled', true).text('Asignando…');

        const res = await fetch(API_ASIGNAR, {
            method: 'POST',
            body: fd,
            credentials: 'include'
        });
        const json = await res.json().catch(() => null);

        if (!res.ok || !json || !json.success) {
            const err = (json && json.error) ? json.error : 'Error asignando ingeniero';
            alert(err);
            $(this).prop('disabled', false).text('Asignar');
            return;
        }

        // ✅ actualizar ticket en memoria para reflejar UI
        const t = findTicketById(tiId);
        if (t) {
            t.usIdIng = usIdIng;
            t.tiProceso = 'revision inicial';
        }

        // cerrar offcanvas asignación
        const el = document.getElementById('offAsignarIng');
        bootstrap.Offcanvas.getInstance(el)?.hide();

        // refrescar UI principal
        applyAndRender();

        // re-render del offTicket si está abierto
        // (si tienes una variable de ticket abierto, úsala; si no, solo lo abrimos de nuevo)
        openTicketOffcanvasById(tiId);

    } catch (err) {
        alert('Error: ' + (err.message || err));
        $(this).prop('disabled', false).text('Asignar');
    }
});

const API_GUARDAR_ANALISIS = 'api/guardar_analisis.php';

let offRevisionInstance = null;
let revCtx = {
    tiId: 0
};

// Abre offcanvas y precarga datos del ticket
function openRevisionInicialOffcanvas(tiId) {
    const t = findTicketById(tiId);
    if (!t) return;

    revCtx.tiId = Number(tiId);

    const pref = clientePrefix(state.meta.clNombre);
    $('#revCodigo').text(`${pref}-${Number(t.tiId)}`);
    $('#revEquipo').text((t.eqModelo || 'Equipo') + (t.eqVersion ? ' · ' + t.eqVersion : ''));
    $('#revSN').text(t.peSN ? ('SN: ' + t.peSN) : 'SN: —');

    $('#revCrit').html(critBadge(t.tiNivelCriticidad));
    $('#revEstado').html(`<span class="${badgeEstado(t.tiEstatus)}">${escapeHtml(t.tiEstatus)}</span>`);
    $('#revPaso').text('Paso: Revisión inicial');

    // Si ya hay diagnóstico previo, lo mostramos (si tu API lo trae)
    $('#revDiagnostico').val((t.tiDiagnostico || '').toString());
    $('#revNext').val('logs');

    updateRevCount();
    $('#revMsg').html('');

    const el = document.getElementById('offRevisionInicial');
    offRevisionInstance = bootstrap.Offcanvas.getOrCreateInstance(el, {
        backdrop: true,
        scroll: false
    });
    offRevisionInstance.show();

    // foco para escribir rápido
    setTimeout(() => document.getElementById('revDiagnostico')?.focus(), 150);
}

// contador
function updateRevCount() {
    const v = ($('#revDiagnostico').val() || '').toString();
    $('#revCount').text(v.length);
    if (v.length > 1200) {
        $('#revCount').addClass('text-danger');
    } else {
        $('#revCount').removeClass('text-danger');
    }
}
$('#revDiagnostico').on('input', updateRevCount);

// templates
$('#revTplFaltan').on('click', function () {
    $('#revDiagnostico').val('Faltan datos');
    updateRevCount();
    $('#revDiagnostico').focus();
});

$('#revTplChecklist').on('click', function () {
    const tpl =
        `• Síntomas reportados:
• Evidencia disponible:
• Hipótesis inicial:
• Información faltante:
• Siguiente paso recomendado:`;
    $('#revDiagnostico').val(tpl);
    updateRevCount();
    $('#revDiagnostico').focus();
});

$('#revLimpiar').on('click', function () {
    $('#revDiagnostico').val('');
    updateRevCount();
    $('#revDiagnostico').focus();
});

// Guardar
$('#revGuardar').on('click', async function () {
    const tiId = Number(revCtx.tiId);
    if (!tiId) return;

    const diag = ($('#revDiagnostico').val() || '').toString().trim();
    const next = ($('#revNext').val() || 'logs').toString();

    // UX: si viene vacío, dejamos que backend ponga "Faltan datos",
    // pero damos un micro-aviso para evitar “guardé en blanco”
    const payload = new FormData();
    payload.append('tiId', String(tiId));
    payload.append('tiDiagnostico', diag);
    payload.append('nextProceso', next);

    $('#revMsg').html('');
    const $btn = $(this);
    $btn.prop('disabled', true).text('Guardando…');

    try {
        const res = await fetch(API_GUARDAR_ANALISIS, {
            method: 'POST',
            body: payload,
            credentials: 'include'
        });
        const json = await res.json().catch(() => null);

        if (!res.ok || !json || !json.success) {
            const err = (json && json.error) ? json.error : 'Error guardando análisis';
            $('#revMsg').html(`<div class="alert alert-danger mb-0">${escapeHtml(err)}</div>`);
            $btn.prop('disabled', false).text('Guardar y continuar');
            return;
        }

        // ✅ Actualizar estado local del ticket (para UI inmediata)
        const t = findTicketById(tiId);
        if (t) {
            t.tiDiagnostico = diag ? diag : 'Faltan datos';
            t.tiProceso = next;
        }

        // cerrar offcanvas
        const el = document.getElementById('offRevisionInicial');
        bootstrap.Offcanvas.getInstance(el)?.hide();

        // refrescar vista
        applyAndRender();
        openTicketOffcanvasById(tiId);

    } catch (e) {
        $('#revMsg').html(`<div class="alert alert-danger mb-0">Error: ${escapeHtml(e.message || e)}</div>`);
        $btn.prop('disabled', false).text('Guardar y continuar');
    }
});

async function apiSetProceso(tiId, proceso){
  const csrf = (window.MRS_CSRF?.csrf || '');
  const res = await fetch(`api/ticket_set_proceso.php`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrf
    },
    body: JSON.stringify({ tiId: Number(tiId), proceso: String(proceso||'') })
  });
  const json = await res.json().catch(()=>({}));
  if(!res.ok || json.success === false) throw new Error(json.error || 'Error ticket_set_proceso');
  return json;
}




let offMeetInstance = null;
let __MEET_TI_ID = 0;

function fmtDT(s) {
    if (!s) return '—';
    return String(s).replace(':00', '').substring(0, 16);
}

async function apiGetMeet(tiId) {
    const res = await fetch(`api/meet_get.php?tiId=${encodeURIComponent(tiId)}`, {
        credentials: 'include',
        cache: 'no-store'
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) throw new Error(json.error || 'Error meet_get');
    return json.meet || null;
}

async function apiAcceptMeet(mpId) {
    const csrf = (window.MRS_CSRF?.csrf || '');
    const res = await fetch(`api/meet_accept.php`, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ csrf_token: csrf, mpId: Number(mpId) })
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) throw new Error(json.error || 'Error meet_accept');
    return json;
}

async function apiCreateMeet(tiId, opciones, plataforma, motivo) {
    const csrf = (window.MRS_CSRF?.csrf || window.MRS_CSRF || '');

    const res = await fetch(`api/meet_create.php`, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
        },
        body: JSON.stringify({
            tiId: Number(tiId),
            opciones,
            plataforma: plataforma || '',
            link: '',
            motivo: motivo || ''
        })
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) throw new Error(json.error || 'Error meet_create');
    return json;
}



function setMeetHeaderFromTicket(t) {
    const pref = clientePrefix(state.meta.clNombre);
    const codigo = `${pref}-${Number(t.tiId)}`;

    $('#meetCodigo').text(codigo);
    $('#meetEquipo').text((t.eqModelo || 'Equipo') + (t.eqVersion ? ' · ' + t.eqVersion : ''));
    $('#meetSN').text(t.peSN ? ('SN: ' + t.peSN) : 'SN: —');
}

function paintMeetStatus(meet) {
    const $badge = $('#meetEstadoBadge');
    const $autor = $('#meetAutor');
    const $box = $('#meetStatusBox');

    if (!meet) {
        $badge.attr('class', 'badge text-bg-secondary').text('Sin solicitud');
        $autor.text('');
        $box.html(`
      <div class="fw-semibold">Sin meet solicitado</div>
      <div class="muted" style="font-size:.9rem;">
        El cliente aún no ha propuesto horarios. (El ingeniero también puede proponer si aplica.)
      </div>
    `);
        return;
    }

    const status = (meet.status || '').toLowerCase();
    const autorTipo = (meet.autorTipo || '').toLowerCase();

    $autor.text(autorTipo ? ('Solicitado por: ' + autorTipo.toUpperCase()) : '');

    if (status === 'confirmado') {
        $badge.attr('class', 'badge text-bg-success').text('Confirmado');
        $box.html(`
      <div class="fw-semibold">Meet confirmado</div>
      <div class="muted" style="font-size:.9rem;">
        Ya existe una opción aceptada. Si se requiere cambio, el solicitante debe enviar 3 nuevas opciones.
      </div>
       <button class="btn btn-primary w-100 mt-3" id="btnMeetContinuar" data-ti="${__MEET_TI_ID}">
          <i class="bi bi-arrow-right-circle"></i> Continuar a Revisión especial
        </button>

    `);
    } else {
        $badge.attr('class', 'badge text-bg-warning').text('Pendiente');
        $box.html(`
      <div class="fw-semibold">Meet pendiente</div>
      <div class="muted" style="font-size:.9rem;">
        Selecciona una opción para confirmar el Meet.
      </div>
    `);
    }
}
// click (delegado)
$(document).on('click', '#btnMeetContinuar', async function () {
    const tiId = Number($(this).data('ti'));
    try {
        await apiSetProceso(tiId, 'revision especial');

        // ✅ refresca UI
        await fetchTickets();               // o tu función de refresh
        openTicketOffcanvasById(tiId);      // reabre ya en “revisión especial”
    } catch (err) {
        alert(err?.message || 'No se pudo avanzar el ticket');
    }
});


function paintMeetOptions(meet) {
    const $wrap = $('#meetOptions');
    $wrap.empty();

    if (!meet || !Array.isArray(meet.opciones) || meet.opciones.length === 0) {
        $wrap.html(`<div class="muted">—</div>`);
        return;
    }

    // Si ya hay aceptada, solo mostramos info (sin aceptar)
    const yaAceptada = meet.opciones.some(o => (o.estado || '').toLowerCase() === 'aceptada');

    meet.opciones.forEach((o, idx) => {
        const estado = (o.estado || '').toLowerCase();
        const labelEstado =
            estado === 'aceptada' ? `<span class="badge text-bg-success ms-2">Aceptada</span>` :
                estado === 'rechazada' ? `<span class="badge text-bg-secondary ms-2">Rechazada</span>` :
                    `<span class="badge text-bg-warning ms-2">Pendiente</span>`;

        const disabled = yaAceptada || estado !== 'pendiente';
        const btnText = disabled ? 'Opción' : 'Aceptar';

        $wrap.append(`
      <button class="btn ${disabled ? 'btn-outline-secondary' : 'btn-outline-primary'} btnMeetOpt"
        data-mpid="${Number(o.mpId)}" ${disabled ? 'disabled' : ''}>
        ${btnText} ${idx + 1}: <b>${escapeHtml(fmtDT(o.inicio))}</b>
        ${labelEstado}
      </button>
    `);
    });
}

async function loadMeetIntoOffcanvas(tiId) {
    $('#meetStatusBox').html('<div class="muted">Cargando…</div>');
    $('#meetOptions').html('');

    const meet = await apiGetMeet(tiId);
    
    paintMeetStatus(meet);
    paintMeetOptions(meet);
}

function openMeetOffcanvasByTicketId(tiId) {
    const t = findTicketById(tiId);
    if (!t) return;

    __MEET_TI_ID = Number(tiId);
    setMeetHeaderFromTicket(t);

    const el = document.getElementById('offMeet');
    offMeetInstance = bootstrap.Offcanvas.getOrCreateInstance(el, { backdrop: true, scroll: false });
    offMeetInstance.show();

    loadMeetIntoOffcanvas(__MEET_TI_ID).catch(err => {
        $('#meetStatusBox').html(`<div class="alert alert-danger">${escapeHtml(err.message || String(err))}</div>`);
    });
}

// Click aceptar una opción
$(document).on('click', '.btnMeetOpt', async function (e) {
    e.preventDefault();
    const mpId = Number($(this).data('mpid'));
    if (!mpId) return;

    try {
        await apiAcceptMeet(mpId);
        await loadMeetIntoOffcanvas(__MEET_TI_ID);
    } catch (err) {
        alert(err.message || String(err));
    }
});

// Proponer 3 opciones por ingeniero (opcional)
$('#btnMeetProponer').on('click', async function () {
    const op1 = ($('#meetOp1').val() || '').trim();
    const op2 = ($('#meetOp2').val() || '').trim();
    const op3 = ($('#meetOp3').val() || '').trim();

    if (!op1 || !op2 || !op3) {
        alert('Debes capturar las 3 opciones.');
        return;
    }

    try {
        await apiCreateMeet(__MEET_TI_ID, [op1, op2, op3], $('#meetPlataforma').val(), $('#meetMotivo').val());
        // limpia inputs
        $('#meetOp1,#meetOp2,#meetOp3').val('');
        $('#meetPlataforma').val('');
        $('#meetMotivo').val('');
        await loadMeetIntoOffcanvas(__MEET_TI_ID);
    } catch (err) {
        alert(err.message || String(err));
    }
});

$('#btnMeetReload').on('click', () => loadMeetIntoOffcanvas(__MEET_TI_ID));

async function solicitarLogs(tiId, folio) {
    try {

        const fd = new FormData();
        fd.append('action', 'solicitar_logs');
        fd.append('tiId', tiId);
        fd.append('folio', folio);

        const response = await fetch('/php/notify.php', {
            method: 'POST',
            body: fd
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Logs solicitados',
                text: 'Se notificó al cliente correctamente.'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudo enviar la notificación.'
            });
        }

    } catch (error) {
        console.error(error);
        Swal.fire({
            icon: 'error',
            title: 'Error inesperado',
            text: 'Ocurrió un problema enviando la notificación.'
        });
    }
}

async function cambiarEstadoTicket(tiId, folio) {
    try {

        const fd = new FormData();
        fd.append('action', 'cambio_estado');
        fd.append('tiId', tiId);
        fd.append('folio', folio);

        const response = await fetch('/php/notify.php', {
            method: 'POST',
            body: fd
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Logs solicitados',
                text: 'Se notificó al cliente correctamente.'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudo enviar la notificación.'
            });
        }

    } catch (error) {
        console.error(error);
        Swal.fire({
            icon: 'error',
            title: 'Error inesperado',
            text: 'Ocurrió un problema enviando la notificación.'
        });
    }
}



// -------------------------
// INIT
// -------------------------
loadRecientes();
fetchTickets();
