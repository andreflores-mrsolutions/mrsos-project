function mostrarToast(tipo, mensaje) {
    const toastId = tipo === 'success' ? '#toastSuccess' : '#toastError';
    const $toastElem = $(toastId);

    if ($toastElem.length === 0) {
        alert(mensaje); // fallback por si no están los toasts
        return;
    }

    $(`${toastId} .toast-body`).text(mensaje);
    const toast = new bootstrap.Toast($toastElem[0]);
    toast.show();
}
function ticketCodigo(t) {
    const pref = clientePrefix(state.meta.clNombre);
    return `${pref}-${Number(t.tiId)}`;
}
async function sendTicketNotification(action, ticket, extra = {}) {
    console.log('sendTicketNotification', { action, ticket, extra });
    if (!action) throw new Error('action requerido');
    if (!ticket) throw new Error('ticket inválido sendTicketNotification');

    const folio = ticketCodigo(findTicketById(ticket.tiId));

    const fd = new FormData();
    fd.append('action', action);
    fd.append('folio', folio);

    // contexto base (lo que tu NotificationService puede usar)
    fd.append('tiId', String(ticket.tiId));
    fd.append('proceso', String(extra.proceso ?? ticket.tiProceso ?? ''));
    fd.append('estado', String(extra.estado ?? ticket.tiEstatus ?? ''));
    fd.append('texto', String(extra.texto ?? ''));      // opcional (para body)
    fd.append('titulo', String(extra.titulo ?? ''));    // opcional (para title)

    // extras arbitrarios (motivo, etc.)
    Object.entries(extra).forEach(([k, v]) => {
        if (v === undefined || v === null) return;
        if (['proceso', 'estado', 'texto', 'titulo'].includes(k)) return;
        fd.append(k, String(v));
    });

    const res = await fetch(NOTIFY_URL, {
        method: 'POST',
        credentials: 'include',
        body: fd
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) {
        throw new Error(json.message || json.error || 'Error notify');
    }
    return json; // {success, sent, errors}
}

//Notificaciones específicas por proceso (para no tener que armar todo el extra cada vez)
async function sendTicketNotificationByProceso(action, ticket) {
    ticket = findTicketById(ticket);
    console.log('sendTicketNotification by proceso', { action, ticket });
    if (!action) throw new Error('action requerido by sendTicketNotificationByProceso');
    if (!ticket) throw new Error('ticket inválido by sendTicketNotificationByProceso');
    if (action === 'asignacion') {
        extra = {
            proceso: 'asignacion',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se esta asignando un ingeniero a tu caso. En breve recibirás una notificación con el nombre del ingeniero asignado y los siguientes pasos a seguir.',
            titulo: 'Asignación de ingeniero'
        };
    }
    if (action === 'revision inicial') {
        extra = {
            proceso: 'revision_inicial',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se esta realizando la revisión inicial de tu caso. Tranquilo, tu caso está siendo atendido y en breve recibirás una notificación con el diagnóstico y los siguientes pasos a seguir.',
            titulo: 'Revisión inicial completada'
        };
    }
    if (action === 'logs') {
        extra = {
            proceso: 'logs',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se requieren los logs para el diagnóstico de tu caso. Por favor, sube los logs necesarios para continuar con el proceso.',
            titulo: 'Solicitud de logs'
        };
    }

    if (action === 'logs solicitados') {
        extra = {
            proceso: 'logs_solicitados',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se han solicitado los logs para el diagnóstico de tu caso. Por favor, sube los logs necesarios para continuar con el proceso.',
            titulo: 'Solicitud de logs'
        };
    }
    if (action === 'meet solicitado') {
        extra = {
            proceso: 'meet_solicitado',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha solicitado una reunión para el diagnóstico de tu caso. Por favor, confirma la reunión para continuar con el proceso.',
            titulo: 'Solicitud de reunión'
        };
    }
    if (action === 'meet confirmado') {
        extra = {
            proceso: 'meet_confirmado',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha confirmado la reunión para el diagnóstico de tu caso. Por favor, asiste a la reunión para continuar con el proceso.',
            titulo: 'Reunión confirmada'
        };
    }
    if (action === 'revision especial') {
        extra = {
            proceso: 'revision_especial',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se han subido con éxito los logs necesarios para la revisión especial de tu caso. El ingeniero asignado está revisando la información proporcionada y en breve recibirás una notificación con el diagnóstico y los siguientes pasos a seguir.',
            titulo: 'Revisión especial completada'
        };
    }
    if (action === 'espera refaccion') {
        extra = {
            proceso: 'espera_refaccion',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se esta en espera de refacción para tu caso. Por favor, aguarda a que llegue la refacción para continuar con el proceso.',
            titulo: 'Espera de refacción'
        };
    }
    if (action === 'solicitud visita') {
        extra = {
            proceso: 'solicitud_visita',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha solicitado una visita técnica para tu caso. Por favor, ACEPTA/RECHAZA/ASIGNA una visita técnica para continuar con el proceso.',
            titulo: 'Solicitud de visita técnica'
        };
    }
    if (action === 'visita_solicitar_folios') {
        extra = {
            proceso: 'solicitud_visita',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', es necesariala signación de un folio de entrada/autorización para coordinar el acceso. Por favor súbelo desde el ticket.',
            titulo: 'Solicitud de visita técnica'
        };
    }

    if (action === 'visita_propuestas') {
        extra = {
            proceso: 'confirmacion visita',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + 'es necesario proponer 3 ventanas (fecha y hora). Una vez confirmada, no podrá cancelarse sin autorización.',
            titulo: 'Confirmación de visita técnica'
        };
    }
    if (action === 'visita_confirmada') {
        extra = {
            proceso: 'confirmacion visita',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha confirmado la visita técnica para tu caso. Es importante que estés presente en la fecha y hora programada para que el ingeniero pueda realizar el diagnóstico y resolver tu caso lo antes posible.',
            titulo: 'Confirmación de visita técnica'
        };
    }
    if (action === 'visita_en_camino') {
        extra = {
            proceso: 'confirmacion visita',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha confirmado la visita técnica para tu caso. Es importante que estés presente en la fecha y hora programada para que el ingeniero pueda realizar el diagnóstico y resolver tu caso lo antes posible.',
            titulo: 'Confirmación de visita técnica'
        };
    }
    if (action === 'fecha asignada') {
        extra = {
            proceso: 'fecha asignada',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha asignado una fecha para la visita técnica. Por favor, mantente disponible en la fecha y hora programada.',
            titulo: 'Fecha asignada'
        };
    }

    if (action === 'espera ventana') {
        extra = {
            proceso: 'espera ventana',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se esta en espera de que se confirme una ventana para la visita técnica de tu caso. Por favor, aguarda a que se confirme la ventana para continuar con el proceso.',
            titulo: 'Espera ventana'
        };
    }
    if (action === 'espera visita') {
        extra = {
            proceso: 'espera visita',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se esta en espera de que el ingeniero llegue a tu domicilio para realizar la visita técnica. Por favor, mantente disponible para recibir al ingeniero.',
            titulo: 'Espera de visita técnica'
        };
    }

    if (action === 'folio_cargado') {
        extra = {
            proceso: 'folio_cargado',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha cargado el folio de la visita técnica para tu caso. Es importante que estés presente en la fecha y hora programada para que el ingeniero pueda realizar el diagnóstico y resolver tu caso lo antes posible.',
            titulo: 'Folio cargado'
        };
    }
    if (action === 'en camino') {
        extra = {
            proceso: 'en_camino',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', el ingeniero se encuentra en camino para visitar tu caso. Por favor, mantente disponible para recibir al ingeniero.',
            titulo: 'Ingeniero en camino'
        };
    }
    if (action === 'espera documentacion') {
        extra = {
            proceso: 'espera documentacion',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se esta en espera de documentación para tu caso. Por favor, aguarda a que llegue la documentación para continuar con el proceso.',
            titulo: 'Espera de documentación'
        };
    }
    if (action === 'finalizado') {
        extra = {
            proceso: 'finalizado',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha finalizado el proceso de visita técnica. Gracias por utilizar nuestros servicios.',
            titulo: 'Ticket finalizado'
        };
    }
    if (action === 'encuesta satisfaccion') {
        extra = {
            proceso: 'encuesta_satisfaccion',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha generado una encuesta de satisfacción. Agradecemos tu elección de MRSolutions para el soporte de tu equipo. Para ayudarnos a mejorar nuestro servicio, te invitamos a completar una breve encuesta de satisfacción. Tu opinión es muy valiosa para nosotros y nos ayudará a brindarte un mejor servicio en el futuro.',
            titulo: 'Encuesta de satisfacción'
        };
    }

    if (action === 'cancelado') {
        extra = {
            proceso: 'cancelado',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', se ha cancelado el proceso de visita técnica. Gracias por utilizar nuestros servicios.',
            titulo: 'Ticket cancelado'
        };
    }
    if (action === 'fuera de alcance') {
        extra = {
            proceso: 'fuera_alcance',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', el ticket se encuentra fuera de alcance. Por favor, contacta con el soporte técnico para más información.',
            titulo: 'Ticket fuera de alcance'
        };
    }
    if (action === 'servicio por evento') {
        extra = {
            proceso: 'servicio_por_evento',
            texto: 'En tu ticket ' + ticketCodigo(ticket) + ', el ticket se encuentra marcado como servicio por evento. Por favor, contacta con el soporte técnico para más información.',
            titulo: 'Este ticket es para servicio por evento'
        };
    }


    await sendTicketNotification(action, ticket, extra);

}
