<x-layout title="Operación interpretada">
    <section class="catalogo-encabezado">
        <div>
            <span class="centro-hero-marca">MENSAJERO MILAC / OPERACIÓN</span>
            <h1>Operación interpretada</h1>
            <p>Acontecimientos identificados por Gemini a partir de mensajes originales. Son propuestas que requieren revisión; ninguna fotografía está verificada visualmente por IA.</p>
        </div>
        <a class="catalogo-volver" href="{{ route('mensajes.indice') }}">Ver mensajes originales ↗</a>
    </section>

    <section class="catalogo-metricas gestor-metricas" aria-label="Resumen operativo">
        <article>
            <span class="catalogo-metrica-icono naranja">✧</span>
            <div><span>Por verificar</span><strong>{{ number_format($resumen['pendientes']) }}</strong><small>Acontecimientos propuestos</small></div>
        </article>
        <article>
            <span class="catalogo-metrica-icono gris">▧</span>
            <div><span>Posibles adjuntos</span><strong>{{ number_format($resumen['adjuntos_posibles']) }}</strong><small>Asociación preliminar, sin validación visual</small></div>
        </article>
    </section>

    <section class="catalogo-seccion">
        <div class="catalogo-titulo">
            <div>
                <h2>Bitácora de acontecimientos</h2>
                <p>Un acontecimiento puede tener un texto, una leyenda multimedia o varias evidencias; los mensajes crudos nunca se fusionan ni eliminan.</p>
            </div>
        </div>
        <form class="catalogo-filtros" method="get" action="{{ route('operacion.indice') }}">
            <div>
                <label for="categoria-operacion">Tipo de acontecimiento</label>
                <select id="categoria-operacion" name="categoria">
                    @foreach(['todos'=>'Todas las categorías', 'logistica'=>'Logística', 'incidencia'=>'Incidencias', 'acuerdo'=>'Acuerdos propuestos', 'solicitud'=>'Solicitudes', 'otro'=>'Otros'] as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($categoria === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <button class="catalogo-aplicar" type="submit">Aplicar filtro</button>
            <a class="catalogo-limpiar" href="{{ route('mensajes.ia.indice') }}">Diagnóstico de Gemini ↗</a>
        </form>

        <div class="catalogo-tarjetas">
            @forelse($filas as $fila)
                @php($item = $fila['acontecimiento'])
                <article class="catalogo-tarjeta" aria-label="Acontecimiento operativo {{ $item->id }}">
                    <div class="catalogo-tarjeta-encabezado">
                        <span class="catalogo-grupo-icono" aria-hidden="true">✧</span>
                        <div class="catalogo-grupo-titulo">
                            <h3>{{ $item->titulo }}</h3>
                            <span>{{ $item->nombre_interno ?: ($item->nombre_grupo ?: 'Grupo sin nombre') }} · {{ ucfirst($item->categoria) }} · #{{ $item->id }}</span>
                        </div>
                        <span class="catalogo-estado neutral">Por verificar</span>
                    </div>
                    <div class="catalogo-tarjeta-cuerpo">
                        <p style="font-size:13px;line-height:1.6;white-space:pre-wrap">{{ $item->resumen }}</p>
                        <p><span>Identificado (UTC)</span><strong>{{ $item->creado_en }}</strong></p>
                    </div>
                    <div class="catalogo-tarjeta-pie" style="display:block">
                        <strong style="font-size:12px">Evidencias del acontecimiento</strong>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px">
                            @foreach($fila['fuentes'] as $fuente)
                                <div style="border:1px solid #e1e7ed;border-radius:10px;padding:9px;max-width:190px;background:#fff">
                                    @if($fuente->tipo_mensaje === 'foto' && $fuente->archivo_id && $fuente->archivo_estado === 'verificado')
                                        <a href="{{ route('mensajes.archivo.ver', $fuente->archivo_id) }}" target="_blank" rel="noopener noreferrer">
                                            <img src="{{ route('mensajes.archivo.ver', $fuente->archivo_id) }}"
                                                 alt="Fotografía original de WhatsApp, no analizada visualmente"
                                                 loading="lazy" style="width:160px;height:105px;object-fit:cover;border-radius:7px">
                                        </a>
                                    @else
                                        <span style="font-size:12px">{{ ucfirst($fuente->tipo_mensaje) }}</span>
                                    @endif
                                    <div style="margin-top:7px;font-size:11px;color:#64748b">
                                        {{ $fuente->vinculo === 'sustento_ia' ? 'Fuente del análisis' : 'Posible adjunto · sin confirmar' }}
                                    </div>
                                    @if($fuente->pie_multimedia)
                                        <div style="font-size:11px">{{ \Illuminate\Support\Str::limit($fuente->pie_multimedia, 100) }}</div>
                                    @endif
                                    <a class="centro-ver-evidencia" href="{{ route('mensajes.evento', $fuente->evento_crudo_id) }}">Original #{{ $fuente->mensaje_id }} ↗</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>
            @empty
                <section class="catalogo-vacio">
                    <h3>Todavía no hay acontecimientos proyectados</h3>
                    <p>Se mostrarán cuando Gemini identifique una novedad del grupo piloto y exista su registro operativo. Los resultados anteriores se pueden proyectar sin llamar de nuevo a Google.</p>
                </section>
            @endforelse
        </div>

        @if($acontecimientos !== null && $acontecimientos->hasPages())
            <nav class="catalogo-paginacion" aria-label="Páginas de acontecimientos">
                {{ $acontecimientos->links() }}
            </nav>
        @endif
    </section>
</x-layout>
