<x-layout title="Evidencia original">
    <nav class="breadcrumbs" aria-label="Ruta de navegación">
        <a href="{{ route('mensajes.indice') }}">Mensajes originales</a>
        <span>/</span>
        <a href="{{ route('mensajes.grupo', $registro->chat_monitoreado_id) }}">Grupo #{{ $registro->chat_monitoreado_id }}</a>
        <span>/</span>
        <span>Evento #{{ $registro->id }}</span>
    </nav>

    <section class="intro compact">
        <p class="eyebrow">EVIDENCIA SIN MODIFICACIONES</p>
        <h1>Evento #{{ $registro->id }}</h1>
        <p class="muted">Información fuente del recolector. Las ediciones y eliminaciones aparecen como eventos distintos, sin sobrescribir el contenido original.</p>
    </section>

    <section class="card">
        <div class="group-title-row">
            <div>
                <span class="badge">{{ $registro->tipo_evento }}</span>
                <span class="badge neutral">{{ $registro->tipo_mensaje ?: 'Sin clasificar' }}</span>
                <h2 class="evidence-title">{{ $resumen }}</h2>
            </div>
            <span class="badge neutral">{{ str_replace('_', ' ', $registro->estado_procesamiento) }}</span>
        </div>
        <dl class="evidence-metadata">
            <div><dt>Recibido</dt><dd>{{ $registro->recibido_en?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?? 'Sin fecha' }}</dd></div>
            <div><dt>Identificador externo</dt><dd class="identifier">{{ $registro->id_evento_externo ?: 'No informado' }}</dd></div>
            <div><dt>Remitente del proveedor</dt><dd class="identifier">{{ $registro->id_remitente_proveedor ?: 'No informado' }}</dd></div>
            <div><dt>Mensaje citado</dt><dd class="identifier">{{ $registro->id_mensaje_citado_proveedor ?: 'No informado' }}</dd></div>
            <div><dt>Mensaje objetivo</dt><dd class="identifier">{{ $registro->id_mensaje_objetivo_proveedor ?: 'No informado' }}</dd></div>
            <div><dt>Versión del contrato</dt><dd>{{ $registro->version_contrato }}</dd></div>
            <div><dt>SHA-256 del contenido</dt><dd class="identifier">{{ $registro->hash_contenido_sha256 }}</dd></div>
            <div><dt>Normalización</dt><dd>{{ $registro->mensaje ? 'Mensaje #' . $registro->mensaje->id : 'Sin proyección a mensaje' }}</dd></div>
        </dl>
    </section>

    @if($registro->archivo)
        <section class="card evidence-section">
            <h2>Archivo privado asociado</h2>
            <p class="muted">El binario no está publicado por esta interfaz; permanece en el almacenamiento privado.</p>
            <dl class="evidence-metadata">
                <div><dt>Tipo</dt><dd>{{ $registro->archivo->tipo_mime ?: 'Desconocido' }}</dd></div>
                <div><dt>Nombre</dt><dd>{{ $registro->archivo->nombre_original ?: 'No informado' }}</dd></div>
                <div><dt>Tamaño</dt><dd>{{ number_format((int) $registro->archivo->tamano_bytes) }} bytes</dd></div>
                <div><dt>Estado</dt><dd>{{ $registro->archivo->estado }}</dd></div>
                <div><dt>SHA-256</dt><dd class="identifier">{{ $registro->archivo->hash_sha256 }}</dd></div>
            </dl>
        </section>
    @endif

    <section class="card evidence-section">
        <div class="group-title-row">
            <div>
                <p class="eyebrow" style="margin:0">ORIGEN DEL REGISTRO</p>
                <h2>Contenido crudo</h2>
            </div>
            <span class="badge neutral">Solo lectura</span>
        </div>
        @if($truncado)
            <p class="notice integrity-warning">La vista muestra los primeros 100,000 caracteres para proteger el rendimiento del navegador. El contenido íntegro permanece en SQL Server.</p>
        @endif
        <pre class="raw-json">{{ $contenido }}</pre>
    </section>
</x-layout>
