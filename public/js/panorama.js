/* Actualización incremental de solo lectura. No recarga la página ni publica mensajes. */
(() => {
    'use strict';

    const pantalla = document.querySelector('[data-panorama-url]');
    const estado = document.getElementById('estado-actualizacion');

    if (!pantalla || !estado) {
        return;
    }

    const intervalo = Number(pantalla.dataset.panoramaIntervalo) || 12000;
    let consultando = false;

    function actualizarSeccion(selector, documento) {
        const actual = document.querySelector(selector);
        const recibido = documento.querySelector(selector);

        if (!actual || !recibido || actual.innerHTML === recibido.innerHTML) {
            return false;
        }

        const carrusel = actual.querySelector('.carrusel-grupos-vertical');
        const posicion = carrusel?.scrollTop ?? 0;
        const foco = actual.contains(document.activeElement) ? document.activeElement : null;
        const enlaceActivo = foco?.closest('a[href]');
        const grupoActivo = foco?.closest('[data-grupo-id]')?.dataset.grupoId;
        const rutaActiva = enlaceActivo?.getAttribute('href');

        actual.innerHTML = recibido.innerHTML;

        const nuevoCarrusel = actual.querySelector('.carrusel-grupos-vertical');
        if (nuevoCarrusel) {
            nuevoCarrusel.scrollTop = posicion;

            // Si el usuario navega con teclado, mantenerlo en el mismo
            // grupo aunque el orden cambie con los mensajes entrantes.
            if (foco === carrusel) {
                nuevoCarrusel.focus({ preventScroll: true });
            } else if (grupoActivo && rutaActiva) {
                const tarjeta = Array.from(nuevoCarrusel.querySelectorAll('[data-grupo-id]'))
                    .find((elemento) => elemento.dataset.grupoId === grupoActivo);
                const destino = Array.from(tarjeta?.querySelectorAll('a[href]') ?? [])
                    .find((enlace) => enlace.getAttribute('href') === rutaActiva);
                destino?.focus({ preventScroll: true });
            }
        }

        return true;
    }

    async function comprobar() {
        if (consultando || document.visibilityState !== 'visible') {
            return;
        }

        consultando = true;

        try {
            const url = new URL(pantalla.dataset.panoramaUrl, window.location.origin);
            const filtros = new URLSearchParams(window.location.search);

            for (const nombre of ['actividad', 'orden']) {
                if (filtros.has(nombre)) {
                    url.searchParams.set(nombre, filtros.get(nombre));
                }
            }

            const respuesta = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!respuesta.ok || respuesta.redirected) {
                throw new Error('La consulta no está disponible.');
            }

            const documento = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
            if (!documento.querySelector('[data-panorama-indicadores]') ||
                !documento.querySelector('[data-panorama-tarjetas]')) {
                throw new Error('Respuesta de actualización no válida.');
            }

            const indicadores = actualizarSeccion('[data-panorama-indicadores]', documento);
            const tarjetas = actualizarSeccion('[data-panorama-tarjetas]', documento);

            if (indicadores || tarjetas) {
                estado.textContent = 'Nuevos mensajes disponibles · Actualizado ' +
                    new Intl.DateTimeFormat('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date());
                estado.classList.remove('actualizacion-fallida');
            } else if (estado.classList.contains('actualizacion-fallida')) {
                estado.textContent = 'Actualización automática activa · Cada 12 segundos';
                estado.classList.remove('actualizacion-fallida');
            }
        } catch {
            estado.textContent = 'No se pudo actualizar la información. Reintentando automáticamente.';
            estado.classList.add('actualizacion-fallida');
        } finally {
            consultando = false;
        }
    }

    window.setInterval(comprobar, intervalo);
})();
