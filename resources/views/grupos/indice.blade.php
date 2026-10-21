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
        <article><span class="catalogo-metrica-icono naranja">✓</span><div><span>Visibles y monitoreados</span><strong data-gestor-contador="visibles">{{ number_format($estadisticas['visibles']) }}</strong><small>Aparecen en el tablero</small></div></article>
        <article><span class="catalogo-metrica-icono gris">○</span><div><span>Ocultos del tablero</span><strong data-gestor-contador="ocultos">{{ number_format($estadisticas['ocultos']) }}</strong><small>Pueden seguir capturando</small></div></article>
        <article><span class="catalogo-metrica-icono gris">♧</span><div><span>Captura autorizada</span><strong>{{ number_format($estadisticas['autorizados']) }}</strong><small>Independiente de su visibilidad</small></div></article>
    </section>

    <section class="catalogo-seccion gestor-seccion" aria-label="Listado y administración de grupos">
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
            <div class="catalogo-aviso gestor-aviso" role="note"><strong>Gestión protegida:</strong> solo puedes consultar grupos. La opción de mostrar u ocultar necesita activación explícita para desarrollo local. Autorizar capturas requiere un administrador autenticado.</div>
        @elseif(config('mensajero.autenticacion.modo') === 'deshabilitado')
            <div class="catalogo-aviso" role="note"><strong>Gestión local temporal habilitada:</strong> puedes mostrar u ocultar grupos uno por uno. No puedes modificar qué grupos captura WhatsApp. Desactiva el permiso local al terminar las pruebas.</div>
        @endif

        <p class="gestor-aclaracion" role="note">
            <strong>Visibilidad:</strong> el interruptor solo muestra u oculta el grupo en el tablero. No modifica la autorización de captura ni elimina mensajes.
        </p>

        <div class="gestor-lista" aria-label="Grupos de WhatsApp registrados">
            <div class="gestor-cabecera-lista" aria-hidden="true">
                <span>Grupo</span>
                <span>Última detección</span>
                <span>Captura</span>
                <span>En el tablero</span>
                <span>Acciones</span>
            </div>
            @forelse($grupos as $grupo)
                @php($autorizado = (bool) ($grupo->es_activo && $grupo->es_monitoreado))
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

                    <div class="gestor-columna gestor-captura">
                        <span class="gestor-etiqueta-movil">Captura</span>
                        <span class="gestor-indicador-captura {{ $autorizado ? 'activa' : 'inactiva' }}">{{ $autorizado ? 'Habilitada' : 'No habilitada' }}</span>
                    </div>

                    <div class="gestor-columna gestor-columna-switch">
                        <span class="gestor-etiqueta-movil">En el tablero</span>
                        @if($puedeCambiarVista)
                            <form class="gestor-form-visibilidad" method="post" action="{{ route('grupos.visibilidad.actualizar', $grupo->id) }}" data-gestor-visibilidad>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="es_visible_tablero" value="0">
                                <label class="gestor-interruptor" for="visibilidad-grupo-{{ $grupo->id }}">
                                    <input id="visibilidad-grupo-{{ $grupo->id }}"
                                           type="checkbox"
                                           role="switch"
                                           name="es_visible_tablero"
                                           value="1"
                                           data-switch-visibilidad
                                           aria-label="Mostrar {{ $tituloGrupo }} en el tablero"
                                           @checked($grupo->es_visible_tablero)>
                                    <span class="gestor-pista-switch" aria-hidden="true"></span>
                                    <span class="gestor-texto-switch" data-etiqueta-visibilidad>{{ $grupo->es_visible_tablero ? 'Visible' : 'Oculto' }}</span>
                                </label>
                                <noscript><button type="submit">Guardar visibilidad</button></noscript>
                                <span class="gestor-estado-guardado" data-estado-guardado role="status" aria-live="polite"></span>
                            </form>
                        @else
                            <span class="gestor-lectura-visibilidad">{{ $grupo->es_visible_tablero ? 'Visible' : 'Oculto' }} · Solo lectura</span>
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
                        @can('administrar')
                            <form method="post" action="{{ route('grupos.monitoreo.actualizar', $grupo->id) }}" class="gestor-form-captura">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="es_monitoreado" value="{{ $grupo->es_monitoreado ? '0' : '1' }}">
                                <button type="submit" class="catalogo-control {{ $grupo->es_monitoreado ? 'secundario' : '' }}"
                                        @disabled(! $grupo->es_activo && ! $grupo->es_monitoreado)>
                                    {{ $grupo->es_monitoreado ? 'Detener captura' : 'Autorizar captura' }}
                                </button>
                                <small>Administración avanzada: cambia qué grupos captura WhatsApp.</small>
                            </form>
                        @endcan
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
