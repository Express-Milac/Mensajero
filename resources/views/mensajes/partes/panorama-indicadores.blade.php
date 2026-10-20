<div data-panorama-indicadores>
    <section class="centro-metricas" aria-label="Indicadores del día">
        <article class="centro-metrica">
            <span class="centro-metrica-icono">▤</span><div><span>Mensajes hoy</span><strong>{{ number_format($mensajesHoy) }}</strong><small>Mensajes recibidos desde medianoche</small></div>
        </article>
        <article class="centro-metrica">
            <span class="centro-metrica-icono naranja">♧</span><div><span>Grupos con mensajes hoy</span><strong>{{ number_format($cantidadConActividad) }} <em>de {{ number_format($totalGrupos) }}</em></strong><small>De los grupos en seguimiento</small></div>
        </article>
        <article class="centro-metrica">
            <span class="centro-metrica-icono violeta">♙</span><div><span>Remitentes detectados</span><strong>{{ number_format($analisis['participantesMuestra']) }}</strong><small>Identificadores distintos en la muestra</small></div>
        </article>
        <article class="centro-metrica">
            <span class="centro-metrica-icono naranja">◷</span><div><span>Último mensaje</span><strong class="centro-hora">{{ $ultimaParticipacion?->timezone(config('app.timezone'))->format('H:i') ?? '—' }}</strong><small>{{ $ultimaParticipacion?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'Sin mensajes registrados' }}</small></div>
        </article>
        <article class="centro-metrica centro-metrica-seguimiento">
            <span class="centro-metrica-icono naranja">▣</span><div><span>Menciones para revisar</span><strong>{{ count($analisis['revision']) }}</strong><small>En la muestra · No son alertas validadas</small></div><a href="#menciones-revision" aria-label="Ver menciones para revisar">→</a>
        </article>
    </section>

    <section class="centro-paneles" id="temas-clave" aria-label="Lectura ejecutiva de conversaciones">
        <article class="centro-panel centro-panel-temas">
            <header><span class="centro-panel-icono">♨</span><h2>Temas clave del día</h2><span class="centro-panel-etiqueta">{{ count($analisis['temas']) }}</span></header>
            <p class="centro-panel-ayuda">Palabras detectadas literalmente en los mensajes.</p>
            <div class="centro-lista-panel">
                @forelse($analisis['temas'] as $indice => $tema)
                    <div class="centro-fila-panel"><span class="centro-indice">{{ $indice + 1 }}</span><span class="centro-descripcion">{{ $tema['nombre'] }}</span><strong class="centro-cantidad">{{ $tema['total'] }}</strong><small>{{ $tema['grupos'] }} {{ $tema['grupos'] === 1 ? 'grupo' : 'grupos' }}</small></div>
                @empty
                    <p class="centro-vacio">Sin temas coincidentes en los mensajes recientes de hoy.</p>
                @endforelse
            </div>
        </article>
        <article class="centro-panel" id="acuerdos-explicitos">
            <header><span class="centro-panel-icono verde">✓</span><h2>Posibles acuerdos mencionados</h2></header>
            <p class="centro-panel-ayuda">Expresiones textuales, sin confirmar su cumplimiento.</p>
            <div class="centro-lista-panel">
                @forelse($analisis['acuerdos'] as $mencion)
                    <a class="centro-fila-enlace" href="{{ route('mensajes.evento', $mencion['evento_id']) }}">
                        <span class="centro-fila-icono verde">✓</span><span><strong>{{ $mencion['texto'] }}</strong><small>{{ $nombresGrupos[$mencion['grupo_id']] ?? 'Grupo en seguimiento' }}</small></span><span aria-hidden="true">↗</span>
                    </a>
                @empty
                    <p class="centro-vacio">Sin frases explícitas de acuerdo o confirmación en los mensajes revisados.</p>
                @endforelse
            </div>
        </article>
        <article class="centro-panel">
            <header><span class="centro-panel-icono">♧</span><h2>Participación reciente</h2></header>
            <p class="centro-panel-ayuda">Identificadores provisionales; no nombres verificados.</p>
            <div class="centro-lista-panel">
                @forelse($analisis['remitentes'] as $remitente)
                    <div class="centro-fila-participacion"><span class="centro-avatar">{{ substr($remitente['nombre'], -2) }}</span><span>{{ $remitente['nombre'] }}</span><small>{{ $remitente['mensajes'] }} {{ $remitente['mensajes'] === 1 ? 'mensaje' : 'mensajes' }}</small></div>
                @empty
                    <p class="centro-vacio">Todavía no hay remitentes identificables en los mensajes de hoy.</p>
                @endforelse
            </div>
        </article>
        <article class="centro-panel" id="menciones-revision">
            <header><span class="centro-panel-icono">⚑</span><h2>Menciones para revisar</h2></header>
            <p class="centro-panel-ayuda">Expresiones como «pendiente», «demora» o «incidencia».</p>
            <div class="centro-lista-panel">
                @forelse($analisis['revision'] as $mencion)
                    <a class="centro-fila-enlace" href="{{ route('mensajes.evento', $mencion['evento_id']) }}">
                        <span class="centro-fila-icono">!</span><span><strong>{{ $mencion['texto'] }}</strong><small>{{ $nombresGrupos[$mencion['grupo_id']] ?? 'Grupo en seguimiento' }}</small></span><span aria-hidden="true">↗</span>
                    </a>
                @empty
                    <p class="centro-vacio">No se detectaron expresiones de seguimiento en los mensajes revisados.</p>
                @endforelse
            </div>
        </article>
    </section>
    <p class="centro-nota-muestra">Análisis basado en los últimos {{ number_format($analisis['mensajesRevisados']) }} mensajes de hoy, hasta un máximo de 300. La detección textual no reemplaza una revisión del contexto.</p>
</div>
