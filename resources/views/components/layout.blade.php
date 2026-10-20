<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Mensajero' }} · MILAC</title>
    <link rel="stylesheet" href="{{ asset('css/mensajero.css') }}?v={{ filemtime(public_path('css/mensajero.css')) }}">
</head>
<body>
    <div class="shell">
        <aside>
            <a class="brand" href="{{ route('tablero') }}"><span class="mark">M</span> MENSAJERO</a>
            <p class="eyebrow">CONVERSACIONES</p>
            <nav class="navigation">
                <a class="nav {{ request()->routeIs('tablero') ? 'active' : '' }}" href="{{ route('tablero') }}">Inicio</a>
                <a class="nav {{ request()->routeIs('grupos.*') ? 'active' : '' }}" href="{{ route('grupos.indice') }}">Grupos</a>
                @if(config('mensajero.autenticacion.modo') !== 'deshabilitado' || (app()->environment(['local', 'testing']) && config('mensajero.consulta_local_sin_autenticacion', false) && in_array(request()->ip(), ['127.0.0.1', '::1'], true)))
                    <a class="nav {{ request()->routeIs('mensajes.*') && !request()->routeIs('mensajes.tecnico') ? 'active' : '' }}" href="{{ route('mensajes.indice') }}">Panorama de grupos</a>
                    @if(config('mensajero.autenticacion.modo') === 'local' && auth()->check() && auth()->user()->can('administrar'))
                        <a class="nav {{ request()->routeIs('mensajes.tecnico') ? 'active' : '' }}" href="{{ route('mensajes.tecnico') }}">Diagnóstico técnico</a>
                    @endif
                @endif
            </nav>
            <div class="aside-note">Lo que se dice.<br>Lo que importa.</div>
        </aside>
        <main>
            <header><span>MILAC / Mensajero</span>@auth<form method="post" action="{{ route('salir') }}">@csrf<button class="text-button">Cerrar sesión</button></form>@endauth</header>
            {{ $slot }}
        </main>
    </div>
</body>
</html>
