<x-layout title="Mensajes originales">
    <section class="intro compact">
        <p class="eyebrow">CAPTURA · GRUPOS AUTORIZADOS</p>
        <h1>Mensajes originales</h1>
        <p class="muted">Consulta de la evidencia registrada en SQL Server. La interpretación con IA aún no está habilitada y esta vista no modifica los mensajes.</p>
    </section>

    <section class="metric-grid" aria-label="Resumen de captura">
        <article class="card metric">
            <p class="eyebrow">GRUPOS MONITOREADOS</p>
            <strong>{{ $grupos->count() }}</strong>
            <span>Activos en la lista permitida</span>
        </article>
        <article class="card metric">
            <p class="eyebrow">EVENTOS CONSERVADOS</p>
            <strong>{{ number_format($cantidadEventos) }}</strong>
            <span>Incluyen mensajes, ediciones y eliminaciones</span>
        </article>
        <article class="card metric">
            <p class="eyebrow">MENSAJES NORMALIZADOS</p>
            <strong>{{ number_format($cantidadNormalizados) }}</strong>
            <span>La normalización automática sigue desactivada</span>
        </article>
        <article class="card metric">
            <p class="eyebrow">PERIODOS DE INTEGRIDAD</p>
            <strong>{{ number_format($cantidadPeriodos) }}</strong>
            <span>Incidencias registradas por las conexiones</span>
        </article>
    </section>

    <section class="card evidence-banner">
        <div>
            <h2>La fuente antes que la interpretación</h2>
            <p class="muted">Puedes inspeccionar cada evento original y su identificador. Que no existan periodos de integridad registrados no implica una garantía de captura completa.</p>
        </div>
        <span class="badge neutral">Solo lectura</span>
    </section>

    <section class="section-heading">
        <div>
            <p class="eyebrow">NAVEGACIÓN POR GRUPO</p>
            <h2>Grupos con monitoreo activo</h2>
        </div>
        <a class="text-link" href="{{ route('grupos.indice') }}">Administración de grupos →</a>
    </section>

    <div class="group-list">
        @forelse($grupos as $grupo)
            <article class="card group-card">
                <div class="group-main">
                    <p class="eyebrow" style="margin:0 0 8px">GRUPO #{{ $grupo->id }}</p>
                    <h2>{{ $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre') }}</h2>
                    <div class="metadata-row">
                        <span>Eventos: {{ number_format($grupo->eventos_crudos_count) }}</span>
                        <span>Conexión: {{ $grupo->conexion?->nombre_visible ?: ($grupo->conexion?->clave_conexion ?: 'No identificada') }}</span>
                    </div>
                </div>
                <div class="group-action">
                    <a class="action-link" href="{{ route('mensajes.grupo', $grupo->id) }}">Ver mensajes →</a>
                </div>
            </article>
        @empty
            <section class="card empty-state">
                <span class="badge neutral">Sin grupos autorizados</span>
                <h2>Todavía no hay grupos monitoreados.</h2>
                <p class="muted">Autoriza un grupo de prueba desde la administración para consultar sus eventos capturados.</p>
            </section>
        @endforelse
    </div>
</x-layout>
