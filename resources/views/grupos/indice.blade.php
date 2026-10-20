<x-layout title="Grupos">
    <section class="catalogo-encabezado">
        <div>
            <span class="centro-hero-marca">MENSAJERO MILAC / GRUPOS</span>
            <h1>Catálogo de grupos</h1>
            <p>Consulta qué grupos detectó Mensajero y cuáles tienen autorizado el seguimiento. Solo se conservan mensajes de los grupos seleccionados expresamente.</p>
        </div>
        <a class="catalogo-volver" href="{{ route('mensajes.indice') }}">Explorar conversaciones <span aria-hidden="true">→</span></a>
    </section>

    @if(session('estado'))
        <div class="notice success" role="status">{{ session('estado') }}</div>
    @endif
    @if($errors->any())
        <div class="notice error-box" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="catalogo-metricas" aria-label="Resumen de grupos">
        <article><span class="catalogo-metrica-icono">♧</span><div><span>Grupos descubiertos</span><strong>{{ number_format($estadisticas['descubiertos']) }}</strong><small>Registrados en el catálogo</small></div></article>
        <article><span class="catalogo-metrica-icono naranja">✓</span><div><span>Monitoreo activo</span><strong>{{ number_format($estadisticas['autorizados']) }}</strong><small>Grupos autorizados y activos</small></div></article>
        <article><span class="catalogo-metrica-icono gris">○</span><div><span>Sin monitorear</span><strong>{{ number_format($estadisticas['sin_monitorear']) }}</strong><small>Mensajes no capturados por selección</small></div></article>
    </section>

    <section class="catalogo-seccion" aria-label="Listado y administración de grupos">
        <div class="catalogo-titulo">
            <div><h2>Grupos disponibles</h2><p>{{ number_format($grupos->total()) }} {{ $grupos->total() === 1 ? 'grupo coincide' : 'grupos coinciden' }} con los filtros seleccionados</p></div>
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

        @if(config('mensajero.autenticacion.modo') === 'deshabilitado')
            <div class="catalogo-aviso" role="note"><strong>Vista de consulta:</strong> el monitoreo solo puede modificarse por un administrador autenticado. La configuración de prueba sin acceso aún no permite hacer cambios desde esta pantalla.</div>
        @endif

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
                        @if(! $grupo->es_activo)
                            <span class="catalogo-estado inactivo">Inactivo</span>
                        @elseif($autorizado)
                            <span class="catalogo-estado activo">Monitoreado</span>
                        @else
                            <span class="catalogo-estado neutral">Sin monitorear</span>
                        @endif
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
</x-layout>
