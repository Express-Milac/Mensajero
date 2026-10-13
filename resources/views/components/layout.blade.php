<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Mensajero' }} · Milac</title>
    <link rel="stylesheet" href="{{ asset('css/mensajero.css') }}">
</head>
<body>
    <div class="shell">
        <aside>
            <a class="brand" href="{{ route('dashboard') }}"><span class="mark">M</span> MENSAJERO</a>
            <p class="eyebrow">CONTROL OPERATIVO</p>
            <a class="nav active" href="{{ route('dashboard') }}">Inicio</a>
            <div class="aside-note">Capturar. Conservar.<br>Verificar.</div>
        </aside>
        <main>
            <header><span>Milac / Mensajero</span>@auth<form method="post" action="{{ route('logout') }}">@csrf<button class="text-button">Cerrar sesión</button></form>@endauth</header>
            {{ $slot }}
        </main>
    </div>
</body>
</html>
