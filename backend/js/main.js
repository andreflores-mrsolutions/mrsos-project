function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  sidebar.classList.toggle("expand");

  // Opcional: Controlar el body para evitar scroll en móviles
  if (sidebar.classList.contains("expand")) {
    document.body.classList.add("sidebar-open");
  } else {
    document.body.classList.remove("sidebar-open");
  }
}

function cerrarOffcanvas(id = "offcanvasTicket") {
  const el = document.getElementById(id);
  if (!el) return;
  const oc = bootstrap.Offcanvas.getOrCreateInstance(el);
  oc.hide();
}

//Todo: Datos sos.php
// Supuesto fetch desde backend
$(document).ready(function () {
  // Simulación: sustituir por llamada real con AJAX
  $.getJSON("../php/getIndexData.php", function (data) {
    $("#nombreUsuario").text(data.nombre);
    $("#tipoPoliza").text(data.poliza);
    $("#totalTickets").text(data.ticketsAbiertos);
    $("#equiposPoliza").text(data.totalEquipos);
  });
});

// Orden oficial de procesos
const PROCESOS = [
  "asignacion",
  "revision inicial",
  "logs",
  "meet",
  "asignacion fecha cliente",
  "asignacion fecha ingeniero", // mismo paso
  "fecha asignada",
  "espera ventana",
  "espera visita",
  "en camino",
  "espera documentacion",
  "encuesta satisfaccion",
  "finalizado",
];

function procesoIdx(proc) {
  const i = PROCESOS.indexOf((proc || "").toLowerCase().trim());
  return i >= 0 ? i : 0;
}

function repaintDetalleProceso(nuevoProceso) {
  const idx = procesoIdx(nuevoProceso);
  const paso = idx + 1;
  const total = PROCESOS.length;
  const pct = Math.round((paso / total) * 100);

  // Texto de proceso
  const $txt = document.getElementById("detalleProcesoText");
  if ($txt) $txt.textContent = nuevoProceso;

  // Barra de progreso
  const $bar = document.getElementById("detalleProgressBar");
  if ($bar) {
    $bar.style.width = pct + "%";
    $bar.textContent = `${paso}/${total}`;
  }

  // Dots
  const $steps = document.getElementById("detalleSteps");
  if ($steps) {
    $steps.innerHTML = PROCESOS.map(
      (_, i) =>
        `<span class="small ${
          i < paso ? "text-success" : "text-muted"
        }" style="font-size:.75em;">●</span>`
    ).join("");
  }
}

/** (Opcional) refrescar listas/tablas sin recargar toda la página */
function refreshListados() {
  if (typeof cargarTicketsPorSede === "function") cargarTodosTickets(); // los bloques por sede
}

$(document).ready(function () {
  // Cargar los tickets al inicio y cuando cambian filtros

  cargarTodosTickets();
  $("#filtroEstado, #filtroMarca, #filtroProceso, #filtroTipoEquipo").change(
    cargarTicketsPorSede
  );
});

//TODO Reiniciar Filtros
$(document).ready(function () {
  // Cargar tickets iniciales

  // Cambio en los filtros
  $("#filtroEstado, #filtroMarca, #filtroProceso, #filtroTipoEquipo").change(
    cargarTicketsPorSede
  );

  // Botón reset filtros
  $("#resetFiltros").click(function () {
    $("#filtroEstado").val("Todo");
    $("#filtroMarca").val("");
    $("#filtroProceso").val("");
    $("#filtroTipoEquipo").val("");

    cargarTodosTickets();
  });

  // Botón recargar
  $("#btnRecargar").click(function () {
    cargarTodosTickets();
    cargarEstadisticas();
  });
});

//TODO Guardar la fecha seleccionada
function guardarFecha(ticketId) {
  const fecha = $(`#fecha_${ticketId}`).val();
  const hora = $(`#hora_${ticketId}`).val();

  if (!fecha || !hora) {
    alert("Por favor selecciona una fecha y hora.");
    return;
  }

  const tiVisita = `${fecha} ${hora}`;
  $.ajax({
    url: "../php/asignar_fecha.php",
    method: "POST",
    data: { ticketId, tiVisita },
    success: function (response) {
      console.log(response); // Para monitoreo
      if (response.success) {
        Swal.fire({
          title: "Fecha Asignada!",
          text: "Fecha asignada correctamente.",
          icon: "success",
        });
        $(`#modalFecha_${ticketId}`).modal("hide");
        cargarTodosTickets(); // Recargar para actualizar
      } else if (response.error) {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: response.error,
          footer: '<a href="#">¿Qué hacer en este caso?</a>',
        });
      } else {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "Error desconocido al asignar fecha.",
          footer: '<a href="#">¿Qué hacer en este caso?</a>',
        });
      }
    },
    error: function () {
      Swal.fire({
        icon: "error",
        title: "Error",
        text: "Error de comunicación con el servidor.",
        footer: '<a href="#">¿Qué hacer en este caso?</a>',
      });
    },
  });
}

//TODO ver mas detalles
// Función para llenar el off-canvas dinámico
// --- helpers ---
// ================== Helpers ==================
const PROCESOS_12 = [
  "asignacion",
  "revision inicial",
  "logs",
  "meet",
  "asignacion fecha cliente",
  "asignacion fecha ingeniero", // mismo paso
  "fecha asignada",
  "espera ventana",
  "espera visita",
  "en camino",
  "espera documentacion",
  "encuesta satisfaccion",
  "finalizado",
];

function norm(s = "") {
  return s
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}
function fmtDate(iso) {
  if (!iso) return "—";
  const d = new Date(iso.replace(" ", "T"));
  return isNaN(d) ? iso : d.toLocaleDateString();
}
function fmtDateTime(iso) {
  if (!iso) return "—";
  const d = new Date(iso.replace(" ", "T"));
  return isNaN(d) ? iso : d.toLocaleString();
}
function badgeCritClass(n) {
  n = String(n || "").trim();
  if (n === "1") return "badge bg-danger";
  if (n === "2") return "badge bg-warning text-dark";
  if (n === "3") return "badge bg-success";
  return "badge bg-secondary";
}
// Badge para tipo de ticket (Servicio/Preventivo/Extra)
function badgeTipoClase(tipo) {
  const t = norm(tipo);
  if (t === "servicio") return "badge bg-primary";
  if (t === "preventivo") return "badge bg-secondary";
  if (t === "extra") return "badge bg-info text-dark";
  return "badge bg-light text-dark";
}

// ====== Render del detalle con Meet corregido ======
function renderDetalleTicket(data) {
  const procN = norm(data.tiProceso || "");
  const progreso = Math.max(0, PROCESOS_12.indexOf(procN) + 1);
  const progresoPorcentaje = Math.round((progreso / PROCESOS_12.length) * 100);
  const badgeColor = badgeCritClass(data.tiNivelCriticidad);
  const editable = procN === "meet"; // ✅ editable solo en proceso "meet"

  // --- Vista principal ---
  let html = `
    <div class="text-center">
      <img src="../img/Equipos/${(data.maNombre || "").toLowerCase()}/${
    data.eqModelo
  }.png"
           alt="Equipo" class="img-fluid mb-3" style="max-height:200px;">
    </div>
    <h5>${data.eqModelo}</h5>
    <p><b>SN:</b> ${data.peSN || "—"}</p>
    <div class="d-flex align-items-center">
      <img src="../img/Marcas/${(data.maNombre || "").toLowerCase()}.png"
           alt="${
             data.maNombre || ""
           }" style="width:50px; height:auto; margin-right:10px;">
      <span>${data.maNombre || ""}</span>
    </div>
    <hr>

    

    <p><b>Descripción:</b><br>${data.tiDescripcion || "—"}</p>
    <p><b>Estado:</b> ${data.tiEstatus || "—"}</p>
    <p><b>Proceso actual:</b> ${data.tiProceso || "—"}</p>
    <div class="progress mb-2">
      <div class="progress-bar" role="progressbar"
           style="width:${progresoPorcentaje}%;" aria-valuenow="${progreso}"
           aria-valuemin="0" aria-valuemax="${
             PROCESOS_12.length
           }">${progreso}/${PROCESOS_12.length}</div>
    </div>
    <div class="d-flex justify-content-between">
      ${PROCESOS_12.map(
        (p, i) =>
          `<span class="small ${
            i < progreso ? "text-success" : "text-muted"
          }" style="font-size:0.75em;">●</span>`
      ).join("")}
    </div>
    <hr>
    <p><b>Nivel de Criticidad:</b> <span class="${badgeColor}">${
    "Nivel " + (data.tiNivelCriticidad || "—")
  }</span></p>
    <p><b>Fecha de Creación:</b> ${fmtDate(data.tiFechaCreacion)}</p>
    <p><b>Fecha/Hora de Visita:</b> ${fmtDateTime(data.tiVisita)}</p>
    
    <!-- 🔹 AQUI vamos a inyectar el bloque de MEET -->
    <hr>
    <p class="">
      <a class="" data-bs-toggle="collapse" href="#collapseExample" role="button" aria-expanded="false" aria-controls="collapseExample" style="color: black;">
        Solicitar un Meet <i class="bi bi-chevron-down"></i>
      </a>
    </p>
    <div class="collapse" id="collapseExample">
      <div id="meetAnchor" class="mb-3"></div>
    </div>
    <hr>
    
  `;

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
              <button class="btn btn-primary" onclick="uploadLogs(${
                data.tiId
              })">
                Subir logs
              </button>
            </div>
          </div>
          <small class="text-muted d-block mt-2">Acepta .log, .txt o comprimidos (.zip/.7z/.rar).</small>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-outline-secondary"
                    onclick="openHelpLogs('${data.maNombre || ""}', '${
      data.eqModelo || ""
    }')">
              ¿Cómo extraer los logs?
            </button>
            <button class="btn btn-outline-info" onclick="pedirAyudaCorreo(${
              data.tiId
            })">
              Pedir ayuda por correo
            </button>
          </div>

          <div class="mt-2">
            <label class="form-label mt-3 mb-1">Preferencia para Meet (opcional)</label>
            <input id="meetPref_${data.tiId}" class="form-control"
                   placeholder="Ej: Proponer Meet mañana 10:00 o pegar un link de Meet">
            <small class="text-muted">Se guardarán al enviar la solicitud.</small>
          </div>
        </div>
      </div>
    `;
  }

  // 2) ASIGNACIÓN DE FECHA (cliente)
  if (procN === "asignacion fecha cliente") {
    html += `
      
      <div class="card border-0" style="background:#f8f9fb">
        <div class="card-body p-3">
          <h6 class="mb-3">Asignación de visita</h6>
          <div class="row g-2">
            <div class="col-12 col-md-4">
              <label class="form-label">Fecha</label>
              <input type="date" id="fecha_${data.tiId}" class="form-control">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Hora</label>
              <input type="time" id="hora_${data.tiId}" class="form-control">
            </div>
            <div class="col-12 col-md-4 d-grid align-items-end">
              <button class="btn btn-success mt-4 mt-md-0" onclick="asignarVisita(${data.tiId})">
                Asignar visita
              </button>
            </div>
          </div>
          <div class="d-grid d-md-inline-block mt-2">
            <button class="btn btn-outline-secondary" onclick="dejarFechaAlIngeniero(${data.tiId})">
              Que el ingeniero proponga la fecha
            </button>
          </div>
        </div>
      </div>
    `;
  }

  // 3) ENCUESTA
  if (
    procN === "encuesta satisfaccion" ||
    procN === "encuesta de satisfaccion"
  ) {
    html += `
      
      <div class="d-grid">
        <button class="btn btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#modalEncuesta_${
          data.tiId
        }">
          Responder encuesta de satisfacción
        </button>
      </div>

      <div class="modal fade" id="modalEncuesta_${
        data.tiId
      }" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form class="modal-content" onsubmit="enviarEncuesta(event, ${
            data.tiId
          })">
            <div class="modal-header">
              <h5 class="modal-title">Encuesta de satisfacción</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label d-block mb-2">1) ¿Cómo calificas el servicio recibido?</label>
                <div class="d-flex justify-content-between px-1">
                  ${[
                    { v: 5, t: "Excelente", e: "😄" },
                    { v: 4, t: "Bueno", e: "🙂" },
                    { v: 3, t: "Regular", e: "😐" },
                    { v: 2, t: "Malo", e: "🙁" },
                    { v: 1, t: "Muy malo", e: "😣" },
                  ]
                    .map(
                      (x) => `
                    <label class="text-center" style="cursor:pointer;">
                      <input type="radio" name="enc_smile_${data.tiId}" value="${x.v}" class="form-check-input d-block mx-auto mb-1">
                      <div style="font-size:1.6rem; line-height:1;">${x.e}</div>
                      <small class="d-block">${x.t}</small>
                    </label>
                  `
                    )
                    .join("")}
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">2) ¿Qué mejorarías del servicio de MR?</label>
                <textarea class="form-control" id="enc_mejora_${
                  data.tiId
                }" rows="3" maxlength="600"></textarea>
              </div>

              <div class="mb-0">
                <label class="form-label">3) ¿Qué tal te pareció la plataforma y qué cambiarías?</label>
                <textarea class="form-control" id="enc_plataforma_${
                  data.tiId
                }" rows="3" maxlength="600"></textarea>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Enviar</button>
            </div>
          </form>
        </div>
      </div>
    `;
  }

  // Inyecta contenido principal
  const cont = document.getElementById("offcanvasContent");
  cont.innerHTML = html;

  // 🔹 Siempre pinto el bloque Meet (visible como estado); editable solo si procN==='meet'
  // Después (usa la API expuesta por modal_meet.js)
  window.MRSOS_MEET?.cargarBloqueMeet(data.tiId, {
    editable,
    targetId: "meetAnchor",
  });
}

// ====== Abrir detalle (sin cambios de fondo) ======
function abrirDetalle(tiId) {
  const body = document.getElementById("offcanvasContent");
  body.innerHTML = `
    <div class="text-center py-4">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-2">Cargando...</p>
    </div>`;
  fetch(`../php/detalle_ticket.php?tiId=${encodeURIComponent(tiId)}`, {
    cache: "no-store",
  })
    .then((r) => r.json())
    .then((json) => {
      if (!json?.success) {
        body.innerHTML = `<p class="text-danger">${
          json?.error || "No se pudo cargar el detalle."
        }</p>`;
        return;
      }
      renderDetalleTicket(json.ticket);
    })
    .catch(() => {
      body.innerHTML = `<p class="text-danger">Error de red.</p>`;
    });
}

// ================== Acciones: Logs ==================
function uploadLogs(tiId) {
  const fi = document.getElementById(`logsFile_${tiId}`);
  if (!fi || !fi.files.length) {
    alert("Selecciona un archivo de logs.");
    return;
  }
  const fd = new FormData();
  fd.append("tiId", tiId);
  fd.append("logs", fi.files[0]);
  // opcional: también podrías mandar eqModelo/maNombre si te sirve
  fetch("../php/subir_logs.php", { method: "POST", body: fd })
    .then((r) => r.json())
    .then((res) => {
      if (res.success) {
        Swal.fire({
          title: "Éxito!",
          text: "Logs cargados con éxito!",
          icon: "success",
        });
        fi.value = "";
        cargarTodosTickets();
        cerrarOffcanvas("offcanvasTicket");
      } else {
        alert(res.error || "No fue posible subir los logs.");
      }
    })
    .catch(() => alert("Error de red al subir los logs."));
}
function openHelpLogs(marca, modelo) {
  const q = new URLSearchParams({ marca, modelo });
  // Página de ayuda con videos por marca/modelo
  window.open(`../ayuda/ayuda_logs.php?${q.toString()}`, "_blank");
}
function pedirAyudaCorreo(tiId) {
  const pref = document.getElementById(`meetPref_${tiId}`)?.value?.trim() || "";
  fetch("../php/solicitar_ayuda_logs.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ tiId, preferenciaMeet: pref }),
  })
    .then((r) => r.json())
    .then((res) => {
      if (res.success) {
        alert("Se notificó al ingeniero.");
      } else {
        alert(res.error || "No se pudo enviar la notificación.");
      }
    })
    .catch(() => alert("Error de red al solicitar ayuda."));
}

// ================== Acciones: Asignación de fecha ==================
function asignarVisita(tiId) {
  const f = document.getElementById(`fecha_${tiId}`)?.value;
  const h = document.getElementById(`hora_${tiId}`)?.value;
  if (!f || !h) {
    alert("Selecciona fecha y hora.");
    return;
  }
  const tiVisita = `${f} ${h}`;
  fetch("../php/asignar_fecha.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ ticketId: tiId, tiVisita }),
  })
    .then((r) => r.json())
    .then((res) => {
      if (res.success) {
        Swal.fire({
          title: "¡Fecha Asignada!",
          text: "Fecha asignada correctamente.",
          icon: "success",
        });
        // refresca el detalle:
        abrirDetalle(tiId);
      } else {
        alert(res.error || "No se pudo asignar la visita.");
      }
    })
    .catch(() => alert("Error de red al asignar la visita."));
}
function dejarFechaAlIngeniero(ticketId) {
  $.ajax({
    url: "../php/dejar_fecha_a_ingeniero.php",
    method: "POST",
    dataType: "json",
    data: { ticketId },
    success: function (res) {
      if (res?.success) {
        // Cierra modal si está abierto
        try {
          document.querySelectorAll(".modal.show").forEach((m) => {
            const inst = bootstrap.Modal.getInstance(m);
            inst && inst.hide();
          });
        } catch (_) {}

        // Notifica y recarga listados
        if (typeof mostrarToast === "function") {
          mostrarToast("success", "El ingeniero propondrá la fecha.");
        } else {
          alert("El ingeniero propondrá la fecha.");
        }

        // Tu refresco habitual
        if (typeof cargarTicketsPorSede === "function") cargarTodosTickets();
        if (typeof cargarTickets === "function") cargarTickets();
      } else {
        const msg = res?.error || "No se pudo dejar la fecha al ingeniero.";
        if (typeof mostrarToast === "function") mostrarToast("error", msg);
        else alert(msg);
      }
    },
    error: function (xhr) {
      console.error(xhr.responseText);
      if (typeof mostrarToast === "function")
        mostrarToast("error", "Error en el servidor.");
      else alert("Error en el servidor.");
    },
  });
}

// ================== Acciones: Encuesta ==================
function enviarEncuesta(e, tiId) {
  e.preventDefault();
  const rating =
    document.querySelector(`input[name="enc_smile_${tiId}"]:checked`)?.value ||
    "";
  if (!rating) {
    alert("Selecciona una calificación.");
    return;
  }
  const mejora = document.getElementById(`enc_mejora_${tiId}`)?.value || "";
  const plataforma =
    document.getElementById(`enc_plataforma_${tiId}`)?.value || "";
  fetch("../php/guardar_encuesta.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ tiId, rating: Number(rating), mejora, plataforma }),
  })
    .then((r) => r.json())
    .then((res) => {
      if (res.success) {
        // cierra modal
        const modalEl = document.getElementById(`modalEncuesta_${tiId}`);
        if (modalEl) {
          const inst =
            bootstrap.Modal.getInstance(modalEl) ||
            new bootstrap.Modal(modalEl);
          inst.hide();
        }
        alert("¡Gracias por tu respuesta!");
        abrirDetalle(tiId);
      } else {
        alert(res.error || "No se pudo guardar la encuesta.");
      }
    })
    .catch(() => alert("Error de red al enviar la encuesta."));
}

// html += `

//           <div class="col-12 col-sm-6 col-md-4 mb-4">
//             <div class="card service-card position-relative overflow-hidden">
//               <!-- Número de ticket -->
//               <div class="ticket-number position-absolute top-5 m-2 start-0 px-2 py-1 bg-white rounded-end">
//                 <strong>${ticket.tiId}</strong>
//               </div>

//               <!-- Imagen del equipo -->
//               <img src="../img/Equipos/${ticket.maNombre.toLowerCase()}/${ticket.eqModelo}.png"
//                   class="card-img-top equipo-img "
//                   alt="${ticket.eqModelo}">

//               <!-- Serial debajo de la imagen -->
//               <div class="serial-number ms-2">
//                 <span class="badge" style="background-color:#cdd6ff; color:black;">${ticket.peSN}</span>
//               </div>

//               <!-- Nombre del modelo en vertical -->
//               <div class="model-vertical text-primary">
//                 ${ticket.eqModelo} ${ticket.eqVersion}
//               </div>
//               <!-- Nombre del modelo en vertical -->
//               <div class="model-vertical-marca text-primary">
//                 <img src="../img/Marcas/${ticket.maNombre.toLowerCase()}.png" alt="${ticket.maNombre}" style="width:120px;">
//               </div>

//               <!-- Puntos de progreso -->
//               <div class="progress-steps d-flex justify-content-between align-items-center mx-5 my-3 px-3" style="margin-top: 50px!important;">
//                 ${procesos.map((p, i) =>
//                       `<span class="step ${i < paso ? 'active' : ''}"></span>`
//                     ).join('')}
//               </div>
//               <div class="progress-bar-custom mx-5 mb-3">
//                 <div class="progress-fill" style="width: ${widthPct}%;"></div>
//               </div>

//               <!-- Iconos de proceso (pon los que necesites) -->
//               <div class="d-flex justify-content-around mb-5 mt-5">
//                 <div class="text-center card-in process-icon">
//                   <img src="../img/Tickets/${proc}.png" alt="${proc}" />
//                   <small>${ticket.tiProceso}</small>
//                 </div>
//                 <!-- ejemplar: si quisieras mostrar otros dos iconos fijos: -->
//                 <div class="text-center process-icon card-in">
//                   <img src="../img/Tickets/ticket de servicio.png" alt="meeting" />
//                   <small>Ticket de Servicio</small>
//                 </div>
//                 <div class="text-center process-icon card-btn">
//                   <img src="../img/Tickets/google.png" alt="logs" />
//                   <small>Google Meet</small>
//                 </div>
//               </div>

//               <!-- Ver más -->
//               <div class="text-center mb-3 px-3">
//                 <button type="button" class="btn w-100 btn-card" onclick="abrirDetalle(${ticket.tiId})" data-bs-toggle="offcanvas" data-bs-target="#offcanvasTicket">Ver más..</button>
//               </div>
//             </div>
//           </div>`;

//TODO
// Helpers de badges
function badgeTipoClase(tipo) {
  const t = (tipo || "").toLowerCase();
  if (t === "servicio") return "bg-primary";
  if (t === "preventivo") return "bg-warning text-dark";
  if (t === "extra") return "bg-info text-dark";
  return "bg-secondary";
}
function badgeEstatusClase(estatus) {
  const e = (estatus || "").toLowerCase();
  if (e === "abierto") return "bg-success";
  if (e === "pospuesto") return "bg-warning text-dark";
  if (e === "finalizado" || e === "cerrado") return "bg-secondary";
  if (e === "cancelado") return "bg-danger";
  return "bg-light text-dark";
}

// RENDER: recibe el arreglo de sedes y pinta (no usa globals)
function renderTicketsPorSede(sedesArr) {
  const wrap = document.getElementById("wrapTicketsSedes");
  if (!wrap) return;

  const sedes = (Array.isArray(sedesArr) ? sedesArr : []).filter(
    (s) => Array.isArray(s.tickets) && s.tickets.length
  );

  if (!sedes.length) {
    wrap.innerHTML =
      '<p class="text-muted mb-0">Sin tickets abiertos por sede.</p>';
    return;
  }

  const html = sedes
    .map((s) => {
      const filas = s.tickets
        .map(
          (t) => `
      <tr>
        <td><span class="badge ${badgeEstatusClase(t.tiEstatus)}">${
            t.tiEstatus || ""
          }</span></td>
        <td>${t.eqModelo || ""}${t.eqVersion ? " " + t.eqVersion : ""}</td>
        <td class="d-none d-sm-table-cell">
          <img src="../img/Marcas/${(
            t.maNombre || ""
          ).toLowerCase()}.png" style="height:20px;" alt="${t.maNombre || ""}">
        </td>
        <td class="d-none d-md-table-cell">${t.peSN || ""}</td>
        <td class="d-none d-lg-table-cell"><span class="badge bg-light text-dark">${
          t.tiProceso || ""
        }</span></td>
        <td class="d-none d-lg-table-cell"><span class="badge ${badgeTipoClase(
          t.tiTipoTicket
        )}">${t.tiTipoTicket || ""}</span></td>
        <td class="d-none d-md-table-cell">${t.tiExtra || ""}</td>
        <td><a href="#" onclick="abrirDetalle(${Number(
          t.tiId
        )})" data-bs-toggle="offcanvas" data-bs-target="#offcanvasTicket">Ver más</a></td>
      </tr>
    `
        )
        .join("");

      return `
      <!-- Encabezado con línea tenue -->
      <div class="d-flex align-items-center gap-3 my-3">
        <h6 class="mb-0 border-bottom">${s.csNombre || "Sin sede"}</h6>
      </div>
      <div class="table-responsive">
        <table class="table table-borderless mb-0">
          <thead class="d-none d-lg-table-header-group">
            <tr>
              <th>Estado</th>
              <th>Equipo</th>
              <th class="d-none d-sm-table-cell">Marca</th>
              <th class="d-none d-md-table-cell">SN</th>
              <th class="d-none d-lg-table-cell">Estatus</th>
              <th class="d-none d-lg-table-cell">Tipo de ticket</th>
              <th class="d-none d-md-table-cell">Extras</th>
              <th></th>
            </tr>
          </thead>
          <tbody>${filas}</tbody>
        </table>
      </div>
    `;
    })
    .join("");

  wrap.innerHTML = html;
}

// FETCH: trae sedes y llama al render (sin variables globales)
// Cargar
function cargarTicketsPorSede(params = {}) {
  const qs = new URLSearchParams();
  // MRA: opcional clId y/o csId
  if (window.SESSION?.rol === "MRA") {
    if (params.clId) qs.set("clId", params.clId);
    if (params.csId) qs.set("csId", params.csId);
  }
  fetch(
    `../php/obtener_tickets_sedes.php${
      qs.toString() ? "?" + qs.toString() : ""
    }`
  )
    .then((r) => r.json())
    .then((json) => {
      if (!json?.success) {
        document.getElementById(
          "wrapTicketsSedes"
        ).innerHTML = `<p class="text-danger">${
          json?.error || "No se pudieron cargar los tickets por sede."
        }</p>`;
        return;
      }
      renderTicketsPorSede(json.sedes || []);
    })
    .catch(() => {
      document.getElementById(
        "wrapTicketsSedes"
      ).innerHTML = `<p class="text-danger">Error al cargar los tickets por sede.</p>`;
    });
}

document.addEventListener("DOMContentLoaded", () => {
  cargarTodosTickets(); // por defecto, según el rol/sede del usuario
});

//TODO MEET
// =======================
// MEET (Front actualizado)
// =======================

// Chip de estado (badge) por estado de meet
function chipMeetColor(estado) {
  switch ((estado || "").toLowerCase()) {
    case "meet ingeniero":
      return "bg-success";
    case "meet solicitado cliente":
    case "meet solicitado ingeniero":
      return "bg-warning text-dark";
    case "meet cliente":
      return "bg-primary";
    default:
      return "bg-secondary";
  }
}

// TODO Elimina el sistema de Tickets
// en main.js (DOMContentLoaded)
document.addEventListener("DOMContentLoaded", () => {
  if (window.SESSION && window.SESSION.rol === "EC") {
    const btn = document.getElementById("btnNuevoTicket");
    if (btn) btn.classList.add("d-none");
  }
});

// =======================
// Vista Tabla / Cards (persistencia)
// =======================
const VIEW_KEY = "mrs_tickets_view";
let CURRENT_VIEW = localStorage.getItem(VIEW_KEY) || "table";

function setView(mode) {
  CURRENT_VIEW = mode === "cards" ? "cards" : "table";
  localStorage.setItem(VIEW_KEY, CURRENT_VIEW);
  // Actualiza estilos activos del toggle
  document.querySelectorAll("#viewToggle [data-mode]").forEach((btn) => {
    btn.classList.toggle("active", btn.dataset.mode === CURRENT_VIEW);
  });
}

// Inicializa toggle
document.addEventListener("DOMContentLoaded", () => {
  // Si existe el toggle en la página
  document.querySelectorAll("#viewToggle [data-mode]").forEach((btn) => {
    btn.addEventListener("click", () => {
      setView(btn.dataset.mode);
      cargarTodosTickets(); // repintar
    });
  });
  setView(CURRENT_VIEW);
});

// =======================
// Normalizadores/re-utilizables
// =======================
function safe(s) {
  return s == null ? "" : String(s);
}
function joinModelo(eqModelo, eqVersion) {
  const m = safe(eqModelo).trim();
  const v = safe(eqVersion).trim();
  return m + (v ? " " + v : "");
}
function fmtFechaHoraLocal(iso) {
  if (!iso) return "—";
  const d = new Date(iso.replace(" ", "T"));
  return isNaN(d) ? iso : d.toLocaleString();
}

// Reusa tus helpers existentes:
/// badgeEstatusClase(estatus)
/// badgeTipoClase(tipo)
/// abrirDetalle(tiId)

// =======================
// Render: Cards
// =======================
function renderTicketCard(t) {
  const cliente = safe(t.clNombre || t.cliente || "");
  const persona = safe(t.persona || t.contacto || t.usuario || "");
  const estadoBadge = badgeEstatusClase(t.tiEstatus);
  const procesoBadge = "badge mrs-badge-soft"; // proceso como pill suave
  const modelo = joinModelo(t.eqModelo, t.eqVersion);
  const fechaVisita = fmtFechaHoraLocal(t.tiVisita);
  const sede = t.csNombre || "—";
  const codigoTicket = `${(sede || "GEN").substring(0, 3).toLowerCase()}-${
    t.tiId
  }`;

  return `
  <div class="mrs-card">
    <div class="mrs-card-header">
      <div class="d-flex align-items-start justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-person text-muted"></i>
          <h6 class="mb-0">${persona || cliente || "—"}</h6>
        </div>
        <span class="badge ${estadoBadge}">${safe(t.tiEstatus) || "—"}</span>
      </div>
    </div>
    <div class="mrs-card-content">
      <div class="d-flex align-items-center gap-2 text-muted mb-1">
        <i class="bi bi-wrench"></i>
        <span class="small">${modelo || "—"}</span>
      </div>
      <div class="d-flex align-items-center gap-2 text-muted mb-1">
        <i class="bi bi-calendar-event"></i>
        <span class="small">${fechaVisita}</span>
      </div>
      <div class="d-flex align-items-center gap-2 text-muted mb-3">
        <i class="bi bi-123"></i>
        <span class="small">${t.peSN || ""}</span>
      </div>
      <div class="d-flex align-items-center gap-2 text-muted mb-3">
        <i class="bi bi-123"></i>
        <span class="small">${t.csNombre || ""}</span>
      </div>

      <div class="d-flex align-items-center justify-content-between">
        <span class="${procesoBadge} px-2 py-1 small">${
    safe(t.tiProceso) || "—"
  }</span>
        <button class="btn btn-primary btn-sm"
          onclick="abrirDetalle(${Number(t.tiId)})"
          data-bs-toggle="offcanvas"
          data-bs-target="#offcanvasTicket">
          Ver más
        </button>
      </div>
    </div>
  </div>`;
}

// =======================
// Render: Tabla (reusa tu tabla por sede)
// =======================
function renderTablaSede(s) {
  const filas = (s.tickets || [])
    .map(
      (t) => `
    <tr>
      <td><span class="badge ${badgeEstatusClase(t.tiEstatus)}">${
        t.tiEstatus || ""
      }</span></td>
      <td>${joinModelo(t.eqModelo, t.eqVersion)}</td>
      <td class="d-none d-sm-table-cell">
        <img src="../img/Marcas/${(t.maNombre || "").toLowerCase()}.png"
             style="height:20px;" alt="${t.maNombre || ""}">
      </td>
      <td class="d-none d-md-table-cell">${t.peSN || ""}</td>
      <td class="d-none d-lg-table-cell">
        <span class="badge bg-light text-dark">${t.tiProceso || ""}</span>
      </td>
      <td class="d-none d-lg-table-cell">
        <span class="badge ${badgeTipoClase(t.tiTipoTicket)}">${
        t.tiTipoTicket || ""
      }</span>
      </td>
      <td class="d-none d-md-table-cell">${t.tiExtra || ""}</td>
      <td>
        <a href="#" onclick="abrirDetalle(${Number(t.tiId)})"
           data-bs-toggle="offcanvas" data-bs-target="#offcanvasTicket">Ver más</a>
      </td>
    </tr>
  `
    )
    .join("");

  return `
    <div class="d-flex align-items-center gap-3 my-3">
      <h6 class="mb-0 border-bottom">${s.csNombre || "Sin sede"}</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-borderless mb-0">
        <thead class="d-none d-lg-table-header-group">
          <tr>
            <th>Estado</th>
            <th>Equipo</th>
            <th class="d-none d-sm-table-cell">Marca</th>
            <th class="d-none d-md-table-cell">SN</th>
            <th class="d-none d-lg-table-cell">Estatus</th>
            <th class="d-none d-lg-table-cell">Tipo de ticket</th>
            <th class="d-none d-md-table-cell">Extras</th>
            <th></th>
          </tr>
        </thead>
        <tbody>${filas}</tbody>
      </table>
    </div>
  `;
}

function renderCardsSede(s) {
  const cards = (s.tickets || []).map((t) => renderTicketCard(t)).join("");
  return `
    <div class="d-flex align-items-center gap-3 my-3">
      <h6 class="mb-0 border-bottom">${s.csNombre || "Sin sede"}</h6>
    </div>
    <div class="mrs-grid">${cards}</div>
  `;
}

// =======================
// Render: Cliente → Sedes
// =======================
function renderCliente(client, mode) {
  const title = client.clNombre || client.cliente || "Sin cliente";
  const sedes = Array.isArray(client.sedes) ? client.sedes : [];
  const bloques = sedes
    .map((s) => (mode === "cards" ? renderCardsSede(s) : renderTablaSede(s)))
    .join("");
  return `
    <div class="my-4">
      <h5 class="mb-2">${title}</h5>
      ${bloques || '<p class="text-muted">Sin tickets en sus sedes.</p>'}
    </div>
  `;
}

// =======================
// Render: todos los clientes
// =======================
function renderTicketsClientes(clientes, mode = CURRENT_VIEW) {
  const wrap = document.getElementById("wrapTicketsClientes");
  if (!wrap) return;

  const arr = Array.isArray(clientes) ? clientes : [];
  if (!arr.length) {
    wrap.innerHTML =
      '<p class="text-muted mb-0">No hay tickets para mostrar.</p>';
    return;
  }

  wrap.innerHTML = arr.map((c) => renderCliente(c, mode)).join("");
}

// =======================
// FETCH: obtener todos agrupado por cliente→sedes
// =======================
function normalizeToClientes(payload) {
  // Esperado: {success:true, clientes:[{clId, clNombre, sedes:[{csId, csNombre, tickets:[]}, ...]}]}
  // Fallback (cuando backend retorna solo sedes): {success:true, sedes:[...]}
  if (Array.isArray(payload?.clientes)) return payload.clientes;
  if (Array.isArray(payload?.sedes)) {
    return [
      {
        clId: 0,
        clNombre: "Sin cliente",
        sedes: payload.sedes,
      },
    ];
  }
  return [];
}

function cargarTodosTickets(params = {}) {
  const qs = new URLSearchParams();
  // Si en tu backend hay filtros opcionales:
  // if (params.estado) qs.set('estado', params.estado);
  // if (params.marca)  qs.set('marca',  params.marca);

  // Endpoint recomendado (nuevo): obtener_todos_tickets.php
  // Si aún no lo tienes, temporalmente intenta con obtener_tickets_sedes.php (devuelve todas)
  const urlPrincipal = `php/obtener_todos_tickets.php`;
  const urlFallback = `php/obtener_tickets_sedes.php`;

  fetch(qs.toString() ? `${urlPrincipal}?${qs}` : urlPrincipal, {
    cache: "no-store",
  })
    .then((r) => (r.ok ? r.json() : Promise.reject()))
    .then((json) => {
      if (!json?.success)
        throw new Error(json?.error || "Respuesta no exitosa");
      const clientes = normalizeToClientes(json);
      renderTicketsClientes(clientes, CURRENT_VIEW);
    })
    .catch(() => {
      // Fallback automático al endpoint existente por sede
      fetch(qs.toString() ? `${urlFallback}?${qs}` : urlFallback, {
        cache: "no-store",
      })
        .then((r) => r.json())
        .then((json) => {
          const clientes = normalizeToClientes(json);
          renderTicketsClientes(clientes, CURRENT_VIEW);
        })
        .catch(() => {
          const wrap = document.getElementById("wrapTicketsClientes");
          if (wrap)
            wrap.innerHTML = `<p class="text-danger">Error al cargar tickets.</p>`;
        });
    });
}

// Arranque
document.addEventListener("DOMContentLoaded", () => {
  cargarTodosTickets();
});






  // Pega tu VAPID PUBLIC KEY en Base64URL (exactamente como la entrega VAPID::createVapidKeys())
  const VAPID_PUBLIC = "TU_PUBLIC_KEY";

  async function urlB64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    return Uint8Array.from([...rawData].map(c => c.charCodeAt(0)));
  }

  async function enableWebPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return alert('Push no soportado');

  const perm = await Notification.requestPermission();
  if (perm !== 'granted') return;

  const reg = await navigator.serviceWorker.register('/service-worker.js');
  const subscription = await reg.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: await urlB64ToUint8Array(VAPID_PUBLIC)
  });

  await fetch('/php/save_subscription.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ subscription })
    // La cookie de sesión viaja sola; PHP sabrá quién eres y pondrá el usId
  });

  alert('Notificaciones activadas');
}

  // Llama esto en un botón o al cargar el admin
  document.addEventListener('DOMContentLoaded', () => {
    // O muestra un botón: <button onclick="enableWebPush()">Activar notificaciones</button>
    // enableWebPush();
  });

