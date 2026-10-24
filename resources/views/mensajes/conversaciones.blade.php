<x-layout title="Conversaciones">
    <section class="centro-pagina-cabecera centro-conversaciones-cabecera" data-panorama-url="{{ route('mensajes.actualizacion', [], false) }}" data-panorama-intervalo="12000">
        <div>
            <span class="centro-hero-marca">MENSAJERO MILAC / CONSULTA</span>
            <h1>Explorar conversaciones</h1>
            <p>Consulta los eventos interpretados por Gemini a la izquierda y los mensajes originales a la derecha. Entra a cada conversación para ver su historial.</p>
        </div>
        <div class="centro-cabecera-acceso">
            <span id="estado-actualizacion" role="status" aria-live="polite">Comprobando mensajes...</span>
            <button type="button" class="centro-actualizar" data-actualizar-manual>↻ Actualizar ahora</button>
        </div>
    </section>

    <section class="centro-conversaciones" aria-label="Conversaciones consultables">
        <div class="titulo-panorama centro-cabecera-grupos">
            <div><h2>Conversaciones recientes</h2><p class="muted">Cada grupo presenta sus eventos interpretados y mensajes originales en columnas independientes. No se incluyen grupos fuera del monitoreo autorizado.</p></div>
            <a class="enlace-panorama" href="{{ route('grupos.indice') }}">Ver catálogo de grupos →</a>
        </div>
        <form class="filtros-panorama centro-filtros" method="get" action="{{ route('mensajes.indice') }}" aria-label="Filtros de conversaciones">
            <div class="centro-busqueda">
                <label for="buscar-conversaciones">Buscar en mensajes recientes</label>
                <input id="buscar-conversaciones" type="search" data-buscar-conversaciones placeholder="Nombre del grupo o texto reciente..." autocomplete="off">
                <small>La búsqueda instantánea se limita a los eventos y mensajes cargados.</small>
            </div>
            <div class="filtro-grupo">
                <label for="actividad">Actividad</label>
                <select id="actividad" name="actividad">
                    <option value="todos" @selected($actividad === 'todos')>Todos los grupos</option>
                    <option value="hoy" @selected($actividad === 'hoy')>Con mensajes hoy</option>
                    <option value="sin_actividad" @selected($actividad === 'sin_actividad')>Sin mensajes hoy</option>
                </select>
            </div>
            <div class="filtro-grupo">
                <label for="orden">Ordenar por</label>
                <select id="orden" name="orden">
                    <option value="recientes" @selected($orden === 'recientes')>Más reciente</option>
                    <option value="actividad" @selected($orden === 'actividad')>Más actividad</option>
                    <option value="nombre" @selected($orden === 'nombre')>Nombre</option>
                </select>
            </div>
            <button class="filtro-aplicar" type="submit">Aplicar</button>
            @if($actividad !== 'todos' || $orden !== 'recientes')
                <a class="filtro-limpiar" href="{{ route('mensajes.indice') }}">Restablecer</a>
            @endif
        </form>
        <p class="centro-resultados-busqueda" data-resultado-busqueda hidden></p>
        @include('mensajes.partes.panorama-tarjetas')
    </section>
    <script src="{{ asset('js/panorama.js') }}?v={{ filemtime(public_path('js/panorama.js')) }}" defer></script>
</x-layout>
