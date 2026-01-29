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
  "revision especial",
  "logs",
  "meet",
  "visita",
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
        `<span class="small ${i < paso ? "text-success" : "text-muted"
        }" style="font-size:.75em;">●</span>`
    ).join("");
  }
}

/** (Opcional) refrescar listas/tablas sin recargar toda la página */
function refreshListados() {
  if (typeof cargarTicketsPorSede === "function") cargarTodosTickets(); // los bloques por sede
}

//TODO Reiniciar Filtros


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
  "revision especial",
  "logs",
  "meet",
  "visita",
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
  fetch("../php/solicitar_ayuda.php", {
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
  const f = document.getElementById(`fecha_${tiId}`)?.value || "";
  const h = document.getElementById(`hora_${tiId}`)?.value || "";

  if (!f || !h) {
    Swal.fire("Campos incompletos", "Selecciona fecha y hora.", "warning");
    return;
  }

  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);
  const sel = new Date(f + "T00:00:00");

  if (sel <= hoy) {
    Swal.fire(
      "Fecha no válida",
      "La fecha de visita debe ser posterior a hoy.",
      "warning"
    );
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
        } catch (_) { }

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