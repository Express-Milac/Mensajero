<x-layout title="Administración de WhatsApp">
    <section class="catalogo-encabezado">
        <div>
            <span class="centro-hero-marca">MENSAJERO MILAC / ADMINISTRACIÓN</span>
            <h1>Conexiones de WhatsApp</h1>
            <p>Registra las líneas empresariales de manera independiente. Cada una tendrá sus grupos autorizados y una sesión propia.</p>
            <p><strong>Etapa inicial:</strong> agrega una línea, vincúlala mediante QR y después autoriza sus grupos desde el gestor de grupos.</p>
        </div>
        <a class="catalogo-volver" href="{{ route('grupos.indice') }}">Ver grupos</a>
    </section>

    @if(session('estado'))
        <div class="notice success" role="status">{{ session('estado') }}</div>
    @endif
    @if($errors->any())
        <div class="notice error-box" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="catalogo-seccion gestor-seccion" aria-labelledby="titulo-agregar">
        <div class="catalogo-titulo">
            <div>
                <h2 id="titulo-agregar">Agregar una línea</h2>
                <p>El registro no vincula WhatsApp ni habilita la captura. Después, inicia la vinculación.</p>
            </div>
        </div>
        <form method="post" action="{{ route('whatsapp.conexiones.registrar') }}" class="catalogo-filtros">
            @csrf
            <div class="catalogo-buscar">
                <label for="nombre_visible">Nombre de la conexión</label>
                <input id="nombre_visible" name="nombre_visible" type="text" maxlength="120" minlength="3"
                       value="{{ old('nombre_visible') }}" autocomplete="off" required
                       placeholder="Por ejemplo: Operaciones Milac">
            </div>
            <button type="submit" class="catalogo-aplicar">Registrar línea</button>
        </form>
    </section>

    <section class="catalogo-seccion gestor-seccion" aria-labelledby="titulo-conexiones">
        <div class="catalogo-titulo">
            <div>
                <h2 id="titulo-conexiones">Líneas registradas</h2>
                <p>{{ $conexiones->total() }} conexión(es) del recolector.</p>
            </div>
            <span class="catalogo-seguridad">Acceso administrativo</span>
        </div>

        @forelse($conexiones as $conexion)
            <article class="gestor-fila">
                <div class="gestor-identidad">
                    <div class="gestor-identidad-texto">
                        <h3>{{ $conexion->nombre_visible ?: 'Línea '.$conexion->id }}</h3>
                        <span>Identificador interno: {{ $conexion->id }}</span>
                    </div>
                </div>
                <div class="gestor-columna"><strong>Estado: {{ str_replace('_', ' ', $conexion->estado) }}</strong></div>
                <div class="gestor-columna">
                    <span>Grupos detectados: {{ $conexion->grupos_detectados }}</span>
                    <span>Grupos autorizados: {{ $conexion->grupos_autorizados }}</span>
                </div>
                <div class="gestor-columna" data-conexion-panel="{{ $conexion->id }}"
                     data-url-estado="{{ route('whatsapp.gestion.estado', $conexion->id) }}">
                    @if(($conexion->metadatos['vinculacion_habilitada'] ?? false) === true)
                        <form method="post" action="{{ route('whatsapp.gestion.detener', $conexion->id) }}">
                            @csrf
                            <button class="catalogo-aplicar" type="submit">Pausar línea</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('whatsapp.gestion.iniciar', $conexion->id) }}">
                            @csrf
                            <button class="catalogo-aplicar" type="submit">Vincular / activar</button>
                        </form>
                    @endif
                    <p data-estado-conexion role="status" aria-live="polite">Consultando el gestor…</p>
                    <img data-qr-conexion alt="QR temporal para vincular esta línea de WhatsApp"
                         width="260" height="260" hidden style="display:none; max-width:100%; height:auto;">
                    <small>Cuando aparezca el QR, escanéalo desde WhatsApp → Dispositivos vinculados.</small>
                </div>
                <details class="gestor-columna" style="width:100%;max-width:100%;">
                    <summary style="cursor:pointer;font-weight:600;">Editar o eliminar conexión</summary>
                    <form method="post" action="{{ route('whatsapp.edicion.actualizar', $conexion->id) }}"
                          style="display:flex;flex-wrap:wrap;gap:.6rem;margin:1rem 0;align-items:end;">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label for="editar-nombre-{{ $conexion->id }}">Nombre de esta línea</label>
                            <input id="editar-nombre-{{ $conexion->id }}" name="nombre_visible" type="text"
                                   minlength="3" maxlength="120" required autocomplete="off"
                                   value="{{ $conexion->nombre_visible }}">
                        </div>
                        <button type="submit" class="catalogo-aplicar">Guardar nombre</button>
                    </form>
                    @php
                        $detalles = is_array($conexion->metadatos) ? $conexion->metadatos : [];
                        $sePuedeEliminar = $conexion->estado === 'sin_configurar'
                            && ($detalles['vinculacion_habilitada'] ?? false) !== true
                            && empty($conexion->id_cuenta_externa)
                            && $conexion->conectado_en === null
                            && $conexion->ultimo_latido_en === null
                            && $conexion->ultima_desconexion_en === null
                            && (int) $conexion->grupos_detectados === 0;
                    @endphp
                    @if($sePuedeEliminar)
                        <form method="post" action="{{ route('whatsapp.edicion.eliminar', $conexion->id) }}"
                              style="border-top:1px solid #b5b5b5;padding-top:1rem;margin-top:1rem;">
                            @csrf
                            @method('DELETE')
                            <label for="confirmar-eliminar-{{ $conexion->id }}">
                                Para eliminar definitivamente esta línea, escribe
                                <strong>ELIMINAR {{ $conexion->id }}</strong>.
                            </label>
                            <input id="confirmar-eliminar-{{ $conexion->id }}" name="confirmacion"
                                   type="text" maxlength="32" required autocomplete="off"
                                   aria-label="Confirmación de eliminación de la línea {{ $conexion->id }}">
                            <button type="submit" class="catalogo-aplicar"
                                    style="background:#8b1e1e;color:#fff;margin:.5rem 0;">
                                Eliminar línea {{ $conexion->id }}
                            </button>
                            <small>Solo se eliminan registros que nunca se vincularon y no contienen chats.</small>
                        </form>
                    @else
                        <p>Eliminación bloqueada: esta línea ya se utilizó o tiene información asociada.
                           Puedes cambiar su nombre o pausarla sin perder datos.</p>
                    @endif
                </details>
            </article>
        @empty
            <div class="catalogo-vacio">
                <h3>Sin líneas registradas</h3>
                <p>Al agregar una línea no se iniciará el recolector hasta vincular WhatsApp y autorizar grupos.</p>
            </div>
        @endforelse

        @if($conexiones->hasPages())
            <nav class="catalogo-paginacion" aria-label="Paginación de conexiones">
                {{ $conexiones->links() }}
            </nav>
        @endif
    </section>
    <script>
    (() => {
        'use strict';
        const paneles = [...document.querySelectorAll('[data-conexion-panel]')];
        if (!paneles.length) return;
        const consultar = async (panel) => {
            if (document.hidden) return;
            const etiqueta = panel.querySelector('[data-estado-conexion]');
            const imagen = panel.querySelector('[data-qr-conexion]');
            try {
                const respuesta = await fetch(panel.dataset.urlEstado, {
                    credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store'
                });
                if (!respuesta.ok) throw new Error('Gestor no disponible');
                const datos = await respuesta.json();
                const estados = {
                    sin_iniciar: 'Sin iniciar', conectando: 'Conectando…',
                    requiere_vinculacion: 'Esperando vinculación', conectado: 'Conectado',
                    desconectado: 'Desconectado', error: 'Error de conexión'
                };
                etiqueta.textContent = estados[datos.estado] || 'Estado desconocido';
                if (datos.qr_vigente && typeof datos.qr_imagen === 'string'
                    && datos.qr_imagen.startsWith('data:image/png;base64,')) {
                    imagen.src = datos.qr_imagen;
                    imagen.hidden = false;
                    imagen.style.display = 'block';
                } else {
                    imagen.removeAttribute('src');
                    imagen.hidden = true;
                    imagen.style.display = 'none';
                }
            } catch {
                etiqueta.textContent = 'Gestor de conexiones no disponible';
                imagen.removeAttribute('src');
                imagen.hidden = true;
                imagen.style.display = 'none';
            }
        };
        paneles.forEach(consultar);
        setInterval(() => paneles.forEach(consultar), 8000);
    })();
    </script>
</x-layout>