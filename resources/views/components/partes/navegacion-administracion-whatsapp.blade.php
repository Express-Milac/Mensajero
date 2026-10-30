{{-- Enlace exclusivo para administradores autenticados; la ruta aplica además el middleware servidor. --}}
@if(
    in_array((string) config('mensajero.autenticacion.modo'), ['corporativo', 'local'], true)
    && auth()->check()
    && \Illuminate\Support\Facades\Gate::allows('administrar')
    && \Illuminate\Support\Facades\Route::has('whatsapp.conexiones.indice')
)
    <span class="centro-menu-separador" aria-hidden="true"></span>
    <p class="eyebrow etiqueta-menu">ADMINISTRACIÓN</p>
    <a class="nav {{ request()->routeIs('whatsapp.*') ? 'active' : '' }}"
       href="{{ route('whatsapp.conexiones.indice') }}"
       @if(request()->routeIs('whatsapp.*')) aria-current="page" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="6" y="2.5" width="12" height="19" rx="2"/>
            <path d="M10 5.5h4M10 18.5h4M3 9v6M21 9v6"/>
        </svg>
        <span>Administración de WhatsApp</span>
    </a>
@endif
