<x-layout title="Panorama ejecutivo">
    <section class="intro compact introduccion-ejecutiva cabecera-ejecutiva" data-panorama-url="{{ route('mensajes.actualizacion', [], false) }}" data-panorama-intervalo="12000">
        <div class="cabecera-ejecutiva-contenido">
            <div class="cabecera-etiqueta"><span class="cabecera-raya"></span> CENTRO DE INFORMACIÓN · EXPRESS MILAC</div>
            <h1>Panorama ejecutivo <span>de conversaciones</span></h1>
            <p class="cabecera-descripcion">Lo que está pasando en los grupos: actividad reciente, quién participa y contenido de los mensajes, en un solo lugar.</p>
            <div class="cabecera-acciones">
                <a class="accion-primaria" href="#grupos-en-seguimiento">Explorar conversaciones <span aria-hidden="true">↗</span></a>
                <button type="button" class="accion-secundaria" data-actualizar-manual>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.8 9a7 7 0 0 1 12-2L20 12M4 12l2.2 3A7 7 0 0 0 18.5 15"/></svg>
                    Actualizar ahora
                </button>
            </div>
        </div>
        <div class="cabecera-panel" aria-label="Estado de actualización">
            <span class="cabecera-panel-rubro">PANORAMA DE ACTIVIDAD</span>
            <div class="cabecera-panel-sello"><span class="sello-punto"></span> Consulta automática</div>
            <p>La información se consulta cada 12 segundos mientras tengas abierto el tablero.</p>
            <div class="cabecera-panel-pie" id="estado-actualizacion" role="status" aria-live="polite">Comprobando mensajes...</div>
        </div>
    </section>

    <section class="seccion-indicadores-ejecutivos" aria-label="Visión general de actividad">
        <div class="linea-seccion">
            <div><span class="seccion-numero">01 / PANORAMA</span><h2>Actividad de los grupos</h2></div>
            <span class="seccion-descripcion">Información registrada, no estimaciones</span>
        </div>
        @include('mensajes.partes.panorama-indicadores')
    </section>

    <section class="seccion-panorama" id="grupos-en-seguimiento" aria-label="Explorador de grupos">
        <div class="titulo-panorama">
            <div>
                <span class="seccion-numero">02 / CONVERSACIONES</span>
                <h2>Grupos y sus mensajes recientes</h2>
                <p class="muted">Consulta los mensajes de cada grupo en su carrusel independiente. Los estados reflejan únicamente mensajes registrados, no niveles de prioridad.</p>
            </div>
            <a class="enlace-panorama" href="{{ route('grupos.indice') }}">Administrar grupos <span aria-hidden="true">↗</span></a>
        </div>

        <form class="filtros-panorama" method="get" action="{{ route('mensajes.indice') }}" aria-label="Filtros del panorama">
            <div class="filtro-grupo">
                <label for="actividad">ACTIVIDAD</label>
                <select id="actividad" name="actividad">
                    <option value="todos" @selected($actividad === 'todos')>Todos los grupos</option>
                    <option value="hoy" @selected($actividad === 'hoy')>Con mensajes hoy</option>
                    <option value="sin_actividad" @selected($actividad === 'sin_actividad')>Sin mensajes registrados hoy</option>
                </select>
            </div>
            <div class="filtro-grupo">
                <label for="orden">ORDENAR POR</label>
                <select id="orden" name="orden">
                    <option value="recientes" @selected($orden === 'recientes')>Última actividad</option>
                    <option value="actividad" @selected($orden === 'actividad')>Más mensajes hoy</option>
                    <option value="nombre" @selected($orden === 'nombre')>Nombre del grupo</option>
                </select>
            </div>
            <button type="submit" class="filtro-aplicar">Aplicar filtros <span aria-hidden="true">→</span></button>
            @if($actividad !== 'todos' || $orden !== 'recientes')
                <a class="filtro-limpiar" href="{{ route('mensajes.indice') }}">Restablecer</a>
            @endif
        </form>
        @include('mensajes.partes.panorama-tarjetas')
    </section>

    <section class="nota-interpretacion" aria-label="Alcance de la información">
        <div>
            <span class="seccion-numero">LECTURA RESPONSABLE</span>
            <h2>De la conversación a las decisiones</h2>
            <p>Este panorama muestra mensajes reales. Los acuerdos, responsables y pendientes se incorporarán únicamente cuando exista análisis verificable de su contenido y puedan consultarse las fuentes originales.</p>
        </div>
        <span>Información trazable<br>Sin prioridades inventadas</span>
    </section>

    <script src="{{ asset('js/panorama.js') }}?v={{ filemtime(public_path('js/panorama.js')) }}" defer></script>
</x-layout>
