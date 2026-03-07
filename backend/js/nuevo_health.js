// admin/js/nuevo_health.js
(function () {
  const ctx = window.MRS_CTX || { clId: 0, baseApi: "api" };
  const csrf =
    window.MRS_CSRF && window.MRS_CSRF.csrf ? window.MRS_CSRF.csrf : "";

  const $csId = $("#csId");
  const $equiposGrid = $("#equiposGrid");
  const $equiposSkeleton = $("#equiposSkeleton");
  const $txtBuscar = $("#txtBuscarEquipo");
  const $alert = $("#alertBox");

  const $usIdCliente = $("#usIdCliente");

  let equiposCache = [];
  let visibleList = []; // resultado tras filtro
  const selected = new Map(); // key peId -> {peId, eqId, modelo, sn}
  const NOTIFY_URL =
    ctx && ctx.NOTIFY_URL ? ctx.NOTIFY_URL : "../php/notify.php";

  function mostrarToast(tipo, mensaje) {
    const toastId = tipo === "success" ? "#toastSuccess" : "#toastError";
    const $toastElem = $(toastId);

    if ($toastElem.length === 0) {
      // fallback
      showAlert(tipo === "success" ? "success" : "danger", mensaje);
      return;
    }

    $(`${toastId} .toast-body`).text(mensaje);
    const toast = new bootstrap.Toast($toastElem[0]);
    toast.show();
  }
  function healthFolio(hcId) {
    return `HC-${Number(hcId)}`;
  }

  async function sendHealthNotification(action, health, extra = {}) {
    console.log("sendHealthNotification", { action, health, extra });
    if (!action) throw new Error("action requerido");
    if (!health || !health.hcId) throw new Error("health inválido");

    const fd = new FormData();
    fd.append("action", action);
    fd.append("folio", String(extra.folio ?? healthFolio(health.hcId)));

    // contexto base (notify.php / NotificationService puede usar esto)
    fd.append("hcId", String(health.hcId));
    fd.append("clId", String(extra.clId ?? ctx.clId ?? ""));
    fd.append("csId", String(extra.csId ?? health.csId ?? ""));
    fd.append(
      "estado",
      String(extra.estado ?? health.hcEstatus ?? "Programado"),
    );
    fd.append("proceso", String(extra.proceso ?? "health_check"));

    // opcionales para title/body
    fd.append("titulo", String(extra.titulo ?? ""));
    fd.append("texto", String(extra.texto ?? ""));

    // extras arbitrarios
    Object.entries(extra).forEach(([k, v]) => {
      if (v === undefined || v === null) return;
      if (
        [
          "proceso",
          "estado",
          "texto",
          "titulo",
          "folio",
          "clId",
          "csId",
        ].includes(k)
      )
        return;
      fd.append(k, String(v));
    });

    const res = await fetch(NOTIFY_URL, {
      method: "POST",
      credentials: "include",
      body: fd,
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) {
      throw new Error(json.message || json.error || "Error notify health");
    }
    return json;
  }

  async function sendHealthNotificationOnCreate(health) {
    const extra = {
      proceso: "health_programado",
      estado: "Programado",
      titulo: "Health Check programado",
      texto: `Se programó un Health Check (${healthFolio(health.hcId)}). Revisa el detalle y confirma la disponibilidad para la visita.`,
      csId: health.csId ?? "",
      folio: healthFolio(health.hcId),
    };
    return sendHealthNotification("health_programado", health, extra);
  }
  function apiUrl(path) {
    return `${ctx.baseApi}/${path}`;
  }

  function showAlert(type, msg) {
    $alert
      .removeClass("d-none alert-success alert-danger alert-warning alert-info")
      .addClass("alert-" + type)
      .text(msg);
  }
  function hideAlert() {
    $alert.addClass("d-none").text("");
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

  function syncSelectedUI() {
    const arr = Array.from(selected.values()).map((x) => ({
      peId: x.peId,
      eqId: x.eqId,
    }));
    $("#items_json").val(JSON.stringify(arr));

    $("#selCountText").text(`${arr.length} equipos`);

    const $list = $("#selList");
    $list.empty();

    if (!arr.length) {
      $list.html(
        `<div class="small text-muted">Aún no seleccionas equipos.</div>`,
      );
      return;
    }

    for (const it of selected.values()) {
      $list.append(`
        <span class="sel-pill">
          <i class="bi bi-cpu"></i>
          <span><b>${escapeHtml(it.modelo)}</b> <span class="text-muted">(${escapeHtml(it.sn || "--")})</span></span>
          <button type="button" class="text-danger ms-1 btnRemoveSel" data-peid="${Number(it.peId)}" title="Quitar">
            <i class="bi bi-x-circle"></i>
          </button>
        </span>
      `);
    }
  }

  function renderEquipos(list) {
    $equiposGrid.empty();

    if (!list.length) {
      $equiposGrid.append(`
        <div class="col-12">
          <div class="alert alert-warning mb-0">
            No hay equipos para la sede seleccionada (o no hay coincidencias con tu búsqueda).
          </div>
        </div>
      `);
      return;
    }

    for (const e of list) {
      const isSel = selected.has(Number(e.peId));
      const polizaBadge = e.polizaTipo
        ? `
        <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">
          ${escapeHtml(e.polizaTipo)}
        </span>`
        : "";

      $equiposGrid.append(`
        <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6 mb-3">
          <div class="card equipo-card h-100 position-relative ${isSel ? "is-selected" : ""}"
               data-peid="${Number(e.peId)}"
               data-eqid="${Number(e.eqId)}"
               data-modelo="${escapeHtml(e.modelo)}"
               data-sn="${escapeHtml(e.sn || "")}">
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
              <span class="badge ${isSel ? "bg-success" : "bg-secondary"} mt-2">
                ${isSel ? "Seleccionado" : "Agregar"}
              </span>
            </div>
          </div>
        </div>
      `);
    }
  }

  function applyFilter() {
    const q = ($txtBuscar.val() || "").toString().trim().toLowerCase();
    visibleList = equiposCache;
    if (q) visibleList = visibleList.filter((e) => e._ft.includes(q));
    renderEquipos(visibleList);
  }

  async function loadSedes() {
    $csId.html(`<option value="">Cargando...</option>`);
    const r = await fetch(
      apiUrl(`health_catalog_sedes.php?clId=${encodeURIComponent(ctx.clId)}`),
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

  async function loadEquipos(csId) {
    $equiposGrid.hide();
    $equiposSkeleton.show();
    $equiposGrid.empty();
    equiposCache = [];
    visibleList = [];
    selected.clear();
    syncSelectedUI();

    const r = await fetch(
      apiUrl(
        `health_catalog_equipos.php?clId=${encodeURIComponent(ctx.clId)}&csId=${encodeURIComponent(csId)}`,
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
    applyFilter();
  }

  async function loadClientesResponsables(csId) {
    $usIdCliente.html(`<option value="">Cargando clientes...</option>`);

    const r = await fetch(
      apiUrl(
        `health_catalog_clientes.php?clId=${encodeURIComponent(ctx.clId)}&csId=${encodeURIComponent(csId)}`,
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
        <option value="${Number(u.usId)}"
          data-nombre="${escapeHtml(u.nombre)}"
          data-correo="${escapeHtml(u.correo || "")}"
          data-telefono="${escapeHtml(u.telefono || "")}">
          ${escapeHtml(u.nombre)}${extra}
        </option>`;
      }),
    );

    $usIdCliente.html(opts.join(""));

    if (j.clientes.length === 1) {
      $usIdCliente.val(String(j.clientes[0].usId)).trigger("change");
    }
  }

  function bindEvents() {
    $(document).on("click", ".equipo-card", function () {
      const peId = Number($(this).data("peid"));
      const eqId = Number($(this).data("eqid"));
      const modelo = $(this).data("modelo");
      const sn = $(this).data("sn");

      if (selected.has(peId)) {
        selected.delete(peId);
      } else {
        selected.set(peId, {
          peId,
          eqId,
          modelo: String(modelo || ""),
          sn: String(sn || ""),
        });
      }
      syncSelectedUI();
      applyFilter(); // para refrescar badges/estilo
      hideAlert();
    });

    $(document).on("click", ".btnRemoveSel", function () {
      const peId = Number($(this).data("peid"));
      selected.delete(peId);
      syncSelectedUI();
      applyFilter();
    });

    $("#btnClearSel").on("click", function () {
      selected.clear();
      syncSelectedUI();
      applyFilter();
    });

    $("#btnSelectAll").on("click", function () {
      for (const e of visibleList) {
        const peId = Number(e.peId);
        if (!selected.has(peId)) {
          selected.set(peId, {
            peId,
            eqId: Number(e.eqId),
            modelo: e.modelo,
            sn: e.sn || "",
          });
        }
      }
      syncSelectedUI();
      applyFilter();
    });

    $("#btnTheme").on("click", function () {
      document.body.classList.toggle("dark-mode");
      document.cookie = `mrs_theme=${document.body.classList.contains("dark-mode") ? "dark" : "light"}; path=/; max-age=31536000`;
    });

    $("#btnReload").on("click", async () => {
      selected.clear();
      syncSelectedUI();
      await loadSedes();
      $equiposGrid.hide();
      $equiposSkeleton.hide();
    });

    $txtBuscar.on("input", applyFilter);

    $("#frmHealth").on("submit", submitHealth);
  }

  $usIdCliente.on("change", function () {
    const $opt = $(this).find("option:selected");
    const nombre = ($opt.data("nombre") || "").toString();
    const correo = ($opt.data("correo") || "").toString();
    const tel = ($opt.data("telefono") || "").toString();

    if (!nombre) return;

    $("#hcNombreContacto").val(nombre);
    if (tel) $("#hcNumeroContacto").val(tel);
    if (correo) $("#hcCorreoContacto").val(correo);

    if (!tel || !correo) {
      showAlert(
        "warning",
        "El responsable no tiene teléfono/correo completo. Verifica y captura manual si aplica.",
      );
    }
  });

  async function submitHealth(e) {
    e.preventDefault();
    hideAlert();

    const csId = Number($("#csId").val() || 0);
    if (!csId) return showAlert("warning", "Selecciona una sede.");

    const items = JSON.parse($("#items_json").val() || "[]");
    if (!items.length)
      return showAlert("warning", "Selecciona al menos 1 equipo.");

    const dt = ($("#hcFechaHora").val() || "").toString().trim();
    if (!dt) return showAlert("warning", "Selecciona fecha y hora.");

    const usIdCliente = Number($("#usIdCliente").val() || 0);
    if (!usIdCliente)
      return showAlert("warning", "Selecciona el cliente responsable.");

    const payload = {
      csrf_token: csrf,
      clId: Number(ctx.clId),
      csId,
      hcFechaHora: dt, // datetime-local
      hcDuracionMins: Number($("#hcDuracionMins").val() || 240),
      hcNombreContacto: $("#hcNombreContacto").val(),
      hcNumeroContacto: $("#hcNumeroContacto").val(),
      hcCorreoContacto: $("#hcCorreoContacto").val(),
      usIdCliente: Number($("#usIdCliente").val() || 0),
      items,
    };

    $("#btnCrear")
      .prop("disabled", true)
      .html(
        `<span class="spinner-border spinner-border-sm"></span> Programando...`,
      );

    try {
      const r = await fetch(apiUrl("health_create.php"), {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
        body: JSON.stringify(payload),
      });
      const j = await r.json();
      if (!j.success)
        return showAlert(
          "danger",
          j.error || "No se pudo crear el Health Check.",
        );

      const health = {
        hcId: j.hcId,
        csId: Number($("#csId").val() || 0),
        hcEstatus: "Programado",
      };

      mostrarToast(
        "success",
        `Health Check creado: ${healthFolio(health.hcId)}`,
      );

      try {
        await sendHealthNotificationOnCreate(health);
        mostrarToast(
          "success",
          `Notificación enviada: ${healthFolio(health.hcId)}`,
        );
      } catch (err) {
        console.warn("notify health fail", err);
        mostrarToast(
          "error",
          `Health creado, pero falló la notificación: ${err.message || err}`,
        );
      }

      // redirige
      setTimeout(() => (window.location.href = `index.php`), 1500);
    } catch (err) {
      showAlert("danger", err.message || "Error inesperado.");
    } finally {
      $("#btnCrear")
        .prop("disabled", false)
        .html(`<i class="bi bi-check2-circle"></i> Programar Health Check`);
    }
  }

  async function init() {
    bindEvents();

    // default datetime-local: hoy + 1 hora
    const now = new Date();
    now.setHours(now.getHours() + 1);
    const pad = (n) => String(n).padStart(2, "0");
    const d = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
    $("#hcFechaHora").val(d);

    try {
      await loadSedes();
      $csId.on("change", async function () {
        const csId = Number($(this).val() || 0);
        if (!csId) {
          $equiposGrid.hide();
          $equiposSkeleton.hide();
          return;
        }
        await Promise.all([loadClientesResponsables(csId), loadEquipos(csId)]);
      });

      // auto si solo hay 1 sede
      setTimeout(() => {
        const opts = $csId.find("option");
        if (opts.length === 2)
          $csId.val($(opts[1]).attr("value")).trigger("change");
      }, 50);
    } catch (err) {
      showAlert("danger", err.message || "No se pudieron cargar catálogos.");
      $csId.html(`<option value="">Error</option>`);
      $equiposSkeleton.hide();
    }
  }

  $(init);
})();
