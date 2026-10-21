<x-layout title="Mensajero MILAC">
    <section class="centro-hero" data-panorama-url="{{ route('mensajes.actualizacion', [], false) }}" data-credencial-tiempo-real="{{ route('mensajes.credencial.tiempo-real', [], false) }}">
        <div class="centro-hero-texto">
            <span class="centro-hero-marca">EXPRESS MILAC</span>
            <h1>Mensajero <span>MILAC</span></h1>
            <p>Lo que está pasando en los grupos. Monitorea conversaciones, identifica actividad y consulta mensajes reales para tomar decisiones informadas.</p>
            <div class="centro-estado" id="estado-actualizacion" role="status" aria-live="polite">Comprobando mensajes...</div>
        </div>
        <div class="centro-hero-imagen" role="img" aria-label="Tractocamión de carga de Express MILAC representado en una ilustración"></div>
    </section>

    @include('mensajes.partes.panorama-indicadores')

    <section class="seccion-panorama centro-conversaciones" id="grupos-en-seguimiento" aria-label="Explorador de grupos">
        <div class="titulo-panorama centro-cabecera-grupos">
            <div>
                <h2>Grupos en seguimiento <span class="centro-total-grupos">({{ number_format($totalGrupos) }})</span></h2>
                <p class="muted">Mensajes conservados, última actividad y acceso a cada conversación autorizada.</p>
            </div>
            <a class="enlace-panorama" href="{{ route('grupos.indice') }}">Administrar grupos ↗</a>
        </div>

        <form class="filtros-panorama centro-filtros" method="get" action="{{ route('mensajes.indice') }}" aria-label="Filtros del panorama">
            <div class="centro-busqueda">
                <label for="buscar-conversaciones">Buscar en grupos y mensajes recientes</label>
                <input type="search" id="buscar-conversaciones" placeholder="Buscar grupos o mensajes recientes..." autocomplete="off" data-buscar-conversaciones>
                <small>Busca entre las tarjetas y sus últimos ocho mensajes.</small>
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
            <button type="submit" class="filtro-aplicar">Aplicar</button>
            <div class="centro-modos" role="group" aria-label="Disposición de tarjetas">
                <button type="button" class="centro-modo" data-modo-tarjetas="cuadricula" aria-pressed="false" title="Vista de cuadrícula">▦</button>
                <button type="button" class="centro-modo activo" data-modo-tarjetas="lista" aria-pressed="true" title="Vista de lista">☷</button>
            </div>
            @if($actividad !== 'todos' || $orden !== 'recientes')
                <a class="filtro-limpiar" href="{{ route('mensajes.indice') }}">Restablecer</a>
            @endif
        </form>
        <p class="centro-resultados-busqueda" data-resultado-busqueda hidden></p>
        @include('mensajes.partes.panorama-tarjetas')
    </section>

    <section class="centro-alcance" aria-label="Transparencia del análisis">
        <strong>Información verificable</strong>
        <p>Los temas, menciones de posibles acuerdos y asuntos para revisar se detectan mediante expresiones presentes en los mensajes registrados. No son acuerdos confirmados, incidentes validados ni alertas automáticas. Cada mención permite consultar su mensaje original. La muestra abarca hasta 300 mensajes recientes de hoy de los grupos autorizados.</p>
    </section>

    <script src="{{ asset('js/panorama.js') }}?v={{ filemtime(public_path('js/panorama.js')) }}" defer></script>
</x-layout>
