// modal_meet.js
(function (window, $) {
  'use strict';

  if (!$) {
    console.error('MRSOS_MEET: jQuery es requerido.');
    return;
  }

  const M = {};
  let meetModalPropuesta, meetModalAsignar;

  // ---------- helpers ----------
  function hoyISO() {
    const d = new Date();
    return d.toISOString().slice(0, 10); // YYYY-MM-DD
  }

  function setMinFechaInputs() {
    const min = hoyISO();
    $('#meetProp_fecha, #meetAsig_fecha').attr('min', min);
  }

  function ajaxMeet(payload) {
    return $.ajax({
      url: '../php/meet_actualizar.php',
      method: 'POST',
      dataType: 'json',
      data: payload
    });
  }

  // ---------- abrir modales ----------
  function openPropuesta(tiId) {
    $('#meetProp_tiId').val(tiId);
    $('#meetFormPropuesta')[0].reset();
    setMinFechaInputs();
    meetModalPropuesta.show();
  }

  function openAsignar(tiId) {
    $('#meetAsig_tiId').val(tiId);
    $('#meetFormAsignar')[0].reset();
    setMinFechaInputs();
    meetModalAsignar.show();
  }

  function cancelarMeet(tiId) {
    if (!tiId) return;

    Swal.fire({
      icon: "warning",
      title: "Cancelar meet",
      text: "¿Seguro que quieres cancelar este meet?",
      input: "textarea",
      inputPlaceholder: "Motivo de cancelación (opcional)",
      inputAttributes: { "aria-label": "Motivo de cancelación" },
      showCancelButton: true,
      confirmButtonText: "Sí, cancelar",
      cancelButtonText: "No",
    }).then((result) => {
      if (!result.isConfirmed) return;

      const formData = new URLSearchParams();
      formData.set("accion", "cancelar");
      formData.set("tiId", String(tiId));
      formData.set("motivo", result.value || "");

      fetch("../php/meet_actualizar.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: formData.toString(),
      })
        .then((r) => r.json())
        .then((res) => {
          if (!res?.success) throw new Error(res?.error || "Error");

          Swal.fire({
            icon: "success",
            title: "Meet cancelado",
            text: "El meet ha sido cancelado correctamente.",
            timer: 2000,
            showConfirmButton: false,
          });

          // cerrar modales si hubiera alguno abierto
          const m1 = document.getElementById("meetModalPropuesta");
          const m2 = document.getElementById("meetModalAsignar");
          [m1, m2].forEach((m) => {
            if (m) {
              const inst = bootstrap.Modal.getInstance(m);
              inst && inst.hide();
            }
          });

          // refrescar lista de tickets / detalle
          cargarTicketsPorSede?.();
          // si el offcanvas sigue abierto, vuelves a pedir el detalle:
          // abrirDetalle(tiId);  // opcional
        })
        .catch((err) => {
          console.error(err);
          Swal.fire("Error", "No fue posible cancelar el meet.", "error");
        });
    });
  }

  // lo exponemos global
  window.cancelarMeet = cancelarMeet;

  // ---------- handlers de submit ----------
  function handleSubmitPropuesta(e) {
    e.preventDefault();

    const tiId = $('#meetProp_tiId').val();
    const fecha = $('#meetProp_fecha').val();
    const hora = $('#meetProp_hora').val();
    const plataforma = $('#meetProp_plataforma').val();
    const link = $('#meetProp_link').val().trim();
    const envio = $('input[name="meetProp_envio"]:checked').val() || 'link';

    if (!tiId || !fecha || !hora || !plataforma) {
      Swal.fire('Datos incompletos', 'Completa fecha, hora y plataforma.', 'warning');
      return;
    }

    ajaxMeet({
      tiId,
      accion: 'proponer',      // <- contrato simple
      quien: 'CLIENTE',        // quien propone
      fecha,
      hora,
      plataforma,
      link,
      envio
    })
      .done(res => {
        if (!res || !res.success) {
          Swal.fire('Error', res?.error || 'No se pudo guardar la propuesta.', 'error');
          return;
        }
        meetModalPropuesta.hide();
        Swal.fire('Listo', 'Tu propuesta de Meet fue enviada.', 'success');
        cargarTicketsPorSede(); // refresca lista
      })
      .fail(() => {
        Swal.fire('Error', 'No se pudo contactar el servidor.', 'error');
      });
  }
  function hayMeetActivo(t) {
    return !!(t.tiMeetFecha && t.tiMeetHora && t.tiMeetEstado !== 'cancelado');
  }


  function handleSubmitAsignar(e) {
    e.preventDefault();

    const tiId = $('#meetAsig_tiId').val();
    const fecha = $('#meetAsig_fecha').val();
    const hora = $('#meetAsig_hora').val();
    const plataforma = $('#meetAsig_plataforma').val();
    const link = $('#meetAsig_link').val().trim();

    if (!tiId || !fecha || !hora || !plataforma) {
      Swal.fire('Datos incompletos', 'Completa fecha, hora y plataforma.', 'warning');
      return;
    }

    ajaxMeet({
      tiId,
      accion: 'asignar',
      quien: 'CLIENTE',
      fecha,
      hora,
      plataforma,
      link
    })
      .done(res => {
        if (!res || !res.success) {
          Swal.fire('Error', res?.error || 'No se pudo guardar el meet.', 'error');
          return;
        }
        meetModalAsignar.hide();
        Swal.fire('Listo', 'El meet quedó asignado.', 'success');
        cargarTicketsPorSede();
      })
      .fail(() => {
        Swal.fire('Error', 'No se pudo contactar el servidor.', 'error');
      });
  }

  // ---------- init global ----------
  M.initMeetUI = function () {
    // instanciar modales
    const m1 = document.getElementById('meetModalPropuesta');
    const m2 = document.getElementById('meetModalAsignar');
    if (m1) meetModalPropuesta = new bootstrap.Modal(m1);
    if (m2) meetModalAsignar = new bootstrap.Modal(m2);

    setMinFechaInputs();

    // delegación sobre botones de acciones rápidas
    $(document).on('click', '[data-meet-action]', function (ev) {
      ev.preventDefault();
      const tiId = $(this).data('ticket-id');
      const modo = $(this).data('meet-action');

      if (!tiId) return;

      if (modo === 'proponer') openPropuesta(tiId);
      else if (modo === 'asignar') openAsignar(tiId);
    });

    // submits
    $('#meetFormPropuesta').on('submit', handleSubmitPropuesta);
    $('#meetFormAsignar').on('submit', handleSubmitAsignar);
  };

  // expone global
  window.MRSOS_MEET = M;

  // auto-init cuando DOM está listo
  $(function () {
    if (window.MRSOS_MEET) {
      window.MRSOS_MEET.initMeetUI();
    }
  });

})(window, window.jQuery);
