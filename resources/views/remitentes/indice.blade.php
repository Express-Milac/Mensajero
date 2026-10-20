<x-layout title="Remitentes">
    <section class="catalogo-encabezado">
        <div>
            <span class="centro-hero-marca">MENSAJERO MILAC / PERSONAS</span>
            <h1>Identificar remitentes</h1>
            <p>Consulta quién participa en los grupos autorizados. Cuando WhatsApp no informa su nombre, puedes asignar uno interno sin modificar los mensajes conservados.</p>
        </div>
        <a class="catalogo-volver" href="{{ route('mensajes.indice') }}">Volver a conversaciones →</a>
    </section>

    <section class="catalogo-seccion" aria-label="Personas detectadas">
        <div class="catalogo-titulo">
            <div>
                <h2>Remitentes detectados</h2>
                <p>{{ number_format($remitentes->total()) }} identificadores distintos{{ $grupoId !== null ? ' en el grupo seleccionado' : ' en los grupos autorizados' }}.</p>
            </div>
        </div>
        <form class="catalogo-filtros" method="get" action="{{ route('remitentes.indice') }}">
            <div>
                <label for="grupo">Filtrar por grupo</label>
                <select id="grupo" name="grupo">
                    <option value="">Todos los grupos autorizados</option>
                    @foreach($grupos as $grupo)
                        <option value="{{ $grupo->id }}" @selected($grupoId === (int) $grupo->id)>{{ $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre') }}</option>
                    @endforeach
                </select>
            </div>
            <button class="catalogo-aplicar" type="submit">Aplicar</button>
            @if($grupoId !== null)<a class="catalogo-limpiar" href="{{ route('remitentes.indice') }}">Ver todos</a>@endif
        </form>

        <p class="catalogo-ayuda-gestor"><strong>Identificación:</strong> el nombre que aparece en WhatsApp es elegido por el participante y no equivale a identidad verificada. El nombre interno que asignes tendrá preferencia en el tablero, las conversaciones y el historial, incluso para mensajes anteriores. Los identificadores privados permanecen protegidos.</p>

        <div class="catalogo-tarjetas">
            @forelse($filas as $fila)
                <article class="catalogo-tarjeta">
                    <div class="catalogo-tarjeta-encabezado">
                        <span class="catalogo-grupo-icono" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>
                        </span>
                        <div class="catalogo-grupo-titulo">
                            <h3>{{ $fila['nombre'] }}</h3>
                            <span>{{ $fila['nombre_asignado'] ? 'Nombre interno asignado' : ($fila['nombre_whatsapp'] ? 'Nombre de perfil de WhatsApp' : 'Pendiente de identificación') }}</span>
                        </div>
                    </div>
                    <div class="catalogo-tarjeta-cuerpo">
                        <p><span>Último grupo</span><strong>{{ $fila['evento']->chat?->nombre_interno ?: ($fila['evento']->chat?->nombre_grupo ?: 'Grupo sin nombre') }}</strong></p>
                        <p><span>Mensajes registrados</span><strong>{{ number_format($fila['mensajes']) }}</strong></p>
                        <p><span>Última participación</span><strong>{{ $fila['evento']->recibido_en?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Sin fecha' }}</strong></p>
                    </div>
                    <div class="catalogo-tarjeta-pie">
                        <a class="catalogo-ver-conversacion" href="{{ route('remitentes.editar', $fila['evento']->id) }}">Identificar o corregir nombre →</a>
                        <a class="centro-ver-evidencia" href="{{ route('mensajes.evento', $fila['evento']->id) }}">Ver mensaje original ↗</a>
                    </div>
                </article>
            @empty
                <section class="catalogo-vacio"><h3>Sin remitentes identificables</h3><p>Todavía no se encontraron identificadores de remitentes en los grupos autorizados para este filtro.</p></section>
            @endforelse
        </div>
        @if($remitentes->hasPages())
            <nav class="catalogo-paginacion" aria-label="Paginación de remitentes">
                @if($remitentes->onFirstPage())<span>← Anterior</span>@else<a href="{{ $remitentes->previousPageUrl() }}">← Anterior</a>@endif
                <span>Página {{ $remitentes->currentPage() }} de {{ $remitentes->lastPage() }}</span>
                @if($remitentes->hasMorePages())<a href="{{ $remitentes->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif
            </nav>
        @endif
    </section>
</x-layout>
