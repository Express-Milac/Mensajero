<x-layout title="Panorama de conversaciones">
    <section class="intro compact introduccion-ejecutiva">
        <p class="eyebrow">MENSAJERO MILAC · PANORAMA DE CONVERSACIONES</p>
        <h1>Lo que está pasando en los grupos</h1>
        <p class="muted">Mensajes recientes, personas que participan y movimiento del día. Abre un grupo para consultar toda la conversación.</p>
    </section>

    <section class="indicadores-ejecutivos" aria-label="Resumen de conversaciones">
        <article class="card indicador-ejecutivo">
            <span>Grupos en seguimiento</span>
            <strong>{{ number_format($totalGrupos) }}</strong>
            <small>Conversaciones seleccionadas</small>
        </article>
        <article class="card indicador-ejecutivo">
            <span>Mensajes registrados hoy</span>
            <strong>{{ number_format($mensajesHoy) }}</strong>
            <small>Contados por fecha de recepción</small>
        </article>
        <article class="card indicador-ejecutivo">
            <span>Grupos con mensajes hoy</span>
            <strong>{{ number_format($cantidadConActividad) }}</strong>
            <small>De {{ number_format($totalGrupos) }} grupos seleccionados</small>
        </article>
        <article class="card indicador-ejecutivo">
            <span>Último mensaje recibido</span>
            <strong class="indicador-fecha">{{ $ultimaParticipacion?->timezone(config('app.timezone'))->format('d/m H:i') ?? 'Sin mensajes' }}</strong>
            <small>Fecha y hora local</small>
        </article>
    </section>

    <section class="seccion-panorama" aria-label="Explorador de grupos">
        <div class="titulo-panorama">
            <div>
                <p class="eyebrow">CONVERSACIONES</p>
                <h2>Grupos y sus mensajes recientes</h2>
                <p class="muted">Cada tarjeta muestra hasta tres mensajes reales conservados. Los colores identifican tarjetas; no califican desempeño ni urgencia.</p>
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

        <div class="cabecera-carrusel">
            <span>{{ $tarjetas->count() }} grupos en esta vista</span>
            <span>Desplázate hacia abajo para explorar tarjetas ↓</span>
        </div>
        <div class="carrusel-grupos-vertical" tabindex="0" role="region" aria-label="Carrusel vertical de tarjetas de grupos">
            @forelse($tarjetas as $indice => $tarjeta)
                @php($grupo = $tarjeta['grupo'])
                @php($identidadVisual = (int) $grupo->id % 6)
                @php($iconos = ['💬', '👥', '📣', '🗨️', '📝', '📨'])
                <article class="tarjeta-directiva tarjeta-directiva-{{ $identidadVisual }}">
                    <div class="tarjeta-directiva-principal">
                        <div class="icono-tarjeta" aria-hidden="true">{{ $iconos[$identidadVisual] }}</div>
                        <div class="titulo-tarjeta">
                            <h3>{{ $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre') }}</h3>
                            <p>{{ $tarjeta['hoy'] }} {{ $tarjeta['hoy'] === 1 ? 'mensaje recibido hoy' : 'mensajes recibidos hoy' }}</p>
                        </div>
                        @if($tarjeta['ensayo'])
                            <span class="etiqueta-ensayo">Ensayo</span>
                        @endif
                    </div>

                    @if(count($tarjeta['mensajes']) > 0)
                        <div class="fragmentos-grupo" aria-label="Últimos mensajes capturados">
                            @foreach($tarjeta['mensajes'] as $mensaje)
                                <div class="fragmento-grupo">
                                    <div class="fragmento-datos">
                                        <span class="fragmento-remitente">{{ $mensaje['remitente'] }}</span>
                                        <time datetime="{{ $mensaje['fecha']?->toIso8601String() }}">{{ $mensaje['fecha']?->timezone(config('app.timezone'))->format('d/m H:i') ?? 'Sin fecha' }}</time>
                                    </div>
                                    <p>{{ $mensaje['texto'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="sin-mensajes-grupo">Todavía no hay mensajes conservados de este grupo para mostrar.</p>
                    @endif
                    <div class="pie-tarjeta-directiva">
                        <span>{{ $tarjeta['ultima']?->timezone(config('app.timezone'))->format('Último mensaje: d/m/Y H:i') ?? 'Sin mensajes registrados' }}</span>
                        <a href="{{ route('mensajes.grupo', $grupo->id) }}">Abrir conversación <span aria-hidden="true">→</span></a>
                    </div>
                </article>
            @empty
                <section class="card empty-state">
                    <h3>No hay grupos para este filtro</h3>
                    <p class="muted">Puedes cambiar el filtro o seleccionar nuevos grupos desde su administración.</p>
                </section>
            @endforelse
        </div>
    </section>

    <section class="nota-interpretacion" aria-label="Alcance de la información">
        <div>
            <h2>Del mensaje a la decisión</h2>
            <p>Por ahora esta pantalla muestra lo que las personas escribieron y cuándo. Los acuerdos, responsables, acciones y pendientes aparecerán aquí cuando el análisis de IA esté habilitado y cada conclusión pueda consultarse junto a sus mensajes de origen.</p>
        </div>
        <span>Contenido verificable · Sin conclusiones automáticas</span>
    </section>
</x-layout>
