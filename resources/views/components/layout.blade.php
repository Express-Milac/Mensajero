<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>{{ $title ?? 'Mensajero' }} · Express MILAC</title>
    <link rel="stylesheet" href="{{ asset('css/mensajero.css') }}?v={{ filemtime(public_path('css/mensajero.css')) }}">
    @if(request()->routeIs('tablero') || request()->routeIs('mensajes.indice'))
        <link rel="stylesheet" href="{{ asset('css/panorama-claro.css') }}?v={{ filemtime(public_path('css/panorama-claro.css')) }}">
    @endif
</head>
<body class="{{ request()->routeIs('tablero', 'mensajes.indice') ? 'panorama-claro' : '' }}">
    <div class="shell">
        <aside class="barra-corporativa" aria-label="Navegación principal">
            <a class="brand marca-corporativa" href="{{ route('tablero') }}" aria-label="Express MILAC, Mensajero, inicio">
                @if(file_exists(public_path('identidad/logo-milac-color.png')))
                    <img class="logotipo-milac" src="{{ asset('identidad/logo-milac-blanco.png') }}" alt="Express MILAC">
                @else
                    <span class="marca-texto">EXPRESS <strong>MILAC</strong></span>
                @endif
                <span class="marca-separador"></span>
                <span class="marca-producto">Mensajero <small>Conversaciones y seguimiento</small></span>
            </a>
            <p class="eyebrow etiqueta-menu">MENSAJERO MILAC</p>
            <nav class="navigation">
                <a class="nav {{ request()->routeIs('tablero') ? 'active' : '' }}" href="{{ route('tablero') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
                    <span>Centro de mando</span>
                </a>
                <a class="nav {{ request()->routeIs('grupos.*') ? 'active' : '' }}" href="{{ route('grupos.indice') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
                    <span>Grupos</span>
                </a>
                @if(config('mensajero.autenticacion.modo') !== 'deshabilitado' || (app()->environment(['local', 'testing']) && config('mensajero.consulta_local_sin_autenticacion', false) && in_array(request()->ip(), ['127.0.0.1', '::1'], true)))
                    <a class="nav {{ request()->routeIs('mensajes.*') && !request()->routeIs('mensajes.tecnico') ? 'active' : '' }}" href="{{ route('mensajes.indice') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5 8 8 0 0 1-3.5-.8L4 20l1.8-5A7.5 7.5 0 1 1 20 11.5z"/><path d="M8.5 11.5h7"/></svg>
                        <span>Conversaciones</span>
                    </a>
                    @if(config('mensajero.autenticacion.modo') === 'local' && auth()->check() && auth()->user()->can('administrar'))
                        <a class="nav {{ request()->routeIs('mensajes.tecnico') ? 'active' : '' }}" href="{{ route('mensajes.tecnico') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1"/><circle cx="12" cy="12" r="5"/></svg>
                            <span>Diagnóstico técnico</span>
                        </a>
                    @endif
                @endif
                @if(request()->routeIs('tablero') || request()->routeIs('mensajes.indice'))
                    <a class="nav" href="{{ route('tablero') }}#temas-clave"><span class="centro-nav-icono" aria-hidden="true">▤</span><span>Temas clave</span></a>
                    <a class="nav" href="{{ route('tablero') }}#acuerdos-explicitos"><span class="centro-nav-icono" aria-hidden="true">✓</span><span>Posibles acuerdos</span></a>
                    <a class="nav" href="{{ route('tablero') }}#menciones-revision"><span class="centro-nav-icono" aria-hidden="true">⚑</span><span>Menciones para revisar</span></a>
                @endif
            </nav>

        </aside>
        <main>
            <header class="barra-superior">
                <div class="ruta-superior">
                    @if(file_exists(public_path('identidad/logo-milac-color.png')))
                        <img class="logotipo-milac-cabecera" src="{{ asset('identidad/logo-milac-color.png') }}" alt="Express MILAC">
                    @else
                        <span class="marca-punto"></span> EXPRESS MILAC
                    @endif
                    <span class="separador-ruta">/</span> MENSAJERO
                </div>
                <div class="barra-superior-derecha">
                    <span class="etiqueta-corporativa">CONSULTA DE GRUPOS AUTORIZADOS</span>
                    @auth
                        <form method="post" action="{{ route('salir') }}">@csrf<button class="text-button boton-salir">Cerrar sesión</button></form>
                    @endauth
                </div>
            </header>
            {{ $slot }}
        </main>
    </div>
</body>
</html>
