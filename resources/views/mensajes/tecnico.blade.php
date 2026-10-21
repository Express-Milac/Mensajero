<x-layout title="Diagnóstico técnico">
    <div class="pagina-diagnostico">
    <nav class="breadcrumbs" aria-label="Ruta de navegación">
        <a href="{{ route('tablero') }}">Mensajero MILAC</a>
        <span>/</span>
        <span>Diagnóstico técnico</span>
    </nav>
    <section class="intro compact">
        <p class="eyebrow">MENSAJERO MILAC / APARTADO TÉCNICO</p>
        <h1>Diagnóstico técnico</h1>
        <p class="muted">Indicadores de almacenamiento, integridad y normalización, independientes del panorama directivo. Solo lectura.</p>
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
            <p class="eyebrow">CONEXIONES DEL RECOLECTOR</p>
            <h2>Grupos de WhatsApp del recolector</h2>
            <p class="fine">Incluye grupos de prueba y cualquier grupo autorizado de esta conexión; no indica que sean operativos.</p>
        </div>
        <a class="text-link" href="{{ route('grupos.indice', ['origen' => 'recolector']) }}">Administrar grupos →</a>
    </section>

    <div class="group-list">
        @forelse($gruposRecolector as $grupo)
            @include('mensajes.partes.tarjeta-grupo', ['grupo' => $grupo])
        @empty
            <section class="card empty-state">
                <h2>No hay grupos del recolector con monitoreo activo.</h2>
                <p class="muted">La lista de grupos autorizados se configura desde la administración.</p>
            </section>
        @endforelse
    </div>

    @if($gruposOtrasConexiones->isNotEmpty())
        <section class="section-heading secondary-heading">
            <div>
                <p class="eyebrow">CONEXIONES DISTINTAS DEL RECOLECTOR</p>
                <h2>Pasarelas y otras conexiones</h2>
                <p class="fine">Estos registros se muestran por separado para no mezclarlos con la captura del recolector. Su origen no determina por sí mismo si pertenecen a una prueba.</p>
            </div>
            <a class="text-link" href="{{ route('grupos.indice', ['origen' => 'otras']) }}">Administrar conexiones →</a>
        </section>

        <div class="group-list">
            @foreach($gruposOtrasConexiones as $grupo)
                @include('mensajes.partes.tarjeta-grupo', ['grupo' => $grupo])
            @endforeach
        </div>
    @endif
    </div>
</x-layout>
