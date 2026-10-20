<div data-panorama-tarjetas>
    <div class="cabecera-carrusel">
        <span class="cantidad-grupos-vista"><span class="cantidad-grupos-circulo">{{ $tarjetas->count() }}</span> grupos en esta vista</span>
        <span class="cabecera-carrusel-pista">Mensajes organizados por grupo · Desplazamiento independiente</span>
    </div>
    <div class="lista-grupos-directivos" aria-label="Grupos con carruseles independientes">
        @forelse($tarjetas as $tarjeta)
            @php($grupo = $tarjeta['grupo'])
            @php($nombreGrupo = $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre'))
            @php($identidadVisual = (int) $grupo->id % 6)
            <article class="tarjeta-directiva tarjeta-directiva-{{ $identidadVisual }}" data-grupo-id="{{ $grupo->id }}">
                <div class="tarjeta-acento"></div>
                <div class="tarjeta-directiva-principal">
                    <span class="icono-tarjeta" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            @switch($identidadVisual)
                                @case(0)
                                    <path d="M4 5h16v11H8l-4 3V5zM8 9h8M8 12h6"/>
                                    @break
                                @case(1)
                                    <circle cx="9" cy="8" r="3"/><path d="M3 19v-2a6 6 0 0 1 12 0v2H3zM16 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5"/>
                                    @break
                                @case(2)
                                    <path d="m3 11 16-6v14L3 13v-2zM7 14v5h4v-4"/>
                                    @break
                                @case(3)
                                    <rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h8M8 17h5"/>
                                    @break
                                @case(4)
                                    <path d="M3 18h18M5 16V6h14v10M9 10h6M9 13h6"/>
                                    @break
                                @default
                                    <circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>
                            @endswitch
                        </svg>
                    </span>
                    <div class="titulo-tarjeta">
                        <h3>{{ $nombreGrupo }}</h3>
                        <p>
                            <span class="punto-actividad {{ $tarjeta['hoy'] > 0 ? 'con-actividad' : '' }}"></span>
                            {{ $tarjeta['hoy'] > 0 ? 'Con mensajes hoy' : 'Sin mensajes hoy' }}
                        </p>
                    </div>
                    <div class="tarjeta-etiquetas">
                        @if($tarjeta['ensayo'])
                            <span class="etiqueta-ensayo">Ensayo</span>
                        @endif
                        <span class="etiqueta-cantidad">{{ number_format($tarjeta['hoy']) }} {{ $tarjeta['hoy'] === 1 ? 'mensaje hoy' : 'mensajes hoy' }}</span>
                    </div>
                </div>

                <div class="tarjeta-contexto">
                    <span>Última actividad</span>
                    <strong>{{ $tarjeta['ultima']?->timezone(config('app.timezone'))->format('d/m/Y · H:i') ?? 'Sin mensajes registrados' }}</strong>
                </div>

                <div class="encabezado-carrusel-mensajes">
                    <div><span class="subtitulo-mensajes">MENSAJES RECIENTES</span><span class="contador-mensajes">Últimos {{ count($tarjeta['mensajes']) }} mensajes</span></div>
                    @if(count($tarjeta['mensajes']) > 3)
                        <div class="controles-carrusel-mensajes" aria-label="Controles de mensajes del grupo">
                            <button type="button" data-mover-grupo="arriba" aria-label="Mensajes anteriores de {{ $nombreGrupo }}" title="Desplazar hacia arriba">↑</button>
                            <button type="button" data-mover-grupo="abajo" aria-label="Mensajes siguientes de {{ $nombreGrupo }}" title="Desplazar hacia abajo">↓</button>
                        </div>
                    @endif
                </div>

                <div class="fragmentos-grupo carrusel-mensajes-grupo" data-carrusel-mensajes tabindex="0" role="region" aria-label="Carrusel de mensajes de {{ $nombreGrupo }}">
                    @forelse($tarjeta['mensajes'] as $mensaje)
                        <div class="fragmento-grupo" data-mensaje-id="{{ $mensaje['id'] }}">
                            <div class="fragmento-datos">
                                <span class="fragmento-remitente"><span class="indicador-remitente" aria-hidden="true"></span>{{ $mensaje['remitente'] }}</span>
                                <time datetime="{{ $mensaje['fecha']?->toIso8601String() }}">{{ $mensaje['fecha']?->timezone(config('app.timezone'))->format('d/m H:i') ?? 'Sin fecha' }}</time>
                            </div>
                            <p>{{ $mensaje['texto'] }}</p>
                        </div>
                    @empty
                        <div class="estado-vacio-grupo">
                            <span class="estado-vacio-icono" aria-hidden="true">○</span>
                            <p class="sin-mensajes-grupo">Este grupo aún no tiene mensajes registrados para mostrar.</p>
                        </div>
                    @endforelse
                </div>

                <div class="pie-tarjeta-directiva">
                    <span>Consulta del historial completo</span>
                    <a href="{{ route('mensajes.grupo', $grupo->id) }}">Abrir conversación <span aria-hidden="true">↗</span></a>
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
