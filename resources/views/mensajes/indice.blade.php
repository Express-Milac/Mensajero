<x-layout title="Panorama de conversaciones">
    <section class="intro compact introduccion-ejecutiva" data-panorama-url="{{ route('mensajes.actualizacion', [], false) }}" data-panorama-intervalo="12000">
        <p class="eyebrow">MENSAJERO MILAC · PANORAMA DE CONVERSACIONES</p>
        <h1>Lo que está pasando en los grupos</h1>
        <p class="muted">Mensajes recientes, personas que participan y movimiento del día. Abre un grupo para consultar toda la conversación.</p>
    </section>

    @include('mensajes.partes.panorama-indicadores')

    <section class="seccion-panorama" aria-label="Explorador de grupos">
        <div class="titulo-panorama">
            <div>
                <p class="eyebrow">CONVERSACIONES</p>
                <h2>Grupos y sus mensajes recientes</h2>
                <p class="muted">Cada tarjeta tiene su propio carrusel vertical de hasta ocho mensajes conservados, con desplazamiento independiente. Los colores identifican grupos, no califican desempeño ni urgencia.</p>
            </div>
            <a class="enlace-panorama" href="{{ route('grupos.indice') }}">Gestionar grupos →</a>
        </div>

        <form class="filtros-panorama" method="get" action="{{ route('mensajes.indice') }}" aria-label="Filtros del panorama">
            <div>
                <label for="actividad">Mensajes del día</label>
                <select id="actividad" name="actividad">
                    <option value="todos" @selected($actividad === 'todos')>Todos los grupos</option>
                    <option value="hoy" @selected($actividad === 'hoy')>Con mensajes hoy</option>
                    <option value="sin_actividad" @selected($actividad === 'sin_actividad')>Sin mensajes registrados hoy</option>
                </select>
            </div>
            <div>
                <label for="orden">Ordenar grupos</label>
                <select id="orden" name="orden">
                    <option value="recientes" @selected($orden === 'recientes')>Mensaje más reciente</option>
                    <option value="actividad" @selected($orden === 'actividad')>Más mensajes hoy</option>
                    <option value="nombre" @selected($orden === 'nombre')>Nombre del grupo</option>
                </select>
            </div>
            <button type="submit" class="secondary">Aplicar</button>
            @if($actividad !== 'todos' || $orden !== 'recientes')
                <a href="{{ route('mensajes.indice') }}">Limpiar filtros</a>
            @endif
        </form>

        <div class="estado-actualizacion" id="estado-actualizacion" role="status" aria-live="polite">Actualización automática cada 12 segundos · Comprobando mensajes</div>
        @include('mensajes.partes.panorama-tarjetas')
    </section>

    <section class="nota-interpretacion" aria-label="Alcance de la información">
        <div>
            <h2>Del mensaje a la decisión</h2>
            <p>Por ahora esta pantalla muestra lo que las personas escribieron y cuándo. Los acuerdos, responsables, acciones y pendientes aparecerán aquí cuando el análisis de IA esté habilitado y cada conclusión pueda consultarse junto a sus mensajes de origen.</p>
        </div>
        <span>Contenido verificable · Sin conclusiones automáticas</span>
    </section>
    <script src="{{ asset('js/panorama.js') }}?v={{ filemtime(public_path('js/panorama.js')) }}" defer></script>
</x-layout>
