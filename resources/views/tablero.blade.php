<x-layout title="Inicio">
    <section class="introduccion">
        <p class="sobrelinea">CENTRO OPERATIVO</p>
        <h1>Todo empieza con<br>la evidencia.</h1>
        @auth
            <p class="atenuado">Hola, {{ auth()->user()->nombre }}. Mensajero está en preparación para captura controlada.</p>
        @else
            <p class="atenuado">Mensajero está en preparación para captura controlada.</p>
            @if(config('mensajero.autenticacion.modo') === 'deshabilitado')
                <p class="aviso">Acceso interno temporal sin autenticación. Las acciones administrativas permanecen restringidas.</p>
            @endif
        @endauth
    </section>

    <div class="cuadricula">
        <section class="tarjeta">
            <span class="insignia">Núcleo de captura</span>
            <h2>Base del sistema</h2>
            <p>SQL Server conserva evidencia y referencias. Redis procesa trabajos y el almacenamiento privado conserva archivos.</p>
            @auth
                <p class="detalle">Rol: {{ auth()->user()->rol->etiqueta() }}</p>
            @endauth
        </section>

        <section class="tarjeta">
            <span class="insignia neutro">Sin conectar</span>
            <h2>WhatsApp Business</h2>
            <p>El número empresarial todavía no está vinculado. Primero se validará la captura, la lista blanca y la recuperación ante fallos.</p>
        </section>
    </div>

    <section class="tarjeta evidencia">
        <div>
            <p class="sobrelinea">DOS VISTAS PERMANENTES</p>
            <h2>La fuente siempre estará disponible.</h2>
            <p class="atenuado">La interpretación de IA nunca sustituirá los mensajes originales.</p>
        </div>
        <div class="pestanas">
            <span>Mensajero IA · pendiente</span>
            <span>Mensajes originales · en construcción</span>
        </div>
    </section>
</x-layout>
