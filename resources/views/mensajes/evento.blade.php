<x-layout title="Detalle de evidencia">
    <nav class="breadcrumbs" aria-label="Ruta de navegación">
        <a href="{{ route('mensajes.indice') }}">Mensajes originales</a>
        <span>/</span>
        <a href="{{ route('mensajes.grupo', $registro->chat_monitoreado_id) }}">Grupo #{{ $registro->chat_monitoreado_id }}</a>
        <span>/</span>
        <span>Evento #{{ $registro->id }}</span>
    </nav>

    <section class="intro compact">
        <p class="eyebrow">CONSULTA DE EVIDENCIA · SOLO LECTURA</p>
        <h1>Detalle del mensaje</h1>
        <p class="muted">Evento #{{ $registro->id }}. La evidencia íntegra se conserva en SQL Server, con datos técnicos disponibles para su verificación.</p>
    </section>

    <section class="card detalle-mensaje" aria-label="Mensaje y remitente">
        <div class="detalle-mensaje-encabezado">
            <div>
                <span class="etiqueta-tipo-mensaje">{{ ucfirst($registro->tipo_evento) }} · {{ $registro->tipo_mensaje ?: 'Tipo sin clasificar' }}</span>
                <h2>Mensaje original</h2>
                <p class="detalle-remitente">{{ $remitente }}</p>
                <time datetime="{{ $registro->recibido_en?->toIso8601String() }}">{{ $registro->recibido_en?->timezone(config('app.timezone'))->format('d/m/Y · H:i:s') ?? 'Sin fecha' }}</time>
            </div>
            <span class="estado-mensaje">{{ match ($registro->estado_procesamiento) {
                'capturado' => 'Capturado',
                'normalizado' => 'Normalizado',
                'pendiente_semantica' => 'Pendiente de interpretación',
                default => 'Estado no clasificado',
            } }}</span>
        </div>
        <p class="detalle-contenido">{{ $resumen }}</p>
        <p class="detalle-nota">Este contenido es una representación de consulta; los registros de origen no se modifican.</p>
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
        <p class="eyebrow">TRAZABILIDAD</p>
        <h2>Información de la evidencia</h2>
        <p class="muted">Los datos de auditoría y el contenido crudo protegido están disponibles cuando se necesiten, sin ocupar la lectura principal.</p>

        <details class="detalle-tecnico">
            <summary>Identificadores y verificación SHA-256</summary>
            <dl class="evidence-metadata">
                <div><dt>Evento</dt><dd>#{{ $registro->id }}</dd></div>
                <div><dt>Recibido</dt><dd>{{ $registro->recibido_en?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?? 'Sin fecha' }}</dd></div>
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
</x-layout>
