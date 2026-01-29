// nuevoticket.js

$(document).ready(function () {
  // Cargar equipos al entrar
  cargarEquipos();

  // =========================
  // Paso 1: elegir equipo
  // =========================
  $("#contenedorEquipos").on("click", ".equipo-card", function () {
    const peId = $(this).data("peid");
    const puedeCrear = $(this).data("puedeCrear"); // booleano desde data-attr
    const estado = $(this).data("estado");         // vigente | proxima | vencida

    if (!peId) {
      mostrarToast('error', 'No se pudo identificar el equipo seleccionado.');
      return;
    }

    // Si la póliza está vencida, NO dejamos avanzar
    if (String(puedeCrear) === "false") {
      mostrarToast('error', 'La póliza de este equipo está vencida. No es posible crear un ticket.');
      return;
    }

    $("#selectedPeId").val(peId); // hidden en el form

    // Pasar al paso 2 (severidad)
    $("#paso1").hide();
    $("#paso2").fadeIn();
  });

  // =========================
  // Paso 2: elegir severidad
  // =========================
  $(".severidad-card").on("click", function () {
    const severidad = $(this).data("severidad");
    if (!severidad) {
      mostrarToast('error', 'No se pudo identificar la severidad.');
      return;
    }

    $("#selectedSeveridad").val(severidad);

    // Resaltar card seleccionada (opcional)
    $(".severidad-card").removeClass("border-primary");
    $(this).addClass("border-primary");

    // Pasar al paso 3 (formulario)
    $("#paso2").hide();
    $("#paso3").fadeIn();
  });

  // =========================
  // Botones "Volver"
  // =========================
  $("#btnVolverPaso1").on("click", function () {
    // Volver de severidad a equipos
    $("#paso2").hide();
    $("#paso1").fadeIn();
  });

  $("#btnVolverPaso2").on("click", function () {
    // Volver de formulario a severidad
    $("#paso3").hide();
    $("#paso2").fadeIn();
  });

  // =========================
  // Paso 3: Enviar ticket
  // =========================
  $("#formTicket").on("submit", function (e) {
    e.preventDefault();

    const peId = $("#selectedPeId").val();
    const severidad = $("#selectedSeveridad").val();
    const descripcion = $("#descripcion").val() ? $("#descripcion").val().trim() : "";
    const contacto = $("#contacto").val() ? $("#contacto").val().trim() : "";
    const telefono = $("#telefono").val() ? $("#telefono").val().trim() : "";
    const email = $("#email").val() ? $("#email").val().trim() : "";

    // Validación básica
    if (!peId || !severidad || !descripcion || !contacto || !telefono || !email) {
      mostrarToast('error', 'Todos los campos son requeridos.');
      return;
    }

    // Validación de correo simple
    const emailRegex = /^\S+@\S+\.\S+$/;
    if (!emailRegex.test(email)) {
      mostrarToast('error', 'Por favor ingresa un correo electrónico válido.');
      return;
    }

    const formData = new FormData(this);
    // Asegurarnos de que se mandan peId y severidad
    formData.set('peId', peId);
    formData.set('severidad', severidad);

    const $btnSubmit = $("#btnEnviarTicket"); // si existe

    if ($btnSubmit.length) {
      $btnSubmit.prop("disabled", true).text("Creando ticket...");
    }

    $.ajax({
      url: '../../php/crear_ticket.php',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (resp) {
        if (resp && resp.success) {
          const ticketId = resp.tiId ? resp.tiId : '';
          const msg = ticketId
            ? `Ticket creado correctamente. ID: ${ticketId}`
            : 'Ticket creado correctamente.';
          mostrarToast('success', msg);

          // Limpiar formulario y regresar al paso 1
          $("#formTicket")[0].reset();
          $("#selectedPeId").val('');
          $("#selectedSeveridad").val('');
          $(".severidad-card").removeClass("border-primary");

          $("#paso3").hide();
          $("#paso1").fadeIn();

          // Opcional: recargar equipos para actualizar "Ticket Activo"
          cargarEquipos();
        } else {
          mostrarToast('error', (resp && resp.error) ? resp.error : 'Error al crear el ticket.');
        }
      },
      error: function () {
        mostrarToast('error', 'Error de comunicación con el servidor al crear el ticket.');
      },
      complete: function () {
        if ($btnSubmit.length) {
          $btnSubmit.prop("disabled", false).text("Crear ticket");
        }
      }
    });
  });
});


// ========================================
// Cargar y pintar equipos en póliza
// ========================================

// Formatea "10,11" -> "ENE 10, ENE 11"
function formatearTickets(prefix, listaIds) {
  if (!listaIds) return '';
  const ids = listaIds
    .split(',')
    .map(id => id.trim())
    .filter(Boolean);

  if (!ids.length) return '';

  return ids.map(id => `${prefix} ${id}`).join(', ');
}

// Pinta las cards de equipos dentro de #contenedorEquipos
function renderEquipos(equipos) {
  const cont = document.getElementById('contenedorEquipos');
  if (!cont) return;

  if (!Array.isArray(equipos) || equipos.length === 0) {
    cont.innerHTML = `
      <div class="col-12">
        <div class="alert alert-warning text-center mb-0">
          No se encontraron equipos en póliza para este cliente.
        </div>
      </div>`;
  return;
  }

  // Agrupar por tipo de equipo (eqTipoEquipo)
  const grupos = {};
  equipos.forEach(equipo => {
    const tipo = equipo.eqTipoEquipo || 'Otros';
    if (!grupos[tipo]) {
      grupos[tipo] = [];
    }
    grupos[tipo].push(equipo);
  });

  let html = '';

  Object.keys(grupos).forEach(tipo => {
    html += `<h3 class="mt-4">${tipo}</h3><div class="row">`;

    grupos[tipo].forEach(equipo => {
      const ticketsCount = Number(
        (equipo.ticketsAbiertosCount !== undefined
          ? equipo.ticketsAbiertosCount
          : equipo.tieneTicket) || 0
      );
      const tieneTicket = ticketsCount > 0;

      // --- Datos de vigencia ---
      const estado = equipo.polizaEstado || 'vigente';     // vigente | proxima | vencida
      const diasRestantes = equipo.diasRestantes;          // puede ser null o negativo
      const puedeCrear = !!equipo.puedeCrearTicket;        // bool

      // Clases para la card
      let borderColor = 'border-success';
      let cardExtraClasses = '';
      if (estado === 'vencida') {
        borderColor = 'border-danger';
        cardExtraClasses = ' opacity-50'; // visualmente deshabilitado
      } else if (estado === 'proxima') {
        borderColor = 'border-warning';
      } else if (tieneTicket) {
        borderColor = 'border-warning';
      }

      // Badge de ticket activo
      const badgeTicket = tieneTicket
        ? `<span class="badge bg-warning text-dark mb-2">Ticket activo</span>`
        : '';

      // Badge de estado de póliza
      let badgePoliza = '';
      const fechaInicio = equipo.pcFechaInicio || '';
      const fechaFin = equipo.pcFechaFin || '';

      if (estado === 'vencida') {
        badgePoliza = `
          <span class="badge bg-danger ms-2">
            Póliza vencida${fechaFin ? ` (venció ${fechaFin})` : ''}
          </span>`;
      } else if (estado === 'proxima') {
        const diasTxt = (typeof diasRestantes === 'number')
          ? `${diasRestantes} día${diasRestantes === 1 ? '' : 's'}`
          : 'Próxima a vencer';
        badgePoliza = `
          <span class="badge bg-warning text-dark ms-2">
            Próxima a vencer (${diasTxt})
          </span>`;
      } else {
        // vigente
        badgePoliza = `
          <span class="badge bg-success-subtle text-success ms-2">
            Vigente${fechaFin ? ` (vence ${fechaFin})` : ''}
          </span>`;
      }

      const prefix = equipo.ticketPrefix || 'TIC';
      const ticketsTexto = tieneTicket
        ? `<p class="mb-0"><strong>Tickets abiertos:</strong> ${formatearTickets(prefix, equipo.ticketsAbiertosIds)}</p>`
        : '';

      const maNombreLower = (equipo.maNombre || '').toLowerCase();
      const eqModelo = equipo.eqModelo || '';
      const sn = equipo.peSN || '';
      const tipoPoliza = equipo.pcTipoPoliza || '--';
      const sede = equipo.csNombre ? ` · ${equipo.csNombre}` : '';

      html += `
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-3">
          <div class="card shadow-sm equipo-card ${borderColor}${cardExtraClasses}"
               data-peid="${equipo.peId}"
               data-estado="${estado}"
               data-puede-crear="${puedeCrear}"
               style="border-width: 2px; cursor:pointer;">
              
            <img src="../img/Equipos/${maNombreLower}/${eqModelo}.png" 
                 class="card-img-top" 
                 alt="${eqModelo}"
                 onerror="this.src='../img/Equipos/default.png';">

            <div class="card-body">
              <h5 class="card-title mb-1">${eqModelo}</h5>
              <p class="mb-1 text-muted" style="font-size:0.85rem;">
                ${fechaInicio && fechaFin ? `${fechaInicio} – ${fechaFin}` : ''}
                ${sede}
              </p>
              ${badgeTicket}
              ${badgePoliza}
              <p class="mb-1 mt-2"><strong>SN:</strong> ${sn}</p>
              <p class="mb-1">
                <img src="../img/Marcas/${maNombreLower}.png" 
                     alt="${equipo.maNombre || ''}" 
                     style="height:30px;"
                     onerror="this.style.display='none';">
              </p>
              <p class="mb-1"><strong>Tipo de Póliza:</strong> ${tipoPoliza}</p>
              ${ticketsTexto}
            </div>
          </div>
        </div>`;
    });

    html += `</div>`; // cierra row del tipo
  });

  cont.innerHTML = html;
}

// Llama al PHP para obtener equipos visibles para el usuario
function cargarEquipos() {
  $.ajax({
    url: '../php/obtener_equipo_poliza.php',
    method: 'GET',
    dataType: 'json',
    success: function (resp) {
      if (!resp || resp.success !== true) {
        mostrarToast('error', (resp && resp.error) ? resp.error : 'No se pudieron cargar los equipos.');
        return;
      }

      const equipos = resp.mode === 'lista'
        ? (resp.equipos || [])
        : (resp.equipos || []); // compat simple

      renderEquipos(equipos);
    },
    error: function () {
      $("#contenedorEquipos").html(
        '<p class="text-center text-danger">Error al cargar equipos.</p>'
      );
    }
  });
}

// ========================================
// Toasts Bootstrap
// ========================================
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
