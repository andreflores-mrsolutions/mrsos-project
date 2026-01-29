// Helpers mínimos
function badgeEstadoTicket(estatus) {
  const e = (estatus || "").toLowerCase();
  if (e === "abierto") return "badge-pill-soft bg-success-subtle text-success-emphasis";
  if (e === "pospuesto") return "badge-pill-soft bg-warning-subtle text-warning-emphasis";
  if (e === "cerrado") return "badge-pill-soft bg-secondary-subtle text-secondary-emphasis";
  return "badge-pill-soft bg-light text-body-secondary";
}

function badgeTipoTicket(tipo) {
  const t = (tipo || "").toLowerCase();
  if (t === "servicio") return "badge-pill-soft bg-primary-subtle text-primary-emphasis";
  if (t === "preventivo") return "badge-pill-soft bg-info-subtle text-info-emphasis";
  if (t === "extra") return "badge-pill-soft bg-secondary-subtle text-secondary-emphasis";
  return "badge-pill-soft bg-light text-body-secondary";
}

function badgeProcesoTicket(proceso) {
  const p = (proceso || "").toLowerCase();
  if (p === "logs") return "badge bg-primary text-white";
  if (p === "meet") return "badge bg-success text-white";
  if (p === "visita") return "badge bg-dark text-white";
  if (p === "encuesta satisfaccion") return "badge bg-warning text-dark";
  return "badge-pill-soft bg-info text-body-secondary";
}
// Convierte nombre de cliente a prefijo de 3 letras: "Enel" → "ENE"
function getClientePrefix(nombre) {
  if (!nombre) return "UNK"; // Unknown
  return nombre
    .normalize("NFD")               // quita acentos
    .replace(/[\u0300-\u036f]/g, "") // elimina restos de acento
    .replace(/[^A-Za-z]/g, "")       // solo letras
    .substring(0, 3)
    .toUpperCase();
}





let MRS_TICKETS_SEDES = [];
let MRS_TICKETS_VISTA = "tabla"; // 'tabla' | 'cards'
let MRS_TICKETS_FILTER = {
  estado: "all",       // all | abierto
  criticidad: "all",   // all | alta
  search: ""           // texto libre
};

function aplicarFiltros(baseSedes) {
  const { estado, criticidad, search } = MRS_TICKETS_FILTER;
  const term = (search || "").trim().toLowerCase();

  const sedesFiltradas = [];

  (baseSedes || []).forEach(s => {
    const prefixCliente = getClientePrefix(s.clNombre);
    const tickets = (s.tickets || []).filter(t => {
      // Filtro por estado
      if (estado === "abierto") {
        if ((t.tiEstatus || "").toLowerCase() !== "abierto") return false;
      }

      // Filtro por criticidad
      if (criticidad === "1" || criticidad === "alta") {
        if ((t.tiNivelCriticidad || "").toLowerCase() !== "1") return false;
      }

      // Filtro de búsqueda
      if (term) {
        const modelo = (t.eqModelo || "").toLowerCase();
        const marca = (t.maNombre || "").toLowerCase();
        const sn = (t.peSN || "").toLowerCase();
        const codigo = `${prefixCliente}-${Number(t.tiId) || ""}`.toLowerCase();

        if (
          !modelo.includes(term) &&
          !marca.includes(term) &&
          !sn.includes(term) &&
          !codigo.includes(term)
        ) {
          return false;
        }
      }

      return true;
    });

    if (tickets.length) {
      sedesFiltradas.push({
        ...s,
        tickets
      });
    }
  });

  return sedesFiltradas;
}

function setVistaTickets(vista) {
  MRS_TICKETS_VISTA = vista === "cards" ? "cards" : "tabla";

  const toggle = document.getElementById("vistaTicketsToggle");
  if (toggle) {
    toggle.querySelectorAll("button[data-vista]").forEach(btn => {
      btn.classList.toggle(
        "active",
        btn.getAttribute("data-vista") === MRS_TICKETS_VISTA
      );
    });
  }

  if (!Array.isArray(MRS_TICKETS_SEDES) || !MRS_TICKETS_SEDES.length) {
    const wrap = document.getElementById("wrapTicketsSedes");
    if (wrap) {
      wrap.innerHTML = `
        <div class="text-center py-4">
          <i class="bi bi-emoji-smile mb-2" style="font-size:2rem; opacity:.4;"></i>
          <p class="mb-1 fw-semibold">No hay tickets abiertos en tus sedes</p>
          <p class="text-muted mb-3" style="font-size:.85rem;">
            Todo está bajo control por ahora. Si necesitas ayuda, puedes levantar un nuevo ticket.
          </p>
          <a href="tickets.php?nuevo=1" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Nuevo ticket
          </a>
        </div>`;
    }
    return;
  }

  const sedesFiltradas = aplicarFiltros(MRS_TICKETS_SEDES);

  if (MRS_TICKETS_VISTA === "cards") {
    renderTicketsPorSedeCards(sedesFiltradas);
  } else {
    renderTicketsPorSedeTabla(sedesFiltradas);
  }
}

function cargarTicketsPorSede() {
  const wrap = document.getElementById("wrapTicketsSedes");
  if (wrap) {
    wrap.innerHTML = '<p class="text-muted mb-0">Cargando tickets...</p>';
  }

  fetch("../php/obtener_tickets_sedes.php", { cache: "no-store" })
    .then(r => r.json())
    .then(json => {
      if (!json || !json.success) {
        if (wrap) {
          wrap.innerHTML = `<p class="text-danger">${(json && json.error) || "No se pudieron cargar los tickets."
            }</p>`;
        }
        return;
      }
      MRS_TICKETS_SEDES = json.sedes || [];
      setVistaTickets(MRS_TICKETS_VISTA);
    })
    .catch(() => {
      if (wrap) {
        wrap.innerHTML = `<p class="text-danger">Error de red al cargar tickets.</p>`;
      }
    });
}

// Inicializar en DOM ready
document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.getElementById("vistaTicketsToggle");
  if (toggle) {
    toggle.addEventListener("click", e => {
      const btn = e.target.closest("button[data-vista]");
      if (!btn) return;
      const vista = btn.getAttribute("data-vista");
      setVistaTickets(vista);
    });

    ["btnRecargar1", "btnRecargar"].forEach(id => {
      const btn = document.getElementById(id);
      if (btn) btn.addEventListener("click", cargarTicketsPorSede);
    });
  }

  const filtros = document.getElementById("filtrosTickets");
  if (filtros) {
    filtros.addEventListener("click", e => {
      const btn = e.target.closest("button[data-filter]");
      if (!btn) return;

      const filter = btn.getAttribute("data-filter");

      // estado + criticidad según botón
      if (filter === "all") {
        MRS_TICKETS_FILTER.estado = "all";
        MRS_TICKETS_FILTER.criticidad = "all";
      } else if (filter === "abierto") {
        MRS_TICKETS_FILTER.estado = "abierto";
      } else if (filter === "alta") {
        MRS_TICKETS_FILTER.criticidad = "alta";
      }

      // marcar activo
      filtros.querySelectorAll("button[data-filter]").forEach(b => {
        b.classList.toggle("active", b === btn);
      });

      setVistaTickets(MRS_TICKETS_VISTA);
    });
  }

  const searchInput = document.getElementById("searchTickets");
  if (searchInput) {
    searchInput.addEventListener("input", () => {
      MRS_TICKETS_FILTER.search = searchInput.value || "";
      setVistaTickets(MRS_TICKETS_VISTA);
    });
  }

  // Primera carga
  cargarTicketsPorSede();
});

function renderTicketsPorSedeTabla(sedesArr) {
  const wrap = document.getElementById("wrapTicketsSedes");
  if (!wrap) return;

  const sedes = Array.isArray(sedesArr)
    ? sedesArr.filter(s => s.tickets && s.tickets.length)
    : [];

  if (!sedes.length) {
    wrap.innerHTML = `
        <div class="text-center py-4">
            <i class="bi bi-emoji-smile mb-2" style="font-size:2rem; opacity:.4;"></i>
            <p class="mb-1 fw-semibold">No hay tickets abiertos en tus sedes</p>
            <p class="text-muted mb-3" style="font-size:.85rem;">
            Todo está bajo control por ahora. Si necesitas ayuda, puedes levantar un nuevo ticket.
            </p>
            <a href="tickets.php?nuevo=1" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Nuevo ticket
            </a>
        </div>
        `;

    return;
  }

  const html = sedes.map(s => {
    const tickets = s.tickets || [];

    const filas = tickets.map(t => {
      const modelo = t.eqModelo || "";
      const version = t.eqVersion ? ` ${t.eqVersion}` : "";
      const equipo = (modelo + version).trim() || "Equipo sin modelo";
      const marca = t.maNombre || "";
      const sn = t.peSN || "SN no registrado";
      const prefix = getClientePrefix(s.clNombre);
      const codigo = `${prefix}-${Number(t.tiId) || "—"}`;

      return `
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
    }).join("");

    return `
      <div class="mrs-sede-block">
        <div class="mrs-sede-header">
          <div class="mrs-sede-title">
            ${s.clNombre ? `${s.clNombre} · ` : ""}${s.csNombre || "Sede sin nombre"}
          </div>
          <span class="mrs-sede-chip">Tickets activos</span>
          <span class="mrs-sede-count">${tickets.length} ticket(s)</span>
        </div>

        <div class="table-responsive">
          <table class="table mrs-table-tickets align-middle">
            <tbody>
              ${filas}
            </tbody>
          </table>
        </div>
      </div>
    `;
  }).join("");

  wrap.innerHTML = html;
}

function renderTicketsPorSedeCards(sedesArr) {
  const wrap = document.getElementById("wrapTicketsSedes");
  if (!wrap) return;

  const sedes = Array.isArray(sedesArr)
    ? sedesArr.filter(s => s.tickets && s.tickets.length)
    : [];

  if (!sedes.length) {
    wrap.innerHTML = '<p class="text-muted mb-0">No hay tickets abiertos en tus sedes.</p>';
    return;
  }

  const html = sedes.map(s => {
    const tickets = s.tickets || [];
    const prefixCliente = getClientePrefix(s.clNombre);

    const cards = tickets.map(t => {
      const modelo = t.eqModelo || "";
      const version = t.eqVersion ? ` ${t.eqVersion}` : "";
      const equipo = (modelo + version).trim() || "Equipo sin modelo";
      const marca = t.maNombre || "";
      const sn = t.peSN || "SN no registrado";
      const codigo = `${prefixCliente}-${Number(t.tiId) || "—"}`;
      const crit = t.tiNivelCriticidad; // 'alta','media','baja'


      return `
        <div class="col-12 col-md-6 col-xl-4">
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
                ${renderQuickActions(t, prefixCliente)}
              </div>
              <a href="#"
                class="small"
                onclick="abrirDetalle(${Number(t.tiId)}, '${prefixCliente}')"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasTicket">
                Ver detalle <i class="bi bi-arrow-right-short"></i>
              </a>
            </div>
          </div>
        </div>
      `;
    }).join("");

    return `
      <div class="mrs-sede-block">
        <div class="mrs-sede-header">
          <div class="mrs-sede-title">
            ${s.clNombre ? `${s.clNombre} · ` : ""}${s.csNombre || "Sede sin nombre"}
          </div>
          <span class="mrs-sede-chip">Tickets activos</span>
          <span class="mrs-sede-count">${tickets.length} ticket(s)</span>
        </div>

        <div class="row g-3">
          ${cards}
        </div>
      </div>
    `;
  }).join("");

  wrap.innerHTML = html;
}






document.addEventListener("DOMContentLoaded", () => {
  cargarTicketsPorSede();
});


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




// Propuesta hecha por MR y esperando respuesta del cliente
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

  // 3) ENCUESTA
  if (procN === "encuesta satisfaccion") {
    html += `
      <div class="d-grid">
        <button class="btn btn-warning text-dark"
                data-bs-toggle="modal"
                data-bs-target="#modalEncuesta_${data.tiId}">
          Responder encuesta de satisfacción
        </button>
      </div>

      <div class="modal fade" id="modalEncuesta_${data.tiId}"
           tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form class="modal-content" onsubmit="enviarEncuesta(event, ${data.tiId})">
            <div class="modal-header">
              <h5 class="modal-title">Encuesta de satisfacción</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label d-block mb-2">
                  1) ¿Cómo calificas el servicio recibido?
                </label>
                <div class="d-flex justify-content-between px-1">
                  ${[
        { v: 5, t: "Excelente", e: "😄" },
        { v: 4, t: "Bueno", e: "🙂" },
        { v: 3, t: "Regular", e: "😐" },
        { v: 2, t: "Malo", e: "🙁" },
        { v: 1, t: "Muy malo", e: "😣" },
      ].map(x => `
                    <label class="text-center" style="cursor:pointer;">
                      <input type="radio" name="enc_smile_${data.tiId}"
                             value="${x.v}"
                             class="form-check-input d-block mx-auto mb-1">
                      <div style="font-size:1.6rem; line-height:1;">${x.e}</div>
                      <small class="d-block">${x.t}</small>
                    </label>
                  `).join("")}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">
                  2) ¿Qué mejorarías del servicio de MR?
                </label>
                <textarea class="form-control" id="enc_mejora_${data.tiId}"
                          rows="3" maxlength="600"></textarea>
              </div>

              <div class="mb-0">
                <label class="form-label">
                  3) ¿Qué tal te pareció la plataforma y qué cambiarías?
                </label>
                <textarea class="form-control" id="enc_plataforma_${data.tiId}"
                          rows="3" maxlength="600"></textarea>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                Cancelar
              </button>
              <button type="submit" class="btn btn-primary">
                Enviar
              </button>
            </div>
          </form>
        </div>
      </div>
    `;
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

// === Acciones rápidas según proceso ===

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
      <div class="d-flex flex-column align-items-end gap-1">
        <button type="button"
                class="btn btn-sm btn-outline-primary"
                onclick="abrirDetalle(${tiId}, '${prefixCliente}')"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasTicket">
          <i class="bi bi-calendar-event me-1"></i>Ver visita
        </button>
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
  }

  return html;
}
// === Manejo de acciones rápidas de meet ===
document.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-meet-action]");
  if (!btn) return;

  const action = btn.getAttribute("data-meet-action");
  const tiId = btn.getAttribute("data-ticket-id");

  if (!tiId) return;

  if (action === "proponer") {
    // abrirModalMeetPropuesta(tiId);
  } else if (action === "asignar") {
    // abrirModalMeetAsignar(tiId);
  } else if (action === "confirmar") {
    // confirmarMeetExistente(tiId);      // ← función que llama a meet_actualizar.php con acción 'confirmar'
  } else if (action === "reprogramar") {
    // abrirModalMeetPropuesta(tiId, { reprogramar: true }); // reutilizas el modal de propuesta
  }
});

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








// Area de estadísticas

let areaChart, donutTipo, donutEstatus;

function initCharts() {
  const areaCtx = document.getElementById('areaChart')?.getContext('2d');
  const tipoCtx = document.getElementById('donutTipo')?.getContext('2d');
  const estCtx = document.getElementById('donutEstatus')?.getContext('2d');

  if (!areaCtx || !tipoCtx || !estCtx) return;

  areaChart = new Chart(areaCtx, {
    type: 'line',
    data: {
      labels: [],
      datasets: [{
        data: [],
        fill: true,
        backgroundColor: 'rgba(115,96,255,0.2)',
        borderColor: 'rgba(115,96,255,1)',
        tension: 0.35,
        pointRadius: 0
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        x: { display: true },
        y: { display: true, beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });

  donutTipo = new Chart(tipoCtx, {
    type: 'doughnut',
    data: {
      labels: [],
      datasets: [{
        data: [],
        backgroundColor: ['#7360ff', '#a29bfe', '#b2bec3', '#dfe6e9']
      }]
    },
    options: {
      cutout: '65%',
      plugins: { legend: { display: false } }
    }
  });

  donutEstatus = new Chart(estCtx, {
    type: 'doughnut',
    data: {
      labels: [],
      datasets: [{
        data: [],
        backgroundColor: ['#28a745', '#6c757d', '#0d6efd', '#adb5bd']
      }]
    },
    options: {
      cutout: '65%',
      plugins: { legend: { display: false } }
    }
  });
}

function updateArea(labels, data) {
  if (!areaChart) return;
  areaChart.data.labels = labels || [];
  areaChart.data.datasets[0].data = data || [];
  areaChart.update();
}

function updateDonutTipo(map) {
  if (!donutTipo) return;
  const labels = ['Servicio', 'Preventivo', 'Extra', 'Otros'];
  donutTipo.data.labels = labels;
  donutTipo.data.datasets[0].data = labels.map(l => (map && map[l]) ? map[l] : 0);
  donutTipo.update();
}

function updateDonutEstatus(map) {
  if (!donutEstatus) return;
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
  if (clId) qs.set('clId', clId);

  fetch(`../php/estadisticas_mes.php${qs.toString() ? '?' + qs.toString() : ''}`)
    .then(r => r.json())
    .then(res => {
      if (!res?.success) throw new Error(res?.error || 'Error');

      updateArea(res.labels, res.data);
      updateDonutTipo(res.porTipo);
      updateDonutEstatus(res.porEstatus);
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
  // ... lo que ya tengas de tickets, filtros, etc.

  // Estadísticas
  initCharts();
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
    const ym = document.getElementById('mesFiltro')?.value || null;
    const csId = e.target.value || null;
    cargarEstadisticas({ ym, lastDays: ym ? null : 30, csId });
  });

  // Si quieres que TODOS los botones de recarga refresquen también las stats:
  document.querySelectorAll('.btnRecargar').forEach(btn => {
    btn.addEventListener('click', () => {
      // cargarTicketsPorSede();  // si ya lo tienes
      cargarEstadisticas({ lastDays: 30 });
    });
  });
});


