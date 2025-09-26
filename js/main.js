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

$(document).ready(function () {
  function contieneInyeccion(str) {
    const pattern =
      /<|>|script|onerror|alert|select|insert|delete|update|union|drop|--|;|['"]/gi;
    return pattern.test(str);
  }

  $("#login-form").on("submit", function (e) {
    e.preventDefault(); // Evita el envío por defecto

    const usId = $("#usId").val().trim();
    const usPass = $("#usPass").val().trim();

    // Validación de campos vacíos
    if (!usId || !usPass) {
      Swal.fire({
        icon: "warning",
        title: "Campos vacíos",
        text: "Por favor completa ambos campos.",
      });
      return;
    }

    // Validación contra inyecciones
    if (contieneInyeccion(usId) || contieneInyeccion(usPass)) {
      Swal.fire({
        icon: "error",
        title: "Entrada no válida",
        text: "Tu información contiene caracteres o palabras no permitidas.",
      });
      return;
    }

    // TODO: Aquí irá el fetch o $.ajax() a login.php
    $.ajax({
      url: "../php/login.php",
      method: "POST",
      data: {
        usId: usId,
        usPass: usPass,
      },
      dataType: "json", // 👈 Esto es importante
      success: function (response) {
        console.log("success: ", response.success);
        console.log("user: ", response.user);
        if (response.success) {
          location.href = "../sos.php";
        } else {
          Swal.fire("Error", "Usuario o contraseña incorrectos", "error");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX error:", status, error);
        Swal.fire("Error", "No se pudo conectar con el servidor", "error");
      },
    });

    // Opcionalmente: this.submit(); para seguir con envío normal
  });

  $("#reset-p-form").on("submit", function (e) {
    e.preventDefault(); // Evita el envío por defecto
    const email = $("#email").val().trim();

    // Validación de campos vacíos
    if (!email) {
      Swal.fire({
        icon: "warning",
        title: "Campos vacíos",
        text: "Por favor completa ambos campos.",
      });
      return;
    }

    // Validación contra inyecciones
    if (contieneInyeccion(email)) {
      Swal.fire({
        icon: "error",
        title: "Entrada no válida",
        text: "Tu información contiene caracteres o palabras no permitidas.",
      });
      return;
    }

    // TODO: Aquí irá el fetch o $.ajax() a login.php
    $.ajax({
      url: "../php/recuperar_password.php",
      method: "POST",
      data: {
        usEmail: email,
      },
      success: function (response) {
        if (response.success) {
          Swal.fire({
            icon: "warning",
            title: "Correo Enviado",
            text: "En tu correo has recibido la solicitud de cambio de Contraseña.",
          });
          return;
        } else {
          Swal.fire("Error", "El email no coincide con el registrado", "error");
        }
      },
    });

    // Opcionalmente: this.submit(); para seguir con envío normal
  });
});

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
        `<span class="small ${i < paso ? "text-success" : "text-muted"
        }" style="font-size:.75em;">●</span>`
    ).join("");
  }
}

/** (Opcional) refrescar listas/tablas sin recargar toda la página */
function refreshListados() {
  if (typeof cargarTicketsPorSede === "function") cargarTicketsPorSede(); // los bloques por sede
}

//TODO: Cards tablas

$(document).ready(function () {
  $.ajax({
    url: "../php/get_tickets.php",
    type: "GET",
    dataType: "json",
    success: function (data) {
      if (data.length > 0) {
        let container = $("#ticketCardsContainer");
        data.forEach((ticket) => {
          const card = `
          
                    <div class="col mx-auto">
                        <div class="card mb-4 rounded-4 shadow p-2 mb-4 bg-white overflow-hidden card-ticket">
                            <div class="position-relative text-center">
                                <img src="https://www.xfusion.com/wp-content/uploads/2023/04/1288H-V7-mb.png" alt="Equipo" class="equipos-card">
                            </div>
                            <div class="card-body" style="font-family: TTNorms;">
                                <div class="row">
                                    <div class="col-5 col-sm-5">
                                        <ul class="list-unstyled text-end mt-3 mb-4">
                                            <li class="mt-2">
                                                <img src="img/Tickets/${ticket.tiProceso
            }.png" alt="Status ${ticket.tiEstatus
            }" class="ticket-card mt-5 mx-auto">
                                            </li>
                                            <li class="mt-2"><b>Estatus:</b></li>
                                            <li>${ticket.tiEstatus}</li>
                                        </ul>
                                    </div>
                                    <div class="col-7 col-sm-7">
                                        <h4 class="card-title text-end">${ticket.eqModelo
            }</h4>
                                        <ul class="list-unstyled text-end mt-3 mb-4 ps-4">
                                            <li class="mt-2"><b>Folio:</b></li>
                                            <li>${ticket.tiId}</li>
                                            <li class="mt-2"><b>Tipo de Servicio:</b></li>
                                            <li>${ticket.tiDescripcion}</li>
                                            <li class="mt-2"><b>SN:</b></li>
                                            <li>${ticket.peSN}</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="row" style="align-items: end;">
                                    <div class="col-6">
                                        <ul class="list-unstyled text-end">
                                            <button type="button" class="btn w-100 btn-card">Ver más..</button>
                                        </ul>
                                    </div>
                                    <div class="col-6">
                                        <ul class="list-unstyled text-end">
                                            <li><b>Marca:</b></li>
                                            <img src="img/Marcas/${ticket.maNombre.toLowerCase()}.png" alt="${ticket.maNombre
            }" style="width:120px;">
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;
          $("#bloqueTickets").removeClass("d-none");
          container.append(card);
        });
      }
    },
  });
});
$(document).ready(function () {
  // Cargar los tickets al inicio y cuando cambian filtros

  cargarTicketsPorSede();
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

    cargarTicketsPorSede();
  });

  // Botón recargar
  $("#btnRecargar").click(function () {
    cargarTicketsPorSede();
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
        cargarTicketsPorSede(); // Recargar para actualizar
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
  "asignacion fecha",
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
      <img src="../img/Equipos/${(data.maNombre || "").toLowerCase()}/${data.eqModelo
    }.png"
           alt="Equipo" class="img-fluid mb-3" style="max-height:200px;">
    </div>
    <h5>${data.eqModelo}</h5>
    <p><b>SN:</b> ${data.peSN || "—"}</p>
    <div class="d-flex align-items-center">
      <img src="../img/Marcas/${(data.maNombre || "").toLowerCase()}.png"
           alt="${data.maNombre || ""
    }" style="width:50px; height:auto; margin-right:10px;">
      <span>${data.maNombre || ""}</span>
    </div>
    <hr>

    <!-- 🔹 AQUI vamos a inyectar el bloque de MEET -->
    <div id="meetAnchor" class="mb-3"></div>

    <p><b>Descripción:</b><br>${data.tiDescripcion || "—"}</p>
    <p><b>Estado:</b> ${data.tiEstatus || "—"}</p>
    <p><b>Proceso actual:</b> ${data.tiProceso || "—"}</p>
    <div class="progress mb-2">
      <div class="progress-bar" role="progressbar"
           style="width:${progresoPorcentaje}%;" aria-valuenow="${progreso}"
           aria-valuemin="0" aria-valuemax="${PROCESOS_12.length
    }">${progreso}/${PROCESOS_12.length}</div>
    </div>
    <div class="d-flex justify-content-between">
      ${PROCESOS_12.map(
      (p, i) =>
        `<span class="small ${i < progreso ? "text-success" : "text-muted"
        }" style="font-size:0.75em;">●</span>`
    ).join("")}
    </div>
    <hr>
    <p><b>Nivel de Criticidad:</b> <span class="${badgeColor}">${"Nivel " + (data.tiNivelCriticidad || "—")
    }</span></p>
    <p><b>Fecha de Creación:</b> ${fmtDate(data.tiFechaCreacion)}</p>
    <p><b>Fecha/Hora de Visita:</b> ${fmtDateTime(data.tiVisita)}</p>
  `;

  // 1) LOGS
  if (procN === "logs") {
    html += `
      <hr>
      <div class="card border-0" style="background:#f8f9fb">
        <div class="card-body p-3">
          <h6 class="mb-3">Subir logs</h6>
          <div class="row g-2 align-items-center">
            <div class="col-12 col-md-8">
              <input type="file" id="logsFile_${data.tiId}" class="form-control"
                     accept=".log,.txt,.zip,.tar,.gz,.7z,.rar, text/plain, application/zip, application/x-7z-compressed, application/x-rar-compressed">
            </div>
            <div class="col-12 col-md-4 d-grid">
              <button class="btn btn-primary" onclick="uploadLogs(${data.tiId
      })">
                Subir logs
              </button>
            </div>
          </div>
          <small class="text-muted d-block mt-2">Acepta .log, .txt o comprimidos (.zip/.7z/.rar).</small>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-outline-secondary"
                    onclick="openHelpLogs('${data.maNombre || ""}', '${data.eqModelo || ""
      }')">
              ¿Cómo extraer los logs?
            </button>
            <button class="btn btn-outline-info" onclick="pedirAyudaCorreo(${data.tiId
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
      <hr>
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
      <hr>
      <div class="d-grid">
        <button class="btn btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#modalEncuesta_${data.tiId
      }">
          Responder encuesta de satisfacción
        </button>
      </div>

      <div class="modal fade" id="modalEncuesta_${data.tiId
      }" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <form class="modal-content" onsubmit="enviarEncuesta(event, ${data.tiId
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
                <textarea class="form-control" id="enc_mejora_${data.tiId
      }" rows="3" maxlength="600"></textarea>
              </div>

              <div class="mb-0">
                <label class="form-label">3) ¿Qué tal te pareció la plataforma y qué cambiarías?</label>
                <textarea class="form-control" id="enc_plataforma_${data.tiId
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
  cargarBloqueMeet(data.tiId, { editable, targetId: "meetAnchor" });
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
        body.innerHTML = `<p class="text-danger">${json?.error || "No se pudo cargar el detalle."
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
        cargarTicketsPorSede();
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
        } catch (_) { }

        // Notifica y recarga listados
        if (typeof mostrarToast === "function") {
          mostrarToast("success", "El ingeniero propondrá la fecha.");
        } else {
          alert("El ingeniero propondrá la fecha.");
        }

        // Tu refresco habitual
        if (typeof cargarTicketsPorSede === "function") cargarTicketsPorSede();
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

//TODO: Estadisticas Mes
let areaChartRef, donutChartRef, barChartRef;

$(document).ready(function () {
  // cargarTickets();
  cargarEstadisticas();

  $("#filtroEstado, #filtroMarca, #filtroProceso, #filtroTipoEquipo").change(
    function () {
      // cargarTickets();
      cargarEstadisticas(); // si quieres que también afecte estadísticas según filtros, quita esto si no
    }
  );

  $("#resetFiltros").on("click", function () {
    $("#filtro-form")[0].reset();
    // cargarTickets();
    cargarEstadisticas();
  });

  $("#btnRecargar").on("click", function () {
    // cargarTickets();
    cargarEstadisticas();
  });
});

// Cargar por defecto: mes actual

let areaChart, donutTipo, donutEstatus;

function initCharts() {
  const areaCtx = document.getElementById('areaChart').getContext('2d');
  areaChart = new Chart(areaCtx, {
    type: 'line',
    data: { labels: [], datasets: [{
      data: [], fill: true,
      backgroundColor: 'rgba(115,96,255,0.2)',
      borderColor: 'rgba(115,96,255,1)',
      tension: 0.35, pointRadius: 0
    }]},
    options: { plugins:{legend:{display:false}}, scales:{x:{display:true}, y:{display:true}} }
  });

  const tipoCtx = document.getElementById('donutTipo').getContext('2d');
  donutTipo = new Chart(tipoCtx, {
    type: 'doughnut',
    data: { labels: [], datasets: [{ data: [], backgroundColor: ['#7360ff','#a29bfe','#b2bec3','#dfe6e9'] }] },
    options: { cutout: '65%', plugins: { legend: { display: false } } }
  });

  const estCtx = document.getElementById('donutEstatus').getContext('2d');
  donutEstatus = new Chart(estCtx, {
    type: 'doughnut',
    data: { labels: [], datasets: [{ data: [], backgroundColor: ['#28a745','#6c757d','#0d6efd','#adb5bd'] }] },
    options: { cutout: '65%', plugins: { legend: { display: false } } }
  });
}

function updateArea(labels, data) {
  areaChart.data.labels = labels || [];
  areaChart.data.datasets[0].data = data || [];
  areaChart.update();
}

function updateDonutTipo(map) {
  const labels = ['Servicio','Preventivo','Extra','Otros'];
  donutTipo.data.labels = labels;
  donutTipo.data.datasets[0].data = labels.map(l => (map && map[l]) ? map[l] : 0);
  donutTipo.update();
}

function updateDonutEstatus(map) {
  const labels = ['Abierto','Cancelado','Finalizado','Otro'];
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

function cargarEstadisticas({ ym=null, lastDays=30, csId=null, clId=null } = {}) {
  const qs = new URLSearchParams();
  if (ym) qs.set('ym', ym);
  if (lastDays) qs.set('lastDays', lastDays);
  if (csId) qs.set('csId', csId);
  if (clId) qs.set('clId', clId); // para MRA

  fetch(`../php/estadisticas_mes.php${qs.toString() ? '?' + qs.toString() : ''}`)
    .then(r => r.json())
    .then(res => {
      if (!res?.success) throw new Error(res?.error || 'Error');

      // charts
      updateArea(res.labels, res.data);
      updateDonutTipo(res.porTipo);
      updateDonutEstatus(res.porEstatus);

      // sedes accesibles (para el select)
      poblarSelectSedes(res.sedes, res.csId || null);
    })
    .catch(err => {
      console.error(err);
      updateArea([], []);
      updateDonutTipo({Servicio:0,Preventivo:0,Extra:0,Otros:0});
      updateDonutEstatus({Abierto:0,Cancelado:0,Finalizado:0,Otro:0});
      poblarSelectSedes([]);
    });
}

document.addEventListener('DOMContentLoaded', () => {
  initCharts();
  // Carga por defecto últimos 30 días
  cargarEstadisticas({ lastDays: 30 });

  document.getElementById('btnUlt30')?.addEventListener('click', () => {
    const csId = document.getElementById('selSede')?.value || '';
    cargarEstadisticas({ lastDays: 30, csId: csId || null });
  });

  document.getElementById('btnMesAplicar')?.addEventListener('click', () => {
    const ym   = document.getElementById('mesFiltro')?.value || null;
    const csId = document.getElementById('selSede')?.value || '';
    cargarEstadisticas({ ym, lastDays: null, csId: csId || null });
  });

  document.getElementById('selSede')?.addEventListener('change', (e) => {
    // Mantén el mismo rango actual (si prefieres 30 días al cambiar sede, cambia esta lógica)
    const ym = document.getElementById('mesFiltro')?.value || null;
    const csId = e.target.value || null;
    cargarEstadisticas({ ym, lastDays: ym ? null : 30, csId });
  });
});


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
        <td><span class="badge ${badgeEstatusClase(t.tiEstatus)}">${t.tiEstatus || ""
            }</span></td>
        <td>${t.eqModelo || ""}${t.eqVersion ? " " + t.eqVersion : ""}</td>
        <td class="d-none d-sm-table-cell">
          <img src="../img/Marcas/${(
              t.maNombre || ""
            ).toLowerCase()}.png" style="height:20px;" alt="${t.maNombre || ""}">
        </td>
        <td class="d-none d-md-table-cell">${t.peSN || ""}</td>
        <td class="d-none d-lg-table-cell"><span class="badge bg-light text-dark">${t.tiProceso || ""
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
  if (window.SESSION?.rol === 'MRA') {
    if (params.clId) qs.set('clId', params.clId);
    if (params.csId) qs.set('csId', params.csId);
  }
  fetch(`../php/obtener_tickets_sedes.php${qs.toString() ? '?' + qs.toString() : ''}`)
    .then(r => r.json())
    .then(json => {
      if (!json?.success) {
        document.getElementById('wrapTicketsSedes').innerHTML =
          `<p class="text-danger">${json?.error || 'No se pudieron cargar los tickets por sede.'}</p>`;
        return;
      }
      renderTicketsPorSede(json.sedes || []);
    })
    .catch(() => {
      document.getElementById('wrapTicketsSedes').innerHTML =
        `<p class="text-danger">Error al cargar los tickets por sede.</p>`;
    });
}

document.addEventListener('DOMContentLoaded', () => {
  cargarTicketsPorSede(); // por defecto, según el rol/sede del usuario
});


//TODO: Logout

// Logout robusto con delegación
document.addEventListener('click', function (e) {
  const a = e.target.closest('#btnLogout');
  if (!a) return;

  e.preventDefault();

  const hrefAjax = a.dataset.href || (a.getAttribute('href') + '?ajax=1');
  const redirect = a.dataset.redirect || '../login/login.php';

  fetch(hrefAjax, {
    method: 'GET',               // si prefieres, usa 'POST' y ajusta logout.php
    credentials: 'same-origin'
  })
  .catch(() => {})               // aunque falle, intentamos redirigir
  .finally(() => { window.location.href = redirect; });
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

// Render del bloque Meet
// data: { tiMeetActivo, tiMeetPlataforma, tiMeetLink }
// opts: { editable: boolean }
function renderMeetBlock(data = {}, opts = {}) {
  const { editable = false } = opts;

  const estado = data.tiMeetActivo || "";
  const plat = data.tiMeetPlataforma || "--";
  const link = data.tiMeetLink || "";

  const chip = estado
    ? `<span class="badge ${chipMeetColor(
      estado
    )} text-capitalize">${estado}</span>`
    : `<span class="badge bg-secondary">sin meet</span>`;

  const linkHtml = link
    ? `<a href="${link}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
         <i class="bi bi-box-arrow-up-right"></i> Abrir
       </a>`
    : `<span class="text-muted">Sin enlace</span>`;

  // Botones se ocultan si !editable
  const clsBtn = editable ? "" : "d-none";

  return `
  
    <div class="meet-block border rounded p-3 mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <strong>Reunión (Meet)</strong>
        ${chip}
      </div>

      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
          <small class="text-muted d-block">Plataforma</small>
          <div>${plat}</div>
        </div>
        <div class="col-12 col-md-8">
          <small class="text-muted d-block">Enlace</small>
          <div>${linkHtml}</div>
        </div>
      </div>

      <div class="mt-3 d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-primary ${clsBtn}" id="btnSolicitarMeet">
          <i class="bi bi-person-video3"></i> Solicitar
        </button>
        <button type="button" class="btn btn-sm btn-success ${clsBtn}" id="btnEstablecerMeet">
          <i class="bi bi-camera-video"></i> Establecer
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger ${clsBtn}" id="btnCancelarMeet">
          <i class="bi bi-x-circle"></i> Cancelar
        </button>

        <!-- Botones informativos (siempre visibles) -->
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAyudaLogs">
          <i class="bi bi-question-circle"></i> Videos de ayuda
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSolicitarAyuda">
          <i class="bi bi-envelope"></i> Solicitar ayuda
        </button>
      </div>
    </div>
  `;
}

// Carga y pinta el bloque Meet dentro del offcanvas
// opts: { editable: boolean }
function cargarBloqueMeet(tiId, opts = {}) {
  const { editable = false, targetId = "offcanvasContent" } = opts;
  return $.getJSON("../php/meet_get.php", { ticketId: tiId }).then((res) => {
    if (!res?.success) return;
    const target =
      document.getElementById(targetId) ||
      document.getElementById("offcanvasContent");
    target.innerHTML = renderMeetBlock(res.data || {}, { editable });
    wireMeetButtons(tiId, res.data || {}, { editable });
  });
}

// En tu wireMeetButtons(tiId, meetData, { editable }) ya mostras el modal en modo cliente/ingeniero.
// Solo asegúrate de llamar a esto dentro de wireMeetButtons, después de mostrar el modal:
function prepararModalMeet(tiId, editable) {
  // Guarda si es editable (proceso 'meet') para re-render
  $("#formMeet").data("editable", !!editable);
  $("#meet_ticketId").val(tiId);

  // Al hacer click en los botones de envío, fijamos el "modo" apropiado
  $('#formMeet [data-rol="cliente"]')
    .off("click.setModo")
    .on("click.setModo", function () {
      $("#meet_modo").val("solicitar_cliente");
    });
  $('#formMeet [data-rol="ingeniero"]')
    .off("click.setModo")
    .on("click.setModo", function () {
      $("#meet_modo").val("establecer_ingeniero");
    });
}

// Handler único del submit del modal
$(document)
  .off("submit", "#formMeet")
  .on("submit", "#formMeet", function (e) {
    e.preventDefault();

    const tiId = $("#meet_ticketId").val();
    const modo = $("#meet_modo").val() || "solicitar_cliente"; // fallback
    const plat = $("#meet_plataforma").val();
    const link = $("#meet_link").val();
    const fecha = $("#meet_fecha").val(); // YYYY-MM-DD (o '')
    const hora = $("#meet_hora").val(); // HH:MM (o '')
    const editable = $("#formMeet").data("editable") === true;

    $.post(
      "../php/meet_actualizar.php",
      {
        ticketId: tiId,
        modo: modo, // 'solicitar_cliente' | 'establecer_ingeniero'
        plataforma: plat || "",
        link: link || "",
        fecha: fecha,
        hora: hora,

      },
      function (r) {
        if (r && r.success) {
          // Cierra el modal
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("modalMeet")
          );
          modal && modal.hide();

          // Refresca bloque Meet dentro del detalle
          cargarBloqueMeet(tiId, { editable, targetId: "meetAnchor" });

          // (Opcional) refrescar listado por sede
          // cargarTicketsPorSede();
        } else {
          alert(r?.error || "No se pudo actualizar la reunión.");
        }
      },
      "json"
    ).fail(() => {
      alert("Error de red al actualizar la reunión.");
    });
  });

// Ata eventos a los botones (si editable)
function wireMeetButtons(tiId, meetData, opts = {}) {
  const editable = !!opts.editable;

  // Siempre: ayuda (videos)
  $("#btnAyudaLogs")
    .off("click")
    .on("click", () => {
      // Ajusta ruta si tu página de ayuda es otra
      window.open("../ayuda/videos_logs.php", "_blank", "noopener");
    });

  // Siempre: solicitar ayuda (correo)
  $("#btnSolicitarAyuda")
    .off("click")
    .on("click", () => {
      $.post(
        "../php/solicitar_ayuda.php",
        { ticketId: tiId },
        (r) => {
          if (r?.success) {
            alert("Solicitud enviada al ingeniero a cargo.");
          } else {
            alert(r?.error || "No se pudo enviar la solicitud.");
          }
        },
        "json"
      );
    });

  if (!editable) {
    // No enlazar acciones de edición si no es el paso "meet"
    $("#btnSolicitarMeet").off("click");
    $("#btnEstablecerMeet").off("click");
    $("#btnCancelarMeet").off("click");
    return;
  }

  // Solicitar (modo cliente)
  // Modo CLIENTE
  function prefillMeetModal(meetData) {
    // plataforma/link ya los rellenas; agrega:
    if (meetData.tiMeetFecha) {
      const d = new Date(meetData.tiMeetFecha.replace(" ", "T"));
      if (!isNaN(d.getTime())) {
        // yyyy-mm-dd
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, "0");
        const dd = String(d.getDate()).padStart(2, "0");
        // hh:mm
        const hh = String(d.getHours()).padStart(2, "0");
        const mi = String(d.getMinutes()).padStart(2, "0");
        $("#meet_fecha").val(`${yyyy}-${mm}-${dd}`);
        $("#meet_hora").val(`${hh}:${mi}`);
      } else {
        $("#meet_fecha").val("");
        $("#meet_hora").val("");
      }
    } else {
      $("#meet_fecha").val("");
      $("#meet_hora").val("");
    }
  }

  $("#btnSolicitarMeet")
    .off("click")
    .on("click", () => {
      $("#meet_ticketId").val(tiId);
      $("#meet_modo").val("solicitar");
      $("#meet_plataforma").val(meetData.tiMeetPlataforma || "");
      $("#meet_link").val(meetData.tiMeetLink || "");
      prefillMeetModal(meetData); // <- fecha/hora
      new bootstrap.Modal(document.getElementById("modalMeet")).show();
    });

  $("#btnEstablecerMeet")
    .off("click")
    .on("click", () => {
      $("#meet_ticketId").val(tiId);
      $("#meet_modo").val("establecer");
      $("#meet_plataforma").val(meetData.tiMeetPlataforma || "");
      $("#meet_link").val(meetData.tiMeetLink || "");
      prefillMeetModal(meetData); // <- fecha/hora
      new bootstrap.Modal(document.getElementById("modalMeet")).show();
    });

  // Cancelar meet
  $("#btnCancelarMeet")
    .off("click")
    .on("click", () => {
      $.post(
        "../php/meet_actualizar.php",
        { ticketId: tiId, modo: "cancelar" },
        (r) => {
          if (r?.success) {
            // Re-cargar bloque y (si quieres) refrescar listado
            cargarBloqueMeet(tiId, { editable: true });
            // cargarTicketsPorSede();
          } else {
            alert(r?.error || "No se pudo cancelar el meet");
          }
        },
        "json"
      );
    });
}

// Videos de ayuda (abre tu página de ayuda según marca/equipo)
$("#btnAyudaLogs")
  .off("click")
  .on("click", () => {
    // Ejemplo: abre una página de ayuda con filtros por marca/equipo
    window.open(`ayuda_videos.php?tiId=${tiId}`, "_blank");
  });

// Notificar ayuda por correo (puedes hacer un endpoint que use PHPMailer)
$("#btnSolicitarAyuda")
  .off("click")
  .on("click", () => {
    $.post(
      "../php/notificar_ayuda.php",
      { ticketId: tiId },
      (r) => {
        if (r?.success) alert("Se notificó al ingeniero de apoyo.");
        else alert(r?.error || "No se pudo enviar la notificación.");
      },
      "json"
    );
  });

// Submit del modal
$("#formMeet")
  .off("submit")
  .on("submit", function (e) {
    e.preventDefault();
    const tiId = $("#meet_ticketId").val();
    const modo = $("#meet_modo").val(); // solicitar | establecer
    const plat = $("#meet_plataforma").val();
    const link = $("#meet_link").val();
    const fecha = $("#meet_fecha").val(); // YYYY-MM-DD (o '')
    const hora = $("#meet_hora").val(); // HH:MM (o '')

    $.post(
      "../php/meet_actualizar.php",
      {
        ticketId: tiId,
        modo,
        plataforma: plat,
        link,
        fecha,
        hora,
      },
      function (r) {
        if (r?.success) {
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("modalMeet")
          );
          modal && modal.hide();
          typeof mostrarToast === "function"
            ? mostrarToast("success", "Meet actualizado.")
            : alert("Meet actualizado.");

          // refresca bloque
          if (typeof cargarBloqueMeet === "function") cargarBloqueMeet(tiId);
          // (opcional) refrescar lista por sede
          // if (typeof cargarTicketsPorSede === 'function') cargarTicketsPorSede();
        } else {
          typeof mostrarToast === "function"
            ? mostrarToast(
              "error",
              r?.error || "No se pudo actualizar el Meet."
            )
            : alert(r?.error || "No se pudo actualizar el Meet.");
        }
      },
      "json"
    ).fail(() => {
      typeof mostrarToast === "function"
        ? mostrarToast("error", "Error de red.")
        : alert("Error de red.");
    });
  });

// TODO: Solicitar Ayuda
// Mostrar/ocultar campos de Meet dentro del modal
$(document)
  .off("change", "#ayuda_meet")
  .on("change", "#ayuda_meet", function () {
    $("#ayuda_meet_wrap").toggleClass("d-none", !this.checked);
  });

// Abre el modal con el ticket actual
function abrirModalAyuda(tiId) {
  $("#ayuda_ticketId").val(tiId);
  $("#ayuda_mensaje").val("");
  $("#ayuda_meet").prop("checked", false).trigger("change");
  $("#ayuda_plataforma").val("");
  $("#ayuda_link").val("");

  new bootstrap.Modal(document.getElementById("modalAyuda")).show();
}

// Conecta el botón del bloque Meet
// (llama esto dentro de wireMeetButtons después de construir el bloque)
function wireSolicitarAyudaButton(tiId) {
  $("#btnSolicitarAyuda")
    .off("click")
    .on("click", function () {
      abrirModalAyuda(tiId);
    });
}

// Enviar el formulario
$(document)
  .off("submit", "#formAyuda")
  .on("submit", "#formAyuda", function (e) {
    e.preventDefault();

    const tiId = $("#ayuda_ticketId").val();
    const msg = $("#ayuda_mensaje").val().trim();
    const pedir = $("#ayuda_meet").is(":checked") ? 1 : 0;
    const plat = $("#ayuda_plataforma").val();
    const link = $("#ayuda_link").val();

    if (!tiId || !msg) {
      typeof mostrarToast === "function"
        ? mostrarToast("error", "Completa el mensaje.")
        : alert("Completa el mensaje.");
      return;
    }

    $.post(
      "../php/solicitar_ayuda.php",
      {
        ticketId: tiId,
        mensaje: msg,
        solicitar_meet: pedir,
        plataforma: plat || "",
        link: link || "",
      },
      function (r) {
        if (r?.success) {
          const modal = bootstrap.Modal.getInstance(
            document.getElementById("modalAyuda")
          );
          modal && modal.hide();

          typeof mostrarToast === "function"
            ? mostrarToast("success", "Solicitud enviada al ingeniero.")
            : alert("Solicitud enviada.");

          // Refresca bloque Meet del detalle (si estás en el offcanvas)
          if (typeof cargarBloqueMeet === "function") {
            cargarBloqueMeet(tiId);
          }
          // (Opcional) refrescar listado por sede:
          // if (typeof cargarTicketsPorSede === 'function') cargarTicketsPorSede();
        } else {
          typeof mostrarToast === "function"
            ? mostrarToast(
              "error",
              r?.error || "No se pudo enviar la solicitud."
            )
            : alert(r?.error || "No se pudo enviar la solicitud.");
        }
      },
      "json"
    ).fail(() => {
      typeof mostrarToast === "function"
        ? mostrarToast("error", "Error de red.")
        : alert("Error de red.");
    });
  });
function fmtDateTimeLocal(dtStr) {
  if (!dtStr) return "—";
  const d = new Date(dtStr.replace(" ", "T")); // simple parse
  if (isNaN(d.getTime())) return dtStr;
  return d.toLocaleString(); // o tu propio formateador
}

function renderMeetBlock(data) {
  const estado = data.tiMeetActivo || null;
  const plat = data.tiMeetPlataforma || "--";
  const link = data.tiMeetLink || "";
  const when = data.tiMeetFecha ? fmtDateTimeLocal(data.tiMeetFecha) : "—";

  const chip = estado
    ? `<span class="badge ${chipMeetColor(estado)}">${estado}</span>`
    : `<span class="badge bg-secondary">sin meet</span>`;

  const linkHtml = link
    ? `<a href="${link}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Abrir</a>`
    : `<span class="text-muted">Sin enlace</span>`;

  return `
    <div class="border rounded p-3 mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <strong>Reunión (Meet)</strong>
        ${chip}
      </div>

      <div class="row g-3 align-items-center">
        <div class="col-12 col-md-4">
          <small class="text-muted">Plataforma</small>
          <div>${plat}</div>
        </div>
        <div class="col-12 col-md-4">
          <small class="text-muted">Fecha/Hora</small>
          <div>${when}</div>
        </div>
        <div class="col-12 col-md-4">
          <small class="text-muted">Enlace</small>
          <div>${linkHtml}</div>
        </div>
      </div>

      <div class="mt-3 d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-primary" id="btnSolicitarMeet">
          <i class="bi bi-person-video3"></i> Solicitar
        </button>
        <button type="button" class="btn btn-sm btn-success" id="btnEstablecerMeet">
          <i class="bi bi-camera-video"></i> Establecer
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger" id="btnCancelarMeet">
          <i class="bi bi-x-circle"></i> Cancelar
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAyudaLogs">
          <i class="bi bi-question-circle"></i> Videos de ayuda
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSolicitarAyuda">
          <i class="bi bi-envelope"></i> Solicitar ayuda
        </button>
      </div>
    </div>
  `;
}



function cardHoja(item) {
  // Marca / modelo
  const marcaImg = item.maNombre ? `../img/Marcas/${item.maNombre.toLowerCase()}.png` : '';
  const titulo = `${item.eqModelo || 'Equipo'} ${item.eqVersion || ''}`.trim();

  const descargable = !!item.disponible && !!item.url;

  return `
      <div class="col-12 col-sm-6 col-md-4">
        <div class="card h-100 ${descargable ? '' : 'border-0'}" style="${descargable ? '' : 'background:#f8f9fb'}">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="badge bg-light text-dark">Ticket #${item.tiId}</span>
              <span class="badge ${item.disponible ? 'bg-success' : 'bg-secondary'}">
                ${item.disponible ? 'Disponible' : 'Pendiente'}
              </span>
            </div>

            <div class="d-flex align-items-center gap-2 mb-2">
              ${marcaImg ? `<img src="${marcaImg}" style="height:22px" alt="${item.maNombre}">` : ''}
              <strong>${titulo || '—'}</strong>
            </div>

            <div class="small text-muted mb-1"><i class="bi bi-geo-alt"></i> ${item.csNombre || 'General'}</div>
            <div class="small text-muted mb-2"><i class="bi bi-calendar"></i> ${item.tiFecha || '—'}</div>

            <div class="d-flex align-items-center justify-content-between mt-3">
              <span class="badge bg-light text-dark">${item.eqTipoEquipo || '—'}</span>
              <span class="badge ${item.tiEstatus === 'Finalizado' ? 'bg-primary' : 'bg-light text-dark'}">${item.tiEstatus}</span>
            </div>
          </div>

          <div class="card-footer bg-transparent border-0">
            ${descargable
      ? `<a href="${item.url}" class="btn btn-outline-primary w-100" download>
                    <i class="bi bi-download"></i> Descargar hoja de servicio
                 </a>`
      : `<button class="btn btn-outline-secondary w-100" disabled>
                    <i class="bi bi-file-earmark"></i> Hoja no disponible
                 </button>`
    }
          </div>
        </div>
      </div>
    `;
}

function cargarHojasServicio() {
  const desde = document.getElementById('hsDesde').value || '';
  const hasta = document.getElementById('hsHasta').value || '';
  const tipo = document.getElementById('hsTipoEquipo').value || '';

  const qs = new URLSearchParams();
  if (desde) qs.set('desde', desde);
  if (hasta) qs.set('hasta', hasta);
  if (tipo) qs.set('tipoEquipo', tipo);

  // Si eres MRA y quieres ver todas, no pases clId. 
  // Si deseas filtrar por cliente/sede desde aquí: qs.set('clId', NNN); qs.set('csId', NNN);

  fetch(`../php/obtener_hojas_servicio.php${qs.toString() ? '?' + qs.toString() : ''}`, { cache: 'no-store' })
    .then(r => r.json())
    .then(json => {
      const wrap = document.getElementById('wrapHojas');
      if (!json?.success) {
        wrap.innerHTML = `<div class="col-12"><div class="alert alert-danger">${json?.error || 'No se pudo cargar.'}</div></div>`;
        return;
      }
      const items = json.items || [];
      if (!items.length) {
        wrap.innerHTML = `<div class="col-12"><p class="text-muted">Sin resultados.</p></div>`;
        return;
      }
      wrap.innerHTML = items.map(cardHoja).join('');
    })
    .catch(() => {
      document.getElementById('wrapHojas').innerHTML =
        `<div class="col-12"><div class="alert alert-danger">Error de red.</div></div>`;
    });
}

document.getElementById('hsBtnFiltrar')?.addEventListener('click', cargarHojasServicio);
document.getElementById('hsBtnReset')?.addEventListener('click', () => {
  document.getElementById('hsDesde').value = '';
  document.getElementById('hsHasta').value = '';
  document.getElementById('hsTipoEquipo').value = '';
  cargarHojasServicio();
});

document.addEventListener('DOMContentLoaded', cargarHojasServicio);

// TODO Elimina el sistema de Tickets
// en main.js (DOMContentLoaded)
document.addEventListener('DOMContentLoaded', () => {
  if (window.SESSION && window.SESSION.rol === 'EC') {
    const btn = document.getElementById('btnNuevoTicket');
    if (btn) btn.classList.add('d-none');
  }
});

