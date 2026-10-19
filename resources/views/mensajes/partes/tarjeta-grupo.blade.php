<article class="card group-card">
    <div class="group-main">
        <p class="eyebrow" style="margin:0 0 8px">GRUPO #{{ $grupo->id }}</p>
        <h2>{{ $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre') }}</h2>
        <div class="metadata-row">
            <span>Eventos: {{ number_format($grupo->eventos_crudos_count) }}</span>
            <span>Conexión: {{ $grupo->conexion?->nombre_visible ?: ($grupo->conexion?->clave_conexion ?: 'No identificada') }}</span>
        </div>
    </div>
    <div class="group-action">
        <a class="action-link" href="{{ route('mensajes.grupo', $grupo->id) }}">Ver mensajes →</a>
    </div>
</article>
