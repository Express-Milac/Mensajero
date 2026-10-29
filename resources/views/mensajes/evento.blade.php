<x-layout title="Detalle del mensaje">
    <div class="pagina-detalle-evidencia">
    <nav class="breadcrumbs" aria-label="Ruta de navegación">
        <a href="{{ route('mensajes.indice') }}">Conversaciones</a>
        <span>/</span>
        <a href="{{ route('mensajes.grupo', $registro->chat_monitoreado_id) }}">{{ optional($registro->chat)->nombre_interno ?: (optional($registro->chat)->nombre_grupo ?: 'Grupo #'.$registro->chat_monitoreado_id) }}</a>
        <span>/</span>
        <span>Evento #{{ $registro->id }}</span>
    </nav>

    <section class="intro compact">
        <p class="eyebrow">MENSAJERO MILAC / CONVERSACIONES / EVIDENCIA</p>
        <h1>Detalle del mensaje</h1>
        <p class="muted">Consulta el contenido, la persona que lo envió y su fecha. La evidencia original permanece protegida y los detalles técnicos están disponibles al final.</p>
    </section>

    <section class="card detalle-mensaje" aria-label="Mensaje y remitente">
        <div class="detalle-mensaje-encabezado">
            <div>
                <span class="etiqueta-tipo-mensaje">{{ ucfirst($registro->tipo_evento) }} · {{ $registro->tipo_mensaje ?: 'Tipo sin clasificar' }}</span>
                <h2>Contenido del mensaje</h2>
                <p class="detalle-remitente">{{ $remitente }}</p>
                @if($telefonoRemitente && ! str_starts_with($remitente, 'Teléfono +'))
                    <p class="telefono-participante">Celular registrado: +{{ $telefonoRemitente }}</p>
                @endif
                @if($registro->tipo_evento === 'mensaje' && $registro->id_remitente_proveedor)
                    <a class="centro-ver-evidencia" href="{{ route('remitentes.editar', $registro->id) }}">Identificar o corregir nombre de esta persona →</a>
                @endif
                <time datetime="{{ ($registro->recibido_en !== null ? $registro->recibido_en->toIso8601String() : '') }}">{{ ($registro->recibido_en !== null ? $registro->recibido_en->timezone(config('app.timezone'))->format('d/m/Y · H:i:s') : 'Sin fecha') }}</time>
            </div>
            <span class="estado-mensaje">{{ [
                'capturado' => 'Capturado',
                'normalizado' => 'Normalizado',
                'pendiente_semantica' => 'Pendiente de interpretación',
            ][$registro->estado_procesamiento] ?? 'Estado no clasificado' }}</span>
        </div>
        <p class="detalle-contenido">{{ $resumen }}</p>
        <p class="detalle-nota">Mensaje #{{ $registro->id }} · Vista de lectura, sin modificaciones sobre la evidencia original.</p>
    </section>

    @if($registro->archivo)
        <section class="card evidence-section">
            <h2>Archivo privado asociado</h2>
            <p class="muted">El archivo se entrega únicamente después de verificar nuevamente su autorización e integridad.</p>
            @include('mensajes.partes.multimedia-privada', ['archivo' => $registro->archivo])
            <details class="detalle-tecnico">
                <summary>Datos del archivo e integridad</summary>
                <dl class="evidence-metadata">
                    <div><dt>Tipo</dt><dd>{{ $registro->archivo->tipo_mime ?: 'Desconocido' }}</dd></div>
                    <div><dt>Nombre</dt><dd>{{ $registro->archivo->nombre_original ?: 'No informado' }}</dd></div>
                    <div><dt>Tamaño</dt><dd>{{ number_format((int) $registro->archivo->tamano_bytes) }} bytes</dd></div>
                    <div><dt>Estado</dt><dd>{{ $registro->archivo->estado }}</dd></div>
                    <div><dt>SHA-256</dt><dd class="identifier">{{ $registro->archivo->hash_sha256 }}</dd></div>
                </dl>
            </details>
        </section>
    @endif

    <section class="card evidence-section seccion-trazabilidad">
        <p class="eyebrow">INFORMACIÓN ADICIONAL</p>
        <h2>Trazabilidad del mensaje</h2>
        <p class="muted">Estos datos sirven para verificaciones y auditorías. No cambian el mensaje ni su autor.</p>

        <details class="detalle-tecnico">
            <summary>Identificadores y verificación SHA-256</summary>
            <dl class="evidence-metadata">
                <div><dt>Evento</dt><dd>#{{ $registro->id }}</dd></div>
                <div><dt>Recibido</dt><dd>{{ ($registro->recibido_en !== null ? $registro->recibido_en->timezone(config('app.timezone'))->format('d/m/Y H:i:s') : 'Sin fecha') }}</dd></div>
                <div><dt>Identificador externo</dt><dd class="identifier">{{ $registro->id_evento_externo ?: 'No informado' }}</dd></div>
                <div><dt>Remitente</dt><dd class="identifier">{{ $remitente }}</dd></div>
                <div><dt>Mensaje citado</dt><dd class="identifier">{{ $registro->id_mensaje_citado_proveedor ?: 'No informado' }}</dd></div>
                <div><dt>Mensaje objetivo</dt><dd class="identifier">{{ $registro->id_mensaje_objetivo_proveedor ?: 'No informado' }}</dd></div>
                <div><dt>Versión del contrato</dt><dd>{{ $registro->version_contrato }}</dd></div>
                <div><dt>SHA-256 del contenido</dt><dd class="identifier">{{ $registro->hash_contenido_sha256 }}</dd></div>
                <div><dt>Normalización</dt><dd>{{ $registro->mensaje ? 'Mensaje #' . $registro->mensaje->id : 'Pendiente: solo evidencia capturada' }}</dd></div>
            </dl>
        </details>

        <details class="detalle-tecnico">
            <summary>Vista protegida del contenido crudo</summary>
            @if($truncado)
                <p class="fine">El contenido supera el tamaño máximo de vista previa y no se muestra para evitar divulgar fragmentos sensibles.</p>
            @endif
            <p class="fine">Los identificadores de participantes y campos confidenciales se ocultan únicamente en esta representación. La evidencia original permanece íntegra en SQL Server y puede contrastarse mediante su SHA-256.</p>
            <pre class="raw-json">{{ $contenido }}</pre>
        </details>
    </section>
    </div>
</x-layout>
