<x-layout title="Historial de grupo">
    <nav class="breadcrumbs" aria-label="Ruta de navegación">
        <a href="{{ route('mensajes.indice') }}">Conversaciones</a>
        <span>/</span>
        <span>Grupo #{{ $grupo->id }}</span>
    </nav>

    <section class="intro compact">
        <p class="eyebrow">CONVERSACIÓN · MENSAJERO MILAC</p>
        <h1>{{ $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre') }}</h1>
        <p class="muted">Lee los mensajes recibidos en este grupo, del más reciente al más antiguo, y consulta la evidencia original cuando necesites revisar el contexto.</p>
        <div class="metadata-row">
            <span>Grupo #{{ $grupo->id }}</span>
            <span>Conexión: {{ optional($grupo->conexion)->nombre_visible ?: optional($grupo->conexion)->clave_conexion }}</span>
            <span>{{ $eventos->total() }} eventos con el filtro actual</span>
        </div>
    </section>

    <section class="card toolbar">
        <form method="get" action="{{ route('mensajes.grupo', $grupo->id) }}" class="filter-row">
            <div>
                <label for="tipo">Tipo de evento</label>
                <select id="tipo" name="tipo">
                    <option value="">Todos</option>
                    @foreach(['mensaje' => 'Mensajes', 'edicion' => 'Ediciones', 'eliminacion' => 'Eliminaciones', 'reaccion' => 'Reacciones', 'otro' => 'Otros'] as $clave => $nombre)
                        <option value="{{ $clave }}" @selected($tipo === $clave)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="tipo_mensaje">Tipo de mensaje</label>
                <select id="tipo_mensaje" name="tipo_mensaje">
                    <option value="">Todos</option>
                    @foreach(['texto' => 'Texto', 'foto' => 'Fotografías', 'video' => 'Videos', 'audio' => 'Audios', 'documento' => 'Documentos', 'ubicacion' => 'Ubicaciones', 'contacto' => 'Contactos', 'otro' => 'Otros'] as $clave => $nombre)
                        <option value="{{ $clave }}" @selected($tipoMensaje === $clave)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="desde">Desde</label>
                <input id="desde" type="date" name="desde" value="{{ $desde }}" aria-label="Fecha desde">
            </div>
            <div>
                <label for="hasta">Hasta</label>
                <input id="hasta" type="date" name="hasta" value="{{ $hasta }}" aria-label="Fecha hasta">
            </div>
            <button type="submit" class="secondary">Filtrar mensajes</button>
            @if($tipo !== '' || $tipoMensaje !== '' || $desde !== '' || $hasta !== '')
                <a href="{{ route('mensajes.grupo', $grupo->id) }}">Limpiar filtros</a>
            @endif
        </form>
    </section>

    <section class="connection-summary" aria-label="Registro de conectividad">
        <div class="connection-summary-heading">
            <div>
                <strong>Registro de conectividad</strong>
                @if($totalPeriodos > 0)
                    <span class="connection-summary-count">{{ $totalPeriodos }} {{ $totalPeriodos === 1 ? 'intervalo registrado' : 'intervalos registrados' }}</span>
                @else
                    <span class="connection-summary-count">Sin intervalos registrados</span>
                @endif
            </div>
            <span class="badge neutral">Información de la conexión</span>
        </div>
        <p>Estos registros corresponden a la conexión del recolector, no son un conteo de mensajes faltantes del grupo. Su existencia o ausencia no demuestra por sí sola una captura completa.</p>
        @if($periodos->isNotEmpty())
            <details class="connection-summary-details">
                <summary>Consultar {{ $periodos->count() === $totalPeriodos ? 'los intervalos' : 'los últimos '.$periodos->count().' intervalos' }}</summary>
                <ul>
                    @foreach($periodos as $periodo)
                        @php
                            $nombresCausas = [
                                'reinicio_no_confirmado' => 'Reinicio sin confirmación',
                                'desconexion_whatsapp' => 'Desconexión de WhatsApp',
                                'fuente_multimedia_ausente' => 'Archivo multimedia no disponible',
                            ];
                            $nombreCausa = $nombresCausas[$periodo->causa] ?? ucfirst(str_replace('_', ' ', $periodo->causa));
                        @endphp
                        <li>
                            <strong>{{ $nombreCausa }}</strong>
                            <span>{{ ($periodo->inicio_en !== null ? $periodo->inicio_en->timezone(config('app.timezone'))->format('d/m/Y H:i:s') : 'Sin fecha') }} — {{ ($periodo->fin_en !== null ? $periodo->fin_en->timezone(config('app.timezone'))->format('d/m/Y H:i:s') : 'Sin fecha') }}</span>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    <p class="fine processing-help">Estado «Capturado»: evidencia original almacenada en SQL Server; la normalización automática sigue desactivada. No impide consultar el texto y su evidencia.</p>

    <section class="conversacion-lectura" aria-label="Mensajes del grupo">
        <div class="encabezado-conversacion">
            <h2>Conversación registrada</h2>
            <p class="fine">{{ $eventos->total() }} eventos · Del más reciente al más antiguo · Solo lectura</p>
        </div>
        @php($diaAnterior = null)
        @forelse($eventos as $fila)
            @php($registro = $fila['evento'])
            @php($fechaLocal = ($registro->recibido_en !== null ? $registro->recibido_en->timezone(config('app.timezone')) : null))
            @php($dia = ($fechaLocal !== null ? $fechaLocal->format('Y-m-d') : 'sin-fecha'))
            @if($dia !== $diaAnterior)
                <div class="separador-fecha" role="heading" aria-level="3">
                    {{ ($fechaLocal !== null ? $fechaLocal->format('d/m/Y') : 'Fecha desconocida') }}
                </div>
                @php($diaAnterior = $dia)
            @endif
            <article class="mensaje-fila" aria-label="Evento #{{ $registro->id }}">
                <span class="mensaje-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($fila['remitente'], 0, 1)) }}</span>
                <div class="mensaje-burbuja">
                    <div class="mensaje-cabecera">
                        <strong class="mensaje-autor">{{ $fila['remitente'] }}</strong>
                        @if($fila['telefono_remitente'] && ! str_starts_with($fila['remitente'], 'Teléfono +'))
                            <span class="telefono-participante">+{{ $fila['telefono_remitente'] }}</span>
                        @endif
                        <time datetime="{{ ($registro->recibido_en !== null ? $registro->recibido_en->toIso8601String() : '') }}">{{ ($fechaLocal !== null ? $fechaLocal->format('H:i:s') : 'Sin hora') }}</time>
                    </div>
                    @if($registro->tipo_evento !== 'mensaje')
                        <span class="etiqueta-tipo-mensaje">{{ [
                            'edicion' => 'Edición',
                            'eliminacion' => 'Eliminación',
                            'reaccion' => 'Reacción',
                        ][$registro->tipo_evento] ?? 'Otro evento' }}</span>
                    @endif
                    <p class="mensaje-texto">{{ \Illuminate\Support\Str::limit($fila['resumen'], 1500) }}</p>
                    @if($registro->archivo)
                        @include('mensajes.partes.multimedia-privada', ['archivo' => $registro->archivo])
                    @endif
                    <div class="mensaje-pie">
                        <span>Evento #{{ $registro->id }}</span>
                        @if($registro->tipo_mensaje && $registro->tipo_mensaje !== 'texto')
                            <span>{{ ucfirst($registro->tipo_mensaje) }}</span>
                        @endif
                        @if($registro->id_mensaje_citado_proveedor)
                            <span>Con cita</span>
                        @endif
                        <span>{{ [
                            'capturado' => 'Capturado',
                            'normalizado' => 'Normalizado',
                            'pendiente_semantica' => 'Pendiente de interpretación',
                        ][$registro->estado_procesamiento] ?? 'Otro estado' }}</span>
                        <a href="{{ route('mensajes.evento', $registro->id) }}">Ver evidencia →</a>
                        @if($registro->tipo_evento === 'mensaje' && $registro->id_remitente_proveedor)
                            <a href="{{ route('remitentes.editar', $registro->id) }}">Identificar remitente →</a>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <section class="card empty-state">
                <h2>No hay eventos para este filtro.</h2>
                <p class="muted">Prueba otro tipo de evento o consulta los registros cuando el recolector haya capturado mensajes nuevos.</p>
            </section>
        @endforelse
    </section>

    @if($eventos->hasPages())
        <nav class="pagination" aria-label="Páginas del historial">
            @if($eventos->onFirstPage())<span>Anterior</span>@else<a href="{{ $eventos->previousPageUrl() }}">Anterior</a>@endif
            <span>Página {{ $eventos->currentPage() }} de {{ $eventos->lastPage() }}</span>
            @if($eventos->hasMorePages())<a href="{{ $eventos->nextPageUrl() }}">Siguiente</a>@else<span>Siguiente</span>@endif
        </nav>
    @endif
</x-layout>
