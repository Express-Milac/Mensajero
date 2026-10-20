/* Panorama de solo lectura. Cada grupo conserva su carrusel independiente. */
(() => {
    'use strict';

    const pantalla = document.querySelector('[data-panorama-url]');
    const estado = document.getElementById('estado-actualizacion');
    const zonaGrupos = document.querySelector('[data-panorama-tarjetas]');

    if (!pantalla || !estado || !zonaGrupos) {
        return;
    }

    const intervalo = Number(pantalla.dataset.panoramaIntervalo) || 12000;
    const botonActualizar = document.querySelector('[data-actualizar-manual]');
    let consultando = false;

    // Los botones solo desplazan mensajes de su propio grupo.
    zonaGrupos.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-mover-grupo]');
        if (!boton || !zonaGrupos.contains(boton)) {
            return;
        }

        const carrusel = boton.closest('[data-grupo-id]')?.querySelector('[data-carrusel-mensajes]');
        if (!carrusel) {
            return;
        }

        const direccion = boton.dataset.moverGrupo === 'arriba' ? -1 : 1;
        const movimiento = Math.max(120, Math.round(carrusel.clientHeight * 0.75));
        const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        carrusel.scrollBy({
            top: movimiento * direccion,
            behavior: movimientoReducido ? 'auto' : 'smooth',
        });
    });

    function actualizarIndicadores(documento) {
        const actual = document.querySelector('[data-panorama-indicadores]');
        const nuevo = documento.querySelector('[data-panorama-indicadores]');

        if (!actual || !nuevo || actual.innerHTML === nuevo.innerHTML) {
            return false;
        }

        actual.innerHTML = nuevo.innerHTML;
        return true;
    }

    function restaurarPosicionCarrusel(anterior, nuevo, desplazamiento, ancla) {
        if (!nuevo || desplazamiento <= 8) {
            return;
        }

        nuevo.scrollTop = desplazamiento;

        if (!ancla) {
            return;
        }

        const mensaje = Array.from(nuevo.querySelectorAll('[data-mensaje-id]'))
            .find((elemento) => elemento.dataset.mensajeId === ancla.id);

        if (mensaje) {
            nuevo.scrollTop += mensaje.getBoundingClientRect().top -
                nuevo.getBoundingClientRect().top - ancla.distancia;
        }
    }

    function actualizarTarjetas(documento) {
        const recibido = documento.querySelector('[data-panorama-tarjetas]');
        if (!recibido || zonaGrupos.innerHTML === recibido.innerHTML) {
            return false;
        }

        const lista = zonaGrupos.querySelector('.lista-grupos-directivos');
        const listaNueva = recibido.querySelector('.lista-grupos-directivos');

        if (!lista || !listaNueva) {
            return false;
        }

        let cambio = false;
        const cabecera = zonaGrupos.querySelector('.cabecera-carrusel');
        const cabeceraNueva = recibido.querySelector('.cabecera-carrusel');

        if (cabecera && cabeceraNueva && cabecera.innerHTML !== cabeceraNueva.innerHTML) {
            cabecera.innerHTML = cabeceraNueva.innerHTML;
            cambio = true;
        }

        const tarjetasNuevas = Array.from(listaNueva.querySelectorAll(':scope > [data-grupo-id]'));

        if (tarjetasNuevas.length === 0) {
            if (lista.innerHTML !== listaNueva.innerHTML) {
                lista.innerHTML = listaNueva.innerHTML;
                cambio = true;
            }
            return cambio;
        }

        // Conservar el grupo visible en la pantalla cuando cambie el orden.
        const grupoVisible = Array.from(lista.querySelectorAll(':scope > [data-grupo-id]'))
            .find((tarjeta) => {
                const rectangulo = tarjeta.getBoundingClientRect();
                return rectangulo.bottom > 125 && rectangulo.top < window.innerHeight - 60;
            });
        const identificadorVisible = grupoVisible?.dataset.grupoId;
        const distanciaVisible = grupoVisible?.getBoundingClientRect().top;

        const identificadoresNuevos = new Set(tarjetasNuevas.map((tarjeta) => tarjeta.dataset.grupoId));

        for (let indice = 0; indice < tarjetasNuevas.length; indice++) {
            const tarjetaRecibida = tarjetasNuevas[indice];
            const identificador = tarjetaRecibida.dataset.grupoId;
            let tarjetaActual = Array.from(lista.children)
                .find((tarjeta) => tarjeta.dataset.grupoId === identificador);

            if (!tarjetaActual) {
                tarjetaActual = tarjetaRecibida.cloneNode(true);
                cambio = true;
            } else if (tarjetaActual.outerHTML !== tarjetaRecibida.outerHTML) {
                const anterior = tarjetaActual.querySelector('[data-carrusel-mensajes]');
                const desplazamiento = anterior?.scrollTop ?? 0;
                const caja = anterior?.getBoundingClientRect();
                const anclaVisible = desplazamiento > 8
                    ? Array.from(anterior.querySelectorAll('[data-mensaje-id]'))
                        .find((mensaje) => mensaje.getBoundingClientRect().bottom > caja.top + 5)
                    : null;
                const ancla = anclaVisible
                    ? { id: anclaVisible.dataset.mensajeId, distancia: anclaVisible.getBoundingClientRect().top - caja.top }
                    : null;

                const foco = tarjetaActual.contains(document.activeElement) ? document.activeElement : null;
                const botonActivo = foco?.getAttribute('data-mover-grupo');
                const focoCarrusel = foco === anterior;
                const focoEnlace = foco?.closest('a[href]')?.getAttribute('href');

                const reemplazo = tarjetaRecibida.cloneNode(true);
                tarjetaActual.replaceWith(reemplazo);
                tarjetaActual = reemplazo;
                const nuevoCarrusel = reemplazo.querySelector('[data-carrusel-mensajes]');

                restaurarPosicionCarrusel(anterior, nuevoCarrusel, desplazamiento, ancla);

                if (focoCarrusel) {
                    nuevoCarrusel?.focus({ preventScroll: true });
                } else if (botonActivo) {
                    reemplazo.querySelector('[data-mover-grupo="' + botonActivo + '"]')
                        ?.focus({ preventScroll: true });
                } else if (focoEnlace) {
                    Array.from(reemplazo.querySelectorAll('a[href]'))
                        .find((enlace) => enlace.getAttribute('href') === focoEnlace)
                        ?.focus({ preventScroll: true });
                }

                cambio = true;
            }

            if (lista.children[indice] !== tarjetaActual) {
                lista.insertBefore(tarjetaActual, lista.children[indice] ?? null);
                cambio = true;
            }
        }

        for (const tarjeta of Array.from(lista.children)) {
            if (!identificadoresNuevos.has(tarjeta.dataset.grupoId)) {
                tarjeta.remove();
                cambio = true;
            }
        }

        // La página se desplaza naturalmente; no existe scroll de grupos.
        if (cambio && identificadorVisible) {
            const destino = Array.from(lista.querySelectorAll(':scope > [data-grupo-id]'))
                .find((tarjeta) => tarjeta.dataset.grupoId === identificadorVisible);

            if (destino) {
                window.scrollBy(0, destino.getBoundingClientRect().top - distanciaVisible);
            }
        }

        return cambio;
    }

    async function comprobar() {
        if (consultando || document.visibilityState !== 'visible') {
            return;
        }

        consultando = true;
        if (botonActualizar) {
            botonActualizar.disabled = true;
        }
        estado.textContent = 'Consultando nuevos mensajes...';

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

            const indicadores = actualizarIndicadores(documento);
            const tarjetas = actualizarTarjetas(documento);

            const horaConsulta = new Intl.DateTimeFormat('es-MX', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            }).format(new Date());

            estado.textContent = (indicadores || tarjetas ? 'Mensajes actualizados' : 'Sin cambios') +
                ' · Última consulta ' + horaConsulta;
            estado.classList.remove('actualizacion-fallida');
        } catch {
            estado.textContent = 'No se pudo actualizar la información. Reintentando automáticamente.';
            estado.classList.add('actualizacion-fallida');
        } finally {
            consultando = false;
            if (botonActualizar) {
                botonActualizar.disabled = false;
            }
        }
    }

    botonActualizar?.addEventListener('click', comprobar);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            void comprobar();
        }
    });
    window.setInterval(comprobar, intervalo);
    void comprobar();
})();
