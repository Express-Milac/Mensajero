'use strict';

/**
 * Controles de Gemini en la misma pantalla, sin navegar ni recargar.
 * La base SQL Server sigue siendo la fuente de verdad: nunca se muestra
 * "Activo" antes de que responda correctamente el servidor.
 */
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.querySelector('[data-gemini-panel]');
    if (!panel) return;

    const formularios = Array.from(panel.querySelectorAll('[data-gemini-form]'));
    const aviso = panel.querySelector('[data-gemini-aviso]');
    const listo = panel.dataset.geminiListo === '1';
    let guardando = false;

    const mostrarAviso = (mensaje, error = false) => {
        if (!aviso) return;
        aviso.textContent = mensaje;
        aviso.dataset.tipo = error ? 'error' : 'correcto';
        aviso.setAttribute('role', error ? 'alert' : 'status');
    };

    const controles = (ocupado) => {
        for (const formulario of formularios) {
            const encendido = formulario.dataset.geminiHabilitado === '1';
            const boton = formulario.querySelector('[data-gemini-boton]');
            if (!boton) continue;
            boton.disabled = ocupado || (!listo && !encendido);
            boton.setAttribute('aria-busy', ocupado ? 'true' : 'false');
            if (ocupado) boton.textContent = 'Guardando...';
        }
    };

    const actualizar = (datos) => {
        if (typeof datos.general !== 'boolean' || !Array.isArray(datos.grupos_habilitados)) {
            throw new Error('El servidor devolvió una confirmación incompleta.');
        }

        const grupos = new Set(datos.grupos_habilitados.map(String));
        for (const formulario of formularios) {
            const id = formulario.dataset.geminiId;
            const encendido = id === '0' ? datos.general : grupos.has(id);
            const campo = formulario.querySelector('input[name="habilitado"]');
            const etiqueta = formulario.querySelector('[data-gemini-consentimiento]');
            const confirmacion = formulario.querySelector('input[name="confirmo_envio"]');
            const boton = formulario.querySelector('[data-gemini-boton]');
            const estado = panel.querySelector('[data-gemini-estado="' + id + '"]');

            formulario.dataset.geminiHabilitado = encendido ? '1' : '0';
            if (campo) campo.value = encendido ? '0' : '1';
            if (confirmacion) {
                confirmacion.checked = false;
                confirmacion.required = !encendido;
            }
            if (etiqueta) etiqueta.hidden = encendido;
            if (boton) boton.textContent = id === '0'
                ? (encendido ? 'Apagar Gemini' : 'Encender Gemini')
                : (encendido ? 'Desactivar' : 'Activar');
            if (estado) {
                estado.textContent = id === '0'
                    ? (encendido ? 'Encendido' : 'Apagado')
                    : (encendido ? (datos.general ? 'Activo' : 'Pausado') : 'Apagado');
            }
        }
    };

    // Los botones están deshabilitados en HTML si JavaScript no está listo.
    // Así no existe un formulario que recargue la página accidentalmente.
    controles(false);

    for (const formulario of formularios) {
        formulario.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            if (guardando) return;

            const activar = formulario.querySelector('input[name="habilitado"]')?.value === '1';
            const consentimiento = formulario.querySelector('input[name="confirmo_envio"]');
            if (activar && consentimiento && !consentimiento.checked) {
                mostrarAviso('Confirma el procesamiento de mensajes nuevos para activar Gemini.', true);
                consentimiento.focus();
                return;
            }

            guardando = true;
            controles(true);
            mostrarAviso('Guardando el cambio en Mensajero...');

            try {
                // POST URL-encoded + _method=PATCH es compatible con Laravel,
                // incluido Windows/PHP, sin recargar ni cambiar de página.
                const respuesta = await fetch(formulario.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    redirect: 'error',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    body: new URLSearchParams(new FormData(formulario)).toString(),
                });

                const tipo = respuesta.headers.get('content-type') || '';
                if (!tipo.includes('application/json')) {
                    throw new Error('El servidor no confirmó el cambio. El estado no se modificó en pantalla.');
                }

                const datos = await respuesta.json();
                if (!respuesta.ok) {
                    const errores = datos.errors ? Object.values(datos.errors).flat().join(' ') : '';
                    throw new Error(datos.mensaje || datos.message || errores || 'No se pudo guardar el cambio.');
                }

                actualizar(datos);
                mostrarAviso(datos.mensaje || 'Cambio guardado correctamente.');
            } catch (error) {
                mostrarAviso(error instanceof Error ? error.message : 'No se pudo confirmar el cambio.', true);
            } finally {
                guardando = false;
                controles(false);
                // No usar location.reload(), location.assign() o reemplazo
                // de página. Se conserva foco, filtros y posición.
            }
        });
    }
});
