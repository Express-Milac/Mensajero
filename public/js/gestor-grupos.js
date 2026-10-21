/* Gestión de visibilidad por grupo, sin navegación ni recarga de página. */
(() => {
    'use strict';

    const controles = document.querySelectorAll('form[data-gestor-visibilidad]');

    function actualizarContador(nombre, diferencia) {
        if (diferencia === 0) return;
        const elemento = document.querySelector('[data-gestor-contador="' + nombre + '"]');
        if (!elemento) return;

        const anterior = Number((elemento.textContent || '').replace(/[^0-9]/g, ''));
        if (!Number.isFinite(anterior)) return;
        elemento.textContent = Math.max(0, anterior + diferencia).toLocaleString('es-MX');
    }

    for (const formulario of controles) {
        const interruptor = formulario.querySelector('[data-switch-visibilidad]');
        const etiqueta = formulario.querySelector('[data-etiqueta-visibilidad]');
        const estado = formulario.querySelector('[data-estado-guardado]');
        const fila = formulario.closest('[data-grupo-id]');
        if (!interruptor || !etiqueta || !estado || !fila) continue;

        let guardado = interruptor.checked;
        const grupoId = Number(fila.dataset.grupoId);

        function mostrar(valor) {
            interruptor.checked = valor;
            etiqueta.textContent = valor ? 'Visible' : 'Oculto';
            interruptor.setAttribute('aria-checked', String(valor));
        }

        formulario.addEventListener('submit', (evento) => evento.preventDefault());

        interruptor.addEventListener('change', async () => {
            const solicitado = interruptor.checked;
            if (solicitado === guardado || interruptor.disabled) return;

            mostrar(solicitado);
            interruptor.disabled = true;
            fila.classList.add('gestor-guardando');
            estado.textContent = 'Guardando…';
            estado.classList.remove('gestor-error');

            try {
                const respuesta = await fetch(formulario.action, {
                    method: 'POST', // @method('PATCH') de Laravel viaja en FormData.
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(formulario),
                });

                const datos = await respuesta.json().catch(() => null);
                if (!respuesta.ok || respuesta.redirected ||
                    !datos || datos.ok !== true ||
                    datos.grupo_id !== grupoId ||
                    typeof datos.es_visible_tablero !== 'boolean') {
                    throw new Error('No se pudo confirmar el cambio de visibilidad.');
                }

                const confirmado = datos.es_visible_tablero;
                const anterior = guardado;
                guardado = confirmado;
                mostrar(confirmado);

                if (anterior !== confirmado) {
                    const diferencia = confirmado ? 1 : -1;
                    actualizarContador('ocultos', -diferencia);
                    if (fila.dataset.capturaAutorizada === '1') {
                        actualizarContador('visibles', diferencia);
                    }
                }

                estado.textContent = 'Guardado';
                window.setTimeout(() => {
                    if (estado.textContent === 'Guardado') estado.textContent = '';
                }, 3000);
            } catch (error) {
                mostrar(guardado);
                estado.textContent = 'No se guardó. Intenta de nuevo.';
                estado.classList.add('gestor-error');
            } finally {
                interruptor.disabled = false;
                fila.classList.remove('gestor-guardando');
            }
        });
    }
})();
