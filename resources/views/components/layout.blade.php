<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Mensajero' }} · MILAC</title>
    <link rel="stylesheet" href="{{ asset('css/mensajero.css') }}">
</head>
<body>
    <div class="marco">
        <aside>
            <a class="marca" href="{{ route('tablero') }}"><span class="simbolo">M</span> MENSAJERO</a>
            <p class="sobrelinea">CONTROL OPERATIVO</p>
            <a class="navegacion activa" href="{{ route('tablero') }}">Inicio</a>
            <div class="nota-lateral">Capturar. Conservar.<br>Verificar.</div>
        </aside>
        <main>
            <header>
                <span>MILAC / Mensajero</span>
                @auth
                    <form method="post" action="{{ route('salir') }}">
                        @csrf
                        <button class="boton-texto">Cerrar sesión</button>
                    </form>
                @endauth
            </header>
            {{ $slot }}
        </main>
    </div>
</body>
</html>
