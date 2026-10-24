<x-layout title="Gestor de grupos">
    <section class="catalogo-encabezado">
        <div>
            <span class="centro-hero-marca">MENSAJERO MILAC / GRUPOS</span>
            <h1>Gestor de grupos</h1>
            <p>La captura y la interpretación de Gemini son controles independientes. Los envíos a Google se administran desde el Control de Gemini.</p>
            <p>Un solo interruptor controla la captura de mensajes y la aparición del grupo en el tablero. Al deshabilitarlo, los mensajes ya registrados se conservan.</p>
        </div>
        <a class="catalogo-volver" href="{{ route('mensajes.indice') }}">Explorar conversaciones <span aria-hidden="true">→</span></a>
    </section>

    @if(session('estado'))
        <div class="notice success" role="status">{{ session('estado') }}</div>
    @endif
    @if($errors->any())
        <div class="notice error-box" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="catalogo-metricas gestor-metricas" aria-label="Resumen de grupos">
        <article><span class="catalogo-metrica-icono">♧</span><div><span>Grupos detectados</span><strong>{{ number_format($estadisticas['descubiertos']) }}</strong><small>Registrados en Mensajero</small></div></article>
        <article><span class="catalogo-metrica-icono naranja">✓</span><div><span>Habilitados</span><strong data-gestor-contador="habilitados">{{ number_format($estadisticas['autorizados']) }}</strong><small>Capturando y visibles</small></div></article>
        <article><span class="catalogo-metrica-icono gris">○</span><div><span>Deshabilitados</span><strong data-gestor-contador="deshabilitados">{{ number_format($estadisticas['no_habilitados']) }}</strong><small>Captura desactivada y ocultos</small></div></article>
    </section>

    <section class="catalogo-seccion gestor-seccion" aria-label="Listado y administración de grupos">
        <div class="catalogo-titulo">
            <div><h2>Habilitar grupos</h2><p>{{ number_format($grupos->total()) }} {{ $grupos->total() === 1 ? 'grupo coincide' : 'grupos coinciden' }} con los filtros seleccionados. Los cambios se guardan individualmente.</p></div>
            <span class="catalogo-seguridad"><span aria-hidden="true">●</span> Acceso controlado</span>
        </div>
        <form method="get" class="catalogo-filtros" action="{{ route('grupos.indice') }}">
            <div class="catalogo-buscar">
                <label for="buscar">Buscar grupo</label>
                <input id="buscar" type="search" name="buscar" value="{{ $buscar }}" placeholder="Nombre o identificador del grupo" autocomplete="off">
            </div>
            <div>
                <label for="estado">Estado del grupo</label>
                <select id="estado" name="estado">
                    <option value="todos" @selected($estado === 'todos')>Todos</option>
                    <option value="monitoreados" @selected($estado === 'monitoreados')>Monitoreados</option>
                    <option value="no_monitoreados" @selected($estado === 'no_monitoreados')>Sin monitorear</option>
                    <option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option>
                </select>
            </div>
            <div>
                <label for="origen">Origen</label>
                <select id="origen" name="origen">
                    <option value="todos" @selected($origen === 'todos')>Todas las conexiones</option>
                    <option value="recolector" @selected($origen === 'recolector')>WhatsApp del recolector</option>
                    <option value="otras" @selected($origen === 'otras')>Otras conexiones</option>
                </select>
            </div>
            <button class="catalogo-aplicar" type="submit">Aplicar filtros</button>
            @if($buscar !== '' || $origen !== 'todos' || $estado !== 'todos')
                <a class="catalogo-limpiar" href="{{ route('grupos.indice') }}">Restablecer</a>
            @endif
        </form>

        @if(config('mensajero.autenticacion.modo') === 'deshabilitado' && ! $puedeCambiarCaptura)
            <div class="catalogo-aviso gestor-aviso" role="note"><strong>Gestión protegida:</strong> solo puedes consultar grupos. Activar o desactivar grupos requiere un administrador o autorización expresa en desarrollo local.</div>
        @endif

        <p class="gestor-aclaracion" role="note">
            <strong>Un solo control:</strong> encendido habilita captura y tablero; apagado deshabilita ambos. Los mensajes históricos no se eliminan.
        </p>

        <div class="gestor-lista" aria-label="Grupos de WhatsApp registrados">
            <div class="gestor-cabecera-lista" aria-hidden="true">
                <span>Grupo</span>
                <span>Última detección</span>
                <span>Captura y tablero</span>
                <span>Acciones</span>
            </div>
            @forelse($grupos as $grupo)
                @php($autorizado = (bool) ($grupo->es_activo && $grupo->es_monitoreado && $grupo->es_visible_tablero))
                @php($tituloGrupo = $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre'))
                <article class="gestor-fila" data-grupo-id="{{ $grupo->id }}" data-captura-autorizada="{{ $autorizado ? '1' : '0' }}">
                    <div class="gestor-identidad">
                        <span class="gestor-avatar" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="3"/><path d="M2 20v-2a6 6 0 0 1 12 0v2H2zm13-6a5 5 0 0 1 6 5v1"/></svg>
                        </span>
                        <div class="gestor-identidad-texto">
                            <h3 title="{{ $tituloGrupo }}">{{ $tituloGrupo }}</h3>
                            <span>{{ $grupo->conexion?->proveedor === 'recolector_whatsapp' ? 'WhatsApp del recolector' : 'Otra conexión' }}@if(! $grupo->es_activo) · Inactivo @endif</span>
                        </div>
                    </div>

                    <div class="gestor-columna gestor-fecha">
                        <span class="gestor-etiqueta-movil">Última detección</span>
                        <time>{{ $grupo->visto_ultima_vez_en?->timezone(config('app.timezone'))->format('d/m/Y · H:i') ?? 'Sin fecha registrada' }}</time>
                    </div>

                    <div class="gestor-columna gestor-columna-switch">
                        <span class="gestor-etiqueta-movil">Captura y tablero</span>
                        @if($puedeCambiarCaptura)
                            <form class="gestor-form-visibilidad" method="post" action="{{ route('grupos.monitoreo.actualizar', $grupo->id) }}" data-gestor-visibilidad>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="es_monitoreado" value="0">
                                <label class="gestor-interruptor" for="habilitar-grupo-{{ $grupo->id }}">
                                    <input id="habilitar-grupo-{{ $grupo->id }}" type="checkbox" role="switch"
                                           name="es_monitoreado" value="1" data-switch-visibilidad
                                           aria-label="Habilitar captura y tablero de {{ $tituloGrupo }}"
                                           @checked($autorizado) @disabled(! $grupo->es_activo)>
                                    <span class="gestor-pista-switch" aria-hidden="true"></span>
                                    <span class="gestor-texto-switch" data-etiqueta-visibilidad>{{ $autorizado ? 'Habilitado' : 'Deshabilitado' }}</span>
                                </label>
                                <noscript><button type="submit" @disabled(! $grupo->es_activo)>Guardar estado</button></noscript>
                                <span class="gestor-estado-guardado" data-estado-guardado role="status" aria-live="polite"></span>
                            </form>
                        @else
                            <span class="gestor-lectura-visibilidad">{{ $autorizado ? 'Habilitado' : 'Deshabilitado' }} · Solo lectura</span>
                        @endif
                    </div>

                    <div class="gestor-columna gestor-acciones-fila">
                        @if($autorizado)
                            <a href="{{ route('mensajes.grupo', $grupo->id) }}" class="gestor-ver-grupo">Conversación <span aria-hidden="true">↗</span></a>
                        @else
                            <span class="gestor-sin-conversacion">Sin acceso</span>
                        @endif
                    </div>

                    <details class="gestor-detalles">
                        <summary>Detalles del grupo</summary>
                        <dl>
                            <div><dt>Identificador interno</dt><dd>{{ $grupo->id }}</dd></div>
                            <div><dt>Identificador de WhatsApp</dt><dd>{{ $grupo->id_chat_proveedor }}</dd></div>
                            <div><dt>Conexión</dt><dd>{{ $grupo->conexion?->nombre_visible ?: ($grupo->conexion?->clave_conexion ?: 'No identificada') }}</dd></div>
                            <div><dt>Versión de autorización</dt><dd>{{ $grupo->version_autorizacion }}</dd></div>
                        </dl>
                    </details>
                </article>
            @empty
                <section class="catalogo-vacio"><h3>No hay grupos para este filtro</h3><p>Prueba otra búsqueda o cambia los criterios.</p></section>
            @endforelse
        </div>

        @if($grupos->hasPages())
            <nav class="catalogo-paginacion" aria-label="Paginación de grupos">
                @if($grupos->onFirstPage())<span>← Anterior</span>@else<a href="{{ $grupos->previousPageUrl() }}">← Anterior</a>@endif
                <span>Página {{ $grupos->currentPage() }} de {{ $grupos->lastPage() }}</span>
                @if($grupos->hasMorePages())<a href="{{ $grupos->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif
            </nav>
        @endif
    </section>
    <script src="{{ asset('js/gestor-grupos.js') }}?v={{ filemtime(public_path('js/gestor-grupos.js')) }}" defer></script>
</x-layout>
