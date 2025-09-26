$(document).ready(function () {
    cargarEquipos();

    // Paso 1 (elige equipo):
    $("#contenedorEquipos").on("click", ".equipo-card", function () {
        const peId = $(this).data("peid");
        $("#selectedPeId").val(peId); // hidden en el form
        $("#paso1").hide(); $("#paso2").fadeIn();
    });

    
    // Paso 2: Click en severidad
    $(".severidad-card").click(function () {
        const severidad = $(this).data("severidad");
        $("#selectedSeveridad").val(severidad);
        $("#paso2").hide();
        $("#paso3").fadeIn();
    });

    // Botón volver a paso 1
    $("#btnVolverPaso1").click(function () {
        $("#paso2").hide();
        $("#paso1").fadeIn();
    });

    // Botón volver a paso 2
    $("#btnVolverPaso2").click(function () {
        $("#paso3").hide();
        $("#paso2").fadeIn();
    });

    // Paso 3: Enviar formulario
    $("#formTicket").submit(function (e) {
        e.preventDefault();

        const eqId = $("#selectedEqId").val();
        const severidad = $("#selectedSeveridad").val();
        const descripcion = $("#descripcion").val().trim();
        const contacto = $("#contacto").val().trim();
        const telefono = $("#telefono").val().trim();
        const email = $("#email").val().trim();

        if (!eqId || !severidad || !descripcion || !contacto || !telefono || !email) {
            mostrarToast('error', 'Todos los campos son requeridos.');
            return;
        }

        if (!/^\S+@\S+\.\S+$/.test(email)) {
            mostrarToast('error', 'Por favor ingresa un correo válido.');
            return;
        }

        const formData = new FormData(this);
        formData.append('eqId', eqId);
        formData.append('severidad', severidad);

        $.ajax({
            url: '../../php/crear_ticket.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response.success) {
                    mostrarToast('success', 'Ticket creado correctamente.');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    mostrarToast('error', response.error || 'Error al crear el ticket.');
                }
            },
            error: function () {
                mostrarToast('error', 'Error en el servidor.');
            }
        });
    });
});

// Cargar equipos por tipo y organizados
function cargarEquipos() {
    $.ajax({
        url: '../php/obtener_equipo_poliza.php',
        method: 'GET',
        success: function (response) {
            if (!response.success) {
                $("#contenedorEquipos").html(`<p class="text-center text-danger">${response.error}</p>`);
                return;
            }

            const equiposPorTipo = {};
            response.equipos.forEach(equipo => {
                const tipo = equipo.eqTipoEquipo;
                if (!equiposPorTipo[tipo]) equiposPorTipo[tipo] = [];
                equiposPorTipo[tipo].push(equipo);
            });

            let html = '';
            for (const tipo in equiposPorTipo) {
                html += `<h3 class="mt-4">${tipo}</h3><div class="row">`;
                equiposPorTipo[tipo].forEach(equipo => {
                    const tieneTicket = parseInt(equipo.tieneTicket) > 0;  // Aquí validamos si tiene ticket activo
                    const borderColor = tieneTicket ? 'border-warning' : 'border-success'; // Cambiar color: naranja si tiene ticket
                    const badge = tieneTicket ? `<span class="badge bg-warning text-dark">Ticket Activo</span>` : '';

                    html += `
                    <div class="col-md-4 col-sm-6 mb-3">
                        <div class="card shadow-sm h-100 equipo-card ${borderColor}" data-peid="${equipo.peId}" style="border-width: 2px;">
                            <img src="../../img/Equipos/${equipo.maNombre.toLowerCase()}/${equipo.eqModelo}.png" class="card-img-top" alt="${equipo.eqModelo}">
                            <div class="card-body">
                                <h5>${equipo.eqModelo}</h5>
                                ${badge}
                                <p><b>SN:</b> ${equipo.peSN}</p>
                                <p><img src="../../img/Marcas/${equipo.maNombre.toLowerCase()}.png" style="height:30px;" alt="${equipo.maNombre}"></p>
                                <p><b>Tipo de Póliza:</b> ${equipo.pcTipoPoliza}</p>
                            </div>
                        </div>
                    </div>`;
                });
                html += `</div>`;
            }

            $("#contenedorEquipos").html(html);

            // Hover y clic
            $(".equipo-card").hover(
                function () { $(this).css({ transform: "scale(1.05)", boxShadow: "0 8px 16px rgba(0,0,0,0.3)" }); },
                function () { $(this).css({ transform: "scale(1)", boxShadow: "0 4px 8px rgba(0,0,0,0.1)" }); }
            ).css({ cursor: "pointer", transition: "transform 0.2s ease, box-shadow 0.2s ease" });
        },
        error: function () {
            $("#contenedorEquipos").html('<p class="text-center text-danger">Error al cargar equipos.</p>');
        }
    });
}

// Mostrar toast
function mostrarToast(tipo, mensaje) {
    const toastId = tipo === 'success' ? '#toastSuccess' : '#toastError';
    $(`${toastId} .toast-body`).text(mensaje);
    const toast = new bootstrap.Toast(document.querySelector(toastId));
    toast.show();
}
