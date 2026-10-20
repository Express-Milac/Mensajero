<div data-panorama-tarjetas>
        <div class="cabecera-carrusel">
            <span>{{ $tarjetas->count() }} grupos en esta vista</span>
            <span>Desplázate hacia abajo para explorar tarjetas ↓</span>
        </div>
        <div class="carrusel-grupos-vertical" tabindex="0" role="region" aria-label="Carrusel vertical de tarjetas de grupos">
            @forelse($tarjetas as $indice => $tarjeta)
                @php($grupo = $tarjeta['grupo'])
                @php($identidadVisual = (int) $grupo->id % 6)
                @php($iconos = ['💬', '👥', '📣', '🗨️', '📝', '📨'])
                <article class="tarjeta-directiva tarjeta-directiva-{{ $identidadVisual }}">
                    <div class="tarjeta-directiva-principal">
                        <div class="icono-tarjeta" aria-hidden="true">{{ $iconos[$identidadVisual] }}</div>
                        <div class="titulo-tarjeta">
                            <h3>{{ $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre') }}</h3>
                            <p>{{ $tarjeta['hoy'] }} {{ $tarjeta['hoy'] === 1 ? 'mensaje recibido hoy' : 'mensajes recibidos hoy' }}</p>
                        </div>
                        @if($tarjeta['ensayo'])
                            <span class="etiqueta-ensayo">Ensayo</span>
                        @endif
                    </div>

                    @if(count($tarjeta['mensajes']) > 0)
                        <div class="fragmentos-grupo" aria-label="Últimos mensajes capturados">
                            @foreach($tarjeta['mensajes'] as $mensaje)
                                <div class="fragmento-grupo">
                                    <div class="fragmento-datos">
                                        <span class="fragmento-remitente">{{ $mensaje['remitente'] }}</span>
                                        <time datetime="{{ $mensaje['fecha']?->toIso8601String() }}">{{ $mensaje['fecha']?->timezone(config('app.timezone'))->format('d/m H:i') ?? 'Sin fecha' }}</time>
                                    </div>
                                    <p>{{ $mensaje['texto'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="sin-mensajes-grupo">Todavía no hay mensajes conservados de este grupo para mostrar.</p>
                    @endif
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
