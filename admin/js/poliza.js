// poliza.js
(function () {
    'use strict';

    const qs = (sel) => document.querySelector(sel);

    let STATE = {
        equipos: [],
        grupos: [],       // agrupados por póliza
        filtrados: []
    };

    // =========================
    // Utilidades
    // =========================

    function mostrarToast(tipo, mensaje) {
        const toastId = tipo === 'success' ? '#toastSuccess' : '#toastError';
        const elem = document.querySelector(toastId);
        if (!elem) {
            alert(mensaje);
            return;
        }
        const body = elem.querySelector('.toast-body');
        if (body) body.textContent = mensaje;
        const t = new bootstrap.Toast(elem);
        t.show();
    }

    function parseFechaOnly(str) {
        if (!str) return null;
        const parts = str.split(' ')[0].split('-');
        if (parts.length !== 3) return null;
        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10) - 1;
        const d = parseInt(parts[2], 10);
        if (Number.isNaN(y) || Number.isNaN(m) || Number.isNaN(d)) return null;
        return new Date(y, m, d);
    }

    function calcularEstadoPoliza(fechaFinStr) {
        const fechaFin = parseFechaOnly(fechaFinStr);
        if (!fechaFin) {
            return {
                estado: 'desconocido',
                dias: null,
                badgeClass: 'badge bg-secondary-subtle text-secondary',
                label: 'Fecha no disponible'
            };
        }
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        fechaFin.setHours(0, 0, 0, 0);
        const diffMs = fechaFin.getTime() - hoy.getTime();
        const dias = Math.round(diffMs / (1000 * 60 * 60 * 24));

        if (dias < 0) {
            return {
                estado: 'vencida',
                dias,
                badgeClass: 'badge badge-poliza-vencida',
                label: `Póliza vencida (venció hace ${Math.abs(dias)} día${Math.abs(dias) === 1 ? '' : 's'})`
            };
        }
        if (dias <= 30) {
            return {
                estado: 'proxima',
                dias,
                badgeClass: 'badge badge-poliza-proxima',
                label: `Próxima a vencer (vence en ${dias} día${dias === 1 ? '' : 's'})`
            };
        }
        return {
            estado: 'vigente',
            dias,
            badgeClass: 'badge badge-poliza-vigente',
            label: `Póliza vigente (vence en ${dias} día${dias === 1 ? '' : 's'})`
        };
    }

    function formatearTickets(prefix, listaIds) {
        if (!listaIds) return '';
        const ids = listaIds
            .split(',')
            .map(id => id.trim())
            .filter(Boolean);
        if (!ids.length) return '';
        return ids.map(id => `${prefix} ${id}`).join(', ');
    }

    function agruparPorPoliza(equipos) {
        const map = new Map();

        equipos.forEach(eq => {
            const key = eq.pcId;
            if (!map.has(key)) {
                map.set(key, {
                    pcId: eq.pcId,
                    pcTipoPoliza: eq.pcTipoPoliza,
                    pcFechaInicio: eq.pcFechaInicio,
                    pcFechaFin: eq.pcFechaFin,
                    clNombre: eq.clNombre,
                    sedeNombre: eq.csNombre || null,
                    equipos: []
                });
            }
            map.get(key).equipos.push(eq);
        });

        return Array.from(map.values()).sort((a, b) => {
            const fa = parseFechaOnly(a.pcFechaFin);
            const fb = parseFechaOnly(b.pcFechaFin);
            if (!fa || !fb) return 0;
            return fa.getTime() - fb.getTime();
        });
    }

    // =========================
    // Render
    // =========================

    function renderPolizas() {
        const cont = qs('#contenedorPolizas');
        const sin = qs('#estadoSinPolizas');
        const lblTotal = qs('#lblEquiposTotal');
        const lblFiltrados = qs('#lblEquiposFiltrados');

        if (!cont) return;

        const totalEquipos = STATE.equipos.length;
        let equiposFiltrados = 0;
        STATE.filtrados.forEach(g => { equiposFiltrados += g.equipos.length; });

        if (lblTotal) lblTotal.textContent = String(totalEquipos);
        if (lblFiltrados) lblFiltrados.textContent = String(equiposFiltrados);

        if (!STATE.filtrados.length) {
            cont.innerHTML = '';
            if (sin) sin.classList.remove('d-none');
            return;
        } else if (sin) {
            sin.classList.add('d-none');
        }

        let html = '';

        STATE.filtrados.forEach(poliza => {
            const estado = calcularEstadoPoliza(poliza.pcFechaFin);
            const tipo = poliza.pcTipoPoliza || 'Sin tipo';
            const cliente = poliza.clNombre || '';
            const sede = poliza.sedeNombre ? ` · ${poliza.sedeNombre}` : '';

            html += `
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <div>
              <div>
                <div class="poliza-header-title mb-1">Póliza #${poliza.pcId}</div>
                <div class="small text-muted">
                    ${poliza.pcFechaInicio} &rarr; ${poliza.pcFechaFin}
                </div>
                </div>
              <div>
              <!-- Botón para ver/descargar PDF de la póliza -->
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary btnVerPolizaPdf"
                    data-pcid="${poliza.pcId}"
                    title="Ver póliza en PDF">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Póliza PDF
                </button>
                <span class="badge poliza-chip bg-primary-subtle text-primary me-1">${tipo}</span>
                <span class="${estado.badgeClass} poliza-chip">${estado.label}</span>
              </div>
              <div class="small text-muted mt-1">
                ${cliente}${sede}
              </div>
              <div class="small text-muted">
                ${poliza.pcFechaInicio ? `Inicio: ${poliza.pcFechaInicio}` : ''} 
                ${poliza.pcFechaFin ? ` · Fin: ${poliza.pcFechaFin}` : ''}
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="row" data-pcid="${poliza.pcId}">
      `;

            poliza.equipos.forEach(eq => {
                const ticketsCount = Number(
                    (eq.ticketsAbiertosCount !== undefined ? eq.ticketsAbiertosCount : eq.tieneTicket) || 0
                );
                const tieneTicket = ticketsCount > 0;
                const prefix = eq.ticketPrefix || 'TIC';
                const ticketsTexto = tieneTicket
                    ? `<small class="text-muted d-block mt-1"><strong>Tickets:</strong> ${formatearTickets(prefix, eq.ticketsAbiertosIds)}</small>`
                    : '';

                const maNombreLower = (eq.maNombre || '').toLowerCase();
                const modelo = eq.eqModelo || '';
                const sn = eq.peSN || '';
                const tipoEq = eq.eqTipoEquipo || '';
                const tipoPoliza = eq.pcTipoPoliza || '--';

                html += `
          <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6 mb-3">
            <div class="card equipo-card border ${tieneTicket ? 'border-warning' : 'border-success'}" 
                 data-peid="${eq.peId}" style="cursor:pointer;">
              <img src="../img/Equipos/${maNombreLower}/${modelo}.png"
                   class="card-img-top"
                   alt="${modelo}"
                   onerror="this.src='../img/Equipos/default.png';">
              <div class="card-body">
                <h6 class="card-title mb-1">${modelo}</h6>
                <small class="text-muted d-block">${tipoEq}</small>
                <small class="text-muted d-block">SN: ${sn}</small>
                <small class="text-muted d-block">Póliza: ${tipoPoliza}</small>
                ${tieneTicket ? '<span class="badge bg-warning text-dark mt-2">Ticket activo</span>' : ''}
                ${ticketsTexto}
              </div>
            </div>
          </div>
        `;
            });

            html += `
            </div>
          </div>
        </div>
      `;
        });

        cont.innerHTML = html;
    }

    // =========================
    // Filtros
    // =========================

    function aplicarFiltros() {
        const txt = qs('#filtroTexto')?.value.trim().toLowerCase() || '';
        const tipoPoliza = qs('#filtroTipoPoliza')?.value || '';
        const estadoPoliza = qs('#filtroEstadoPoliza')?.value || '';

        const filtrados = STATE.grupos
            .map(poliza => {
                const est = calcularEstadoPoliza(poliza.pcFechaFin);

                if (tipoPoliza && (poliza.pcTipoPoliza || '') !== tipoPoliza) {
                    return null;
                }

                if (estadoPoliza && est.estado !== estadoPoliza) {
                    return null;
                }

                const equiposFiltrados = (poliza.equipos || []).filter(eq => {
                    if (!txt) return true;
                    const blob = [
                        eq.eqModelo,
                        eq.peSN,
                        eq.peDescripcion,
                        poliza.pcTipoPoliza,
                        poliza.clNombre,
                        poliza.sedeNombre
                    ].join(' ').toLowerCase();
                    return blob.includes(txt);
                });

                if (!equiposFiltrados.length) return null;

                return {
                    ...poliza,
                    equipos: equiposFiltrados
                };
            })
            .filter(Boolean);

        STATE.filtrados = filtrados;
        renderPolizas();
    }

    // =========================
    // Cargar datos
    // =========================

    function cargarEquiposPoliza() {
        $.ajax({
            url: '../php/obtener_equipo_poliza.php',
            method: 'GET',
            dataType: 'json',
            success: function (resp) {
                if (!resp || resp.success !== true) {
                    mostrarToast('error', (resp && resp.error) ? resp.error : 'No se pudieron cargar las pólizas.');
                    return;
                }

                const equipos = resp.mode === 'lista' ? (resp.equipos || []) : (resp.equipos || []);
                STATE.equipos = equipos;
                STATE.grupos = agruparPorPoliza(equipos);
                STATE.filtrados = STATE.grupos.slice();
                renderPolizas();
            },
            error: function () {
                mostrarToast('error', 'Error de red al cargar las pólizas.');
            }
        });
    }

    // =========================
    // Offcanvas detalle
    // =========================

    function cargarDetalleEquipo(peId) {
        if (!peId) return;
        $.ajax({
            url: '../php/obtener_equipo_poliza.php',
            method: 'GET',
            data: { peId: peId },
            dataType: 'json',
            success: function (resp) {
                if (!resp || resp.success !== true || !resp.equipo) {
                    mostrarToast('error', (resp && resp.error) ? resp.error : 'No se pudo cargar el detalle del equipo.');
                    return;
                }
                const eq = resp.equipo;

                const titulo = `${eq.eqModelo || ''}`;
                const subt = `${eq.maNombre || ''} · SN: ${eq.peSN || ''}`;
                const cliente = eq.clNombre || '';
                const sede = eq.csNombre || 'Sin sede asociada';
                const tipoPoliza = eq.pcTipoPoliza || '--';
                const fechas = `${eq.pcFechaInicio || ''} - ${eq.pcFechaFin || ''}`;
                const estado = calcularEstadoPoliza(eq.pcFechaFin);
                const descripcion = eq.peDescripcion || 'Sin descripción registrada';
                const so = eq.peSO || 'Sin sistema operativo registrado';

                const ticketsCount = Number(
                    (eq.ticketsAbiertosCount !== undefined ? eq.ticketsAbiertosCount : eq.tieneTicket) || 0
                );
                const prefix = eq.ticketPrefix || 'TIC';
                const ticketsTexto = ticketsCount > 0
                    ? `Hay ${ticketsCount} ticket(s) activo(s): ${formatearTickets(prefix, eq.ticketsAbiertosIds)}`
                    : 'No hay tickets activos para este equipo.';

                qs('#detTituloEquipo').textContent = titulo;
                qs('#detSubtituloEquipo').textContent = subt;
                qs('#detCliente').textContent = cliente;
                qs('#detSede').textContent = sede;
                qs('#detPolizaLinea').innerHTML =
                    `<span class="badge poliza-chip bg-primary-subtle text-primary me-1">${tipoPoliza}</span>
           <span class="${estado.badgeClass} poliza-chip">${estado.label}</span>`;
                qs('#detPolizaFechas').textContent = `Vigencia: ${fechas}`;
                qs('#detModelo').textContent = `Modelo: ${eq.eqModelo || ''}`;
                qs('#detSN').textContent = `SN: ${eq.peSN || ''}`;
                qs('#detSO').textContent = `Sistema Operativo: ${so}`;
                qs('#detDescripcion').textContent = descripcion;
                qs('#detTickets').textContent = ticketsTexto;

                const img = qs('#detImagenEquipo');
                if (img) {
                    const maNombreLower = (eq.maNombre || '').toLowerCase();
                    const modelo = eq.eqModelo || '';
                    img.src = `../img/Equipos/${maNombreLower}/${modelo}.png`;
                }

                const canvasEl = document.getElementById('offcanvasEquipoDetalle');
                if (canvasEl) {
                    if (window.bootstrap && bootstrap.Offcanvas) {
                        const off = new bootstrap.Offcanvas(canvasEl);
                        off.show();
                    } else {
                        // fallback sencillo: mostrar un modal alterno o solo no hacer nada
                        console.warn('Bootstrap Offcanvas no está disponible.');
                    }
                }

            },
            error: function () {
                mostrarToast('error', 'Error de red al cargar el detalle del equipo.');
            }
        });
    }

    // =========================
    // Eventos
    // =========================

    document.addEventListener('DOMContentLoaded', () => {
        // Cargar datos iniciales
        cargarEquiposPoliza();

        // Filtros
        const filtroTexto = qs('#filtroTexto');
        const filtroTipoPoliza = qs('#filtroTipoPoliza');
        const filtroEstadoPoliza = qs('#filtroEstadoPoliza');
        const btnLimpiar = qs('#btnLimpiarFiltrosPoliza');

        if (filtroTexto) {
            filtroTexto.addEventListener('input', aplicarFiltros);
        }
        if (filtroTipoPoliza) {
            filtroTipoPoliza.addEventListener('change', aplicarFiltros);
        }
        if (filtroEstadoPoliza) {
            filtroEstadoPoliza.addEventListener('change', aplicarFiltros);
        }
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                if (filtroTexto) filtroTexto.value = '';
                if (filtroTipoPoliza) filtroTipoPoliza.value = '';
                if (filtroEstadoPoliza) filtroEstadoPoliza.value = '';
                STATE.filtrados = STATE.grupos.slice();
                renderPolizas();
            });
        }

        // Recargar desde top bar
        const btnRecargar = document.getElementById('btnRecargarPolizas');
        const btnRecargarSm = document.getElementById('btnRecargarPolizasSm');
        if (btnRecargar) {
            btnRecargar.addEventListener('click', (e) => {
                e.preventDefault();
                cargarEquiposPoliza();
            });
        }
        if (btnRecargarSm) {
            btnRecargarSm.addEventListener('click', (e) => {
                e.preventDefault();
                cargarEquiposPoliza();
            });
        }

        // Click en cards de equipo -> offcanvas detalle
        const cont = document.getElementById('contenedorPolizas');
        if (cont) {
            cont.addEventListener('click', (ev) => {
                const card = ev.target.closest('.equipo-card');
                if (!card) return;
                const peId = card.getAttribute('data-peid');
                if (!peId) return;
                cargarDetalleEquipo(peId);
            });
        }
    });
    // === Ver / descargar PDF de la póliza ===
    $(document).on('click', '.btnVerPolizaPdf', function () {
        const pcId = $(this).data('pcid');
        if (!pcId) {
            alert('No se pudo identificar la póliza.');
            return;
        }

        // URL al PHP que sirve el PDF
        const url = `../php/ver_poliza_pdf.php?pcId=${encodeURIComponent(pcId)}`;
        window.open(url, '_blank'); // nueva pestaña
    });

})();
