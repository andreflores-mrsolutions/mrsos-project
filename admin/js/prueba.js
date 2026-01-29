// ../js/admin_usuarios.js
(function () {
  'use strict';

  let STATE = {
    scope: null,
    cliente: null,
    zonas: [],
    sedes: [],
    usuarios: [],
    filtrados: []
  };

  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  function inicializar() {
    // Si no existe la tabla, no hacemos nada (evita errores si se carga en otra página)
    if (!$('#tbodyUsuarios')) return;

    cargarDatos();

    const txtBuscar = $('#txtBuscarUsuario');
    const filtroRol = $('#filtroRolCliente');
    const filtroZona = $('#filtroZona');
    const filtroSede = $('#filtroSede');
    const filtroNotifMail = $('#filtroNotifMail');
    const filtroNotifInApp = $('#filtroNotifInApp');
    const btnLimpiar = $('#btnLimpiarFiltros');
    // const btnCrear = $('#btnCrearUsuario');

    if (txtBuscar) {
      txtBuscar.addEventListener('input', aplicarFiltros);
    }
    if (filtroRol) {
      filtroRol.addEventListener('change', aplicarFiltros);
    }
    if (filtroZona) {
      filtroZona.addEventListener('change', () => {
        syncSedesConZona();
        aplicarFiltros();
      });
    }
    if (filtroSede) {
      filtroSede.addEventListener('change', aplicarFiltros);
    }
    if (filtroNotifMail) {
      filtroNotifMail.addEventListener('change', aplicarFiltros);
    }
    if (filtroNotifInApp) {
      filtroNotifInApp.addEventListener('change', aplicarFiltros);
    }
    if (btnLimpiar) {
      btnLimpiar.addEventListener('click', () => {
        if (txtBuscar) txtBuscar.value = '';
        if (filtroRol) filtroRol.value = '';
        if (filtroZona) filtroZona.value = '';
        syncSedesConZona();
        if (filtroSede) filtroSede.value = '';
        if (filtroNotifMail) filtroNotifMail.checked = false;
        if (filtroNotifInApp) filtroNotifInApp.checked = false;
        aplicarFiltros();
      });
    }


  }

  function cargarDatos() {
    fetch('../php/adm_sedes_list.php', {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    })
      .then(r => r.json())
      .then(res => {
        if (!res.success) {
          if (window.Swal) {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: res.error || 'No se pudieron cargar los usuarios.'
            });
          } else {
            alert(res.error || 'No se pudieron cargar los usuarios.');
          }
          return;
        }
        STATE.scope = res.scope || null;
        STATE.cliente = res.cliente || null;
        STATE.zonas = res.zonas || [];
        STATE.sedes = res.sedes || [];
        STATE.usuarios = res.usuarios || [];

        llenarFiltrosZonaSede();
        actualizarResumenScope();
        aplicarFiltros();
      })
      .catch(err => {
        console.error(err);
        if (window.Swal) {
          Swal.fire({
            icon: 'error',
            title: 'Error de red',
            text: 'No se pudieron cargar los usuarios (error de red).'
          });
        } else {
          alert('No se pudieron cargar los usuarios (error de red).');
        }
      });
  }

  function llenarFiltrosZonaSede() {
    const filtroZona = $('#filtroZona');
    const filtroSede = $('#filtroSede');

    if (filtroZona) {
      filtroZona.innerHTML = '<option value="">Todas</option>';
      STATE.zonas.forEach(z => {
        const opt = document.createElement('option');
        opt.value = String(z.czId);
        opt.textContent = z.czNombre || ('Zona ' + z.czId);
        filtroZona.appendChild(opt);
      });
    }

    if (filtroSede) {
      filtroSede.innerHTML = '<option value="">Todas</option>';
      STATE.sedes.forEach(s => {
        const opt = document.createElement('option');
        opt.value = String(s.csId);
        opt.textContent = s.csNombre || ('Sede ' + s.csId);
        filtroSede.appendChild(opt);
      });
    }
  }

  function syncSedesConZona() {
    const filtroZona = $('#filtroZona');
    const filtroSede = $('#filtroSede');
    if (!filtroZona || !filtroSede) return;

    const czSel = filtroZona.value ? parseInt(filtroZona.value, 10) : null;

    filtroSede.innerHTML = '<option value="">Todas</option>';
    STATE.sedes.forEach(s => {
      if (czSel !== null && s.czId !== null && s.czId !== czSel) {
        return;
      }
      const opt = document.createElement('option');
      opt.value = String(s.csId);
      opt.textContent = s.csNombre || ('Sede ' + s.csId);
      filtroSede.appendChild(opt);
    });
  }

  function actualizarResumenScope() {
    const scope = STATE.scope || {};
    const badgeScope = $('#badgeScope');
    const badgeZonas = $('#badgeZonas');
    const badgeSedes = $('#badgeSedes');

    let labelScope = 'Sin datos';
    if (scope.tipo === 'ALL_MR') labelScope = 'MR · acceso completo';
    else if (scope.tipo === 'GLOBAL') labelScope = 'Admin global';
    else if (scope.tipo === 'ZONA') labelScope = 'Admin por zona';
    else if (scope.tipo === 'SEDE') labelScope = 'Admin por sede';

    if (badgeScope) badgeScope.textContent = labelScope;

    if (badgeZonas) {
      const totalZ = STATE.zonas.length;
      badgeZonas.textContent = totalZ ? `Zonas: ${totalZ}` : 'Zonas: —';
    }
    if (badgeSedes) {
      const totalS = STATE.sedes.length;
      badgeSedes.textContent = totalS ? `Sedes: ${totalS}` : 'Sedes: —';
    }
  }

  function aplicarFiltros() {
    const txtBuscar = $('#txtBuscarUsuario');
    const filtroRol = $('#filtroRolCliente');
    const filtroZona = $('#filtroZona');
    const filtroSede = $('#filtroSede');
    const filtroNotifMail = $('#filtroNotifMail');
    const filtroNotifInApp = $('#filtroNotifInApp');

    const term = (txtBuscar?.value || '').trim().toLowerCase();
    const rolFilter = filtroRol?.value || '';
    const czFilter = filtroZona?.value ? parseInt(filtroZona.value, 10) : null;
    const csFilter = filtroSede?.value ? parseInt(filtroSede.value, 10) : null;
    const onlyMail = !!(filtroNotifMail && filtroNotifMail.checked);
    const onlyInApp = !!(filtroNotifInApp && filtroNotifInApp.checked);

    const filtrados = STATE.usuarios.filter(u => {
      // Texto
      if (term) {
        const blob = (u.nombreCompleto + ' ' + u.correo + ' ' + u.username).toLowerCase();
        if (!blob.includes(term)) return false;
      }

      // Notificaciones
      if (onlyMail && !u.notifMail) return false;
      if (onlyInApp && !u.notifInApp) return false;

      const roles = u.rolesCliente || [];

      // Sin rolesCliente: lo tratamos como USUARIO sin ubicación
      if (!roles.length) {
        if (rolFilter && rolFilter !== 'USUARIO') return false;
        if (czFilter !== null || csFilter !== null) return false;
        return true;
      }

      // Filtro de rol cliente
      if (rolFilter) {
        const matchRol = roles.some(r => r.rol === rolFilter);
        if (!matchRol) return false;
      }

      // Filtro de zona
      if (czFilter !== null) {
        const inZona = roles.some(r => r.czId === czFilter);
        const inSedeDeZona = roles.some(r => {
          if (!r.csId) return false;
          const sede = STATE.sedes.find(s => s.csId === r.csId);
          return sede && sede.czId === czFilter;
        });
        if (!inZona && !inSedeDeZona) return false;
      }

      // Filtro de sede
      if (csFilter !== null) {
        const inSede = roles.some(r => r.csId === csFilter);
        if (!inSede) return false;
      }

      return true;
    });

    STATE.filtrados = filtrados;
    pintarTabla();
  }

  function pintarTabla() {
    const tbody = $('#tbodyUsuarios');
    const sinDatos = $('#estadoSinUsuarios');
    const lblFiltrados = $('#countUsuariosFiltrados');
    const lblTotal = $('#countUsuariosTotal');

    if (!tbody) return; // <- evita "Cannot set properties of null"

    tbody.innerHTML = '';

    if (lblTotal) {
      lblTotal.textContent = String(STATE.usuarios.length);
    }
    if (lblFiltrados) {
      lblFiltrados.textContent = String(STATE.filtrados.length);
    }

    if (!STATE.filtrados.length) {
      if (sinDatos) sinDatos.classList.remove('d-none');
      return;
    } else if (sinDatos) {
      sinDatos.classList.add('d-none');
    }

    STATE.filtrados.forEach(u => {
      const tr = document.createElement('tr');

      // Col avatar
      const tdAvatar = document.createElement('td');
      const initials = (u.nombre || '?').substring(0, 1).toUpperCase();
      const avatar = document.createElement('div');
      avatar.className = 'user-avatar-circle bg-primary-subtle text-primary';
      avatar.textContent = initials;
      tdAvatar.appendChild(avatar);
      tr.appendChild(tdAvatar);

      // Col principal
      const tdMain = document.createElement('td');
      const nameEl = document.createElement('div');
      nameEl.className = 'fw-semibold';
      nameEl.textContent = u.nombreCompleto;

      const userEl = document.createElement('div');
      userEl.className = 'small text-muted';
      userEl.textContent = u.username;

      const badgesEl = document.createElement('div');
      badgesEl.className = 'mt-1';
      (u.rolesCliente || []).forEach(r => {
        const span = document.createElement('span');
        span.className = 'badge bg-primary-subtle text-primary user-chip-role me-1';
        let label = r.rol;
        if (r.rol === 'ADMIN_GLOBAL') label = 'Admin global';
        else if (r.rol === 'ADMIN_ZONA') label = 'Admin zona';
        else if (r.rol === 'ADMIN_SEDE') label = 'Admin sede';
        else if (r.rol === 'USUARIO') label = 'Usuario';
        else if (r.rol === 'VISOR') label = 'Visor';
        span.textContent = label;
        badgesEl.appendChild(span);
      });

      tdMain.appendChild(nameEl);
      tdMain.appendChild(userEl);
      tdMain.appendChild(badgesEl);
      tr.appendChild(tdMain);

      // Col correo (md+)
      const tdCorreo = document.createElement('td');
      tdCorreo.className = 'd-none d-md-table-cell';
      tdCorreo.textContent = u.correo || '';
      tr.appendChild(tdCorreo);

      // Col rol (lg+)
      const tdRol = document.createElement('td');
      tdRol.className = 'd-none d-lg-table-cell';
      const roles = u.rolesCliente || [];
      if (roles.length) {
        tdRol.textContent = roles.map(r => r.rol).join(', ');
      } else {
        tdRol.textContent = 'USUARIO';
      }
      tr.appendChild(tdRol);

      // Col ubicación (lg+)
      const tdUbic = document.createElement('td');
      tdUbic.className = 'd-none d-lg-table-cell';
      const zonasSet = new Set();
      const sedesSet = new Set();
      (u.rolesCliente || []).forEach(r => {
        if (r.czNombre) zonasSet.add(r.czNombre);
        if (r.csNombre) sedesSet.add(r.csNombre);
      });

      if (zonasSet.size || sedesSet.size) {
        zonasSet.forEach(z => {
          const span = document.createElement('span');
          span.className = 'badge badge-zona me-1';
          span.textContent = z;
          tdUbic.appendChild(span);
        });
        sedesSet.forEach(s => {
          const span = document.createElement('span');
          span.className = 'badge badge-sede me-1';
          span.textContent = s;
          tdUbic.appendChild(span);
        });
      } else {
        tdUbic.innerHTML = '<span class="text-muted small">Sin zona/sede</span>';
      }
      tr.appendChild(tdUbic);

      // Col acciones
      const tdAcc = document.createElement('td');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm btn-outline-secondary';
      btn.innerHTML = '<i class="bi bi-three-dots-vertical"></i>';
      btn.addEventListener('click', () => {
        window.location.href = 'adm_usuario_detalle.php?usId=' + encodeURIComponent(u.usId);
      });
      tdAcc.appendChild(btn);
      tr.appendChild(tdAcc);

      tbody.appendChild(tr);
    });
  }

  document.addEventListener('DOMContentLoaded', inicializar);

    // ========== CREAR USUARIO ==========

  let modalCrearUsuario = null;
  let modalEditarUsuario = null;


  function limpiarFormCrearUsuario() {
    const form = document.getElementById('formCrearUsuario');
    if (!form) return;
    form.reset();
  }

  // Llena el select de Zonas en el modal usando STATE.zonas
  function llenarZonasModal() {
    const selZona = document.getElementById('uZona');
    if (!selZona) return;

    selZona.innerHTML = '<option value="">Sin zona específica</option>';

    (STATE.zonas || []).forEach(z => {
      const opt = document.createElement('option');
      opt.value = String(z.czId);
      opt.textContent = z.czNombre || ('Zona ' + z.czId);
      selZona.appendChild(opt);
    });
  }

  // Llena el select de Sedes en el modal usando STATE.sedes, filtrando por zona si se indica
  function llenarSedesModal(czIdFiltrar = null) {
    const selSede = document.getElementById('uSede');
    if (!selSede) return;

    selSede.innerHTML = '<option value="">Sin sede específica</option>';

    (STATE.sedes || []).forEach(s => {
      if (czIdFiltrar !== null && s.czId !== czIdFiltrar) {
        return;
      }
      const opt = document.createElement('option');
      opt.value = String(s.csId);
      opt.textContent = s.csNombre || ('Sede ' + s.csId);
      selSede.appendChild(opt);
    });
  }

  // Validación básica del payload antes de enviar
  function validarPayloadUsuario(payload) {
    if (!payload.nombre) return 'El nombre es obligatorio.';
    if (!payload.apaterno) return 'El apellido paterno es obligatorio.';
    if (!payload.correo) return 'El correo es obligatorio.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.correo)) return 'Correo no válido.';
    if (!payload.username) return 'El nombre de usuario es obligatorio.';
    if (!/^[A-Za-z0-9_-]{3,20}$/.test(payload.username)) {
      return 'Usuario inválido. Usa de 3 a 20 caracteres (letras, números, "-" y "_").';
    }
    if (!payload.nivel) return 'Selecciona un nivel.';

    // Reglas opcionales según nivel
    if (payload.nivel === 'ADMIN_ZONA' && !payload.zonaId) {
      return 'Para ADMIN_ZONA debes seleccionar una zona.';
    }
    if (payload.nivel === 'ADMIN_SEDE' && !payload.sedeId) {
      return 'Para ADMIN_SEDE debes seleccionar una sede.';
    }

    return null;
  }

  document.addEventListener('DOMContentLoaded', () => {
    // Instanciar modal
    const modalEl = document.getElementById('modalCrearUsuario');
    if (modalEl) {
      modalCrearUsuario = new bootstrap.Modal(modalEl);
    }

    // Botón "Nuevo usuario"
    const btnCrear = document.getElementById('btnCrearUsuario');
    if (btnCrear && modalCrearUsuario) {
      btnCrear.addEventListener('click', (e) => {
        e.preventDefault();
        limpiarFormCrearUsuario();
        // Llenar combo Zona/Sede con lo que ya cargó STATE
        llenarZonasModal();
        llenarSedesModal(null);
        modalCrearUsuario.show();
      });
    }

    // Cuando cambia la zona, filtramos sedes
    const selZona = document.getElementById('uZona');
    if (selZona) {
      selZona.addEventListener('change', () => {
        const val = selZona.value ? parseInt(selZona.value, 10) : null;
        llenarSedesModal(val);
      });
    }

    // Submit del formulario de creación
    const formCrear = document.getElementById('formCrearUsuario');
    if (formCrear) {
      formCrear.addEventListener('submit', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const nombre    = document.getElementById('uNombre')?.value.trim()   || '';
        const apaterno  = document.getElementById('uAPaterno')?.value.trim() || '';
        const amaterno  = document.getElementById('uAMaterno')?.value.trim() || '';
        const correo    = document.getElementById('uCorreo')?.value.trim()   || '';
        const telefono  = document.getElementById('uTelefono')?.value.trim() || '';
        const username  = document.getElementById('uUsername')?.value.trim() || '';
        const nivel     = document.getElementById('uNivel')?.value           || '';
        const zonaId    = document.getElementById('uZona')?.value || '';
        const sedeId    = document.getElementById('uSede')?.value || '';
        const nota      = document.getElementById('uNota')?.value.trim()     || '';

        const payload = {
          nombre,
          apaterno,
          amaterno,
          correo,
          telefono,
          username,
          nivel,
          zonaId: zonaId || null,
          sedeId: sedeId || null,
          nota
        };

        const err = validarPayloadUsuario(payload);
        if (err) {
          if (window.Swal) {
            Swal.fire('Validación', err, 'warning');
          } else {
            alert(err);
          }
          return;
        }

        fetch('../php/adm_usuario_crear.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(payload)
        })
          .then(r => r.json())
          .then(res => {
            if (res.success) {
              if (window.Swal) {
                Swal.fire({
                  title: 'Usuario creado',
                  text: 'El usuario se creó correctamente.',
                  icon: 'success'
                }).then(() => {
                  modalCrearUsuario.hide();
                  limpiarFormCrearUsuario();
                  // Recargamos datos (usuarios/zonas/sedes) y repaint
                  cargarDatos();
                });
              } else {
                alert('Usuario creado correctamente.');
                modalCrearUsuario.hide();
                limpiarFormCrearUsuario();
                cargarDatos();
              }
            } else {
              const msg = res.error || 'No se pudo crear el usuario.';
              if (window.Swal) {
                Swal.fire('Error', msg, 'error');
              } else {
                alert(msg);
              }
            }
          })
          .catch(err => {
            console.error(err);
            if (window.Swal) {
              Swal.fire('Error', 'Fallo de red al crear usuario.', 'error');
            } else {
              alert('Fallo de red al crear usuario.');
            }
          });
      });
    }
  });

  

})();
