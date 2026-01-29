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

  // Modales (se instancian en DOMContentLoaded)
  let modalCrearUsuario = null;
  let modalEditarUsuario = null;

  /* ===========================
   *  INICIALIZACIÓN GENERAL
   * =========================== */
  function inicializar() {
    if (!$('#tbodyUsuarios')) return;

    cargarDatos();

    const txtBuscar = $('#txtBuscarUsuario');
    const filtroRol = $('#filtroRolCliente');
    const filtroZona = $('#filtroZona');
    const filtroSede = $('#filtroSede');
    const filtroNotifMail = $('#filtroNotifMail');
    const filtroNotifInApp = $('#filtroNotifInApp');
    const btnLimpiar = $('#btnLimpiarFiltros');

    if (txtBuscar) txtBuscar.addEventListener('input', aplicarFiltros);
    if (filtroRol) filtroRol.addEventListener('change', aplicarFiltros);
    if (filtroZona) {
      filtroZona.addEventListener('change', () => {
        syncSedesConZona();
        aplicarFiltros();
      });
    }
    if (filtroSede) filtroSede.addEventListener('change', aplicarFiltros);
    if (filtroNotifMail) filtroNotifMail.addEventListener('change', aplicarFiltros);
    if (filtroNotifInApp) filtroNotifInApp.addEventListener('change', aplicarFiltros);

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

    // Delegación para botón EDITAR en la tabla
    const tbody = $('#tbodyUsuarios');
    if (tbody) {
      tbody.addEventListener('click', (e) => {
        const btnEdit = e.target.closest('.btn-edit-user');
        if (btnEdit) {
          const usId = parseInt(btnEdit.dataset.usId, 10);
          if (usId > 0) {
            abrirModalEditarUsuario(usId);
          }
        }
      });
    }
  }

  /* ===========================
   *  CARGA DE DATOS DESDE PHP
   * =========================== */
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

  /* ===========================
   *  FILTROS ZONA / SEDE
   * =========================== */
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

  /* ===========================
   *  FILTRO DE USUARIOS
   * =========================== */
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

  /* ===========================
   *  PINTAR TABLA
   * =========================== */
  function pintarTabla() {
    const tbody = $('#tbodyUsuarios');
    const sinDatos = $('#estadoSinUsuarios');
    const lblFiltrados = $('#countUsuariosFiltrados');
    const lblTotal = $('#countUsuariosTotal');

    if (!tbody) return;

    tbody.innerHTML = '';

    if (lblTotal) lblTotal.textContent = String(STATE.usuarios.length);
    if (lblFiltrados) lblFiltrados.textContent = String(STATE.filtrados.length);

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

      // Col acciones (EDITAR)
      const tdAcc = document.createElement('td');
      tdAcc.innerHTML = `
        <button type="button"
                class="btn btn-sm btn-outline-primary btn-edit-user"
                data-us-id="${u.usId}">
          <i class="bi bi-pencil"></i>
        </button>
      `;
      tr.appendChild(tdAcc);

      tbody.appendChild(tr);
    });
  }

  /* ===========================
   *  CREAR USUARIO
   * =========================== */
  function limpiarFormCrearUsuario() {
    const form = document.getElementById('formCrearUsuario');
    if (!form) return;
    form.reset();
  }

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

    if (payload.nivel === 'ADMIN_ZONA' && !payload.zonaId) {
      return 'Para ADMIN_ZONA debes seleccionar una zona.';
    }
    if (payload.nivel === 'ADMIN_SEDE' && !payload.sedeId) {
      return 'Para ADMIN_SEDE debes seleccionar una sede.';
    }

    return null;
  }

  /* ===========================
   *  EDITAR USUARIO - HELPERS
   * =========================== */

  function llenarSedesEditarModal() {
    const selSede = document.getElementById('editSedeId');
    if (!selSede) return;

    selSede.innerHTML = '<option value="">Sin sede específica</option>';

    (STATE.sedes || []).forEach(s => {
      const opt = document.createElement('option');
      opt.value = String(s.csId);
      opt.textContent = s.csNombre || ('Sede ' + s.csId);
      selSede.appendChild(opt);
    });
  }

  function abrirModalEditarUsuario(usId) {
    // Reset básico
    const form = document.getElementById('formEditarUsuario');
    if (form) form.reset();

    // Campos de pass deshabilitados al inicio
    const chkCambiarPass = document.getElementById('chkCambiarPass');
    const editPass1 = document.getElementById('editPass1');
    const editPass2 = document.getElementById('editPass2');

    if (chkCambiarPass) chkCambiarPass.checked = false;
    if (editPass1) { editPass1.disabled = true; editPass1.value = ''; }
    if (editPass2) { editPass2.disabled = true; editPass2.value = ''; }

    // Llenar combo de sedes para este modal
    llenarSedesEditarModal();

    fetch('../php/adm_usuario_detalle.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'usId=' + encodeURIComponent(usId)
    })
      .then(r => r.json())
      .then(res => {
        if (!res.success) {
          Swal.fire('Error', res.error || 'No se pudo obtener detalles del usuario.', 'error');
          return;
        }
        const u = res.usuario;

        $('#editUsId').value = u.usId;
        $('#editNombre').value = u.nombre || '';
        $('#editApaterno').value = u.apaterno || '';
        $('#editAmaterno').value = u.amaterno || '';
        $('#editCorreo').value = u.correo || '';
        $('#editTelefono').value = u.telefono || '';
        $('#editUsername').value = u.username || '';
        $('#editNivel').value = u.nivel || '';

        const editSede = $('#editSedeId');
        if (editSede) {
          if (typeof u.sedeId !== 'undefined' && u.sedeId !== null) {
            editSede.value = String(u.sedeId);
          } else {
            editSede.value = '';
          }
        }

        const avatarDiv = $('#editAvatarPreview');
        const labelUser = $('#editUsernameLabel');
        if (avatarDiv) {
          if (u.avatarUrl) {
            avatarDiv.style.backgroundImage = `url('${u.avatarUrl}')`;
            avatarDiv.style.backgroundSize = 'cover';
            avatarDiv.textContent = '';
          } else {
            avatarDiv.style.backgroundImage = 'none';
            avatarDiv.textContent = (u.nombre || 'U').trim().substring(0, 1).toUpperCase();
          }
        }
        if (labelUser) {
          labelUser.textContent = '@' + (u.username || '');
        }

        if (modalEditarUsuario) {
          modalEditarUsuario.show();
        }
      })
      .catch(() => {
        Swal.fire('Error', 'Error de red al obtener detalles del usuario.', 'error');
      });
  }

  function guardarEdicionUsuario() {
    const usId = parseInt($('#editUsId').value, 10) || 0;
    const nombre = $('#editNombre').value.trim();
    const apaterno = $('#editApaterno').value.trim();
    const amaterno = $('#editAmaterno').value.trim();
    const correo = $('#editCorreo').value.trim();
    const telefono = $('#editTelefono').value.trim();
    const username = $('#editUsername').value.trim();
    const nivel = $('#editNivel').value;
    const sedeId = $('#editSedeId').value;

    const chkPass = $('#chkCambiarPass');
    const editPass1 = $('#editPass1');
    const editPass2 = $('#editPass2');

    const cambiarPass = chkPass && chkPass.checked;
    const newPass = cambiarPass ? (editPass1?.value || '') : '';
    const newPass2 = cambiarPass ? (editPass2?.value || '') : '';

    if (!usId) {
      Swal.fire('Error', 'Usuario inválido.', 'error');
      return;
    }
    if (!nombre || !apaterno || !correo || !username || !nivel) {
      Swal.fire('Campos requeridos', 'Nombre, apellidos, correo, usuario y nivel son obligatorios.', 'warning');
      return;
    }

    const payload = {
      usId,
      nombre,
      apaterno,
      amaterno,
      correo,
      telefono,
      username,
      nivel,
      sedeId: sedeId || null,
      newPass,
      newPass2
    };

    fetch('../php/adm_usuario_actualizar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => r.json())
      .then(res => {
        if (!res.success) {
          Swal.fire('Error', res.error || 'No se pudieron guardar los cambios.', 'error');
          return;
        }
        Swal.fire({
          title: 'Guardado',
          text: 'Los datos del usuario se actualizaron correctamente.',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });

        if (modalEditarUsuario) modalEditarUsuario.hide();

        if (typeof cargarDatos === 'function') {
          cargarDatos();
        }
      })
      .catch(() => {
        Swal.fire('Error', 'Error de red al guardar cambios.', 'error');
      });
  }

  /* ===========================
   *  DOMContentLoaded HOOKS
   * =========================== */

  document.addEventListener('DOMContentLoaded', inicializar);

  document.addEventListener('DOMContentLoaded', () => {
    // Instanciar modales
    const modalCrearEl = document.getElementById('modalCrearUsuario');
    const modalEditarEl = document.getElementById('modalEditarUsuario');

    if (modalCrearEl) modalCrearUsuario = new bootstrap.Modal(modalCrearEl);
    if (modalEditarEl) modalEditarUsuario = new bootstrap.Modal(modalEditarEl);

    // Botón "Nuevo usuario"
    const btnCrear = document.getElementById('btnCrearUsuario');
    if (btnCrear && modalCrearUsuario) {
      btnCrear.addEventListener('click', (e) => {
        e.preventDefault();
        limpiarFormCrearUsuario();
        llenarZonasModal();
        llenarSedesModal(null);
        modalCrearUsuario.show();
      });
    }

    // Cambiar zona -> filtrar sedes (modal crear)
    const selZona = document.getElementById('uZona');
    if (selZona) {
      selZona.addEventListener('change', () => {
        const val = selZona.value ? parseInt(selZona.value, 10) : null;
        llenarSedesModal(val);
      });
    }

    // Submit CREAR USUARIO
    const formCrear = document.getElementById('formCrearUsuario');
    if (formCrear) {
      formCrear.addEventListener('submit', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const nombre = document.getElementById('uNombre')?.value.trim() || '';
        const apaterno = document.getElementById('uAPaterno')?.value.trim() || '';
        const amaterno = document.getElementById('uAMaterno')?.value.trim() || '';
        const correo = document.getElementById('uCorreo')?.value.trim() || '';
        const telefono = document.getElementById('uTelefono')?.value.trim() || '';
        const username = document.getElementById('uUsername')?.value.trim() || '';
        const nivel = document.getElementById('uNivel')?.value || '';
        const zonaId = document.getElementById('uZona')?.value || '';
        const sedeId = document.getElementById('uSede')?.value || '';
        const nota = document.getElementById('uNota')?.value.trim() || '';

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
          if (window.Swal) Swal.fire('Validación', err, 'warning');
          else alert(err);
          return;
        }

        fetch('../php/adm_usuario_crear.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        })
          .then(r => r.json())
          .then(res => {
            if (res.success) {
              if (window.Swal) {
                Swal.fire({
                  title: 'Usuario creado',
                  text: 'El usuario se creó correctamente y se envió el correo de bienvenida.',
                  icon: 'success'
                }).then(() => {
                  modalCrearUsuario.hide();
                  limpiarFormCrearUsuario();
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
              if (window.Swal) Swal.fire('Error', msg, 'error');
              else alert(msg);
            }
          })
          .catch(err => {
            console.error(err);
            if (window.Swal) Swal.fire('Error', 'Fallo de red al crear usuario.', 'error');
            else alert('Fallo de red al crear usuario.');
          });
      });
    }

    // Checkbox "cambiar contraseña" (modal editar)
    const chkCambiarPass = document.getElementById('chkCambiarPass');
    const editPass1 = document.getElementById('editPass1');
    const editPass2 = document.getElementById('editPass2');

    if (chkCambiarPass) {
      chkCambiarPass.addEventListener('change', () => {
        const enabled = chkCambiarPass.checked;
        if (editPass1) {
          editPass1.disabled = !enabled;
          if (!enabled) editPass1.value = '';
        }
        if (editPass2) {
          editPass2.disabled = !enabled;
          if (!enabled) editPass2.value = '';
        }
      });
    }

    // Submit EDITAR USUARIO
    const formEditar = document.getElementById('formEditarUsuario');
    if (formEditar) {
      formEditar.addEventListener('submit', (ev) => {
        ev.preventDefault();
        guardarEdicionUsuario();
      });
    }
  });

})();
