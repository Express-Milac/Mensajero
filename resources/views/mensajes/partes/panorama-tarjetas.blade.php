<div data-panorama-tarjetas>
    <div class="cabecera-carrusel">
        <span class="cantidad-grupos-vista"><span class="cantidad-grupos-circulo">{{ $tarjetas->count() }}</span> grupos en esta vista</span>
        <span class="cabecera-carrusel-pista">Cada grupo conserva su carrusel vertical de mensajes</span>
    </div>
    <div class="lista-grupos-directivos centro-lista-grupos" aria-label="Grupos con carruseles independientes">
        @forelse($tarjetas as $tarjeta)
            @php($grupo = $tarjeta['grupo'])
            @php($nombreGrupo = $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre'))
            @php($identidadVisual = (int) $grupo->id % 6)
            <article class="tarjeta-directiva centro-tarjeta-grupo tarjeta-directiva-{{ $identidadVisual }}" data-grupo-id="{{ $grupo->id }}" data-texto-busqueda="{{ mb_strtolower($nombreGrupo.' '.implode(' ', array_column($tarjeta['mensajes'], 'texto')), 'UTF-8') }}">
                <div class="tarjeta-acento"></div>
                <div class="tarjeta-directiva-principal">
                    <span class="icono-tarjeta" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="8" cy="9" r="3"/><circle cx="17" cy="9" r="3"/><path d="M2.5 19v-2a5.5 5.5 0 0 1 11 0v2zm12 0v-2a5.5 5.5 0 0 1 7 0v2z"/></svg>
                    </span>
                    <div class="titulo-tarjeta">
                        <h3>{{ $nombreGrupo }}</h3>
                        <p><span class="punto-actividad {{ $tarjeta['hoy'] > 0 ? 'con-actividad' : '' }}"></span>{{ $tarjeta['hoy'] > 0 ? 'Con mensajes hoy' : 'Sin mensajes hoy' }}</p>
                    </div>
                    <div class="tarjeta-etiquetas">
                        @if($tarjeta['ensayo'])<span class="etiqueta-ensayo">Ensayo</span>@endif
                    </div>
                </div>

                <div class="centro-resumen-grupo" aria-label="Resumen verificable del grupo">
                    <strong>Resumen del grupo</strong>
                    @if($tarjeta['mensajes'] !== [])
                        <p>{{ number_format($tarjeta['hoy']) }} {{ $tarjeta['hoy'] === 1 ? 'mensaje recibido hoy.' : 'mensajes recibidos hoy.' }} Último contenido: «{{ $tarjeta['mensajes'][0]['texto'] }}»</p>
                    @else
                        <p>Todavía no se conservan mensajes de este grupo.</p>
                    @endif
                </div>

                <div class="encabezado-carrusel-mensajes">
                    <div><span class="subtitulo-mensajes">Mensajes recientes</span><span class="contador-mensajes">Últimos {{ count($tarjeta['mensajes']) }} mensajes</span></div>
                    @if(count($tarjeta['mensajes']) > 3)
                        <div class="controles-carrusel-mensajes" aria-label="Controles de mensajes del grupo">
                            <button type="button" data-mover-grupo="arriba" aria-label="Mensajes anteriores de {{ $nombreGrupo }}" title="Subir dentro del grupo">↑</button>
                            <button type="button" data-mover-grupo="abajo" aria-label="Mensajes siguientes de {{ $nombreGrupo }}" title="Bajar dentro del grupo">↓</button>
                        </div>
                    @endif
                </div>
                <div class="fragmentos-grupo carrusel-mensajes-grupo" data-carrusel-mensajes tabindex="0" role="region" aria-label="Carrusel de mensajes de {{ $nombreGrupo }}">
                    @forelse($tarjeta['mensajes'] as $mensaje)
                        <div class="fragmento-grupo" data-mensaje-id="{{ $mensaje['id'] }}">
                            <div class="centro-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($mensaje['remitente'], -2, 2, 'UTF-8'), 'UTF-8') }}</div>
                            <div class="centro-mensaje-cuerpo">
                                <div class="fragmento-datos">
                                    <span class="fragmento-remitente">{{ $mensaje['remitente'] }}@if($mensaje['telefono_remitente'] && ! str_starts_with($mensaje['remitente'], 'Teléfono +')) <small class="telefono-participante">+{{ $mensaje['telefono_remitente'] }}</small>@endif</span>
                                    <time datetime="{{ $mensaje['fecha']?->toIso8601String() }}">{{ $mensaje['fecha']?->timezone(config('app.timezone'))->format('d/m H:i') ?? 'Sin fecha' }}</time>
                                </div>
                                <p>{{ $mensaje['texto'] }}</p>
                                <a class="centro-ver-evidencia" href="{{ route('mensajes.evento', $mensaje['id']) }}">Ver mensaje original ↗</a>
                                @if($mensaje['tiene_remitente'])
                                    <a class="centro-ver-evidencia" href="{{ route('remitentes.editar', $mensaje['id']) }}">Identificar remitente ↗</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="estado-vacio-grupo"><span class="estado-vacio-icono" aria-hidden="true">○</span><p class="sin-mensajes-grupo">Aún no hay mensajes conservados.</p></div>
                    @endforelse
                </div>
                <div class="pie-tarjeta-directiva">
                    <span>{{ number_format($tarjeta['hoy']) }} {{ $tarjeta['hoy'] === 1 ? 'mensaje hoy' : 'mensajes hoy' }} · {{ $tarjeta['ultima']?->timezone(config('app.timezone'))->format('d/m H:i') ?? 'Sin actividad registrada' }}</span>
                    <a href="{{ route('mensajes.grupo', $grupo->id) }}">Abrir conversación <span aria-hidden="true">→</span></a>
                </div>
            </article>
        @empty
            <section class="card empty-state"><h3>No hay grupos para este filtro</h3><p class="muted">Cambia los filtros para explorar las conversaciones autorizadas.</p></section>
        @endforelse
    </div>
</div>
