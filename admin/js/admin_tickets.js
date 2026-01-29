// admin_tickets.js
(function () {
  'use strict';

  // ==============================
  //  ESTADO GLOBAL
  // ==============================

  const STATE = {
    clientes: [],              // [{clId, clNombre, totalTickets, ...}]
    ticketsPorCliente: {},     // clId -> [tickets]
    clienteActual: null,       // {clId, clNombre, ...}
    vista: 'tabla'             // 'tabla' | 'cards'
  };

  let CLIENTE_ACTUAL_ID = null;
  let CLIENTE_ACTUAL_TICKETS = [];

  // ==============================
  //  HELPERS DOM
  // ==============================

  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  // ==============================
  //  HELPERS DE FORMATO (reusan si ya existen)
  // ==============================

  function badgeEstadoTicket(estado) {
    if (window.badgeEstadoTicket) return window.badgeEstadoTicket(estado);

    const e = (estado || '').toLowerCase();
    if (e === 'abierto') return 'badge bg-success-subtle text-success';
    if (e === 'cerrado') return 'badge bg-secondary-subtle text-secondary';
    if (e === 'pospuesto') return 'badge bg-warning-subtle text-warning';
    if (e === 'cancelado') return 'badge bg-danger-subtle text-danger';
    return 'badge bg-light text-muted';
  }

  function badgeProcesoTicket(proceso) {
    if (window.badgeProcesoTicket) return window.badgeProcesoTicket(proceso);

    const p = (proceso || '').toLowerCase();
    if (p === 'en sitio') return 'bg-info text-dark';
    if (p === 'remoto') return 'bg-primary';
    if (p === 'diagnóstico') return 'bg-warning text-dark';
    return 'bg-light text-muted';
  }

  function badgeTipoTicket(tipo) {
    if (window.badgeTipoTicket) return window.badgeTipoTicket(tipo);

    const t = (tipo || '').toLowerCase();
    if (t === 'correctivo') return 'badge bg-danger-subtle text-danger';
    if (t === 'preventivo') return 'badge bg-success-subtle text-success';
    if (t === 'asesoría') return 'badge bg-info-subtle text-info';
    return 'badge bg-light text-muted';
  }

  function fmtVisitaFechaHora(t) {
    if (window.fmtVisitaFechaHora) return window.fmtVisitaFechaHora(t);

    if (!t || !t.tiFechaVisita) return '';
    // Muy simplificado, ajústalo si ya tienes un formateador global
    return t.tiFechaVisita;
  }

  function fmtVisitaDuracion(t) {
    if (window.fmtVisitaDuracion) return window.fmtVisitaDuracion(t);

    if (!t || !t.tiDuracion) return '';
    return ` · ${t.tiDuracion}`;
  }
  // ==============================
  function renderQuickActions(t, prefixCliente) {
    const proc = (t.tiProceso || "").toLowerCase();
    const tiId = Number(t.tiId) || 0;
    const ma = t.maNombre || "";
    const modelo = t.eqModelo || "";

    const editableMeet = proc === "meet";
    const hayMeet = hayMeetActivo(t);
    const meetIngPend = esMeetPropuestoPorIngenieroPendiente(t);

    let html = "";

    // LOGS
    if (proc === "logs") {
      html += `
      <div class="d-flex flex-wrap justify-content-end gap-1">
        <button type="button"
                class="btn btn-sm btn-outline-primary"
                onclick="abrirDetalle(${tiId}, '${prefixCliente}')"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasTicket">
          <i class="bi bi-upload me-1"></i>Subir logs
        </button>
        <button type="button"
                class="btn btn-sm btn-outline-secondary"
                title="¿Cómo extraer logs?"
                onclick="openHelpLogs('${ma}', '${modelo}')">
          <i class="bi bi-question-circle"></i>
        </button>
      </div>
    `;
    }

    // MEET
    else if (proc === "meet") {
      // 1) No hay meet todavía → proponer / asignar
      if (editableMeet && !hayMeet) {
        html += `
        <button type="button"
                class="btn btn-outline-primary btn-sm"
                data-meet-action="proponer"
                data-ticket-id="${t.tiId}">
          Proponer Meet
        </button>

        <button type="button"
                class="btn btn-outline-success btn-sm"
                data-meet-action="asignar"
                data-ticket-id="${t.tiId}">
          Asignar Meet
        </button>
      `;
      }
      // 2) Hay meet propuesto por MR y falta que el cliente responda
      else if (editableMeet && meetIngPend) {
        html += `
        <div class="d-flex flex-wrap justify-content-end gap-1">
          <button type="button"
                  class="btn btn-success btn-sm"
                  data-meet-action="confirmar"
                  data-ticket-id="${t.tiId}">
            Confirmar cita
          </button>

           <button type="button"
                class="btn btn-outline-primary btn-sm"
                data-meet-action="proponer"
                data-ticket-id="${t.tiId}">
          Proponer Meet
        </button>
        </div>
        <div class="small text-muted mt-1">
          El ingeniero ha propuesto una fecha. Confírmalas o propone otra.
        </div>
      `;
      }
      // 3) Hay meet activo en cualquier otro estado
      else if (editableMeet && hayMeet) {
        html += `
      <button type="button"
                class="btn btn-outline-primary btn-sm"
                data-meet-action="proponer"
                data-ticket-id="${t.tiId}">
          Proponer Nuevo Meet
        </button>
        <div class="alert alert-info py-1 px-2 mb-0">
          Ya existe un meet activo para este ticket.
        </div>
      `;
      }
    }

    // ASIGNACIÓN DE VENTANA
    else if (proc === "visita") {
      const hay = hayVisitaActiva(t);
      const requiereF = requiereFolioVisita(t);


      html += `
      <div class="d-flex flex-column align-items-end gap-1">
        <button type="button"
                class="btn btn-sm btn-outline-primary"
                onclick="abrirDetalle(${tiId}, '${prefixCliente}')"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasTicket">
          <i class="bi bi-calendar-event me-1"></i>Ver visita
        </button>
        ${requiereF ? `
          <span class="badge bg-warning-subtle text-warning-emphasis small">
            Folio de entrada pendiente
          </span>` : ``}
      </div>
    `;

      // Si NO hay visita aún -> mostrar botones para crear
      if (!hay) {
        html += `
        <div class="d-flex flex-wrap justify-content-end gap-1">
          <button type="button"
                  class="btn btn-sm btn-outline-primary"
                  data-visita-action="proponer"
                  data-ticket-id="${t.tiId}">
            Proponer visita
          </button>
          <button type="button"
                  class="btn btn-sm btn-outline-success"
                  data-visita-action="asignar"
                  data-ticket-id="${t.tiId}">
            Asignar visita
          </button>
        </div>
      `;
      } else {
        // Si YA hay visita (pendiente o confirmada) -> NO mostrar botones de crear,
        // sólo un resumen para el cliente
        html += `
        <div class="text-end">
          <div class="small text-muted">${textoVisitaEstado(t)}</div>
          <div class="small">${textoVisitaAutor(t)}</div>
        </div>
      `;
      }
    }

    // ENCUESTA + HOJA DE SERVICIO
    else if (
      proc === "encuesta satisfaccion" ||
      proc === "encuesta de satisfaccion"
    ) {
      html += `
      <div class="d-flex flex-wrap justify-content-end gap-1">
        <button type="button"
                class="btn btn-sm btn-outline-warning"
                onclick="abrirDetalle(${tiId}, '${prefixCliente}')"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasTicket">
          <i class="bi bi-clipboard-check me-1"></i>Encuesta
        </button>
        <a href="../php/descargar_hoja_servicio.php?tiId=${tiId}"
           class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-file-earmark-arrow-down me-1"></i>Hoja de servicio
        </a>
      </div>
    `;
    } else {
      html += `
    <button class="btn btn-sm btn-outline-primary" type="button">
        <i class="bi bi-envelope"></i>
      </button>
    `;
    }

    return html;
  }


  // Agrupa tickets por zona/sede
  function agruparPorZonaSede(tickets) {
    const grupos = {};

    tickets.forEach(t => {
      const zona = t.czNombre || 'Sin zona';
      const sede = t.csNombre || 'Sin sede';
      const key = `${zona}||${sede}`;

      if (!grupos[key]) {
        grupos[key] = {
          titulo: `${zona} · ${sede}`,
          tickets: []
        };
      }
      grupos[key].tickets.push(t);
    });

    return grupos;
  }

  // ==============================
  //  CARGA DE CLIENTES
  // ==============================

  function cargarClientes() {
    const contClientes = $('#listaClientesTickets');
    if (!contClientes) return;

    contClientes.innerHTML = `
      <div class="text-center text-muted small py-3">
        Cargando clientes...
      </div>
    `;

    // Ajusta la URL a tu PHP real
    fetch('../php/adm_tickets_clientes.php')
      .then(response => response.json())
      .then(resp => {
        if (!resp || resp.success !== true) {
          contClientes.innerHTML = `
            <div class="alert alert-danger small mb-0">
              ${resp && resp.error ? resp.error : 'No se pudieron cargar los clientes.'}
            </div>`;
          return;
        }

        STATE.clientes = resp.clientes || [];
        renderClientes();
      })
      .catch(() => {
        contClientes.innerHTML = `
          <div class="alert alert-danger small mb-0">
            Error de red al cargar clientes.
          </div>`;
      });
  }

  function renderClientes() {
    const contClientes = $('#listaClientesTickets');
    if (!contClientes) return;

    const clientes = STATE.clientes;
    if (!clientes.length) {
      contClientes.innerHTML = `
        <div class="alert alert-light border small mb-0">
          No se encontraron clientes para mostrar.
        </div>`;
      return;
    }

    let html = '<div class="d-flex flex-wrap gap-2">';
    clientes.forEach(c => {
      const total = Number(c.totalTickets || 0);
      html += `
        <button type="button"
                class="btn btn-sm btn-outline-secondary mrs-chip-cliente"
                data-clId="${c.clId}">
          <div class="d-flex flex-column text-start">
            <span class="fw-semibold">${c.clNombre || ('Cliente #' + c.clId)}</span>
            <span class="small text-muted">
              Tickets: ${total}
            </span>
          </div>
        </button>
      `;
    });
    html += '</div>';

    contClientes.innerHTML = html;

    // Eventos de selección de cliente
    $$('.mrs-chip-cliente').forEach(btn => {
      btn.addEventListener('click', () => {
        const clId = parseInt(btn.getAttribute('data-clid'), 10);
        const cliente = STATE.clientes.find(c => Number(c.clId) === clId);
        if (!cliente) return;
        seleccionarCliente(cliente);
      });
    });
  }

  // ==============================
  //  SELECCIÓN DE CLIENTE
  // ==============================

  function seleccionarCliente(cliente) {
    CLIENTE_ACTUAL_ID = Number(cliente.clId);
    const lblCli = $('#lblClienteSeleccionado');
    const lblTot = $('#lblTotalTickets');
    const bloque = $('#bloqueTickets');

    if (lblCli) lblCli.textContent = cliente.clNombre || `Cliente #${cliente.clId}`;
    if (lblTot) lblTot.textContent = cliente.totalTickets || 0;
    if (bloque) bloque.classList.remove('d-none');

    // Si ya tenemos cache de tickets del cliente, usamos eso
    if (STATE.ticketsPorCliente[CLIENTE_ACTUAL_ID]) {
      CLIENTE_ACTUAL_TICKETS = STATE.ticketsPorCliente[CLIENTE_ACTUAL_ID];
      repintarTicketsClienteActual();
      return;
    }

    cargarTicketsDeCliente(CLIENTE_ACTUAL_ID);
  }

  function cargarTicketsDeCliente(clId) {
    const cont = $('#contenedorTickets');
    if (cont) {
      cont.innerHTML = `
        <div class="text-center text-muted small py-3">
          Cargando tickets del cliente...
        </div>
      `;
    }

    // Ajusta la URL a tu PHP real
    fetch(`../php/adm_tickets_cliente_tickets.php?clId=${encodeURIComponent(clId)}`)
      .then(response => response.json())
      .then(resp => {
        if (!resp || resp.success !== true) {
          if (cont) {
            cont.innerHTML = `
              <div class="alert alert-danger small mb-0">
                ${resp && resp.error ? resp.error : 'No se pudieron cargar los tickets del cliente.'}
              </div>`;
          }
          return;
        }

        const tickets = resp.tickets || [];
        STATE.ticketsPorCliente[clId] = tickets;
        CLIENTE_ACTUAL_TICKETS = tickets;

        const lblTot = $('#lblTotalTickets');
        if (lblTot) lblTot.textContent = tickets.length;

        repintarTicketsClienteActual();
      })
      .catch(() => {
        if (cont) {
          cont.innerHTML = `
            <div class="alert alert-danger small mb-0">
              Error de red al cargar los tickets.
            </div>`;
        }
      });
  }

  // ==============================
  //  VISTA (TABLA / CARDS)
  // ==============================

  function repintarTicketsClienteActual() {
    const cont = $('#contenedorTickets');
    if (!cont) return;

    const tickets = CLIENTE_ACTUAL_TICKETS || [];

    if (!tickets.length) {
      cont.innerHTML = `
        <div class="alert alert-light border small mb-0 text-center">
          No hay tickets para este cliente con los filtros actuales.
        </div>`;
      return;
    }

    if (STATE.vista === 'tabla') {
      renderTicketsTablaPorZonaSede(tickets);
    } else {
      renderTicketsCardsPorZonaSede(tickets);
    }
  }

  function renderTicketsTablaPorZonaSede(tickets) {
    const cont = $('#contenedorTickets');
    if (!cont) return;

    const grupos = agruparPorZonaSede(tickets);
    let html = '';

    Object.keys(grupos).forEach(key => {
      const grupo = grupos[key];
      const titulo = grupo.titulo;
      const lista = grupo.tickets || [];


      html += `
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <div>
              <div class="text-uppercase small text-muted">Zona / Sede</div>
              <div class="fw-semibold">${titulo}</div>
            </div>
            <span class="badge bg-primary-subtle text-primary">
              ${lista.length} ticket(s)
            </span>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <tbody>
      `;

      lista.forEach(t => {
        const prefix = t.ticketPrefix || 'TIC';
        const codigo = `${prefix} ${t.tiId}`;
        const equipo = t.eqModelo || 'Equipo';
        const sn = t.peSN || '';
        const marca = t.maNombre || '';
        const modelo = t.eqModelo || "";
        const version = t.eqVersion ? ` ${t.eqVersion}` : "";

        html += `
          <tr>
          <td class="align-middle" style="width: 20%;">
            <div class="mrs-ticket-main">
              <div>
                <span class="${badgeEstadoTicket(t.tiEstatus)}">${t.tiEstatus || "—"}</span>
              </div>
              <div class="d-flex flex-wrap gap-1">
                ${t.tiProceso ? `<span class="badge-pill-soft ${badgeProcesoTicket(t.tiProceso)}">${t.tiProceso}</span>` : ""}
                ${t.tiTipoTicket ? `<span class="${badgeTipoTicket(t.tiTipoTicket)}">${t.tiTipoTicket}</span>` : ""}
              </div>
              <div class="mrs-ticket-id mt-1">
                ${codigo}
              </div>
            </div>
          </td>

          <td class="align-middle" style="width: 60%;">
            <div class="mrs-ticket-body">
              <div class="mrs-ticket-title">
                ${equipo}
              </div>
              <div class="mrs-ticket-sub">
                SN: <span class="fw-medium">${sn}</span>
                ${marca ? ` · ${marca}` : ""}
              </div>
              <div class="mrs-ticket-meta">
                ${fmtVisitaFechaHora(t)
            ? `<span><i class="bi bi-tools me-1"></i>${fmtVisitaFechaHora(t)}${fmtVisitaDuracion(t)}</span>`
            : ''
          }
                ${t.tiExtra
            ? `<span class="text-truncate" style="max-width: 260px;">${t.tiExtra}</span>`
            : ''
          }
              </div>
            </div>
          </td>
          <td class="align-middle text-end mrs-ticket-action" style="width: 20%;">
            <div class="mb-1">
              ${renderQuickActions(t, prefix)}
            </div>
            <a href="#"
              class="small"
              onclick="abrirDetalle(${Number(t.tiId)}, '${prefix}')"
              data-bs-toggle="offcanvas"
              data-bs-target="#offcanvasTicket">
              Ver detalle <i class="bi bi-arrow-right-short"></i>
            </a>
          </td>
        </tr>
        `;
      });

      html += `
                </tbody>
              </table>
            </div>
          </div>
        </div>
      `;
    });

    cont.innerHTML = html;
  }

  function renderTicketsCardsPorZonaSede(tickets) {
    const cont = $('#contenedorTickets');
    if (!cont) return;

    const grupos = agruparPorZonaSede(tickets);
    let html = '';

    Object.keys(grupos).forEach(key => {
      const grupo = grupos[key];
      const titulo = grupo.titulo;
      const lista = grupo.tickets || [];

      html += `
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <div>
              <div class="text-uppercase small text-muted">Zona / Sede</div>
              <div class="fw-semibold">${titulo}</div>
            </div>
            <span class="badge bg-primary-subtle text-primary">
              ${lista.length} ticket(s)
            </span>
          </div>
          <div class="card-body row">
      `;

      lista.forEach(t => {
        const prefix = t.ticketPrefix || 'TIC';
        const codigo = `${prefix} ${t.tiId}`;
        const equipo = t.eqModelo || 'Equipo';
        const sn = t.peSN || '';
        const marca = t.maNombre || '';
        const modelo = t.eqModelo || "";
        const version = t.eqVersion ? ` ${t.eqVersion}` : "";
        const crit = t.tiNivelCriticidad; // 'alta','media','baja'

        html += `
                    <div class="col-12 col-md-6 col-xl-4 mt-1">
                        <div class="mrs-card mrs-ticket-card h-100" data-crit="${crit}">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="${badgeEstadoTicket(t.tiEstatus)}">
                                    ${t.tiEstatus || "—"}
                                </span>
                                <span class="mrs-ticket-id-small">
                                    ${codigo}
                                </span>
                                </div>

                                <div class="mrs-ticket-card-title mb-1">
                                ${equipo}
                                </div>

                                <div class="mrs-ticket-card-sub mb-2">
                                SN: <span class="fw-medium">${sn}</span>
                                ${marca ? ` · ${marca}` : ""}
                                </div>

                                <div class="d-flex flex-wrap gap-1 mb-2 mrs-ticket-card-meta">
                                ${t.tiProceso ? `<span class="badge-pill-soft ${badgeProcesoTicket(t.tiProceso)}">${t.tiProceso}</span>` : ""}
                                ${t.tiTipoTicket ? `<span class="${badgeTipoTicket(t.tiTipoTicket)}">${t.tiTipoTicket}</span>` : ""}
                                ${fmtVisitaFechaHora(t)
            ? `<span><i class="bi bi-truck me-1"></i>${fmtVisitaFechaHora(t)}${fmtVisitaDuracion(t)}</span>`
            : ''
          }
                                ${t.tiFolioEntrada ? `<span class="badge bg-success-subtle text-success-emphasis">Folio: ${t.tiFolioEntrada}</span>` : ""}
                                </div>

                                ${t.tiExtra ? `
                                <div class="mrs-ticket-card-sub mb-2 text-truncate" title="${t.tiExtra}">
                                    ${t.tiExtra}
                                </div>` : ""}

                                <div class="d-flex justify-content-between align-items-end mt-auto pt-1">
                                <div class="me-2">
                                    ${renderQuickActions(t, prefix)}
                                </div>
                                <a href="#"
                                    class="small"
                                    onclick="abrirDetalle(${Number(t.tiId)}, '${prefix}')"
                                    data-bs-toggle="offcanvas"
                                    data-bs-target="#offcanvasTicket">
                                    Ver detalle <i class="bi bi-arrow-right-short"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                `;
      });

      html += `
          </div>
        </div>
      `;
    });

    cont.innerHTML = html;
  }

  // ==============================
  //  INICIALIZACIÓN
  // ==============================

  function inicializar() {
    // Cargar clientes apenas arranca
    cargarClientes();

    // Toggle vista
    const btnVistaTabla = $('#btnVistaTabla');
    const btnVistaCards = $('#btnVistaCards');

    if (btnVistaTabla) {
      btnVistaTabla.addEventListener('click', (e) => {
        e.preventDefault();
        STATE.vista = 'tabla';
        btnVistaTabla.classList.add('active');
        btnVistaCards && btnVistaCards.classList.remove('active');
        repintarTicketsClienteActual();
      });
    }

    if (btnVistaCards) {
      btnVistaCards.addEventListener('click', (e) => {
        e.preventDefault();
        STATE.vista = 'cards';
        btnVistaCards.classList.add('active');
        btnVistaTabla && btnVistaTabla.classList.remove('active');
        repintarTicketsClienteActual();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', inicializar);

})();



// ====== Abrir detalle (usa prefijo de cliente) ======


function esMeetPropuestoPorIngenieroPendiente(t) {
  const estado = (t.tiMeetEstado || "").toLowerCase();
  const autor = (t.tiMeetModo || "").toLowerCase();

  if (autor !== "propuesta_ingeniero") return false;

  // Ajusta los textos a lo que estés guardando en BD
  return (
    estado === "pendiente_cliente" ||
    estado === "propuesta" ||
    estado === "pendiente"
  );
}

function fmtMeetFechaHora(t) {
  if (!t || !t.tiMeetFecha) return "";
  const f = t.tiMeetFecha;
  const h = t.tiMeetHora || "";
  return h ? `${f} ${h}` : f;
}
function fmtVisitaFechaHora(t) {
  // Si ya guardas separados:
  if (t.tiVisitaFecha && t.tiVisitaHora) {
    return `${t.tiVisitaFecha} ${t.tiVisitaHora}`;
  }
  // Si de momento sólo usas tiVisita (datetime)
  if (t.tiVisita) return fmtDateTime(t.tiVisita);
  return "";
}
function fmtVisitaDuracion(t) {
  const mins = parseInt(t.tiVisitaDuracionMins || 0, 10);
  if (!mins) return '';
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  if (h && m) return ` (${h}h ${m}m)`;
  if (h) return ` (${h}h)`;
  return ` (${m}m)`;
}


// ====== Helpers de Meet ======
// Meet considerado "activo" si está pendiente o confirmada
function hayMeetActivo(t) {
  const est = (t.tiMeetEstado || "").toLowerCase();
  return est === "pendiente" || est === "confirmada";
}

// Visita activa: igual lógica
function hayVisitaActiva(t) {
  const est = (t.tiVisitaEstado || "").toLowerCase();
  return est === "pendiente" || est === "confirmada" || est === "requiere_folio" || est === "folio_generado";
}

function requiereFolioVisita(t) {
  const est = (t.tiVisitaEstado || "").toLowerCase();
  return est === "requiere_folio";
}

function textoVisitaEstado(t) {
  const est = (t.tiVisitaEstado || "").toLowerCase();
  if (!est) return "Sin visita";
  if (est === "pendiente") return "Visita pendiente de realizar";
  if (est === "confirmada") return "Visita confirmada";
  if (est === "cancelada") return "Visita cancelada";
  return "Estado de visita: " + t.tiVisitaEstado;
}
function textoVisitaAutor(t) {
  const modo = (t.tiVisitaModo || "").toLowerCase();   // 'cliente' | 'ingeniero'
  const actor = (t.tiVisitaActor || "").toLowerCase(); // ej. 'propuesta_cliente', 'asignada_cliente', 'propuesta_ingeniero', etc.

  if (!modo && !actor) return "—";

  if (modo === "cliente") {
    if (actor.includes("propuesta")) return "Propuesta por el cliente";
    if (actor.includes("asignada")) return "Asignada por el cliente";
    return "Definida por el cliente";
  }

  if (modo === "ingeniero") {
    if (actor.includes("propuesta")) return "Propuesta por MR Solutions";
    if (actor.includes("asignada")) return "Asignada por MR Solutions";
    return "Definida por MR Solutions";
  }

  return t.tiVisitaActor || "—";
}


// ====== Render del detalle con Meet corregido ======
function renderDetalleTicket(data, codigoTicket) {
  const procN = norm(data.tiProceso || "");
  const progreso = Math.max(0, PROCESOS_12.indexOf(procN) + 1);
  const progresoPorcentaje = Math.round(
    (progreso / PROCESOS_12.length) * 100
  );
  const badgeColor = badgeCritClass(data.tiNivelCriticidad);

  // Meet: editable solo en proceso "meet"
  const editableMeet = procN === "meet";
  const hayMeet = hayMeetActivo(data);

  // mínimo para visitas: mañana
  const hoy = new Date();
  hoy.setDate(hoy.getDate() + 1);
  const minVisita = hoy.toISOString().slice(0, 10); // 'YYYY-MM-DD'

  const codigoHtml = codigoTicket
    ? `<div class="text-muted fw-semibold mb-1" style="letter-spacing:.04em;font-size:.8rem;">
         ${codigoTicket}
       </div>`
    : "";

  // --- Vista principal ---
  let html = `
    <div class="text-center">
      <img src="../img/Equipos/${(data.maNombre || "").toLowerCase()}/${data.eqModelo}.png"
           alt="Equipo" class="img-fluid mb-3" style="max-height:200px;">
    </div>
    ${codigoHtml}
    <h5>${data.eqModelo}</h5>
    <p><b>SN:</b> ${data.peSN || "—"}</p>
    <div class="d-flex align-items-center">
      <img src="../img/Marcas/${(data.maNombre || "").toLowerCase()}.png"
           alt="${data.maNombre || ""}" style="width:50px; height:auto; margin-right:10px;">
      <span>${data.maNombre || ""}</span>
    </div>
    <hr>  

    <p><b>Descripción:</b><br>${data.tiDescripcion || "—"}</p>
    <p><b>Estado:</b> ${data.tiEstatus || "—"}</p>
    <p><b>Proceso actual:</b> ${data.tiProceso || "—"}</p>
    <div class="progress mb-2">
      <div class="progress-bar" role="progressbar"
           style="width:${progresoPorcentaje}%;" aria-valuenow="${progreso}"
           aria-valuemin="0" aria-valuemax="${PROCESOS_12.length}">
        ${progreso}/${PROCESOS_12.length}
      </div>
    </div>
    <div class="d-flex justify-content-between">
      ${PROCESOS_12.map(
    (p, i) =>
      `<span class="small ${i < progreso ? "text-success" : "text-muted"
      }" style="font-size:0.75em;">●</span>`
  ).join("")}
    </div>
    <hr>
    <p><b>Nivel de Criticidad:</b> <span class="${badgeColor}">
      ${"Nivel " + (data.tiNivelCriticidad || "—")}
    </span></p>
    <p><b>Fecha de Creación:</b> ${fmtDate(data.tiFechaCreacion)}</p>
    <p><b>Fecha/Hora de Visita:</b> ${fmtDateTime(data.tiVisita)}</p>
    
    <hr>
    <!-- Bloque Meet solo estado -->
    <div class="mb-2">
      <h6 class="mb-1">Meet</h6>
      <p class="mb-1">
        <b>Estado:</b> ${data.tiMeetEstado || "—"}
        &nbsp;–&nbsp;<b>Asignado por:</b> ${data.tiMeetAutorNombre || "—"}
      </p>
      <p class="mb-1"><b>Plataforma:</b> ${data.tiMeetPlataforma || "—"}</p>
      <p class="mb-1"><b>Fecha/Hora:</b> ${fmtMeetFechaHora(data) || "—"}</p>
    </div>
  `;
  // --- Folio de entrada (solo si la visita requiere folio) ---
  if (requiereFolioVisita(data)) {
    html += `
      <hr>
      <div class="card border-0 mb-3" style="background:#fff7e6;">
        <div class="card-body py-2 px-3">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <strong>Folio de entrada pendiente</strong>
              <div class="small text-muted">
                El ingeniero ya envió sus datos de acceso. Registra el folio de entrada del sitio.
              </div>
            </div>
            <button type="button"
                    class="btn btn-sm btn-outline-primary"
                    onclick="abrirCapturaFolio(${data.tiId})">
              Capturar folio
            </button>
          </div>
        </div>
      </div>
    `;
  }
  // Bloque de acceso / folio de entrada
  if (data.tiFolioEntrada) {
    html += `
    <p><b>Folio de entrada:</b> ${data.tiFolioEntrada}</p>
    ${data.tiFolioArchivo
        ? `<p><a href="../uploads/folios/${data.tiFolioArchivo}" target="_blank">
             Ver comprobante de entrada
           </a></p>`
        : ''
      }
  `;
  }


  // Botones rápidos de meet (solo si es proceso meet y NO hay meet activo)
  if (editableMeet && !hayMeet) {
    html += `
      <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button"
                class="btn btn-sm btn-outline-primary"
                data-meet-action="proponer"
                data-ticket-id="${data.tiId}">
          Proponer meet
        </button>
        <button type="button"
                class="btn btn-sm btn-outline-success"
                data-meet-action="asignar"
                data-ticket-id="${data.tiId}">
          Asignar meet
        </button>
      </div>
    `;
  } else if (editableMeet && hayMeet) {
    html += `
      <div class="d-flex flex-wrap gap-2 mb-2">
        <button type="button"
                class="btn btn-sm btn-outline-danger"
                onclick="cancelarMeet(${data.tiId})">
          Cancelar meet
        </button>
      </div>
      <div class="alert alert-info py-1 px-2 mb-2">
        Ya existe una fecha de meet pendiente/confirmada.
        (Luego aquí añadimos reprogramar/cancelar).
      </div>
    `;
  }

  html += `<hr>`;

  // 1) LOGS
  if (procN === "logs") {
    html += `
      <div class="card border-0" style="background:#f8f9fb">
        <div class="card-body p-3">
          <h6 class="mb-3">Subir logs</h6>
          <div class="row g-2 align-items-center">
            <div class="col-12 col-md-8">
              <input type="file" id="logsFile_${data.tiId}" class="form-control"
                     accept=".log,.txt,.zip,.tar,.gz,.7z,.rar, text/plain, application/zip, application/x-7z-compressed, application/x-rar-compressed">
            </div>
            <div class="col-12 col-md-4 d-grid">
              <button class="btn btn-primary" onclick="uploadLogs(${data.tiId})">
                Subir logs
              </button>
            </div>
          </div>
          <small class="text-muted d-block mt-2">
            Acepta .log, .txt o comprimidos (.zip/.7z/.rar).
          </small>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-outline-secondary"
                    onclick="openHelpLogs('${data.maNombre || ""}', '${data.eqModelo || ""}')">
              ¿Cómo extraer los logs?
            </button>
            <button class="btn btn-outline-info" onclick="pedirAyudaCorreo(${data.tiId})">
              Pedir ayuda por correo
            </button>
          </div>
        </div>
      </div>
    `;
  }

  // 2) ASIGNACIÓN DE FECHA (cliente)
  if (procN === "visita") {
    const hayV = hayVisitaActiva(data);


    html += `<div class="mb-2">
        <h6 class="mb-1">Visita</h6>
        <p class="mb-1">
          <b>Estado:</b> ${data.tiVisitaEstado || '—'}
          &nbsp;–&nbsp;<b>Asignado por:</b> ${data.tiVisitaAutorNombre || '—'}
        </p>
        <p class="mb-1"><b>Fecha/Hora:</b> ${fmtVisitaFechaHora(data) || '—'}</p>
      </div>`;

    if (!hayV) {
      // Igualito que Meet, pero llamando a VISITA
      html += `
        <button type="button"
                class="btn btn-sm btn-outline-danger"
                onclick="cancelarVisita(${data.tiId})">
          Cancelar visita
        </button>
      </div>
      <div class="d-flex flex-wrap justify-content-end gap-1">
        <button type="button"
                class="btn btn-sm btn-outline-success"
                data-visita-action="asignar"
                data-ticket-id="${data.tiId}">
          Asignar ventana
        </button>
      </div>
    `;
    } else {
      html += `
      <div class="d-flex flex-wrap justify-content-end gap-1 mb-1">
        <button type="button"
                class="btn btn-sm btn-outline-danger"
                onclick="cancelarVisita(${data.tiId})">
          Cancelar visita
        </button>
      </div>
      <div class="d-flex flex-wrap justify-content-end gap-1">
        <button type="button"
                class="btn btn-sm btn-outline-primary"
                data-visita-action="asignar"
                data-ticket-id="${data.tiId}">
          Reprogramar ventana
        </button>
        <button type="button"
                class="btn btn-sm btn-outline-success"
                data-visita-action="confirmar"
                data-ticket-id="${data.tiId}">
          Confirmar ventana
        </button>
      </div>
      <div class="alert alert-info py-1 px-2 mt-1 mb-0">
        Ya existe una ventana de visita pendiente/confirmada.
      </div>
    `;
    }
  }


  // Inyecta contenido principal
  const cont = document.getElementById("offcanvasContent");
  cont.innerHTML = html;
}

function guardarFolioEntrada(tiId) {
  const form = document.getElementById('formFolioEntrada');
  if (!form) return;

  const folioInput = document.getElementById('folioEntrada');
  const folio = (folioInput?.value || '').trim();

  if (!folio) {
    alert('Ingresa un folio de entrada.');
    return;
  }

  if (!/^[A-Za-z0-9\-_]{3,100}$/.test(folio)) {
    alert('El folio sólo puede contener letras, números, guion y guion bajo (3-100 caracteres).');
    return;
  }

  const fd = new FormData(form);
  fd.set('tiId', tiId);
  fd.set('folio', folio);

  fetch('../php/acceso_guardar_folio.php', {
    method: 'POST',
    body: fd
  })
    .then(r => r.json())
    .then(res => {
      if (!res?.success) {
        throw new Error(res?.error || 'No se pudo guardar el folio.');
      }
      alert('Folio guardado correctamente.');
      // Recargar detalle para ver el folio en modo "solo lectura"
      abrirDetalle(tiId);
    })
    .catch(err => {
      console.error(err);
      alert(err.message || 'Error al guardar el folio.');
    });
}
// ===== Enviar encuesta ======
function omitirEncuesta(tiId) {
  fetch(`../php/encuesta_omitir.php`, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `tiId=${encodeURIComponent(tiId)}`
  })
    .then(r => r.json())
    .then(res => {
      if (!res?.success) throw new Error(res?.error || "Error");
      const modal = document.getElementById(`modalEncuesta_${tiId}`);
      if (modal) bootstrap.Modal.getInstance(modal)?.hide();
      // Opcional: recargar tickets/estado
    })
    .catch(() => alert("No fue posible omitir la encuesta."));
}

// ====== Abrir detalle (usa prefijo de cliente) ======
function abrirDetalle(tiId, clientePrefix = null) {
  const body = document.getElementById("offcanvasContent");
  if (body) {
    body.innerHTML = `
      <div class="text-center py-4">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2">Cargando...</p>
      </div>`;
  }

  fetch(`../php/detalle_ticket.php?tiId=${encodeURIComponent(tiId)}`, {
    cache: "no-store",
  })
    .then((r) => r.json())
    .then((json) => {
      if (!json?.success) {
        if (body) {
          body.innerHTML = `<p class="text-danger">${json?.error || "No se pudo cargar el detalle."
            }</p>`;
        }
        return;
      }

      const tk = json.ticket;

      // Si no viene prefijo por parámetro, lo calculamos desde el nombre del cliente
      const prefix =
        clientePrefix ||
        getClientePrefix(tk.clNombre || tk.clienteNombre || "");

      const codigo = `${prefix}-${Number(tiId) || "—"}`;

      // Opcional: actualizar el título del offcanvas si tienes un ID para eso
      const titleEl = document.getElementById("offcanvasTicketLabel"); // ajusta al id real
      if (titleEl) {
        titleEl.textContent = codigo;
      }

      // Render del detalle con el código tipo ENE-10
      renderDetalleTicket(tk, codigo);
    })
    .catch(() => {
      if (body) {
        body.innerHTML = `<p class="text-danger">Error de red.</p>`;
      }
    });
}


function abrirCapturaFolio(tiId) {
  Swal.fire({
    title: 'Folio de entrada',
    html: `
      <input id="swal_folio" class="swal2-input" placeholder="Folio alfanumérico">
      <input id="swal_archivo" type="file" class="swal2-file" accept=".pdf,image/*">
      <textarea id="swal_coment" class="swal2-textarea" placeholder="Comentario opcional"></textarea>
    `,
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Guardar',
    cancelButtonText: 'Cancelar',
    preConfirm: () => {
      const folio = document.getElementById('swal_folio').value.trim();
      const fileElem = document.getElementById('swal_archivo');
      const coment = document.getElementById('swal_coment').value.trim();

      if (!folio) {
        Swal.showValidationMessage('Escribe el folio de entrada.');
        return false;
      }

      const fd = new FormData();
      fd.append('tiId', tiId);
      fd.append('folio', folio);
      fd.append('coment', coment);
      if (fileElem.files[0]) {
        fd.append('archivo', fileElem.files[0]);
      }

      return fetch('../php/visita_folio_guardar.php', {
        method: 'POST',
        body: fd
      })
        .then(r => r.json())
        .then(res => {
          if (!res || !res.success) {
            throw new Error(res?.error || 'No se pudo guardar el folio.');
          }
          return res;
        })
        .catch(err => {
          Swal.showValidationMessage(err.message);
        });
    }
  }).then(result => {
    if (result.isConfirmed) {
      Swal.fire('Guardado', 'El folio de entrada se registró correctamente.', 'success');
      // Refrescar cards + detalle
      cargarTicketsPorSede();
      abrirDetalle(tiId);
    }
  });
}

