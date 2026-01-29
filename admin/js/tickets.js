let VISTA_TICKETS = 'tabla'; // 'tabla' | 'cards'
document.addEventListener('DOMContentLoaded', () => {
  inicializar();

  // Toggle de vista
  const btnVistaTabla  = document.getElementById('btnVistaTabla');
  const btnVistaCards  = document.getElementById('btnVistaCards');

  if (btnVistaTabla) {
    btnVistaTabla.addEventListener('click', (e) => {
      e.preventDefault();
      VISTA_TICKETS = 'tabla';
      btnVistaTabla.classList.add('active');
      btnVistaCards && btnVistaCards.classList.remove('active');
      // repintar tickets del cliente actualmente seleccionado
      repintarTicketsClienteActual();
    });
  }

  if (btnVistaCards) {
    btnVistaCards.addEventListener('click', (e) => {
      e.preventDefault();
      VISTA_TICKETS = 'cards';
      btnVistaCards.classList.add('active');
      btnVistaTabla && btnVistaTabla.classList.remove('active');
      repintarTicketsClienteActual();
    });
  }
});
let CLIENTE_ACTUAL_ID = null;
let CLIENTE_ACTUAL_TICKETS = []; // cache de tickets del cliente

// Ejemplo cuando haces click en un cliente
function seleccionarCliente(cliente) {
  CLIENTE_ACTUAL_ID = cliente.clId;
  CLIENTE_ACTUAL_TICKETS = cliente.tickets || []; // o como lo recibas

  const lblCli = document.getElementById('lblClienteSeleccionado');
  const lblTot = document.getElementById('lblTotalTickets');
  const bloque = document.getElementById('bloqueTickets');

  if (lblCli) lblCli.textContent = cliente.clNombre || `Cliente #${cliente.clId}`;
  if (lblTot) lblTot.textContent = CLIENTE_ACTUAL_TICKETS.length;
  if (bloque) bloque.classList.remove('d-none');

  repintarTicketsClienteActual();
}
function repintarTicketsClienteActual() {
  if (!CLIENTE_ACTUAL_TICKETS || !CLIENTE_ACTUAL_TICKETS.length) {
    const cont = document.getElementById('contenedorTickets');
    if (cont) {
      cont.innerHTML = `
        <div class="alert alert-light border text-center small mb-0">
          No hay tickets para este cliente con los filtros actuales.
        </div>`;
    }
    return;
  }

  if (VISTA_TICKETS === 'tabla') {
    renderTicketsTablaPorZonaSede(CLIENTE_ACTUAL_TICKETS);
  } else {
    renderTicketsCardsPorZonaSede(CLIENTE_ACTUAL_TICKETS);
  }
}
function renderTicketsTablaPorZonaSede(tickets) {
  const cont = document.getElementById('contenedorTickets');
  if (!cont) return;

  // agrupar por zona + sede
  const grupos = agruparPorZonaSede(tickets);

  let html = '';

  Object.keys(grupos).forEach(key => {
    const grupo = grupos[key];
    const titulo = grupo.titulo;       // "Zona Norte · Sede Toluca" o similar
    const lista  = grupo.tickets || [];

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
      const prefix  = t.ticketPrefix || 'TIC';
      const codigo  = `${prefix} ${t.tiId}`;
      const equipo  = t.eqModelo || 'Equipo';
      const sn      = t.peSN || '';
      const marca   = t.maNombre || '';

      html += `
        <tr>
          <td class="align-middle" style="width: 20%;">
            <div class="mrs-ticket-main">
              <div>
                <span class="${badgeEstadoTicket(t.tiEstatus)}">
                  ${t.tiEstatus || "—"}
                </span>
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
  const cont = document.getElementById('contenedorTickets');
  if (!cont) return;

  const grupos = agruparPorZonaSede(tickets);
  let html = '';

  Object.keys(grupos).forEach(key => {
    const grupo = grupos[key];
    const titulo = grupo.titulo;
    const lista  = grupo.tickets || [];

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
        <div class="card-body">
    `;

    lista.forEach(t => {
      const prefix  = t.ticketPrefix || 'TIC';
      const codigo  = `${prefix} ${t.tiId}`;
      const equipo  = t.eqModelo || 'Equipo';
      const sn      = t.peSN || '';
      const marca   = t.maNombre || '';

      html += `
        <div class="card mrs-ticket-card border-0 mb-2">
          <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-2">
            <div style="min-width: 180px;">
              <div>
                <span class="${badgeEstadoTicket(t.tiEstatus)}">
                  ${t.tiEstatus || "—"}
                </span>
              </div>
              <div class="d-flex flex-wrap gap-1 mt-1">
                ${t.tiProceso ? `<span class="badge-pill-soft ${badgeProcesoTicket(t.tiProceso)}">${t.tiProceso}</span>` : ""}
                ${t.tiTipoTicket ? `<span class="${badgeTipoTicket(t.tiTipoTicket)}">${t.tiTipoTicket}</span>` : ""}
              </div>
              <div class="mrs-ticket-id mt-1">
                ${codigo}
              </div>
            </div>

            <div class="flex-grow-1">
              <div class="mrs-ticket-title">
                ${equipo}
              </div>
              <div class="mrs-ticket-sub">
                SN: <span class="fw-medium">${sn}</span>
                ${marca ? ` · ${marca}` : ""}
              </div>
              <div class="mrs-ticket-meta mt-1">
                ${fmtVisitaFechaHora(t)
                  ? `<span class="me-2"><i class="bi bi-tools me-1"></i>${fmtVisitaFechaHora(t)}${fmtVisitaDuracion(t)}</span>`
                  : ''
                }
                ${t.tiExtra
                  ? `<span class="text-truncate d-block" style="max-width: 320px;">${t.tiExtra}</span>`
                  : ''
                }
              </div>
            </div>

            <div class="text-md-end">
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
function agruparPorZonaSede(tickets) {
  const grupos = {};

  tickets.forEach(t => {
    const zona = t.czNombre || 'Sin zona';
    const sede = t.csNombre || 'Sin sede';
    const key  = `${zona}||${sede}`;

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
