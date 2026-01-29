// modal_visita.js
(function (window, $) {


  'use strict';

  if (!$) {
    console.error('MRSOS_visita: jQuery es requerido.');
    return;
  }

  const M = {};
  let visitaModalPropuesta, visitaModalAsignar;

  // ---------- helpers ----------
  function hoyISO() {
    const d = new Date();
    return d.toISOString().slice(0, 10); // YYYY-MM-DD
  }

  function setMinFechaInputs() {
    const min = hoyISO();
    $('#visitaProp_fecha, #visitaAsig_fecha').attr('min', min);
  }

  function ajaxvisita(payload) {
    return $.ajax({
      url: '../php/visita_actualizar.php',
      method: 'POST',
      dataType: 'json',
      data: payload
    });
  }

  // ---------- abrir modales ----------
  function openPropuesta(tiId) {
    $('#visitaProp_tiId').val(tiId);
    $('#visitaFormPropuesta')[0].reset();
    setMinFechaInputs();
    visitaModalPropuesta.show();
  }

  function openAsignar(tiId) {
    $('#visitaAsig_tiId').val(tiId);
    $('#visitaFormAsignar')[0].reset();
    setMinFechaInputs();
    visitaModalAsignar.show();
  }

  // ---------- handlers de submit ----------
  function handleSubmitPropuesta(e) {
    e.preventDefault();

    const tiId = $('#visitaProp_tiId').val();
    const fecha = $('#visitaProp_fecha').val();
    const hora = $('#visitaProp_hora').val();
    const envio = $('input[name="visitaProp_envio"]:checked').val() || 'correo';

    const h = parseInt($('#visitaProp_duracionHoras').val() || '0', 10);
    const m = parseInt($('#visitaProp_duracionMinutos').val() || '0', 10);
    const duracionMin = (h * 60) + m;

    if (!tiId || !fecha || !hora) {
      Swal.fire('Datos incompletos', 'Completa fecha y hora.', 'warning');
      return;
    }

    ajaxvisita({
      tiId,
      accion: 'proponer',
      quien: 'CLIENTE',
      fecha,
      hora,
      envio,
      duracionMin
    })
      .done(res => {
        if (!res || !res.success) {
          Swal.fire('Error', res?.error || 'No se pudo guardar el visita.', 'error');
          return;
        }
        visitaModalPropuesta.hide();
        Swal.fire('Listo', 'El visita quedó propuesto.', 'success');
        cargarTicketsPorSede();
      })
      .fail(() => {
        Swal.fire('Error', 'No se pudo contactar el servidor.', 'error');
      });
  }
  function cancelarVisita(tiId) {
    if (!tiId) return;

    Swal.fire({
      icon: "warning",
      title: "Cancelar visita",
      text: "¿Seguro que quieres cancelar esta visita/ventana?",
      input: "textarea",
      inputPlaceholder: "Motivo de cancelación (opcional)",
      showCancelButton: true,
      confirmButtonText: "Sí, cancelar",
      cancelButtonText: "No",
    }).then((result) => {
      if (!result.isConfirmed) return;

      const formData = new URLSearchParams();
      formData.set("accion", "cancelar");
      formData.set("tiId", String(tiId));
      formData.set("motivo", result.value || "");

      fetch("../php/visita_actualizar.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: formData.toString(),
      })
        .then((r) => r.json())
        .then((res) => {
          if (!res?.success) throw new Error(res?.error || "Error");

          Swal.fire({
            icon: "success",
            title: "Visita cancelada",
            text: "La visita ha sido cancelada correctamente.",
            timer: 2000,
            showConfirmButton: false,
          });

          const m1 = document.getElementById("visitaModalPropuesta");
          const m2 = document.getElementById("visitaModalAsignar");
          [m1, m2].forEach((m) => {
            if (m) {
              const inst = bootstrap.Modal.getInstance(m);
              inst && inst.hide();
            }
          });

          cargarTicketsPorSede?.();
        })
        .catch((err) => {
          console.error(err);
          Swal.fire("Error", "No fue posible cancelar la visita.", "error");
        });
    });
  }

  window.cancelarVisita = cancelarVisita;

  function handleSubmitAsignar(e) {
    e.preventDefault();

    const tiId = $('#visitaAsig_tiId').val();
    const fecha = $('#visitaAsig_fecha').val();
    const hora = $('#visitaAsig_hora').val();

    const h = parseInt($('#visitaAsig_duracionHoras').val() || '0', 10);
    const m = parseInt($('#visitaAsig_duracionMinutos').val() || '0', 10);
    const duracionMin = (h * 60) + m;

    const requiereAcceso = $('#visitaAsig_reqAcceso').is(':checked') ? 1 : 0;
    const extraAcceso = $('#visitaAsig_extraAcceso').val() || '';

    if (!tiId || !fecha || !hora) {
      Swal.fire('Datos incompletos', 'Completa fecha y hora.', 'warning');
      return;
    }

    ajaxvisita({
      tiId,
      accion: 'asignar',
      quien: 'CLIENTE',
      fecha,
      hora,
      duracionMin,
      requiereAcceso,
      extraAcceso
    })
      .then(() => {
        $('#visitaModalAsignar').modal('hide');
        Swal.fire('Visita asignada', 'La ventana de visita ha sido guardada.', 'success');
        // recargar tickets o detalle si lo deseas
      })
      .catch(err => {
        console.error(err);
        Swal.fire('Error', err?.message || 'No se pudo guardar la visita.', 'error');
      });
  }


  // ---------- init global ----------
  M.initvisitaUI = function () {
    // instanciar modales
    const m1 = document.getElementById('visitaModalPropuesta');
    const m2 = document.getElementById('visitaModalAsignar');
    if (m1) visitaModalPropuesta = new bootstrap.Modal(m1);
    if (m2) visitaModalAsignar = new bootstrap.Modal(m2);

    setMinFechaInputs();

    // delegación sobre botones de acciones rápidas
    $(document).on('click', '[data-visita-action]', function (ev) {
      ev.preventDefault();
      const tiId = $(this).data('ticket-id');
      const modo = $(this).data('visita-action');

      if (!tiId) return;

      if (modo === 'proponer') openPropuesta(tiId);
      else if (modo === 'asignar') openAsignar(tiId);
    });

    // submits
    $('#visitaFormPropuesta').on('submit', handleSubmitPropuesta);
    $('#visitaFormAsignar').on('submit', handleSubmitAsignar);
  };

  // expone global
  window.MRSOS_visita = M;

  // auto-init cuando DOM está listo
  $(function () {
    if (window.MRSOS_visita) {
      window.MRSOS_visita.initvisitaUI();
    }
  });

})(window, window.jQuery);


