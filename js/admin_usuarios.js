const PER_PAGE = 30;

const state = {
  page: 1,
  q: '',
  csId: 0,   // sede
  acId: 0    // AC (usuario administrador de cliente)
};

// Helpers
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

function resolveUserImgPath(username) {
  // el backend ya intenta resolver; esto es fallback
  return `../img/Usuario/${username}.jpg`;
}

function cardUsuario(u) {
  const nombre = [u.usNombre, u.usAPaterno, u.usAMaterno].filter(Boolean).join(' ');
  const sede = u.csNombre || '—';
  const img = u.imgUrl || resolveUserImgPath(u.usUsername);

  return `
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <img src="${img}" onerror="this.src='../img/Usuario/default.webp'"
               class="rounded-circle flex-shrink-0"
               style="width:64px;height:64px;object-fit:cover;">
          <div class="flex-grow-1">
            <div class="fw-semibold">${nombre}</div>
            <div class="text-muted small">${sede}</div>
          </div>
        </div>
        <div class="card-footer bg-white d-flex gap-2">
          <button class="btn btn-outline-primary w-50"
                  data-id="${u.usId}" data-action="ver">Ver más</button>

          <div class="dropup w-50">
            <button class="btn btn-primary dropdown-toggle w-100" data-bs-toggle="dropdown">Acciones</button>
            <ul class="dropdown-menu dropdown-menu-end w-100">
              <li><a class="dropdown-item" href="#" data-id="${u.usId}" data-action="pw">Cambiar contraseña</a></li>
              <li><a class="dropdown-item" href="#" data-id="${u.usId}" data-action="rol">Cambiar rol</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="#" data-id="${u.usId}" data-action="del">Eliminar usuario</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>`;
}

function renderUsers(users, total, page) {
  const grid = $('#gridUsuarios');
  grid.innerHTML = users.map(cardUsuario).join('') ||
    `<div class="col-12"><div class="text-center text-muted py-5">Sin usuarios.</div></div>`;

  // acciones de las cards
  $$('[data-action]', grid).forEach(el => {
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      const id = el.getAttribute('data-id');
      const action = el.getAttribute('data-action');
      if (action === 'ver') verUsuario(id);
      if (action === 'pw') abrirCambiarPassword(id);
      if (action === 'rol') abrirCambiarRol(id);
      if (action === 'del') eliminarUsuario(id);
    });
  });

  renderPagination(total, page);
}

function renderPagination(total, page) {
  const ul = $('#paginacionUsuarios');
  const pages = Math.max(1, Math.ceil(total / PER_PAGE));
  const cur = Math.min(page, pages);

  const li = [];
  li.push(`<li class="page-item ${cur === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-p="${cur - 1}">&laquo;</a></li>`);
  for (let p = 1; p <= pages; p++) {
    li.push(`<li class="page-item ${p === cur ? 'active' : ''}">
              <a class="page-link" href="#" data-p="${p}">${p}</a></li>`);
  }
  li.push(`<li class="page-item ${cur === pages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-p="${cur + 1}">&raquo;</a></li>`);

  ul.innerHTML = li.join('');
  $$('a[data-p]', ul).forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      const p = parseInt(a.getAttribute('data-p'), 10);
      if (!isNaN(p) && p >= 1 && p <= pages) {
        state.page = p;
        cargarUsuarios();
      }
    });
  });
}

async function cargarUsuarios() {
  const body = {
    page: state.page,
    perPage: PER_PAGE,
    q: state.q || '',
    csId: Number(state.csId || 0),
    acId: Number(state.acId || 0)
  };

  const grid = $('#gridUsuarios');
  grid.innerHTML = `<div class="col-12 text-center py-4">
    <div class="spinner-border text-primary"></div></div>`;

  try {
    const r = await fetch('../php/adm_usuarios_list.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    const j = await r.json();
    if (!j?.success) {
      grid.innerHTML = `<div class="col-12 text-danger">${j?.error || 'Error al cargar usuarios'}</div>`;
      return;
    }
    renderUsers(j.users || [], j.total || 0, j.page || 1);
  } catch {
    grid.innerHTML = `<div class="col-12 text-danger">Error de red</div>`;
  }
}

async function verUsuario(usId) {
  const bodyEl = $('#offcanvasUsuarioBody');
  bodyEl.innerHTML = `<div class="text-center py-4">
    <div class="spinner-border text-primary"></div><p class="mt-2">Cargando…</p></div>`;

  try {
    const r = await fetch(`../php/adm_usuario_detalle.php?usId=${encodeURIComponent(usId)}`);
    const j = await r.json();
    if (!j?.success) { bodyEl.innerHTML = `<p class="text-danger">${j.error || 'Error'}</p>`; return; }

    const u = j.user;
    const nombre = [u.usNombre, u.usAPaterno, u.usAMaterno].filter(Boolean).join(' ');
    const img = u.imgUrl || resolveUserImgPath(u.usUsername);

    bodyEl.innerHTML = `
      <div class="d-flex gap-3 align-items-center mb-3">
        <img src="${img}" onerror="this.src='../img/Usuario/default.webp'"
             class="rounded-circle" style="width:72px;height:72px;object-fit:cover;">
        <div>
          <div class="fw-semibold fs-5">${nombre}</div>
          <div class="text-muted small">${u.usRol || ''} · ${u.csNombre || '—'}</div>
        </div>
      </div>

      <div class="mb-2">
        <label class="text-muted small">Correo</label>
        <div class="input-group">
          <input class="form-control" value="${u.usCorreo || ''}" readonly>
          <button class="btn btn-outline-secondary" type="button" data-copy="${u.usCorreo || ''}">
            <i class="bi bi-clipboard"></i>
          </button>
        </div>
      </div>

      <div class="mb-2">
        <label class="text-muted small">Teléfono</label>
        <div class="input-group">
          <input class="form-control" value="${u.usTelefono || ''}" readonly>
          <button class="btn btn-outline-secondary" type="button" data-copy="${u.usTelefono || ''}">
            <i class="bi bi-clipboard"></i>
          </button>
        </div>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-outline-primary" data-id="${u.usId}" data-action="pw">Cambiar contraseña</button>
        <button class="btn btn-primary" data-id="${u.usId}" data-action="rol">Cambiar rol</button>
      </div>
    `;

    // copiar
    $$('[data-copy]', bodyEl).forEach(btn => {
      btn.addEventListener('click', () => {
        const txt = btn.getAttribute('data-copy') || '';
        navigator.clipboard?.writeText(txt);
      });
    });

    // acciones rápidas desde el offcanvas
    $$('[data-action]', bodyEl).forEach(b => {
      b.addEventListener('click', () => {
        const id = b.getAttribute('data-id');
        if (b.getAttribute('data-action') === 'pw') abrirCambiarPassword(id);
        if (b.getAttribute('data-action') === 'rol') abrirCambiarRol(id);
      });
    });

    new bootstrap.Offcanvas('#offcanvasUsuario').show();
  } catch {
    bodyEl.innerHTML = `<p class="text-danger">Error de red</p>`;
  }
}

function abrirCambiarPassword(usId) {
  $('#pw_usId').value = usId;
  $('#pw_1').value = '';
  $('#pw_2').value = '';
  new bootstrap.Modal('#modalPassword').show();
}

function abrirCambiarRol(usId) {
  $('#rol_usId').value = usId;
  new bootstrap.Modal('#modalRol').show();
}

async function eliminarUsuario(usId) {
  if (!confirm('¿Eliminar este usuario? Esta acción no se puede deshacer.')) return;
  try {
    const r = await fetch('../php/adm_usuario_eliminar.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ usId })
    });
    const j = await r.json();
    if (j?.success) cargarUsuarios();
    else Swal.fire({
      icon: "error",
      title: j?.error || 'No se pudo eliminar',
      text: "¡Algo salio mal!",
      footer: '<a href="#">Contactar a soporte</a>'
    });
  } catch {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "¡Algo salio mal!",
      footer: '<a href="#">Contactar a soporte</a>'
    });
  }
}

// Submit: cambiar contraseña
$('#formPassword')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const f = e.currentTarget;
  if (!f.checkValidity()) { f.classList.add('was-validated'); return; }
  const p1 = $('#pw_1').value.trim();
  const p2 = $('#pw_2').value.trim();
  if (p1 !== p2) { alert('Las contraseñas no coinciden'); return; }

  const btn = $('#btnPasswordGuardar') || $('#formPassword button[type="submit"]');
  btn.disabled = true;
  try {
    const r = await fetch('../php/adm_usuario_password.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ usId: $('#pw_usId').value, password: p1 })
    });
    const j = await r.json();
    if (j?.success) bootstrap.Modal.getInstance($('#modalPassword')).hide();
    else Swal.fire({
      icon: "error",
      title: j?.error || 'No se pudo actualizar',
      text: "¡Algo salio mal!",
      footer: '<a href="#">Contactar a soporte</a>'
    });
  } catch {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "¡Algo salio mal!",
      footer: '<a href="#">Contactar a soporte</a>'
    });
  }
  finally { btn.disabled = false; }
});

// Submit: cambiar rol
$('#formRol')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = $('#btnRolGuardar') || $('#formRol button[type="submit"]');
  btn.disabled = true;
  try {
    const r = await fetch('../php/adm_usuario_rol.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ usId: $('#rol_usId').value, usRol: $('#rol_select').value })
    });
    const j = await r.json();
    if (j?.success) {
      bootstrap.Modal.getInstance($('#modalRol')).hide();
      cargarUsuarios();
    } else Swal.fire({
      icon: "error",
      title: j?.error || 'No se pudo actualizar',
      text: "¡Algo salio mal!",
      footer: '<a href="#">Contactar a soporte</a>'
    });
  } catch {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "¡Algo salio mal!",
      footer: '<a href="#">Contactar a soporte</a>'
    });
  }
  finally { btn.disabled = false; }
});

// Submit: crear nuevo usuario
// Submit: crear nuevo usuario (REEMPLAZA este handler por completo)
$('#formNuevoUsuario')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.currentTarget;
  const btn = $('#btnNuevoUsuario') || form.querySelector('button[type="submit"]');

  const fd = new FormData(form);
  // Normaliza nombres que el backend espera
  const payload = {
    usNombre: (fd.get('usNombre') || '').trim(),
    usAPaterno: (fd.get('usAPaterno') || '').trim(),
    usAMaterno: (fd.get('usAMaterno') || '').trim(),
    usCorreo: (fd.get('usCorreo') || fd.get('usEmail') || '').trim(),
    usTelefono: (fd.get('usTelefono') || fd.get('usTel') || '').trim(),
    usUsername: (fd.get('usUsername') || '').trim(),
    usRol: (fd.get('usRol') || '').trim(),      // AC | UC | EC
    csId: Number(fd.get('csId') || 0),
    clId: Number(fd.get('clId') || 0),             // oculto si no eres MRA/MRSA
    usPass: (fd.get('usPass') || '').trim()
  };

  // Validaciones mínimas
  if (!payload.usNombre || !payload.usAPaterno || !payload.usCorreo || !payload.usUsername) {
    alert('Completa nombre, apellido paterno, correo y usuario.');
    return;
  }
  if (!payload.usRol) {
    alert('Selecciona un rol (AC/UC/EC).'); return;
  }
  if (!payload.csId) {
    alert('Selecciona una sede.'); return;
  }
  // Si eres MRA/MRSA y creas para otros clientes, exige clId
  // (si no eres MRA/MRSA, viene fijo por input hidden)
  const rolSesion = (document.body.getAttribute('data-rol') || '').toUpperCase();
  if (['MRA', 'MRSA'].includes(rolSesion) && !payload.clId) {
    alert('Selecciona el cliente.'); return;
  }

  btn.disabled = true;
  const oldText = btn.textContent;
  btn.textContent = 'Creando...';

  try {
    const r = await fetch('../php/adm_usuario_crear.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if (!j?.success) {
      Swal.fire({
      icon: "error",
      title: j?.error || 'No se pudo crear el usuario',
      text: "¡Algo salio mal!",
      footer: '<a href="#">Contactar a soporte</a>'
    });
      return;
    }
    if (j.tempPass) {
      alert(`Usuario creado.\nContraseña temporal: ${j.tempPass}`);
    }
    bootstrap.Modal.getInstance($('#modalNuevoUsuario'))?.hide();
    form.reset();
    cargarUsuarios();
  } catch {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "¡Algo salio mal!",
      footer: '<a href="#">Contactar a soporte</a>'
    });
  } finally {
    btn.disabled = false;
    btn.textContent = oldText;
  }
});


// Filtros
$('#usr_q')?.addEventListener('input', (e) => {
  state.q = e.target.value; state.page = 1; cargarUsuarios();
});
$('#usr_sede')?.addEventListener('change', (e) => {
  state.csId = Number(e.target.value || 0); state.page = 1; cargarUsuarios();
});
$('#usr_ac')?.addEventListener('change', (e) => {
  state.acId = Number(e.target.value || 0); state.page = 1; cargarUsuarios();
});

// Cargar combos iniciales y grid
document.addEventListener('DOMContentLoaded', async () => {
  try {
    const r1 = await fetch('../php/adm_sedes_list.php');
    const j1 = await r1.json();
    if (j1?.success) {
      // filtro
      const selFiltro = $('#usr_sede');
      j1.sedes.forEach(s => {
        const o = document.createElement('option');
        o.value = s.csId; o.textContent = s.csNombre;
        selFiltro?.appendChild(o);
      });
      // modal nuevo
      const selModal = $('#nu_csId');
      j1.sedes.forEach(s => {
        const o = document.createElement('option');
        o.value = s.csId; o.textContent = s.csNombre;
        selModal?.appendChild(o);
      });
    }
  } catch { }

  // (opcional) si eres MRA/MRSA y quieres seleccionar cliente en el modal:
  try {
    if (['MRA', 'MRSA'].includes((document.body.getAttribute('data-rol') || '').toUpperCase())) {
      const r2 = await fetch('../php/adm_clientes_list.php'); // si ya lo tienes; si no, omite
      const j2 = await r2.json();
      if (j2?.success) {
        const sel = $('#nu_clId');
        j2.clientes.forEach(c => {
          const o = document.createElement('option');
          o.value = c.clId; o.textContent = c.clNombre;
          sel?.appendChild(o);
        });
      }
    }
  } catch { }

  cargarUsuarios();
});

