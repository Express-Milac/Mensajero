<x-layout title="Historial de grupo">
    <nav class="breadcrumbs" aria-label="Ruta de navegación">
        <a href="{{ route('mensajes.indice') }}">Mensajes originales</a>
        <span>/</span>
        <span>Grupo #{{ $grupo->id }}</span>
    </nav>

    <section class="intro compact">
        <p class="eyebrow">HISTORIAL CRONOLÓGICO</p>
        <h1>{{ $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre') }}</h1>
        <p class="muted">Eventos originales conservados en SQL Server, ordenados por fecha de recepción. No se modifican los mensajes ni se ejecuta inteligencia artificial al consultarlos.</p>
        <div class="metadata-row">
            <span>Grupo #{{ $grupo->id }}</span>
            <span>Conexión: {{ $grupo->conexion?->nombre_visible ?: $grupo->conexion?->clave_conexion }}</span>
            <span>{{ $eventos->total() }} eventos con el filtro actual</span>
        </div>
    </section>

    <section class="card toolbar">
        <form method="get" action="{{ route('mensajes.grupo', $grupo->id) }}" class="filter-row">
            <div>
                <label for="tipo">Tipo de evento</label>
                <select id="tipo" name="tipo">
                    <option value="">Todos</option>
                    @foreach(['mensaje' => 'Mensajes', 'edicion' => 'Ediciones', 'eliminacion' => 'Eliminaciones', 'reaccion' => 'Reacciones', 'otro' => 'Otros'] as $clave => $nombre)
                        <option value="{{ $clave }}" @selected($tipo === $clave)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="secondary">Aplicar filtro</button>
            @if($tipo !== '')<a href="{{ route('mensajes.grupo', $grupo->id) }}">Limpiar</a>@endif
        </form>
    </section>

    @if($periodos->isNotEmpty())
        <section class="notice integrity-warning">
            <strong>Integridad pendiente de conciliación</strong>
            <p>La conexión asociada al grupo tiene {{ $periodos->count() }} periodos recientes registrados. Estos intervalos corresponden a la conexión, no necesariamente a cada mensaje de este grupo.</p>
            <details>
                <summary>Consultar periodos de la conexión</summary>
                <ul>
                    @foreach($periodos as $periodo)
                        <li>{{ $periodo->causa }} · {{ $periodo->inicio_en?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?? 'Sin fecha' }} — {{ $periodo->fin_en?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?? 'Sin fecha' }}</li>
                    @endforeach
                </ul>
            </details>
        </section>
    @else
        <p class="fine integrity-note">No hay periodos de integridad registrados para esta conexión. Esto no garantiza ausencia de mensajes faltantes.</p>
    @endif

    <section class="event-list" aria-label="Eventos del grupo">
        @forelse($eventos as $fila)
            @php($registro = $fila['evento'])
            <article class="card event-card">
                <div class="event-header">
                    <div class="event-badges">
                        <span class="badge {{ $registro->tipo_evento === 'mensaje' ? '' : 'neutral' }}">{{ ucfirst($registro->tipo_evento) }}</span>
                        <span class="badge neutral">{{ $registro->tipo_mensaje ?: 'Tipo histórico sin clasificar' }}</span>
                        <span class="badge {{ $registro->estado_procesamiento === 'normalizado' ? '' : 'neutral' }}">{{ str_replace('_', ' ', $registro->estado_procesamiento) }}</span>
                    </div>
                    <time datetime="{{ $registro->recibido_en?->toIso8601String() }}">{{ $registro->recibido_en?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?? 'Fecha desconocida' }}</time>
                </div>
                <p class="event-sender">{{ $fila['remitente'] }}</p>
                <p class="event-text">{{ \Illuminate\Support\Str::limit($fila['resumen'], 600) }}</p>
                <div class="event-footer">
                    <span>Evento #{{ $registro->id }}</span>
                    @if($registro->archivo)<span>Archivo: {{ $registro->archivo->estado }}</span>@endif
                    @if($registro->id_mensaje_citado_proveedor)<span>Contiene una cita</span>@endif
                    <a href="{{ route('mensajes.evento', $registro->id) }}">Ver evidencia →</a>
                </div>
            </article>
        @empty
            <section class="card empty-state">
                <h2>No hay eventos para este filtro.</h2>
                <p class="muted">Prueba otro tipo de evento o consulta los registros cuando el recolector haya capturado mensajes nuevos.</p>
            </section>
        @endforelse
    </section>

    @if($eventos->hasPages())
        <nav class="pagination" aria-label="Páginas del historial">
            @if($eventos->onFirstPage())<span>Anterior</span>@else<a href="{{ $eventos->previousPageUrl() }}">Anterior</a>@endif
            <span>Página {{ $eventos->currentPage() }} de {{ $eventos->lastPage() }}</span>
            @if($eventos->hasMorePages())<a href="{{ $eventos->nextPageUrl() }}">Siguiente</a>@else<span>Siguiente</span>@endif
        </nav>
    @endif
</x-layout>
