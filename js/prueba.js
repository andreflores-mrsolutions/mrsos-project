// ../js/admin_tickets.js
(function () {
  'use strict';

  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  let STATE = {
    clientes: [],
    clienteSeleccionado: null,
    tickets: []
  };

  document.addEventListener('DOMContentLoaded', () => {
    // Cargar clientes
    cargarClientes();

    // Botón de tema reutilizando lógica simple
    const btnTheme = $('#btnThemeDesktop');
    if (btnTheme) {
      btnTheme.addEventListener('click', () => {
        const isDark = document.body.classList.contains('dark-mode');
        document.body.classList.toggle('dark-mode', !isDark);
        const icon = btnTheme.querySelector('i');
        if (icon) {
          icon.classList.remove('bi-moon', 'bi-moon-fill');
          icon.classList.add(!isDark ? 'bi-moon-fill' : 'bi-moon');
        }
        document.cookie = 'mrs_theme=' + (!isDark ? 'dark' : 'light') + ';path=/;SameSite=Lax';
      });
    }
  });

  // ===========================
  // Cargar lista de clientes
  // ===========================
  function cargarClientes() {
    fetch('../php/adm_tickets_list.php?mode=clientes')
      .then(res => res.json())
      .then(resp => {
        if (!resp || !resp.success) {
          mostrarError(resp?.error || 'No se pudieron cargar los clientes.');
          return;
        }
        STATE.clientes = resp.clientes || [];
        pintarClientes();
      })
      .catch(() => {
        mostrarError('Error de red al cargar clientes.');
      });
  }

  function pintarClientes() {
    const cont = $('#contenedorClientes');
    const lblTotal = $('#lblTotalClientes');
    if (!cont) return;

    cont.innerHTML = '';

    if (lblTotal) {
      lblTotal.textContent = String(STATE.clientes.length);
    }

    if (!STATE.clientes.length) {
      cont.innerHTML = '<span class="text-muted small">No hay clientes con tickets.</span>';
      return;
    }

    STATE.clientes.forEach(cl => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'cliente-chip';
      btn.dataset.clId = cl.clId;

      let initials = (cl.clNombre || '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(w => w[0].toUpperCase())
        .join('');

      btn.innerHTML = `
        <span class="badge bg-primary-subtle text-primary fw-bold">${initials}</span>
        <span>${cl.clNombre}</span>
        <span class="badge bg-light text-muted ms-1">${cl.totalTickets || 0}</span>
      `;

      btn.addEventListener('click', () => {
        seleccionarCliente(cl);
      });

      cont.appendChild(btn);
    });
  }

  function seleccionarCliente(cl) {
    STATE.clienteSeleccionado = cl;
    // Marcar activo visualmente
    $$('.cliente-chip').forEach(b => {
      b.classList.toggle('active', b.dataset.clId == cl.clId);
    });

    if ($('#lblClienteSeleccionado')) {
      $('#lblClienteSeleccionado').textContent = cl.clNombre || '';
    }
    if ($('#bloqueTickets')) {
      $('#bloqueTickets').classList.remove('d-none');
    }

    cargarTicketsCliente(cl.clId);
  }

  // ===========================
  // Cargar tickets por cliente
  // ===========================
  function cargarTicketsCliente(clId) {
    if (!clId) return;

    fetch(`../php/adm_tickets_list.php?mode=tickets&clId=${encodeURIComponent(clId)}`)
      .then(res => res.json())
      .then(resp => {
        if (!resp || !resp.success) {
          mostrarError(resp?.error || 'No se pudieron cargar los tickets del cliente.');
          return;
        }
        STATE.tickets = resp.tickets || [];
        pintarTicketsAgrupados();
      })
      .catch(() => {
        mostrarError('Error de red al cargar tickets del cliente.');
      });
  }

  function pintarTicketsAgrupados() {
    const cont = $('#contenedorTickets');
    const lblTotal = $('#lblTotalTickets');

    if (!cont) return;

    cont.innerHTML = '';

    if (lblTotal) {
      lblTotal.textContent = String(STATE.tickets.length);
    }

    if (!STATE.tickets.length) {
      cont.innerHTML = `
        <div class="text-center text-muted small py-3">
          <i class="bi bi-inboxes mb-2" style="font-size:1.5rem;"></i><br>
          No hay tickets para este cliente.
        </div>`;
      return;
    }


    // Agrupar por zona + sede
    const grupos = new Map();
    STATE.tickets.forEach(t => {
      const zona = t.czNombre || 'Sin zona';
      const sede = t.csNombre || 'Sin sede';
      const key = `${zona}|||${sede}`;
      if (!grupos.has(key)) {
        grupos.set(key, {
          zona,
          sede,
          tickets: []
        });
      }
      grupos.get(key).tickets.push(t);
    });

    let html = '';

    grupos.forEach(gr => {
      html += `
        <div class="mb-3">
          <div class="ticket-zona-title">
            ${gr.zona !== 'Sin zona' ? 'Zona: ' + gr.zona : 'Sin zona definida'}
          </div>
          <div class="fw-semibold mb-1">
            Sede: ${gr.sede}
          </div>
          <div class="list-group list-group-flush">
      `;

      gr.tickets.forEach(t => {
        const badgeEstado = construirBadgeEstado(t.tiEstatus, t.tiProceso);
        const fechaTxt = t.tiFechaCreacion || t.tiFecha || '';

        html += `
          <tr>
          <td class="align-middle" style="width: 20%;">
            <div class="mrs-ticket-main">
              <div>
                <span class="${badgeEstadoTicket(t.tiEstatus)}">${t.tiEstatus || "—"}</span>
              </div>
              <div class="d-flex flex-wrap gap-1">
                ${t.tiProceso ? `<span class="badge-pill-soft ${badgeProcesoTicket(t.tiProceso)}">${t.tiProceso}</span>` : ""}
                ${t.tiTipoTicket ? `<span class="${badgeTipoTicket(t.tiTipoTicket)}">${t.tiTipoTicket}</span>` : ""}
              </div>
              <div class="mrs-ticket-id mt-1">
                ${codigo}
              </div>
            </div>
          </td>

          <td class="align-middle" style="width: 60%;">
            <div class="mrs-ticket-body">
              <div class="mrs-ticket-title">
                ${t.eqModelo ? ' · ' + t.eqModelo : ''}
              </div>
              <div class="mrs-ticket-sub">
                SN: <span class="fw-medium">${t.peSN ? ' · SN: ' + t.peSN : ''}</span>
                
              </div>
              <div class="mrs-ticket-meta">
                ${fmtVisitaFechaHora(t)
            ? `<span><i class="bi bi-tools me-1"></i>${fmtVisitaFechaHora(t)}${fmtVisitaDuracion(t)}</span>`
            : ''
          }
                ${t.tiExtra
            ? `<span class="text-truncate" style="max-width: 260px;">${t.tiExtra}</span>`
            : ''
          }
              </div>
            </div>
          </td>
          <td class="align-middle text-end mrs-ticket-action" style="width: 20%;">
            <div class="mb-1">
              ${renderQuickActions(t, prefix)}
            </div>
            <a href="#"
              class="small"
              onclick="abrirDetalle(${Number(t.tiId)}, '${prefix}')"
              data-bs-toggle="offcanvas"
              data-bs-target="#offcanvasTicket">
              Ver detalle <i class="bi bi-arrow-right-short"></i>
            </a>
          </td>
        </tr>
        `;
      });

      html += `
          </div>
        </div>
      `;
    });

    cont.innerHTML = html;
  }

  function construirBadgeEstado(estatus, proceso) {
    estatus = estatus || '';
    proceso = proceso || '';

    let clase = 'bg-secondary-subtle text-secondary';
    let texto = estatus;

    if (estatus === 'Abierto') {
      clase = 'bg-success-subtle text-success';
    } else if (estatus === 'Pospuesto') {
      clase = 'bg-warning-subtle text-warning';
    } else if (estatus === 'Cerrado') {
      clase = 'bg-secondary text-light';
    }

    if (proceso) {
      texto += ' · ' + proceso;
    }

    return `<span class="badge ${clase}">${texto}</span>`;
  }

  function mostrarError(msg) {
    if (window.Swal) {
      Swal.fire('Error', msg, 'error');
    } else {
      alert(msg);
    }
  }

})();
