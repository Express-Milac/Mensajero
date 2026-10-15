<x-layout title="Inicio">
    @php($usuario = auth()->user())
    <section class="intro">
        <p class="eyebrow">CENTRO OPERATIVO</p>
        <h1>Todo empieza con<br>la evidencia.</h1>
        <p class="muted">Hola, {{ $usuario?->nombre ?? 'equipo MILAC' }}. La base de captura ya fue validada en SQL Server.</p>
    </section>
    <div class="grid">
        <section class="card">
            <span class="badge">Hito 1 validado</span>
            <h2>Base del sistema</h2>
            <p>Acceso controlado, almacenamiento desacoplado y núcleo de captura probados.</p>
            @if(config('mensajero.autenticacion.modo') === 'deshabilitado')
                <p class="fine"><strong>Acceso interno temporal:</strong> la autenticación corporativa aún no está integrada.</p>
            @else
                <p class="fine">Tu rol: {{ $usuario?->rol?->etiqueta() ?? 'Sin rol' }}</p>
            @endif
            @can('administrar')<a href="{{ route('estado') }}">Consultar diagnóstico de servicios →</a>@endcan
        </section>
        <section class="card">
            <span class="badge neutral">Preparando conexión</span>
            <h2>Grupos de WhatsApp</h2>
            <p>La lista permitida ya puede administrarse sobre los grupos que vaya descubriendo la pasarela.</p>
            <a href="{{ route('grupos.indice') }}">Revisar grupos descubiertos →</a>
        </section>
    </div>
    <section class="card evidence">
        <div><p class="eyebrow">DOS VISTAS PERMANENTES</p><h2>La fuente siempre estará disponible.</h2><p class="muted">Cada interpretación deberá conducir a sus mensajes originales.</p></div>
        <div class="tabs"><span>Mensajero IA · pendiente</span><span>Mensajes originales · pendiente</span></div>
    </section>
</x-layout>
