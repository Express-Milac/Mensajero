<div data-panorama-tarjetas>
    <div class="cabecera-carrusel">
        <span>{{ $tarjetas->count() }} grupos en esta vista</span>
        <span>Cada grupo tiene su propia lista desplazable de mensajes</span>
    </div>

    <div class="lista-grupos-directivos" aria-label="Grupos con carruseles independientes">
        @forelse($tarjetas as $tarjeta)
            @php($grupo = $tarjeta['grupo'])
            @php($nombreGrupo = $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre'))
            @php($identidadVisual = (int) $grupo->id % 6)
            @php($iconos = ['💬', '👥', '📣', '🗨️', '📝', '📨'])
            <article class="tarjeta-directiva tarjeta-directiva-{{ $identidadVisual }}" data-grupo-id="{{ $grupo->id }}">
                <div class="tarjeta-directiva-principal">
                    <div class="icono-tarjeta" aria-hidden="true">{{ $iconos[$identidadVisual] }}</div>
                    <div class="titulo-tarjeta">
                        <h3>{{ $nombreGrupo }}</h3>
                        <p>{{ $tarjeta['hoy'] }} {{ $tarjeta['hoy'] === 1 ? 'mensaje recibido hoy' : 'mensajes recibidos hoy' }}</p>
                    </div>
                    @if($tarjeta['ensayo'])
                        <span class="etiqueta-ensayo">Ensayo</span>
                    @endif
                </div>

                <div class="encabezado-carrusel-mensajes">
                    <span>Últimos {{ count($tarjeta['mensajes']) }} mensajes</span>
                    @if(count($tarjeta['mensajes']) > 3)
                        <div class="controles-carrusel-mensajes" aria-label="Controles de mensajes del grupo">
                            <button type="button" data-mover-grupo="arriba" aria-label="Mensajes anteriores de {{ $nombreGrupo }}" title="Subir en este grupo">↑</button>
                            <button type="button" data-mover-grupo="abajo" aria-label="Mensajes siguientes de {{ $nombreGrupo }}" title="Bajar en este grupo">↓</button>
                        </div>
                    @endif
                </div>
                <div class="fragmentos-grupo carrusel-mensajes-grupo" data-carrusel-mensajes tabindex="0" role="region" aria-label="Carrusel de mensajes de {{ $nombreGrupo }}">
                    @forelse($tarjeta['mensajes'] as $mensaje)
                        <div class="fragmento-grupo" data-mensaje-id="{{ $mensaje['id'] }}">
                            <div class="fragmento-datos">
                                <span class="fragmento-remitente">{{ $mensaje['remitente'] }}</span>
                                <time datetime="{{ $mensaje['fecha']?->toIso8601String() }}">{{ $mensaje['fecha']?->timezone(config('app.timezone'))->format('d/m H:i') ?? 'Sin fecha' }}</time>
                            </div>
                            <p>{{ $mensaje['texto'] }}</p>
                        </div>
                    @empty
                        <p class="sin-mensajes-grupo">Todavía no hay mensajes conservados de este grupo para mostrar.</p>
                    @endforelse
                </div>

                <div class="pie-tarjeta-directiva">
                    <span>{{ $tarjeta['ultima']?->timezone(config('app.timezone'))->format('Último mensaje: d/m/Y H:i') ?? 'Sin mensajes registrados' }}</span>
                    <a href="{{ route('mensajes.grupo', $grupo->id) }}">Abrir conversación <span aria-hidden="true">→</span></a>
                </div>
            </article>
        @empty
            <section class="card empty-state">
                <h3>No hay grupos para este filtro</h3>
                <p class="muted">Puedes cambiar el filtro o seleccionar nuevos grupos desde su administración.</p>
            </section>
        @endforelse
    </div>
</div>
