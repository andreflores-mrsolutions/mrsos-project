
const NOTIFY_URL = '../php/notify.php';

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

// -------------------------
// Logs · Acciones ADMIN
// -------------------------
const logsActionState = {
    mode: null, // 'solicitar' | 'continuar'
    tiId: 0,
};

const ADMIN_STEPSs = [
    'asignacion', 'revision inicial', 'logs', 'meet', 'revision especial', 'espera refaccion',
    'visita', 'fecha asignada', 'espera ventana', 'espera visita', 'en camino',
    'espera documentacion', 'encuesta satisfaccion', 'finalizado', 'cancelado',
    'fuera de alcance', 'servicio por evento'
];

function fillNextStepsSelect(currentStep) {
    const $sel = $('#laNextStep');
    $sel.empty();

    // regla: no muestres "logs" como siguiente en continuar (ya lo pasaste)
    const list = ADMIN_STEPSs.filter(x => x !== 'logs');

    list.forEach(s => {
        $sel.append(`<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`);
    });

    // sugerencia default: si vienes de logs, normalmente a revision especial
    if ((currentStep || '') === 'logs') $sel.val('revision especial');
}

function openLogsAccionOffcanvas(mode, tiId) {
    const t = findTicketById(tiId);
    if (!t) return;

    logsActionState.mode = mode;
    logsActionState.tiId = Number(tiId);

    const pref = clientePrefix(state.meta.clNombre);
    $('#laCodigo').text(`${pref}-${Number(t.tiId)}`);
    $('#laEquipo').text((t.eqModelo || 'Equipo') + (t.eqVersion ? ' · ' + t.eqVersion : ''));
    $('#laSN').text(t.peSN ? ('SN: ' + t.peSN) : 'SN: —');

    const step = normalizeStep(t.tiProceso);

    if (mode === 'solicitar') {
        $('#offLogsAccionLabel').text('Solicitar nuevamente logs');
        $('#offLogsAccionSub').text('Envía solicitud al cliente y regresa el ticket a “logs”.');
        $('#laLabel').text('Motivo de solicitud');
        $('#laTexto').attr('placeholder', 'Ej: Logs antiguos / incorrectos / incompletos. Indica qué se requiere.');
        $('#laNextWrap').addClass('d-none');
        $('#laSubmit').text('Solicitar logs');
    } else {
        $('#offLogsAccionLabel').text('Diagnóstico y continuar');
        $('#offLogsAccionSub').text('Guarda diagnóstico y mueve el ticket al siguiente proceso.');
        $('#laLabel').text('Diagnóstico / estado');
        $('#laTexto').attr('placeholder', 'Describe diagnóstico (evidencia, hipótesis, impacto) y qué sigue.');
        $('#laNextWrap').removeClass('d-none');
        fillNextStepsSelect(step);
        $('#laSubmit').text('Guardar y continuar');
    }

    // contador
    const v = $('#laTexto').val() || '';
    $('#laCount').text(v.length);

    const el = document.getElementById('offLogsAccion');
    bootstrap.Offcanvas.getOrCreateInstance(el, { backdrop: true, scroll: false }).show();
}

// UI helpers del offcanvas
$(document).on('input', '#laTexto', function () {
    $('#laCount').text((this.value || '').length);
});
$(document).on('click', '[data-fill]', function () {
    const txt = String($(this).data('fill') || '');
    const $ta = $('#laTexto');
    const cur = $ta.val() || '';
    $ta.val(cur ? (cur + '\n' + txt) : txt).trigger('input');
});
$('#laClear').on('click', () => $('#laTexto').val('').trigger('input'));

// Botones en offVerLogs
$('#btnSolicitarLogs').on('click', function () {
    if (!asgContext?.tiId) return;            // o tu contexto real de logs
    openLogsAccionOffcanvas('solicitar', asgContext.tiId);
});

$('#btnLogsOK').on('click', function () {
    if (!asgContext?.tiId) return;
    openLogsAccionOffcanvas('continuar', asgContext.tiId);
});

// ---- APIs ----
async function apiSolicitarLogs(tiId, motivo) {
    console.log('apiSolicitarLogs', { tiId, motivo });
    const csrf = (window.MRS_CSRF?.csrf || '');
    const res = await fetch(`api/logs_solicitar.php`, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ tiId: Number(tiId), motivo: motivo || '' })
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) throw new Error(json.error || 'Error logs_solicitar');
    await sendTicketNotificationByProceso('solicitar_logs', tiId);
    return json;
}

async function apiDiagnosticoContinuar(tiId, diagnostico, nextStep) {
    const csrf = (window.MRS_CSRF?.csrf || '');
    const res = await fetch(`api/ticket_diagnostico.php`, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
        },
        body: JSON.stringify({
            tiId: Number(tiId),
            diagnostico: diagnostico || '',
            nextStep: nextStep || ''
        })
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) { throw new Error(json.error || 'Error ticket_diagnostico'); return; }
    await sendTicketNotificationByProceso(nextStep, tiId);
    mostrarToast('success', 'Ingeniero asignado correctamente y notificado al cliente.');
    return json;

}

// Submit del offcanvas
$('#laSubmit').on('click', async function () {
    const tiId = logsActionState.tiId;
    const texto = ($('#laTexto').val() || '').trim();


    try {
        $(this).prop('disabled', true);
        console.log('Submit acción logs', { mode: logsActionState.mode, tiId, texto });

        if (logsActionState.mode === 'solicitar') {
            await apiSolicitarLogs(tiId, texto || 'Solicito nuevamente logs.');
        } else {
            const nextStep = String($('#laNextStep').val() || '').trim();
            await apiDiagnosticoContinuar(tiId, texto || 'Faltan datos', nextStep);

        }

        // Cerrar offcanvas
        bootstrap.Offcanvas.getInstance(document.getElementById('offLogsAccion'))?.hide();
        // Recargar tickets para reflejar nuevo estado/proceso
        await fetchTickets();
        // (opcional) reabrir el ticket offcanvas principal si quieres
        // openTicketOffcanvasById(tiId);

    } catch (err) {
        alert(err?.message || 'Error');
    } finally {
        $(this).prop('disabled', false);
    }
});

async function notifyCambioProceso(t, nuevoProceso, nota) {
    return sendTicketNotification('cambio_estado', t, {
        proceso: nuevoProceso,
        texto: nota || `El ticket avanzó a: ${nuevoProceso}`,
        titulo: 'Actualización de ticket'
    });
}

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
            label: 'Revisión',
            kind: 'outline',
            action: 'revision_especial'
        },];

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
$(document).on('click', '#offPrimaryAction', async function () {
    const tiId = Number($(this).data('ti'));
    const t = findTicketById(tiId);
    const tifolio = ticketCodigo(findTicketById(tiId));
    const action = String($(this).data('action') || '');

    if (action === 'admin_logs') {

        // Ej: después de guardar en API el cambio de proceso:
        // await notifyCambioProceso(t, 'solicitar_logs', 'Se requieren los logs para continuar con el diagnóstico. Por favor, súbelos usando el botón "Subir Logs".');
        await sendTicketNotification('solicitar_logs', t, {
            proceso: 'logs',
            texto: 'Necesitamos que vuelvas a cargar los logs. Los anteriores no son válidos / están desactualizados.',
            titulo: 'Solicitud de logs'
        });
        return;
    }
    if (action === 'admin_revision_especial') {
        verLogsOffcanvas(tiId);
        // reivisionLogs(tiId, tifolio);
        return;
    }

    if (action === 'asignar_ingeniero') {
        openAsignarIngenieroOffcanvas(tiId);

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
let verLogsInstance = null;
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

async function verLogsOffcanvas(tiId) {
    const t = findTicketById(tiId);
    if (!t) return;

    asgContext.tiId = Number(tiId);
    asgContext.ticket = t;

    // header (usa tus ids actuales)
    const pref = clientePrefix(state.meta.clNombre);
    $('#asgTicketCodigoLogs').text(`${pref}-${Number(t.tiId)}`);
    $('#asgTicketEquipoLogs').text((t.eqModelo || 'Equipo') + (t.eqVersion ? ' · ' + t.eqVersion : ''));
    $('#asgTicketSNLogs').text(t.peSN ? ('SN: ' + t.peSN) : 'SN: —');
    $('#asgTicketPasoLogs').text(`Paso: ${t.tiProceso || '—'}`);
    $('#asgTicketEstadoLogs').text(t.tiEstatus || '—');

    // UI
    $('#logsLoading, #asgLoading').removeClass('d-none').text('Cargando logs...');
    $('#logsEmpty, #asgEmpty').addClass('d-none');
    $('#logsList, #asgWrap').empty();
    $('#logViewer').text('');
    $('#logTooLarge').addClass('d-none');

    const el = document.getElementById('offVerLogs');
    verLogsInstance = bootstrap.Offcanvas.getOrCreateInstance(el, { backdrop: true, scroll: false });
    verLogsInstance.show();

    // ✅ ahora sí: trae archivos y rindea
    const files = await fetchLogs(asgContext.tiId);
    renderLogs(files);
}
async function fetchLogs(tiId) {
    const csrf = (window.MRS_CSRF?.csrf || window.MRS_CSRF || '');

    const res = await fetch(`api/logs_list.php?tiId=${encodeURIComponent(Number(tiId))}`, {
        method: 'GET',
        credentials: 'include',
        headers: { 'X-CSRF-Token': csrf }, // no es obligatorio en GET, pero ok
        cache: 'no-store'
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) throw new Error(json.error || 'Error logs_list');

    // json.files debe ser arreglo
    return Array.isArray(json.files) ? json.files : [];
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

function bytesToHuman(bytes) {
    const b = Number(bytes || 0);
    if (!b) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(Math.floor(Math.log(b) / Math.log(1024)), units.length - 1);
    return (b / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
}

function isTxtLike(name) {
    const n = (name || '').toLowerCase();
    return n.endsWith('.txt') || n.endsWith('.log') || n.endsWith('.out') || n.endsWith('.cfg') || n.endsWith('.ini');
}

/**
 * Renderiza la vista de logs:
 * - panel izquierdo: lista de archivos
 * - panel derecho: visor (preview) o aviso “muy extenso”
 */
function renderLogs(files) {
    files = Array.isArray(files) ? files : [];

    $('#logsLoading').addClass('d-none');
    $('#logsEmpty').toggleClass('d-none', files.length !== 0);
    $('#logsCount').text(files.length ? `${files.length} archivo(s)` : '0');

    $('#logsList').empty();
    $('#logViewer').text('');
    $('#logViewTitle').text('Selecciona un archivo');
    $('#logViewMeta').text('—');
    $('#logTooLarge').addClass('d-none');
    $('#btnDownloadLog').addClass('d-none').attr('href', '#');

    if (!files.length) return;

    files.forEach(f => {
        const filename = f.filename || 'archivo.txt';
        const sizeTxt = bytesToHuman(f.size_bytes);
        const uploaded = f.uploaded_at ? String(f.uploaded_at).substring(0, 19).replace('T', ' ') : '—';
        const badge = isTxtLike(filename)
            ? `<span class="badge rounded-pill text-bg-light border">TXT</span>`
            : `<span class="badge rounded-pill text-bg-secondary">ARCHIVO</span>`;

        const canPreview = !!f.can_preview; // ideal que venga del server por size/mime
        const btnVer = canPreview
            ? `<button class="btn btn-dark btn-sm px-3 btnVerLog" data-id="${Number(f.id)}">Ver</button>`
            : `<button class="btn btn-outline-secondary btn-sm px-3 btnVerLog" data-id="${Number(f.id)}">Ver</button>`;

        const card = $(`
      <div class="p-3 border rounded-4 mb-2" style="background:#fff; cursor:pointer;">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="fw-bold" style="font-size:.95rem;">${escapeHtml(filename)}</div>
          ${badge}
        </div>

        <div class="text-muted mt-1" style="font-size:.85rem;">
          <div class="d-flex justify-content-between"><span>Tamaño</span><span>${escapeHtml(sizeTxt)}</span></div>
          <div class="d-flex justify-content-between"><span>Subido</span><span>${escapeHtml(uploaded)}</span></div>
        </div>

        <div class="mt-2 d-flex gap-2">
          ${btnVer}
          <a class="btn btn-outline-secondary btn-sm px-3"
             href="${escapeHtml(f.download_url || '#')}"
             ${f.download_url ? 'download' : ''}
             onclick="event.stopPropagation();">
             Descargar
          </a>
        </div>
      </div>
    `);

        // click al card = ver (si se puede)
        card.on('click', function () {
            const id = Number(f.id);
            if (id) openLogPreview(id);
        });

        $('#logsList').append(card);
    });
}

async function apiLogPreview(fileId) {
    const csrf = (window.MRS_CSRF?.csrf || window.MRS_CSRF || '');

    const res = await fetch(`api/logs_preview.php?id=${encodeURIComponent(Number(fileId))}`, {
        method: 'GET',
        credentials: 'include',
        headers: { 'X-CSRF-Token': csrf },
        cache: 'no-store'
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) throw new Error(json.error || 'Error logs_preview');
    return json;
}

function setDownload(url) {
    if (url) {
        $('#btnDownloadLog').removeClass('d-none').attr('href', url);
    } else {
        $('#btnDownloadLog').addClass('d-none').attr('href', '#');
    }
}

function bytesToHuman(bytes) {
    const b = Number(bytes || 0);
    if (!b) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(Math.floor(Math.log(b) / Math.log(1024)), units.length - 1);
    return (b / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
}

/**
 * Abre vista previa del txt/log.
 * - si too_large: muestra aviso + botón descargar
 * - si ok: imprime contenido en <pre>
 */
async function openLogPreview(fileId) {
    try {
        $('#logTooLarge').addClass('d-none');
        $('#logViewer').text('Cargando...');
        $('#logViewTitle').text('Cargando...');
        $('#logViewMeta').text('—');
        setDownload(null);

        const data = await apiLogPreview(fileId);

        const filename = data.filename || 'Log';
        const sizeTxt = bytesToHuman(data.size_bytes);

        $('#logViewTitle').text(filename);

        if (data.download_url) setDownload(data.download_url);

        if (data.too_large) {
            $('#logViewMeta').text(`${sizeTxt} · Vista previa deshabilitada`);
            $('#logViewer').text('');
            $('#logTooLarge')
                .removeClass('d-none')
                .text('Este archivo es muy extenso para mostrarse en pantalla. Descárgalo para revisarlo completo.');
            return;
        }

        const content = (data.content ?? '').toString();
        $('#logViewMeta').text(`${sizeTxt} · Vista previa`);
        $('#logViewer').text(content || '(Archivo vacío)');
    } catch (err) {
        console.error(err);
        $('#logViewTitle').text('No se pudo cargar');
        $('#logViewMeta').text('—');
        $('#logViewer').text('');
        $('#logTooLarge')
            .removeClass('d-none')
            .text('No se pudo cargar la vista previa. Descarga el archivo para revisarlo.');
    }
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

        await sendTicketNotification('solicitar_logs', t, {
            proceso: 'asignar_ingeniero',
            texto: 'Se te ha asignado un ingeniero para revisar tu caso. Por favor, sube los logs necesarios para que pueda comenzar con el diagnóstico.',
            titulo: 'Asignación de ingeniero'
        });
        mostrarToast('success', 'Ingeniero asignado correctamente y notificado al cliente.');
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
        await sendTicketNotification('revision_inicial', t, {
            proceso: 'revision_inicial',
            texto: 'Se ha completado la revisión inicial de tu caso. Por favor, revisa el diagnóstico y sigue las indicaciones para el siguiente paso.',
            titulo: 'Revisión inicial completada'
        });
        mostrarToast('success', 'Revisión inicial guardada y cliente notificado.');
        // refrescar vista
        applyAndRender();
        openTicketOffcanvasById(tiId);

    } catch (e) {
        $('#revMsg').html(`<div class="alert alert-danger mb-0">Error: ${escapeHtml(e.message || e)}</div>`);
        $btn.prop('disabled', false).text('Guardar y continuar');
    }
});

async function apiSetProceso(tiId, proceso) {
    const csrf = (window.MRS_CSRF?.csrf || '');
    const res = await fetch(`api/ticket_set_proceso.php`, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ tiId: Number(tiId), proceso: String(proceso || '') })
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) throw new Error(json.error || 'Error ticket_set_proceso');
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
          <i class="bi bi-arrow-right-circle"></i> Continuar proceso
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
    openLogsAccionOffcanvas('continuar', tiId);
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

async function sendTicketNotification(action, ticket, extra = {}) {
    console.log('sendTicketNotification', { action, ticket, extra });
    if (!action) throw new Error('action requerido');
    if (!ticket) throw new Error('ticket inválido sendTicketNotification');

    const folio = ticketCodigo(findTicketById(ticket.tiId));

    const fd = new FormData();
    fd.append('action', action);
    fd.append('folio', folio);

    // contexto base (lo que tu NotificationService puede usar)
    fd.append('tiId', String(ticket.tiId));
    fd.append('proceso', String(extra.proceso ?? ticket.tiProceso ?? ''));
    fd.append('estado', String(extra.estado ?? ticket.tiEstatus ?? ''));
    fd.append('texto', String(extra.texto ?? ''));      // opcional (para body)
    fd.append('titulo', String(extra.titulo ?? ''));    // opcional (para title)

    // extras arbitrarios (motivo, etc.)
    Object.entries(extra).forEach(([k, v]) => {
        if (v === undefined || v === null) return;
        if (['proceso', 'estado', 'texto', 'titulo'].includes(k)) return;
        fd.append(k, String(v));
    });

    const res = await fetch(NOTIFY_URL, {
        method: 'POST',
        credentials: 'include',
        body: fd
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) {
        throw new Error(json.message || json.error || 'Error notify');
    }
    return json; // {success, sent, errors}
}

async function sendTicketNotificationByProceso(action, ticket) {
    ticket = findTicketById(ticket);
    console.log('sendTicketNotification by proceso', { action, ticket });
    if (!action) throw new Error('action requerido');
    if (!ticket) throw new Error('ticket inválido by proceso');
    if (action === 'asignacion') {
        extra = {
            proceso: 'asignacion',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se esta asignando un ingeniero a tu caso. En breve recibirás una notificación con el nombre del ingeniero asignado y los siguientes pasos a seguir.',
            titulo: 'Asignación de ingeniero'
        };
    }
    if (action === 'revision inicial') {
        extra = {
            proceso: 'revision_inicial',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se esta realizando la revisión inicial de tu caso. Tranquilo, tu caso está siendo atendido y en breve recibirás una notificación con el diagnóstico y los siguientes pasos a seguir.',
            titulo: 'Revisión inicial completada'
        };
    }
if (action === 'logs') {
        extra = {
            proceso: 'logs',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se requieren los logs para el diagnóstico de tu caso. Por favor, sube los logs necesarios para continuar con el proceso.',
            titulo: 'Solicitud de logs'
        };
    }

    if (action === 'logs solicitados') {
        extra = {
            proceso: 'logs_solicitados',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se han solicitado los logs para el diagnóstico de tu caso. Por favor, sube los logs necesarios para continuar con el proceso.',
            titulo: 'Solicitud de logs'
        };
    }
    if (action === 'meet solicitado') {
        extra = {
            proceso: 'meet_solicitado',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha solicitado una reunión para el diagnóstico de tu caso. Por favor, confirma la reunión para continuar con el proceso.',
            titulo: 'Solicitud de reunión'
        };
    }
    if (action === 'meet confirmado') {
        extra = {
            proceso: 'meet_confirmado',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha confirmado la reunión para el diagnóstico de tu caso. Por favor, asiste a la reunión para continuar con el proceso.',
            titulo: 'Reunión confirmada'
        };
    }
    if (action === 'revision especial') {
        extra = {
            proceso: 'revision_especial',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se han subido con éxito los logs necesarios para la revisión especial de tu caso. El ingeniero asignado está revisando la información proporcionada y en breve recibirás una notificación con el diagnóstico y los siguientes pasos a seguir.',
            titulo: 'Revisión especial completada'
        };
    }
    if (action === 'espera refaccion') {
        extra = {
            proceso: 'espera_refaccion',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se esta en espera de refacción para tu caso. Por favor, aguarda a que llegue la refacción para continuar con el proceso.',
            titulo: 'Espera de refacción'
        };
    }
    if (action === 'solicitud visita') {
        extra = {
            proceso: 'solicitud_visita',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha solicitado una visita técnica para tu caso. Por favor, ACEPTA/RECHAZA/ASIGNA una visita técnica para continuar con el proceso.',
            titulo: 'Solicitud de visita técnica'
        };
    }
    if (action === 'confirmacion visita') {
        extra = {
            proceso: 'confirmacion visita',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha confirmado la visita técnica para tu caso. Es importante que estés presente en la fecha y hora programada para que el ingeniero pueda realizar el diagnóstico y resolver tu caso lo antes posible.',
            titulo: 'Confirmación de visita técnica'
        };
    }
    if (action === 'en camino') {
        extra = {
            proceso: 'en_camino',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', el ingeniero se encuentra en camino para visitar tu caso. Por favor, mantente disponible para recibir al ingeniero.',
            titulo: 'Ingeniero en camino'
        };
    }
    if (action === 'espera documentacion') {
        extra = {
            proceso: 'espera documentacion',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se esta en espera de documentación para tu caso. Por favor, aguarda a que llegue la documentación para continuar con el proceso.',
            titulo: 'Espera de documentación'
        };
    }
    if (action === 'encuesta satisfaccion') {
        extra = {
            proceso: 'encuesta_satisfaccion',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha generado una encuesta de satisfacción. Agradecemos tu elección de MRSolutions para el soporte de tu equipo. Para ayudarnos a mejorar nuestro servicio, te invitamos a completar una breve encuesta de satisfacción. Tu opinión es muy valiosa para nosotros y nos ayudará a brindarte un mejor servicio en el futuro.',
            titulo: 'Encuesta de satisfacción'
        };
    }


    await sendTicketNotification(action, ticket, extra);

}

function mostrarToast(tipo, mensaje) {
    const toastId = tipo === 'success' ? '#toastSuccess' : '#toastError';
    const $toastElem = $(toastId);

    if ($toastElem.length === 0) {
        alert(mensaje); // fallback por si no están los toasts
        return;
    }

    $(`${toastId} .toast-body`).text(mensaje);
    const toast = new bootstrap.Toast($toastElem[0]);
    toast.show();
}


// -------------------------
// INIT
// -------------------------
loadRecientes();
fetchTickets();
