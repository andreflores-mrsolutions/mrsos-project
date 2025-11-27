// ======================================================
// Helpers de imagen
// ======================================================
function imagenIngenieroPath(ing) {
  // Ruta: /img/Ingeniero/{usId}.jpg  (ajústala a tu convención real)
  // Si usas nombres, cámbialo a ing.usImagenNombre si lo tuvieras.
  return `../img/Ingeniero/${ing.usId}.svg`;
}

// ======================================================
// Render de acción por proceso (solo implementamos 'asignacion')
// ======================================================
function renderAccionesPorProceso(t) {
  const proceso = (t.tiProceso || "").toLowerCase();

  // Botón universal "Ver más"
  const btnVerMas = `
    <button class="btn btn-primary btn-sm"
      onclick="abrirDetalle(${Number(t.tiId)})"
      data-bs-toggle="offcanvas" data-bs-target="#offcanvasTicket">Ver más</button>`;

  if (proceso === "asignacion") {
    // Solo "Siguiente"
    return `
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-success btn-sm" onclick="openAsignacion(${Number(
          t.tiId
        )})">Siguiente</button>
        ${btnVerMas}
      </div>
    `;
  }

  // Otros procesos: lo dejamos para tu siguiente prompt
  return `
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-warning btn-sm" disabled>Anterior</button>
      <button class="btn btn-success btn-sm" disabled>Siguiente</button>
      ${btnVerMas}
    </div>
  `;
}

// === AJUSTA tu render de card para usar estas acciones ===
function renderTicketCard(t) {
  const persona = (t.persona || t.tiNombreContacto || "").trim();
  const modelo = joinModelo(t.eqModelo, t.eqVersion);
  const estadoBadge = badgeEstatusClase(t.tiEstatus);
  const sn = t.peSN || "—";
  const sede = t.clNombre || "—";
  const codigoTicket = `${(sede || "GEN").substring(0, 3).toLowerCase()}-${
    t.tiId
  }`;

  return `
  <div class="mrs-card">
    <div class="mrs-card-header">
      <div class="d-flex align-items-start justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-person text-muted"></i>
          <h6 class="mb-0">${persona || "—"}</h6>
        </div>
        <span class="badge ${estadoBadge}">${t.tiEstatus || "—"}</span>
        
      </div>
      <span class="badge bg-dark mt-1   ">${t.tiProceso || "—"}</span>
    </div>
    <div class="mrs-card-content">
      <div class="d-flex align-items-center gap-2 text-muted mb-1">
        <i class="bi bi-ticket"></i><span class="small">${
          codigoTicket || "—"
        }</span>
      </div>
      <div class="d-flex align-items-center gap-2 text-muted mb-1">
        <i class="bi bi-wrench"></i><span class="small">${modelo || "—"}</span>
      </div>
      <div class="d-flex align-items-center gap-2 text-muted mb-1">
        <i class="bi bi-calendar"></i><span class="small">${t.tiVisita || "No asignada"}</span>
      </div>
      <div class="d-flex align-items-center gap-2 text-muted mb-3">
        <span class="small">123</span><span class="small">${sn}</span>
      </div>

      ${renderAccionesPorProceso(t)}
    </div>
  </div>`;
}

// ======================================================
// Modal Asignación
// ======================================================
let _tiIdAsignacion = null;

function openAsignacion(tiId) {
  _tiIdAsignacion = Number(tiId);
  // Limpia contenedor
  const wrap = document.getElementById("wrapIngenieros");
  if (wrap)
    wrap.innerHTML = `<div class="text-muted">Cargando ingenieros...</div>`;
  document.getElementById("btnContinuarAsignacion")?.classList.add("d-none");

  // Abre modal
  const modal = new bootstrap.Modal(document.getElementById("modalAsignacion"));
  modal.show();

  // Trae ingenieros
  fetch("php/obtener_ingenieros.php", { cache: "no-store" })
    .then((r) => r.json())
    .then((json) => {
      if (!json?.success) throw new Error(json?.error || "Error");
      pintarIngenierosPorTier(json.ingenieros || []);
    })
    .catch(() => {
      if (wrap)
        wrap.innerHTML = `<div class="text-danger">No se pudieron cargar los ingenieros.</div>`;
    });
}

function pintarIngenerosTier(tier, lista) {
  const title = tier; // 'Tier 1' | 'Tier 2' | 'Tier 3'
  const cards = lista.map((ing) => cardIngeniero(ing)).join("");
  return `
    <div class="mb-4">
      <h6 class="mb-2">${title}</h6>
      <div class="row g-3">${
        cards ||
        `<div class="col-12"><div class="text-muted">Sin ingenieros en ${title}.</div></div>`
      }</div>
    </div>
  `;
}
function badgeExpert(estatus) {
  const e = estatus || "";
  if (e === "Cloud") return "bg-success text-light";
  if (e === "OS") return "bg-warning text-dark";
  if (e === "Storage" || e === "cerrado") return "bg-secondary";
  if (e === "Datacom") return "bg-danger";
  if (e === "Virtualización") return "bg-danger";
  if (e === "Backup") return "bg-danger";
  return "bg-light text-dark " + e;
}

function cardIngeniero(ing) {
  const nombre = `${ing.usNombre} ${ing.usAPaterno}`.trim();
  const correo = ing.usCorreo || "";
  const username = ing.usUsername || "";
  const tel = ing.usTelefono || "";
  const experto = ing.ingExperto || "Otro";
  const estadoBadge = badgeExpert(ing.ingExperto);
  const img = imagenIngenieroPath(ing);
  const desc = ing.ingDescripcion || "";
  const tier = ing.ingTier || "Tier 1";

  return `
  <div class="col-12 col-md-6">
    <div class="mrs-ing-card d-flex gap-3 align-items-center">
      <img class="img" src="${img}" alt="${nombre}" onerror="this.src='../img/Ingeniero/placeholder.webp'">
      <div class="flex-fill">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <strong>${nombre}</strong>
          <span class="mrs-ing-badge ${estadoBadge}">${experto}</span>
        </div>
        <div class="small text-muted mb-2"><i class="bi bi-person me-1"></i>${username}</div>
        <div class="small text-muted mb-2"><i class="bi bi-envelope me-1"></i>${correo}</div>
        <div class="small text-muted mb-2"><i class="bi bi-telephone me-1"></i>${tel}</div>
        <div class="small text-muted mb-3"><i class="bi bi-shield me-1"></i>${desc}</div>
        
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-success btn-sm" onclick="asignarIngeniero(${
            ing.usId
          }, '${tier.replace(/'/g, "\\'")}')">Asignar</button>
          <button class="btn btn-primary btn-sm" onclick="verMasIngeniero(${
            ing.usId
          })">Ver más</button>
        </div>
      </div>
    </div>
  </div>`;
}

function pintarIngenierosPorTier(ingenieros) {
  // Agrupa por Tier
  const byTier = { "Tier 1": [], "Tier 2": [], "Tier 3": [] };
  ingenieros.forEach((ing) => {
    const tier = byTier[ing.ingTier] ? ing.ingTier : "Tier 1";
    byTier[tier].push(ing);
  });

  const wrap = document.getElementById("wrapIngenieros");
  if (!wrap) return;

  wrap.innerHTML = `
    ${pintarIngenerosTier("Tier 1", byTier["Tier 1"])}
    ${pintarIngenerosTier("Tier 2", byTier["Tier 2"])}
    ${pintarIngenerosTier("Tier 3", byTier["Tier 3"])}
  `;
}

// Placeholder “Ver más”
function verMasIngeniero(usId) {
  // Aquí puedes abrir un offcanvas/Modal con más detalles del ingeniero si gustas
  console.log("Ver más ingeniero", usId);
}

// ======================================================
// POST: asignar ingeniero y avanzar proceso
// ======================================================
function asignarIngeniero(usIdIng, tier) {
  if (!_tiIdAsignacion) return;

  const payload = new URLSearchParams();
  payload.set("tiId", _tiIdAsignacion);
  payload.set("usIdIng", usIdIng);
  payload.set("nextProceso", "revision inicial"); // acordado por ahora

  fetch("php/asignar_ingeniero.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: payload.toString(),
  })
    .then((r) => r.json())
    .then((json) => {
      if (!json?.success) throw new Error(json?.error || "Error al asignar");
      // Cierra modal
      const modalEl = document.getElementById("modalAsignacion");
      const modal = bootstrap.Modal.getInstance(modalEl);
      modal?.hide();

      // Refresca la vista
      cargarTodosTickets();
    })
    .catch((err) => {
      alert("No se pudo asignar el ingeniero: " + err.message);
    });
}
