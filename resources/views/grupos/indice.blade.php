<x-layout title="Gestor de grupos">
    <section class="catalogo-encabezado">
        <div>
            <span class="centro-hero-marca">MENSAJERO MILAC / GRUPOS</span>
            <h1>Gestor de grupos</h1>
            <p>Controla qué grupos aparecen en Mensajero MILAC. Puedes ocultar las conversaciones de prueba sin borrar sus registros ni detener su captura. El monitoreo es un permiso distinto.</p>
        </div>
        <a class="catalogo-volver" href="{{ route('mensajes.indice') }}">Explorar conversaciones <span aria-hidden="true">→</span></a>
    </section>

    @if(session('estado'))
        <div class="notice success" role="status">{{ session('estado') }}</div>
    @endif
    @if($errors->any())
        <div class="notice error-box" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="catalogo-metricas gestor-metricas" aria-label="Resumen de visibilidad">
        <article><span class="catalogo-metrica-icono">♧</span><div><span>Grupos detectados</span><strong>{{ number_format($estadisticas['descubiertos']) }}</strong><small>Registrados en el sistema</small></div></article>
        <article><span class="catalogo-metrica-icono naranja">✓</span><div><span>Visibles y monitoreados</span><strong>{{ number_format($estadisticas['visibles']) }}</strong><small>Aparecen en el tablero</small></div></article>
        <article><span class="catalogo-metrica-icono gris">○</span><div><span>Ocultos del tablero</span><strong>{{ number_format($estadisticas['ocultos']) }}</strong><small>Pueden seguir capturando</small></div></article>
        <article><span class="catalogo-metrica-icono gris">♧</span><div><span>Captura autorizada</span><strong>{{ number_format($estadisticas['autorizados']) }}</strong><small>Independiente de su visibilidad</small></div></article>
    </section>

    <section class="catalogo-seccion" aria-label="Listado y administración de grupos">
        <div class="catalogo-titulo">
            <div><h2>Administrar visibilidad</h2><p>{{ number_format($grupos->total()) }} {{ $grupos->total() === 1 ? 'grupo coincide' : 'grupos coinciden' }} con los filtros seleccionados. Los cambios se guardan individualmente.</p></div>
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
                <label for="visibilidad">Vista ejecutiva</label>
                <select id="visibilidad" name="visibilidad">
                    <option value="todos" @selected($visibilidad === 'todos')>Todos</option>
                    <option value="visibles" @selected($visibilidad === 'visibles')>Visibles</option>
                    <option value="ocultos" @selected($visibilidad === 'ocultos')>Ocultos</option>
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
            @if($buscar !== '' || $origen !== 'todos' || $estado !== 'todos' || $visibilidad !== 'todos')
                <a class="catalogo-limpiar" href="{{ route('grupos.indice') }}">Restablecer</a>
            @endif
        </form>

        @if(config('mensajero.autenticacion.modo') === 'deshabilitado' && ! $puedeCambiarVista)
            <div class="catalogo-aviso" role="note"><strong>Gestión protegida:</strong> solo puedes consultar grupos. La opción de mostrar u ocultar necesita activación explícita para desarrollo local. Autorizar capturas requiere un administrador autenticado.</div>
        @elseif(config('mensajero.autenticacion.modo') === 'deshabilitado')
            <div class="catalogo-aviso" role="note"><strong>Gestión local temporal habilitada:</strong> puedes mostrar u ocultar grupos uno por uno. No puedes modificar qué grupos captura WhatsApp. Desactiva el permiso local al terminar las pruebas.</div>
        @endif

        <div class="catalogo-ayuda-gestor" role="note"><strong>Dos decisiones diferentes:</strong> «Mostrar en tablero» solo cambia la presentación. «Autorizar monitoreo» permite capturar mensajes; no se modifica al ocultar un grupo. Ningún registro histórico se elimina.</div>
        <div class="catalogo-tarjetas">
            @forelse($grupos as $grupo)
                @php($autorizado = $grupo->es_activo && $grupo->es_monitoreado)
                @php($tituloGrupo = $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre'))
                <article class="catalogo-tarjeta">
                    <div class="catalogo-tarjeta-encabezado">
                        <span class="catalogo-grupo-icono" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="3"/><path d="M2 20v-2a6 6 0 0 1 12 0v2H2zm13-6a5 5 0 0 1 6 5v1"/></svg>
                        </span>
                        <div class="catalogo-grupo-titulo">
                            <h3>{{ $tituloGrupo }}</h3>
                            <span>{{ $grupo->nombre_interno && $grupo->nombre_grupo && $grupo->nombre_interno !== $grupo->nombre_grupo ? $grupo->nombre_grupo : 'Grupo de WhatsApp' }}</span>
                        </div>
                        <div class="gestor-etiquetas">
                            <span class="catalogo-estado {{ $grupo->es_visible_tablero ? 'activo' : 'inactivo' }}">{{ $grupo->es_visible_tablero ? 'Visible en tablero' : 'Oculto del tablero' }}</span>
                            <span class="catalogo-estado {{ $autorizado ? 'activo' : 'neutral' }}">{{ $autorizado ? 'Captura habilitada' : 'Captura no habilitada' }}</span>
                            @if(! $grupo->es_activo)<span class="catalogo-estado inactivo">Grupo inactivo</span>@endif
                        </div>
                    </div>
                    <div class="catalogo-tarjeta-cuerpo">
                        <p><span>Origen</span><strong>{{ $grupo->conexion?->proveedor === 'recolector_whatsapp' ? 'WhatsApp del recolector' : 'Otra conexión' }}</strong></p>
                        <p><span>Última detección</span><strong>{{ $grupo->visto_ultima_vez_en?->timezone(config('app.timezone'))->format('d/m/Y · H:i') ?? 'Sin fecha registrada' }}</strong></p>
                        <details class="catalogo-detalles">
                            <summary>Detalles de identificación</summary>
                            <dl>
                                <div><dt>Identificador interno</dt><dd>{{ $grupo->id }}</dd></div>
                                <div><dt>Identificador de WhatsApp</dt><dd>{{ $grupo->id_chat_proveedor }}</dd></div>
                                <div><dt>Conexión</dt><dd>{{ $grupo->conexion?->nombre_visible ?: ($grupo->conexion?->clave_conexion ?: 'No identificada') }}</dd></div>
                                <div><dt>Versión de autorización</dt><dd>{{ $grupo->version_autorizacion }}</dd></div>
                            </dl>
                        </details>
                    </div>
                    <div class="catalogo-tarjeta-pie">
                        @if($autorizado)
                            <a class="catalogo-ver-conversacion" href="{{ route('mensajes.grupo', $grupo->id) }}">Ver conversación <span aria-hidden="true">↗</span></a>
                        @else
                            <span class="catalogo-sin-conversacion">Sin consulta de mensajes habilitada</span>
                        @endif
                        @if($puedeCambiarVista)
                            <form method="post" action="{{ route('grupos.visibilidad.actualizar', $grupo->id) }}" data-confirmar-gestion="{{ $grupo->es_visible_tablero ? 'Ocultar este grupo del tablero sin detener su captura?' : 'Mostrar este grupo en el tablero?' }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="es_visible_tablero" value="{{ $grupo->es_visible_tablero ? '0' : '1' }}">
                                <button type="submit" class="catalogo-control gestor-control-vista {{ $grupo->es_visible_tablero ? 'secundario' : '' }}">
                                    {{ $grupo->es_visible_tablero ? 'Ocultar del tablero' : 'Mostrar en tablero' }}
                                </button>
                            </form>
                        @else
                            <span class="gestor-permiso-requerido">Gestión restringida</span>
                        @endif
                        @can('administrar')
                            <form method="post" action="{{ route('grupos.monitoreo.actualizar', $grupo->id) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="es_monitoreado" value="{{ $grupo->es_monitoreado ? '0' : '1' }}">
                                <button type="submit" class="catalogo-control {{ $grupo->es_monitoreado ? 'secundario' : '' }}" @disabled(! $grupo->es_activo && ! $grupo->es_monitoreado)>
                                    {{ $grupo->es_monitoreado ? 'Detener monitoreo' : 'Autorizar monitoreo' }}
                                </button>
                            </form>
                        @endcan
                    </div>
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
