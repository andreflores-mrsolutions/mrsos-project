function cargarHojasServicio() {
  const desde = document.getElementById('hsDesde').value || '';
  const hasta = document.getElementById('hsHasta').value || '';
  const tipo = document.getElementById('hsTipoEquipo').value || '';

  const qs = new URLSearchParams();
  if (desde) qs.set('desde', desde);
  if (hasta) qs.set('hasta', hasta);
  if (tipo) qs.set('tipoEquipo', tipo);

  // Si eres MRA y quieres ver todas, no pases clId. 
  // Si deseas filtrar por cliente/sede desde aquí: qs.set('clId', NNN); qs.set('csId', NNN);

  fetch(`../php/obtener_hojas_servicio.php${qs.toString() ? '?' + qs.toString() : ''}`, { cache: 'no-store' })
    .then(r => r.json())
    .then(json => {
      const wrap = document.getElementById('wrapHojas');
      if (!json?.success) {
        wrap.innerHTML = `<div class="col-12"><div class="alert alert-danger">${json?.error || 'No se pudo cargar.'}</div></div>`;
        return;
      }
      const items = json.items || [];
      if (!items.length) {
        wrap.innerHTML = `<div class="col-12"><p class="text-muted">Sin resultados.</p></div>`;
        return;
      }
      wrap.innerHTML = items.map(cardHoja).join('');
    })
    .catch(() => {
      document.getElementById('wrapHojas').innerHTML =
        `<div class="col-12"><div class="alert alert-danger">Error de red.</div></div>`;
    });
}

document.getElementById('hsBtnFiltrar')?.addEventListener('click', cargarHojasServicio);
document.getElementById('hsBtnReset')?.addEventListener('click', () => {
  document.getElementById('hsDesde').value = '';
  document.getElementById('hsHasta').value = '';
  document.getElementById('hsTipoEquipo').value = '';
  cargarHojasServicio();
});

document.addEventListener('DOMContentLoaded', cargarHojasServicio);




function cardHoja(item) {
  // Marca / modelo
  const marcaImg = item.maNombre ? `../img/Marcas/${item.maNombre.toLowerCase()}.png` : '';
  const titulo = `${item.eqModelo || 'Equipo'} ${item.eqVersion || ''}`.trim();

  const descargable = !!item.disponible && !!item.url;

  return `
      <div class="col-12 col-sm-6 col-md-4">
        <div class="card h-100 ${descargable ? '' : 'border-0'}" style="${descargable ? '' : 'background:#f8f9fb'}">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="badge bg-light text-dark">Ticket #${item.tiId}</span>
              <span class="badge ${item.disponible ? 'bg-success' : 'bg-secondary'}">
                ${item.disponible ? 'Disponible' : 'Pendiente'}
              </span>
            </div>

            <div class="d-flex align-items-center gap-2 mb-2">
              ${marcaImg ? `<img src="${marcaImg}" style="height:22px" alt="${item.maNombre}">` : ''}
              <strong>${titulo || '—'}</strong>
            </div>

            <div class="small text-muted mb-1"><i class="bi bi-geo-alt"></i> ${item.csNombre || 'General'}</div>
            <div class="small text-muted mb-2"><i class="bi bi-calendar"></i> ${item.tiFecha || '—'}</div>

            <div class="d-flex align-items-center justify-content-between mt-3">
              <span class="badge bg-light text-dark">${item.eqTipoEquipo || '—'}</span>
              <span class="badge ${item.tiEstatus === 'Finalizado' ? 'bg-primary' : 'bg-light text-dark'}">${item.tiEstatus}</span>
            </div>
          </div>

          <div class="card-footer bg-transparent border-0">
            ${descargable
      ? `<a href="${item.url}" class="btn btn-outline-primary w-100" download>
                    <i class="bi bi-download"></i> Descargar hoja de servicio
                 </a>`
      : `<button class="btn btn-outline-secondary w-100" disabled>
                    <i class="bi bi-file-earmark"></i> Hoja no disponible
                 </button>`
    }
          </div>
        </div>
      </div>
    `;
}