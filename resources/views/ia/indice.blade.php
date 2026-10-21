<x-layout title="Eventos operativos con IA">
    <section class="catalogo-encabezado">
        <div>
            <span class="centro-hero-marca">MENSAJERO MILAC / INTELIGENCIA ARTIFICIAL</span>
            <h1>Eventos operativos detectados</h1>
            <p>Interpretaciones preliminares elaboradas a partir de mensajes conservados. No son hechos verificados, acuerdos confirmados ni instrucciones operativas.</p>
        </div>
        <a class="catalogo-volver" href="{{ route('tablero') }}">Volver al centro de mando</a>
    </section>

    <section class="catalogo-seccion" aria-label="Eventos operativos pendientes de revisión">
        <div class="catalogo-titulo">
            <div>
                <h2>Pendientes de revisión</h2>
                <p>Los resultados de Gemini requieren validación humana antes de cualquier decisión.</p>
            </div>
        </div>

        <div class="catalogo-tarjetas">
            @forelse($filas as $fila)
                @php($evento = $fila['registro'])
                <article class="catalogo-tarjeta">
                    <div class="catalogo-tarjeta-encabezado">
                        <span class="catalogo-grupo-icono" aria-hidden="true">✧</span>
                        <div class="catalogo-grupo-titulo">
                            <h3>{{ $evento->titulo }}</h3>
                            <span>{{ $evento->nombre_interno ?: ($evento->nombre_grupo ?: 'Grupo sin nombre') }} · {{ ucfirst($evento->categoria) }}</span>
                        </div>
                        <span class="catalogo-estado neutral">Por verificar</span>
                    </div>

                    <div class="catalogo-tarjeta-cuerpo">
                        <p style="display:block;line-height:1.6;font-size:12px;white-space:pre-wrap">{{ $evento->resumen }}</p>
                        <p><span>Registrado (UTC)</span><strong>{{ $evento->creado_en }}</strong></p>
                    </div>
                    <div class="catalogo-tarjeta-pie">
                        <div>
                            <strong style="font-size:11px">Mensajes que respaldan esta interpretación</strong>
                            @foreach($fila['fuentes'] as $fuente)
                                <a class="centro-ver-evidencia" style="margin-left:8px" href="{{ route('mensajes.evento', $fuente->evento_crudo_id) }}">Mensaje #{{ $fuente->id }} ↗</a>
                            @endforeach
                        </div>
                    </div>
                </article>
            @empty
                <section class="catalogo-vacio">
                    <h3>Sin eventos propuestos</h3>
                    <p>Gemini está deshabilitado por defecto. Cuando se autorice el piloto y se procesen mensajes nuevos del grupo de prueba, aparecerán aquí las interpretaciones con sus fuentes.</p>
                </section>
            @endforelse
        </div>

        @if($analisis !== null && $analisis->hasPages())
            <nav class="catalogo-paginacion" aria-label="Paginación de eventos IA">
                {{ $analisis->links() }}
            </nav>
        @endif
    </section>
</x-layout>
