<x-layout title="Grupos">
    <section class="intro compact">
        <p class="eyebrow">LISTA PERMITIDA</p>
        <h1>Grupos descubiertos</h1>
        <p class="muted">Los grupos nuevos aparecen desactivados por defecto. Mensajero solo capturará contenido de los grupos expresamente autorizados.</p>
    </section>

    @if(session('estado'))
        <div class="notice success">{{ session('estado') }}</div>
    @endif

    @if($errors->any())
        <div class="notice error-box">{{ $errors->first() }}</div>
    @endif

    <section class="card toolbar">
        <form method="get" action="{{ route('grupos.indice') }}" class="search-form">
            <label for="buscar">Buscar grupo</label>
            <div class="search-row">
                <input id="buscar" name="buscar" value="{{ $buscar }}" placeholder="Nombre o identificador del grupo">
                <button class="secondary" type="submit">Buscar</button>
                @if($buscar !== '')<a class="clear-link" href="{{ route('grupos.indice') }}">Limpiar</a>@endif
            </div>
        </form>
        @if(config('mensajero.autenticacion.modo') === 'deshabilitado')
            <p class="fine operation-note"><strong>Modo temporal sin autenticación:</strong> la consulta está disponible, pero los cambios administrativos están bloqueados desde la web. En el servidor puede usarse <code>php artisan mensajero:monitoreo-chat ID activar</code>.</p>
        @endif
    </section>

    <section class="group-list">
        @forelse($grupos as $grupo)
            <article class="card group-card">
                <div class="group-main">
                    <div class="group-title-row">
                        <div>
                            <p class="eyebrow">GRUPO #{{ $grupo->id }}</p>
                            <h2>{{ $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre') }}</h2>
                        </div>
                        <div class="group-badges">
                            <span class="badge {{ $grupo->es_monitoreado ? '' : 'neutral' }}">{{ $grupo->es_monitoreado ? 'Monitoreado' : 'No monitoreado' }}</span>
                            @if(! $grupo->es_activo)<span class="badge danger">Inactivo</span>@endif
                        </div>
                    </div>
                    @if($grupo->nombre_interno && $grupo->nombre_grupo && $grupo->nombre_interno !== $grupo->nombre_grupo)
                        <p class="muted">WhatsApp: {{ $grupo->nombre_grupo }}</p>
                    @endif
                    <p class="identifier">{{ $grupo->id_chat_proveedor }}</p>
                    <div class="metadata-row">
                        <span>Conexión: {{ $grupo->conexion?->nombre_visible ?: $grupo->conexion?->clave_conexion }}</span>
                        <span>Versión de autorización: {{ $grupo->version_autorizacion }}</span>
                        <span>Última detección: {{ $grupo->visto_ultima_vez_en?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Sin registro' }}</span>
                    </div>
                </div>

                <div class="group-action">
                    @if($grupo->es_monitoreado && $grupo->es_activo && (config('mensajero.autenticacion.modo') !== 'deshabilitado' || (app()->environment(['local', 'testing']) && config('mensajero.consulta_local_sin_autenticacion', false) && in_array(request()->ip(), ['127.0.0.1', '::1'], true))))
                        <p><a class="action-link" href="{{ route('mensajes.grupo', $grupo->id) }}">Ver mensajes →</a></p>
                    @endif
                    @can('administrar')
                        <form method="post" action="{{ route('grupos.monitoreo.actualizar', $grupo->id) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="es_monitoreado" value="{{ $grupo->es_monitoreado ? '0' : '1' }}">
                            <button class="{{ $grupo->es_monitoreado ? 'secondary danger-button' : 'primary compact-button' }}" type="submit" @disabled(! $grupo->es_activo && ! $grupo->es_monitoreado)>
                                {{ $grupo->es_monitoreado ? 'Dejar de monitorear' : 'Monitorear grupo' }}
                            </button>
                        </form>
                    @else
                        <span class="fine">Cambio administrativo protegido</span>
                    @endcan
                </div>
            </article>
        @empty
            <section class="card empty-state">
                <span class="badge neutral">Sin grupos</span>
                <h2>Todavía no hay grupos descubiertos.</h2>
                <p class="muted">Cuando la pasarela autohospedada sincronice los grupos de la cuenta controlada, aparecerán aquí desactivados por defecto.</p>
            </section>
        @endforelse
    </section>

    @if($grupos->hasPages())
        <nav class="pagination" aria-label="Paginación">
            @if($grupos->onFirstPage())<span>Anterior</span>@else<a href="{{ $grupos->previousPageUrl() }}">Anterior</a>@endif
            <span>Página {{ $grupos->currentPage() }} de {{ $grupos->lastPage() }}</span>
            @if($grupos->hasMorePages())<a href="{{ $grupos->nextPageUrl() }}">Siguiente</a>@else<span>Siguiente</span>@endif
        </nav>
    @endif
</x-layout>
