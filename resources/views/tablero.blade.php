{{-- Vista de compatibilidad heredada. La ruta /tablero utiliza mensajes.indice. --}}
<x-layout title="Mensajero MILAC">
    <section class="catalogo-encabezado">
        <div>
            <span class="centro-hero-marca">EXPRESS MILAC</span>
            <h1>Mensajero MILAC</h1>
            <p>El panorama de conversaciones se encuentra en el Centro de mando. Esta vista se conserva únicamente para compatibilidad con referencias internas antiguas.</p>
        </div>
        <a class="catalogo-volver" href="{{ route('tablero') }}">Abrir Centro de mando →</a>
    </section>
</x-layout>
