<x-layout title="Inicio">
    <section class="intro"><p class="eyebrow">CENTRO OPERATIVO</p><h1>Todo empieza con<br>la evidencia.</h1><p class="muted">Hola, {{ auth()->user()->name }}. Esta instalación está en preparación.</p></section>
    <div class="grid">
        <section class="card"><span class="badge">Milestone 1</span><h2>Base del sistema</h2><p>Acceso seguro y configuración de infraestructura.</p><p class="fine">Tu rol: {{ auth()->user()->role->label() }}</p>@can('administer')<a href="{{ route('health') }}">Consultar diagnóstico de servicios →</a>@endcan</section>
        <section class="card"><span class="badge neutral">Sin conectar</span><h2>WhatsApp</h2><p>Aún no hay una sesión vinculada. El descubrimiento y la selección de grupos llegarán en las siguientes entregas.</p></section>
    </div>
    <section class="card evidence"><div><p class="eyebrow">DOS VISTAS PERMANENTES</p><h2>La fuente siempre estará disponible.</h2><p class="muted">Cada interpretación deberá conducir a sus mensajes originales.</p></div><div class="tabs"><span>Mensajero IA · pendiente</span><span>Mensajes originales · pendiente</span></div></section>
</x-layout>
