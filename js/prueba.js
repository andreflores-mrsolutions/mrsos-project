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


$(document).ready(function () {
  function contieneInyeccion(str) {
    const pattern = /<|>|script|onerror|alert|select|insert|delete|update|union|drop|--|;|['"]/gi;
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
        text: "Por favor completa ambos campos."
      });
      return;
    }

    // Validación contra inyecciones
    if (contieneInyeccion(usId) || contieneInyeccion(usPass)) {
      Swal.fire({
        icon: "error",
        title: "Entrada no válida",
        text: "Tu información contiene caracteres o palabras no permitidas."
      });
      return;
    }

    // TODO: Aquí irá el fetch o $.ajax() a login.php
    $.ajax({
      url: '../php/login.php',
      method: 'POST',
      data: {
        usId: usId,
        usPass: usPass
      },
      dataType: 'json', // 👈 Esto es importante
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
      }
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
        text: "Por favor completa ambos campos."
      });
      return;
    }

    // Validación contra inyecciones
    if (contieneInyeccion(email)) {
      Swal.fire({
        icon: "error",
        title: "Entrada no válida",
        text: "Tu información contiene caracteres o palabras no permitidas."
      });
      return;
    }

    // TODO: Aquí irá el fetch o $.ajax() a login.php
    $.ajax({
      url: '../php/recuperar_password.php',
      method: 'POST',
      data: {
        usEmail: email,
      },
      success: function (response) {
        if (response.success) {
          Swal.fire({
            icon: "warning",
            title: "Correo Enviado",
            text: "En tu correo has recibido la solicitud de cambio de Contraseña."
          });
          return;
        }
        else {
          Swal.fire("Error", "El email no coincide con el registrado", "error");
        }
      }
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

//TODO: Cards tablas

$(document).ready(function () {
  $.ajax({
    url: 'php/get_tickets.php',
    type: 'GET',
    dataType: 'json',
    success: function (data) {
      if (data.length > 0) {
        let container = $('#ticketCardsContainer');
        data.forEach(ticket => {
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
                                                <img src="img/Tickets/${ticket.tiProceso}.png" alt="Status ${ticket.tiEstatus}" class="ticket-card mt-5 mx-auto">
                                            </li>
                                            <li class="mt-2"><b>Estatus:</b></li>
                                            <li>${ticket.tiEstatus}</li>
                                        </ul>
                                    </div>
                                    <div class="col-7 col-sm-7">
                                        <h4 class="card-title text-end">${ticket.eqModelo}</h4>
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
                                            <img src="img/Marcas/${ticket.maNombre.toLowerCase()}.png" alt="${ticket.maNombre}" style="width:120px;">
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
    }
  });
});
$(document).ready(function () {
  // Cargar los tickets al inicio y cuando cambian filtros
  cargarTickets();

  $("#filtroEstado, #filtroMarca, #filtroProceso, #filtroTipoEquipo").change(cargarTickets);
});


//TODO Reiniciar Filtros
$(document).ready(function () {
  // Cargar tickets iniciales
  cargarTickets();

  // Cambio en los filtros
  $("#filtroEstado, #filtroMarca, #filtroProceso, #filtroTipoEquipo").change(cargarTickets);

  // Botón reset filtros
  $("#resetFiltros").click(function () {
    $("#filtroEstado").val('Todo');
    $("#filtroMarca").val('');
    $("#filtroProceso").val('');
    $("#filtroTipoEquipo").val('');
    cargarTickets();
  });

  // Botón recargar
  $("#btnRecargar").click(function () {
    cargarTickets();
  });
});


//TODO Filtro para cards y asignacion de fechas 
// Función para cargar los tickets con los filtros
function cargarTickets() {
  const estado = $("#filtroEstado").val();
  const marca = $("#filtroMarca").val();
  const proceso = $("#filtroProceso").val();
  const tipoEquipo = $("#filtroTipoEquipo").val();

  $.ajax({
    url: '../php/obtener_tickets.php',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ estado, marca, proceso, tipoEquipo }),
    success: function (response) {
      let html = '';
      if (response.length === 0) {
        html = '<p class="text-center">No hay tickets que coincidan con los filtros.</p>';
      } else {
        // array de procesos EN ORDEN para dibujar pasos
        const procesos = [
          'asignacion', 'revision inicial', 'logs', 'meet', 'revision especial',
          'asignacion fecha', 'fecha asignada', 'espera ventana', 'espera visita',
          'en camino', 'espera documentacion', 'encuesta satisfaccion', 'finalizado'
        ];

        response.forEach(ticket => {
          const proc = (ticket.tiProceso || '').toLowerCase();
          const paso = procesos.indexOf(proc) + 1;               // Paso actual (1–13)
          const total = procesos.length;                         // 13
          const widthPct = Math.round(paso / total * 100);

          html += `
          
          <div class="col-12 col-sm-6 col-md-4 mb-4">
            <div class="card service-card position-relative overflow-hidden">
              <!-- Número de ticket -->
              <div class="ticket-number position-absolute top-0 start-0 px-2 py-1 bg-white rounded-end">
                <strong>${ticket.tiId}</strong>
              </div>

              <!-- Imagen del equipo -->
              <img src="../img/Equipos/${ticket.maNombre.toLowerCase()}/${ticket.eqModelo}.png"
                  class="card-img-top equipo-img "
                  alt="${ticket.eqModelo}">

              <!-- Serial debajo de la imagen -->
              <div class="serial-number ms-2">
                <span class="badge" style="background-color:#cdd6ff; color:black;">${ticket.peSN}</span>
              </div>

              <!-- Nombre del modelo en vertical -->
              <div class="model-vertical text-primary">
                ${ticket.eqModelo} ${ticket.eqVersion}
              </div>
              <!-- Nombre del modelo en vertical -->
              <div class="model-vertical-marca text-primary">
                <img src="../img/Marcas/${ticket.maNombre.toLowerCase()}.png" alt="${ticket.maNombre}" style="width:120px;">
              </div>

              <!-- Puntos de progreso -->
              <div class="progress-steps d-flex justify-content-between align-items-center mx-5 my-3 px-3">
                ${procesos.map((p, i) =>
                      `<span class="step ${i < paso ? 'active' : ''}"></span>`
                    ).join('')}
              </div>
              <div class="progress-bar-custom mx-5 mb-3">
                <div class="progress-fill" style="width: ${widthPct}%;"></div>
              </div>

              <!-- Iconos de proceso (pon los que necesites) -->
              <div class="d-flex justify-content-around mb-5 mt-5">
                <div class="text-center card-in process-icon">
                  <img src="../img/Tickets/${proc}.png" alt="${proc}" />
                  <small>${ticket.tiProceso}</small>
                </div>
                <!-- ejemplar: si quisieras mostrar otros dos iconos fijos: -->
                <div class="text-center process-icon card-in">
                  <img src="../img/Tickets/ticket de servicio.png" alt="meeting" />
                  <small>Ticket de Servicio</small>
                </div>
                <div class="text-center process-icon card-btn">
                  <img src="../img/Tickets/google.png" alt="logs" />
                  <small>Google Meet</small>
                </div>
              </div>

              <!-- Ver más -->
              <div class="text-center mb-3 px-3">
                <button type="button" class="btn w-100 btn-card" onclick="abrirDetalle(${ticket.tiId})" data-bs-toggle="offcanvas" data-bs-target="#offcanvasTicket">Ver más..</button>
              </div>
            </div>
          </div>`;
        });
      }
      $("#contenedorTickets").html(html);
    },
    error: function () {
      $("#contenedorTickets").html('<p class="text-center text-danger">Error al cargar los tickets.</p>');
    }
  });
}


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
    url: '../php/asignar_fecha.php',
    method: 'POST',
    data: { ticketId, tiVisita },
    success: function (response) {
      console.log(response); // Para monitoreo
      if (response.success) {
        alert("Fecha asignada correctamente.");
        $(`#modalFecha_${ticketId}`).modal('hide');
        cargarTickets(); // Recargar para actualizar
      } else if (response.error) {
        alert("Error: " + response.error);
      } else {
        alert("Error desconocido al asignar fecha.");
      }
    },
    error: function () {
      alert("Error de comunicación con el servidor.");
    }
  });
}



//TODO ver mas detalles
// Función para llenar el off-canvas dinámico
function abrirDetalle(ticketId) {
  $.ajax({
    url: '../php/detalle_ticket.php',
    method: 'POST',
    data: { ticketId },
    success: function (data) {
      // Determinar color del badge por criticidad
      let badgeColor = 'bg-success';
      if (data.tiNivelCriticidad == '1') badgeColor = 'bg-danger';
      else if (data.tiNivelCriticidad == '2') badgeColor = 'bg-warning';

      // Progreso del proceso
      const procesos = [
        'asignacion', 'revision inicial', 'logs', 'meet', 'asignacion fecha', 'fecha asignada',
        'espera ventana', 'espera visita', 'en camino', 'espera documentacion',
        'encuesta satisfaccion', 'finalizado'
      ];
      const progreso = procesos.indexOf(data.tiProceso.toLowerCase()) + 1;
      const progresoPorcentaje = (progreso / procesos.length) * 100;

      // Fechas formateadas
      const fechaCreacion = new Date(data.tiFechaCreacion).toLocaleDateString();
      const visita = data.tiVisita ? new Date(data.tiVisita).toLocaleString() : 'No asignada';

      // Contenido HTML
      $("#offcanvasContent").html(`
        <div class="text-center">
          <img src="../img/Equipos/${data.maNombre.toLowerCase()}/${data.eqModelo}.png" alt="Equipo" class="img-fluid mb-3" style="max-height:200px;">
        </div>
        <h5>${data.eqModelo}</h5>
        <p><b>SN:</b> ${data.peSN}</p>
        <div class="d-flex align-items-center">
          <img src="../img/Marcas/${data.maNombre.toLowerCase()}.png" alt="${data.maNombre}" style="width:50px; height:auto; margin-right:10px;">
          <span>${data.maNombre}</span>
        </div>
        <hr>
        <p><b>Descripción:</b><br>${data.tiDescripcion}</p>
        <p><b>Estado:</b> ${data.tiEstatus}</p>
        <p><b>Proceso actual:</b> ${data.tiProceso}</p>
        <div class="progress mb-2">
          <div class="progress-bar" role="progressbar" style="width:${progresoPorcentaje}%;" aria-valuenow="${progreso}" aria-valuemin="0" aria-valuemax="12">${progreso}/12</div>
        </div>
        <div class="d-flex justify-content-between">
          ${procesos.map((p, i) => `
            <span class="small ${i < progreso ? 'text-success' : 'text-muted'}" style="font-size:0.75em;">●</span>
          `).join('')}
        </div>
        <hr>
        <p><b>Nivel de Criticidad:</b> <span class="badge ${badgeColor}">${'Nivel ' + data.tiNivelCriticidad}</span></p>
        <p><b>Fecha de Creación:</b> ${fechaCreacion}</p>
        <p><b>Fecha/Hora de Visita:</b> ${visita}</p>
      `);
    },
    error: function () {
      $("#offcanvasContent").html('<p class="text-center text-danger">Error al cargar los detalles del ticket.</p>');
    }
  });
}

