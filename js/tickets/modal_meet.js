/* ============================
   MR SoS – Meet UI (clean)
   Requiere: jQuery, Bootstrap 5 bundle
   ============================ */

(function () {
  // ---- Utilidades pequeñas ----
  function ensureMeetModalsMounted() {
    if (document.getElementById("modalMeet")) return;
    const html = `
      <!-- Modal principal Meet -->
      <div class="modal fade" id="modalMeet" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <form class="modal-content" id="formMeet">
            <div class="modal-header">
              <h5 class="modal-title">Reunión</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" id="meet_ticketId" name="ticketId">
              <input type="hidden" id="meet_modo" name="modo" value="solicitar_cliente">
              <div class="mb-3">
                <label class="form-label">Plataforma</label>
                <select class="form-select" id="meet_plataforma" name="plataforma" required>
                  <option value="">— Selecciona —</option>
                  <option value="Google">Google</option>
                  <option value="Teams">Teams</option>
                  <option value="Zoom">Zoom</option>
                  <option value="Otro">Otro</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Enlace</label>
                <input type="url" class="form-control" id="meet_link" name="link" placeholder="https://...">
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label">Fecha</label>
                  <input type="date" class="form-control" id="meet_fecha" name="fecha">
                </div>
                <div class="col-6">
                  <label class="form-label">Hora</label>
                  <input type="time" class="form-control" id="meet_hora" name="hora">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button data-rol="cliente" type="submit" class="btn btn-primary">Solicitar</button>
              <button data-rol="ingeniero" type="submit" class="btn btn-success">Establecer</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal cancelar -->
      <div class="modal fade" id="modalCancelarMeet" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <form class="modal-content" id="formCancelarMeet">
            <div class="modal-header">
              <h5 class="modal-title">Cancelar reunión</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" id="cancel_ticketId" name="ticketId">
              <p class="mb-3">¿Seguro que deseas cancelar la reunión?</p>
              <div class="mb-3">
                <label class="form-label">Motivo</label>
                <textarea class="form-control" id="cancel_motivo" rows="2" required></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-danger" type="submit">Confirmar cancelación</button>
            </div>
          </form>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML("beforeend", html);
  }

  function chipMeetColor(estado) {
    switch ((estado || "").toLowerCase()) {
      case "meet ingeniero": return "bg-success";
      case "meet solicitado cliente":
      case "meet solicitado ingeniero": return "bg-warning text-dark";
      case "meet cliente": return "bg-primary";
      default: return "bg-secondary";
    }
  }

  function prefillMeetModal(meetData) {
    // Si ya traes fecha/hora, sepáralas (YYYY-MM-DD HH:MM:SS)
    const dt = meetData?.tiCitaConfirmada || meetData?.tiCitaPropuesta || "";
    if (dt && dt.includes(" ")) {
      const [f, h] = dt.split(" ");
      $("#meet_fecha").val(f);
      $("#meet_hora").val(h?.slice(0,5) || "");
    }
  }

  // ---- Render principal ----
  // Usa CLASES para evitar IDs duplicados si hay varios bloques.
  function renderMeetBlock(data = {}, opts = {}) {
    const editable = !!opts.editable;
    const estado = data.tiMeetActivo || "";
    const plat   = data.tiMeetPlataforma || "--";
    const link   = data.tiMeetLink || "";
    const when   = data.tiMeetFecha ? data.tiMeetFecha : "—";

    const chip = estado
      ? `<span class="badge ${chipMeetColor(estado)} text-capitalize">${estado}</span>`
      : `<span class="badge bg-secondary">sin meet</span>`;

    const linkHtml = link
      ? `<a href="${link}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> Abrir</a>`
      : `<span class="text-muted">Sin enlace</span>`;

    const editBtns = editable ? `
      <button type="button" class="btn btn-sm btn-primary js-solicitar-meet">
        <i class="bi bi-person-video3"></i> Solicitar
      </button>
      <button type="button" class="btn btn-sm btn-success js-establecer-meet">
        <i class="bi bi-camera-video"></i> Establecer
      </button>
      <button type="button" class="btn btn-sm btn-outline-danger js-cancelar-meet">
        <i class="bi bi-x-circle"></i> Cancelar
      </button>
    ` : "";

    return `
      <div class="border rounded p-3 mb-3 js-meet-block" data-ti-id="${data.tiId || ""}">
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
          ${editBtns}
          <button type="button" class="btn btn-sm btn-outline-secondary js-ayuda-videos">
            <i class="bi bi-question-circle"></i> Videos de ayuda
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary js-solicitar-ayuda">
            <i class="bi bi-envelope"></i> Solicitar ayuda
          </button>
        </div>
      </div>
    `;
  }

  // ---- Pintar + enlazar ----
  async function cargarBloqueMeet(tiId, opts = {}) {
    const { editable = false, targetId = "offcanvasContent" } = opts;
    ensureMeetModalsMounted();

    return $.getJSON("../php/meet_get.php", { ticketId: tiId }).then((res) => {
      if (!res?.success) return;
      const data = res.data || {};
      const target = document.getElementById(targetId) || document.getElementById("offcanvasContent");
      if (!target) return;

      target.querySelector("#meetAnchor")
        ? (target.querySelector("#meetAnchor").innerHTML = renderMeetBlock(data, { editable }))
        : (target.innerHTML = renderMeetBlock(data, { editable }));
    });
  }

  // ---- Delegación de eventos (robusta) ----
  function initMeetUI(root = document) {
    ensureMeetModalsMounted();

    // Abrir videos ayuda (siempre)
    $(root)
      .off("click.meet", ".js-ayuda-videos")
      .on("click.meet", ".js-ayuda-videos", function () {
        window.open("../ayuda/videos_logs.php", "_blank", "noopener");
      });

    // Solicitar ayuda (siempre)
    $(root)
      .off("click.meet", ".js-solicitar-ayuda")
      .on("click.meet", ".js-solicitar-ayuda", function () {
        const tiId = $(this).closest(".js-meet-block").data("ti-id");
        $.post(
          "../php/solicitar_ayuda.php",
          { ticketId: tiId },
          (r) => alert(r?.success ? "Solicitud enviada al ingeniero a cargo." : (r?.error || "No se pudo enviar la solicitud.")),
          "json"
        );
      });

    // Solicitar (cliente)
    $(root)
      .off("click.meet", ".js-solicitar-meet")
      .on("click.meet", ".js-solicitar-meet", function () {
        const $block = $(this).closest(".js-meet-block");
        const tiId = $block.data("ti-id");
        $("#meet_ticketId").val(tiId);
        $("#meet_modo").val("solicitar_cliente");
        $("#meet_plataforma").val("");
        $("#meet_link").val("");
        $("#meet_fecha").val("");
        $("#meet_hora").val("");
        const el = document.getElementById("modalMeet");
        if (el) new bootstrap.Modal(el).show();
      });

    // Establecer (ingeniero)
    $(root)
      .off("click.meet", ".js-establecer-meet")
      .on("click.meet", ".js-establecer-meet", function () {
        const $block = $(this).closest(".js-meet-block");
        const tiId = $block.data("ti-id");
        $("#meet_ticketId").val(tiId);
        $("#meet_modo").val("establecer_ingeniero");
        // Prefill si lo deseas: toma los visibles del bloque
        const plat = $block.find(".col-12.col-md-4:nth-child(1) div").text().trim();
        if (["Google","Teams","Zoom","Otro"].includes(plat)) $("#meet_plataforma").val(plat);
        const el = document.getElementById("modalMeet");
        if (el) new bootstrap.Modal(el).show();
      });

    // Cancelar
    $(root)
      .off("click.meet", ".js-cancelar-meet")
      .on("click.meet", ".js-cancelar-meet", function () {
        const $block = $(this).closest(".js-meet-block");
        const tiId = $block.data("ti-id");
        $("#cancel_ticketId").val(tiId);
        const el = document.getElementById("modalCancelarMeet");
        if (el) new bootstrap.Modal(el).show();
      });

    // Submit principal (Solicitar / Establecer)
    $(root)
      .off("submit.meet", "#formMeet")
      .on("submit.meet", "#formMeet", function (e) {
        e.preventDefault();
        const tiId  = $("#meet_ticketId").val();
        const modo  = $("#meet_modo").val() || "solicitar_cliente";
        const plat  = $("#meet_plataforma").val() || "";
        const link  = $("#meet_link").val() || "";
        const fecha = $("#meet_fecha").val();
        const hora  = $("#meet_hora").val();

        $.post(
          "../php/meet_actualizar.php",
          { ticketId: tiId, modo, plataforma: plat, link, fecha, hora },
          (r) => {
            if (r?.success) {
              const inst = bootstrap.Modal.getInstance(document.getElementById("modalMeet"));
              inst && inst.hide();
              // Re-pinta (si tienes un anchor específico, pásalo)
              cargarBloqueMeet(tiId, { editable: true, targetId: "meetAnchor" });
            } else {
              alert(r?.error || "No se pudo actualizar la reunión.");
            }
          },
          "json"
        ).fail(() => alert("Error de red al actualizar la reunión."));
      });

    // Submit cancelar
    $(root)
      .off("submit.meet", "#formCancelarMeet")
      .on("submit.meet", "#formCancelarMeet", function (e) {
        e.preventDefault();
        const tiId = $("#cancel_ticketId").val();
        const motivo = $("#cancel_motivo").val().trim();
        $.post(
          "../php/meet_actualizar.php",
          { ticketId: tiId, modo: "cancelar", motivo },
          (r) => {
            if (r?.success) {
              const inst = bootstrap.Modal.getInstance(document.getElementById("modalCancelarMeet"));
              inst && inst.hide();
              cargarBloqueMeet(tiId, { editable: true, targetId: "meetAnchor" });
            } else {
              alert(r?.error || "No se pudo cancelar la reunión.");
            }
          },
          "json"
        ).fail(() => alert("Error de red al cancelar la reunión."));
      });
  }

  // ---- Exponer API mínima ----
  window.MRSOS_MEET = {
    initMeetUI,
    cargarBloqueMeet,
    renderMeetBlock,     // por si quieres renderizar fuera
    ensureMeetModalsMounted
  };

  // Auto-init básico
  $(document).ready(() => {
    MRSOS_MEET.initMeetUI(document);
  });
})();
