// admin/js/nuevo_ticket.js
(function () {
  const ctx = window.MRS_CTX || {
    clId: 0,
    baseApi: "api",
    NOTIFY_URL: "../php/notify.php",
  };
  const csrf =
    window.MRS_CSRF && window.MRS_CSRF.csrf ? window.MRS_CSRF.csrf : "";

  const NOTIFY_URL = ctx.NOTIFY_URL || "../php/notify.php";

  const $csId = $("#csId");
  const $usIdCliente = $("#usIdCliente");

  const $equiposGrid = $("#equiposGrid");
  const $equiposSkeleton = $("#equiposSkeleton");
  const $txtBuscar = $("#txtBuscarEquipo");
  const $fltTicket = $("#fltTicketActivo");

  let equiposCache = [];

  function apiUrl(path) {
    return `${ctx.baseApi}/${path}`;
  }

  function mostrarToast(tipo, mensaje) {
    const toastId = tipo === "success" ? "#toastSuccess" : "#toastError";
    const $toastElem = $(toastId);

    if ($toastElem.length === 0) {
      alert(mensaje);
      return;
    }
    $(`${toastId} .toast-body`).text(mensaje);
    const toast = new bootstrap.Toast($toastElem[0]);
    toast.show();
  }

  function escapeHtml(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function normalizeFilterText(e) {
    return `${e.modelo} ${e.tipoEquipo} ${e.sn} ${e.polizaTipo} ${e.marca}`.toLowerCase();
  }

  function renderEquipos(list) {
    $equiposGrid.empty();

    if (!list.length) {
      $equiposGrid.append(`
        <div class="col-12">
          <div class="alert alert-warning mb-0">No hay equipos para la sede o tu filtro.</div>
        </div>
      `);
      return;
    }

    for (const e of list) {
      const borderClass =
        e.ticketsActivos > 0 ? "border border-danger" : "border border-warning";
      const badgeTicket =
        e.ticketsActivos > 0
          ? `<span class="badge bg-danger text-white mt-2">Ticket activo</span>`
          : `<span class="badge bg-success text-white mt-2">Sin tickets</span>`;

      const ticketsTxt =
        e.ticketsActivos > 0 && e.ticketsList?.length
          ? `<small class="text-muted d-block mt-1"><strong>Tickets:</strong> ${escapeHtml(e.ticketsList.join(", "))}</small>`
          : `<small class="text-muted d-block mt-1"><strong>Tickets:</strong> --</small>`;

      const polizaBadge = e.polizaTipo
        ? `
        <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">
          ${escapeHtml(e.polizaTipo)}
        </span>`
        : "";

      $equiposGrid.append(`
        <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6 mb-3">
          <div class="card equipo-card ${borderClass} h-100 position-relative"
               data-peid="${Number(e.peId)}"
               data-eqid="${Number(e.eqId)}"
               data-sn="${escapeHtml(e.sn)}"
               data-modelo="${escapeHtml(e.modelo)}"
               data-poliza="${escapeHtml(e.polizaTipo || "")}">
            ${polizaBadge}
            <div class="equipo-img-wrap">
              <img src="${escapeHtml(e.img)}" class="card-img-top equipo-img" alt="${escapeHtml(e.modelo)}"
                   onerror="this.src='../img/Equipos/default.png';">
            </div>
            <div class="card-body">
              <h6 class="card-title mb-1">${escapeHtml(e.modelo)}</h6>
              <small class="text-muted d-block">${escapeHtml(e.tipoEquipo || "Equipo")}</small>
              <small class="text-muted d-block">SN: ${escapeHtml(e.sn || "--")}</small>
              <small class="text-muted d-block">Marca: ${escapeHtml(e.marca || "--")}</small>
              ${badgeTicket}
              ${ticketsTxt}
            </div>
          </div>
        </div>
      `);
    }
  }

  function applyFilters() {
    const q = ($txtBuscar.val() || "").toString().trim().toLowerCase();
    const t = $fltTicket.val();

    let list = equiposCache;

    if (q) list = list.filter((e) => e._ft.includes(q));
    if (t === "with") list = list.filter((e) => Number(e.ticketsActivos) > 0);
    if (t === "without")
      list = list.filter((e) => Number(e.ticketsActivos) === 0);

    renderEquipos(list);
  }

  async function loadSedes() {
    $csId.html(`<option value="">Cargando...</option>`);
    const r = await fetch(
      apiUrl(`ticket_catalog_sedes.php?clId=${encodeURIComponent(ctx.clId)}`),
      {
        headers: { "X-CSRF-Token": csrf },
      },
    );
    const j = await r.json();
    if (!j.success) throw new Error(j.error || "Error sedes");

    const opts = [`<option value="">Selecciona sede...</option>`].concat(
      j.sedes.map(
        (s) =>
          `<option value="${Number(s.csId)}">${escapeHtml(s.csNombre)}</option>`,
      ),
    );
    $csId.html(opts.join(""));
  }

  async function loadClientesResponsables(csId) {
    $usIdCliente.html(`<option value="">Cargando clientes...</option>`);

    const r = await fetch(
      apiUrl(
        `ticket_catalog_clientes.php?clId=${encodeURIComponent(ctx.clId)}&csId=${encodeURIComponent(csId)}`,
      ),
      {
        headers: { "X-CSRF-Token": csrf },
      },
    );
    const j = await r.json();
    if (!j.success) throw new Error(j.error || "Error clientes");

    if (!j.clientes || j.clientes.length === 0) {
      $usIdCliente.html(
        `<option value="">Sin usuarios disponibles para esta sede</option>`,
      );
      return;
    }

    const opts = [
      `<option value="">Selecciona cliente responsable...</option>`,
    ].concat(
      j.clientes.map((u) => {
        const extra = u.ucrRol ? ` · ${escapeHtml(u.ucrRol)}` : "";
        return `
      <option
        value="${Number(u.usId)}"
        data-nombre="${escapeHtml(u.nombre)}"
        data-correo="${escapeHtml(u.correo || "")}"
        data-telefono="${escapeHtml(u.telefono || "")}"
      >
        ${escapeHtml(u.nombre)}${extra}
      </option>`;
      }),
    );

    $usIdCliente.html(opts.join(""));

    // auto select si solo hay 1
    if (j.clientes.length === 1) {
      $usIdCliente.val(String(j.clientes[0].usId));
    }
  }

  async function loadEquipos(csId) {
    $equiposGrid.hide();
    $equiposSkeleton.show();
    $equiposGrid.empty();
    equiposCache = [];

    const r = await fetch(
      apiUrl(
        `ticket_catalog_equipos.php?clId=${encodeURIComponent(ctx.clId)}&csId=${encodeURIComponent(csId)}`,
      ),
      {
        headers: { "X-CSRF-Token": csrf },
      },
    );
    const j = await r.json();
    if (!j.success) throw new Error(j.error || "Error equipos");

    equiposCache = (j.equipos || []).map((e) => {
      e._ft = normalizeFilterText(e);
      return e;
    });

    $equiposSkeleton.hide();
    $equiposGrid.show();
    applyFilters();
  }

  function clearEquipo() {
    $("#peId").val("");
    $("#eqId").val("");
    $(".equipo-card").removeClass("is-selected");
    $("#selEquipoText").text("Aún no seleccionas un equipo.");
  }

  // NOTIFY (simplificado para creación)
  async function sendTicketNotification(action, ticket, extra = {}) {
    const fd = new FormData();
    fd.append("action", action);
    fd.append("folio", String(ticket.folio || `MRS-${ticket.tiId}`));

    fd.append("tiId", String(ticket.tiId));
    fd.append("proceso", String(extra.proceso ?? ticket.tiProceso ?? ""));
    fd.append("estado", String(extra.estado ?? ticket.tiEstatus ?? ""));
    fd.append("texto", String(extra.texto ?? ""));
    fd.append("titulo", String(extra.titulo ?? ""));

    Object.entries(extra).forEach(([k, v]) => {
      if (v === undefined || v === null) return;
      if (["proceso", "estado", "texto", "titulo"].includes(k)) return;
      fd.append(k, String(v));
    });

    const res = await fetch(NOTIFY_URL, {
      method: "POST",
      credentials: "include",
      body: fd,
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) {
      throw new Error(json.message || json.error || "Error notify");
    }
    return json;
  }

  async function sendTicketNotificationOnCreate(ticket) {
    const extra = {
      proceso: "asignacion",
      titulo: "Ticket creado",
      texto: `Se creó tu ticket ${ticket.folio}. En breve se asignará un ingeniero y recibirás los siguientes pasos.`,
      estado: ticket.tiEstatus || "Abierto",
    };
    return sendTicketNotification("asignacion", ticket, extra);
  }

  async function submitTicket(e) {
    e.preventDefault();

    const csId = Number($("#csId").val() || 0);
    const peId = Number($("#peId").val() || 0);
    const eqId = Number($("#eqId").val() || 0);
    const usIdCliente = Number($("#usIdCliente").val() || 0);

    if (!csId) return mostrarToast("error", "Selecciona una sede.");
    if (!usIdCliente)
      return mostrarToast("error", "Selecciona el cliente responsable.");
    if (!peId || !eqId)
      return mostrarToast("error", "Selecciona un equipo (card).");

    const payload = {
      csrf_token: csrf,
      clId: Number(ctx.clId),
      csId,
      peId,
      eqId,
      usIdCliente, // 👈 NUEVO
      tiTipoTicket: $("#tiTipoTicket").val(),
      tiNivelCriticidad: $('input[name="tiNivelCriticidad"]:checked').val(),
      tiNombreContacto: $("#tiNombreContacto").val(),
      tiNumeroContacto: $("#tiNumeroContacto").val(),
      tiCorreoContacto: $("#tiCorreoContacto").val(),
      tiDescripcion: $("#tiDescripcion").val(),
    };

    $("#btnCrear")
      .prop("disabled", true)
      .html(
        `<span class="spinner-border spinner-border-sm"></span> Creando...`,
      );

    try {
      const r = await fetch(apiUrl("ticket_create.php"), {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
        body: JSON.stringify(payload),
      });
      const j = await r.json();

      if (!j.success) {
        mostrarToast("error", j.error || "No se pudo crear el ticket.");
        return;
      }

      const ticket = j.ticket || {
        tiId: j.tiId,
        folio: j.folio,
        tiProceso: "asignacion",
        tiEstatus: "Abierto",
      };

      mostrarToast("success", `Ticket creado: ${ticket.folio}`);

      // Notificación al cliente responsable
      try {
        await sendTicketNotificationOnCreate(ticket);
        mostrarToast(
          "success",
          `Notificación enviada a cliente (${ticket.folio}).`,
        );
      } catch (err) {
        console.warn("notify fail", err);
        mostrarToast(
          "error",
          `Ticket creado, pero falló la notificación: ${err.message || err}`,
        );
      }

      setTimeout(() => {
        window.location.href = `tickets.php?clId=${ctx.clId}`;
      }, 800);
    } catch (err) {
      mostrarToast("error", err.message || "Error inesperado.");
    } finally {
      $("#btnCrear")
        .prop("disabled", false)
        .html(`<i class="bi bi-check2-circle"></i> Crear ticket`);
    }
  }

  function bindThemeButton() {
    $("#btnTheme").on("click", function () {
      document.body.classList.toggle("dark-mode");
      document.cookie = `mrs_theme=${document.body.classList.contains("dark-mode") ? "dark" : "light"}; path=/; max-age=31536000`;
    });
  }

  function bindSelectCard() {
    $(document).on("click", ".equipo-card", function () {
      $(".equipo-card").removeClass("is-selected");
      $(this).addClass("is-selected");

      const peId = $(this).data("peid");
      const eqId = $(this).data("eqid");
      const modelo = $(this).data("modelo");
      const sn = $(this).data("sn");
      const pol = $(this).data("poliza");

      $("#peId").val(peId);
      $("#eqId").val(eqId);

      $("#selEquipoText").html(`
        <span class="badge text-bg-light border">eqId: ${Number(eqId)}</span>
        <span class="badge text-bg-light border">peId: ${Number(peId)}</span>
        <div class="mt-2"><b>${escapeHtml(modelo)}</b></div>
        <div class="text-muted small">SN: ${escapeHtml(sn)} · Póliza: ${escapeHtml(pol || "--")}</div>
      `);
    });
  }

  async function init() {
    bindSelectCard();
    bindThemeButton();

    $("#btnClearEquipo").on("click", clearEquipo);
    $("#btnReload").on("click", async () => {
      clearEquipo();
      $usIdCliente.html(
        `<option value="">Selecciona una sede primero...</option>`,
      );
      await loadSedes();
      $equiposGrid.hide();
      $equiposSkeleton.hide();
    });

    $txtBuscar.on("input", applyFilters);
    $fltTicket.on("change", applyFilters);
    $usIdCliente.on("change", function () {
      const $opt = $(this).find("option:selected");
      const nombre = ($opt.data("nombre") || "").toString();
      const correo = ($opt.data("correo") || "").toString();
      const tel = ($opt.data("telefono") || "").toString();

      if (!nombre) return;

      // Autollenado
      $("#tiNombreContacto").val(nombre);
      if (tel) $("#tiNumeroContacto").val(tel);
      if (correo) $("#tiCorreoContacto").val(correo);

      if (!tel || !correo) {
        mostrarToast(
          "error",
          "El responsable no tiene teléfono/correo completo, valida y captura manual si aplica.",
        );
      } else {
        mostrarToast(
          "success",
          "Contacto autollenado desde cliente responsable.",
        );
      }
    });

    $("#frmTicket").on("submit", submitTicket);

    await loadSedes();

    $csId.on("change", async function () {
      clearEquipo();

      const csId = Number($(this).val() || 0);
      if (!csId) {
        $usIdCliente.html(
          `<option value="">Selecciona una sede primero...</option>`,
        );
        $equiposGrid.hide();
        $equiposSkeleton.hide();
        return;
      }

      try {
        await Promise.all([loadClientesResponsables(csId), loadEquipos(csId)]);
      } catch (err) {
        mostrarToast("error", err.message || "Error cargando catálogos.");
      }
    });

    // Auto-select si solo hay 1 sede
    setTimeout(() => {
      const opts = $csId.find("option");
      if (opts.length === 2) {
        $csId.val($(opts[1]).attr("value")).trigger("change");
      }
    }, 60);
  }

  $(init);
})();
