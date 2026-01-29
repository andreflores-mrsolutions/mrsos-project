
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


document.addEventListener("DOMContentLoaded", function () {
    const toggle = document.getElementById("darkModeToggle");
    const logo   = document.getElementById("logoMR");

    // rutas del logo
    const LOGO_LIGHT = "img/MRlogo.png";        
    const LOGO_DARK  = "img/image.png";   

    // Cargar preferencia guardada
    const savedMode = localStorage.getItem("mr-dark-mode");
    if (savedMode === "enabled") {
        document.body.classList.add("dark-mode");
        toggle.checked = true;
        logo.src = LOGO_DARK;
    } else {
        logo.src = LOGO_LIGHT;
    }

    toggle.addEventListener("change", function () {
        if (this.checked) {
            document.body.classList.add("dark-mode");
            logo.src = LOGO_DARK;
            localStorage.setItem("mr-dark-mode", "enabled");
        } else {
            document.body.classList.remove("dark-mode");
            logo.src = LOGO_LIGHT;
            localStorage.setItem("mr-dark-mode", "disabled");
        }
    });
});
