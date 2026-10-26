<div data-panorama-tarjetas>
    <div class="cabecera-carrusel">
        <span class="cantidad-grupos-vista"><span class="cantidad-grupos-circulo">{{ $tarjetas->count() }}</span> grupos en esta vista</span>
        <span class="cabecera-carrusel-pista">Izquierda: eventos interpretados · Derecha: mensajes originales</span>
    </div>

    <div class="lista-grupos-directivos centro-lista-grupos modo-lista" aria-label="Seguimiento por grupo: eventos y mensajes originales">
        @forelse($tarjetas as $tarjeta)
            @php($grupo = $tarjeta['grupo'])
            @php($nombreGrupo = $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre'))
            @php($identidadVisual = (int) $grupo->id % 6)
            <article class="tarjeta-directiva centro-tarjeta-grupo tarjeta-directiva-{{ $identidadVisual }}"
                data-grupo-id="{{ $grupo->id }}"
                data-texto-busqueda="{{ mb_strtolower($nombreGrupo.' '.implode(' ', array_column($tarjeta['mensajes'], 'texto')).' '.implode(' ', array_column($tarjeta['eventos'], 'titulo')).' '.implode(' ', array_column($tarjeta['eventos'], 'resumen')), 'UTF-8') }}">
                <div class="tarjeta-acento"></div>
                <div class="tarjeta-directiva-principal">
                    <span class="icono-tarjeta" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="8" cy="9" r="3"/><circle cx="17" cy="9" r="3"/><path d="M2.5 19v-2a5.5 5.5 0 0 1 11 0v2zm12 0v-2a5.5 5.5 0 0 1 7 0v2z"/></svg>
                    </span>
                    <div class="titulo-tarjeta">
                        <h3>{{ $nombreGrupo }}</h3>
                        <p><span class="punto-actividad {{ $tarjeta['hoy'] > 0 ? 'con-actividad' : '' }}"></span>{{ $tarjeta['hoy'] > 0 ? 'Con mensajes hoy' : 'Sin mensajes hoy' }} · {{ count($tarjeta['eventos']) }} {{ count($tarjeta['eventos']) === 1 ? 'evento reciente' : 'eventos recientes' }}</p>
                    </div>
                    <div class="tarjeta-etiquetas">
                        @if($tarjeta['ensayo'])<span class="etiqueta-ensayo">Ensayo</span>@endif
                    </div>
                </div>

                <div class="centro-columnas-seguimiento">
                    {{-- Eventos siempre a la izquierda, sin intervención humana para publicarlos. --}}
                    <section class="centro-panel-seguimiento centro-panel-eventos" aria-label="Eventos interpretados de {{ $nombreGrupo }}">
                        <div class="encabezado-carrusel-mensajes">
                            <div>
                                <span class="subtitulo-mensajes">Eventos</span>
                                <span class="contador-mensajes">Últimos {{ count($tarjeta['eventos']) }} generados por IA</span>
                            </div>
                            @if(count($tarjeta['eventos']) > 2)
                                <div class="controles-carrusel-mensajes" aria-label="Controles de eventos de {{ $nombreGrupo }}">
                                    <button type="button" data-mover-eventos="arriba" aria-label="Eventos anteriores de {{ $nombreGrupo }}">↑</button>
                                    <button type="button" data-mover-eventos="abajo" aria-label="Eventos siguientes de {{ $nombreGrupo }}">↓</button>
                                </div>
                            @endif
                        </div>
                        <div class="fragmentos-grupo carrusel-mensajes-grupo carrusel-eventos-grupo"
                            data-carrusel-eventos tabindex="0" role="region" aria-label="Lista de eventos de {{ $nombreGrupo }}">
                            @forelse($tarjeta['eventos'] as $evento)
                                <div class="fragmento-grupo centro-fragmento-evento" data-evento-id="{{ $evento['id'] }}">
                                    <div class="centro-avatar centro-avatar-ia" aria-hidden="true">✦</div>
                                    <div class="centro-mensaje-cuerpo">
                                        <div class="fragmento-datos">
                                            <span class="fragmento-remitente">{{ ['logistica' => 'Logística', 'incidencia' => 'Incidencia', 'acuerdo' => 'Acuerdo', 'solicitud' => 'Solicitud', 'otro' => 'Otro'][$evento['categoria']] ?? ucfirst($evento['categoria']) }} · Evento #{{ $evento['id'] }}</span>
                                            <time datetime="{{ $evento['fecha']->toIso8601String() }}">{{ $evento['fecha']->timezone(config('app.timezone'))->format('d/m H:i') }}</time>
                                        </div>
                                        <strong class="centro-evento-titulo">{{ $evento['titulo'] }}</strong>
                                        <p class="centro-evento-resumen">{{ \Illuminate\Support\Str::limit($evento['resumen'], 340) }}</p>

                                        @if($evento['fotografias'] || $evento['documentos'] || $evento['videos'] || $evento['audios'])
                                            <div class="centro-evento-archivos" aria-label="Archivos vinculados al evento">
                                                @if($evento['fotografias'])
                                                    <span class="centro-archivo-etiqueta">📷 {{ $evento['fotografias'] }} {{ $evento['fotografias'] === 1 ? 'foto' : 'fotos' }}</span>
                                                @endif
                                                @if($evento['documentos'])
                                                    <span class="centro-archivo-etiqueta">▤ {{ $evento['documentos'] }} {{ $evento['documentos'] === 1 ? 'documento' : 'documentos' }}</span>
                                                @endif
                                                @if($evento['videos'])
                                                    <span class="centro-archivo-etiqueta">▶ {{ $evento['videos'] }} {{ $evento['videos'] === 1 ? 'video' : 'videos' }}</span>
                                                @endif
                                                @if($evento['audios'])
                                                    <span class="centro-archivo-etiqueta">♫ {{ $evento['audios'] }} {{ $evento['audios'] === 1 ? 'audio' : 'audios' }}</span>
                                                @endif
                                            </div>
                                        @endif

                                        @if($evento['miniaturas'] !== [])
                                            <div class="centro-evento-miniaturas" aria-label="Vista previa de fotografías originales">
                                                @foreach($evento['miniaturas'] as $foto)
                                                    <a href="{{ route('mensajes.archivo.ver', $foto['archivo_id']) }}" target="_blank" rel="noopener noreferrer"
                                                        title="{{ $foto['vinculo'] === 'sustento_ia' ? 'Fotografía del mensaje origen' : 'Fotografía asociada por proximidad' }}">
                                                        <img src="{{ route('mensajes.archivo.ver', $foto['archivo_id']) }}" loading="lazy"
                                                            alt="Fotografía original asociada al evento; no interpretada visualmente por Gemini">
                                                    </a>
                                                @endforeach
                                                @if($evento['fotografias'] > count($evento['miniaturas']))
                                                    <span class="centro-evento-mas-fotos">+{{ $evento['fotografias'] - count($evento['miniaturas']) }} fotos</span>
                                                @endif
                                            </div>
                                        @endif

                                        @if($evento['adjuntos_tentativos'] > 0)
                                            <small class="centro-evento-nota">Archivos asociados por remitente y tiempo; no verificados por IA visual.</small>
                                        @elseif($evento['fotografias'] > 0)
                                            <small class="centro-evento-nota">Fotografías originales; Gemini interpretó texto, no imágenes.</small>
                                        @endif

                                        <div class="centro-evento-enlaces">
                                            <a class="centro-ver-evidencia" href="{{ route('operacion.indice') }}#acontecimiento-{{ $evento['id'] }}">Ver evento y evidencias ↗</a>
                                            <a class="centro-ver-evidencia" href="{{ route('mensajes.evento', $evento['evento_origen_id']) }}">Mensaje origen ↗</a>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="estado-vacio-grupo">
                                    <span class="estado-vacio-icono" aria-hidden="true">✧</span>
                                    <p class="sin-mensajes-grupo">Sin eventos interpretados de este grupo por ahora.</p>
                                </div>
                            @endforelse
                        </div>
                    </section>

                    {{-- Mensajes originales a la derecha, con el diseño conocido. --}}
                    <section class="centro-panel-seguimiento centro-panel-mensajes" aria-label="Mensajes originales de {{ $nombreGrupo }}">
                        <div class="encabezado-carrusel-mensajes">
                            <div>
                                <span class="subtitulo-mensajes">Mensajes originales</span>
                                <span class="contador-mensajes">Últimos {{ count($tarjeta['mensajes']) }} mensajes</span>
                            </div>
                            @if(count($tarjeta['mensajes']) > 3)
                                <div class="controles-carrusel-mensajes" aria-label="Controles de mensajes de {{ $nombreGrupo }}">
                                    <button type="button" data-mover-grupo="arriba" aria-label="Mensajes anteriores de {{ $nombreGrupo }}">↑</button>
                                    <button type="button" data-mover-grupo="abajo" aria-label="Mensajes siguientes de {{ $nombreGrupo }}">↓</button>
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
                    </section>
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
