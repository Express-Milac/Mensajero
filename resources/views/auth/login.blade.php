<x-layout title="Acceso">
    <section class="login card">
        <p class="eyebrow">BIENVENIDO A MENSAJERO</p>
        <h1>La operación,<br>con contexto.</h1>
        <p class="muted">Accede al centro de información operativa.</p>
        <form method="post" action="{{ route('login') }}">
            @csrf
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus maxlength="254">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="1024">
            @if($errors->any())<p role="alert" class="error">{{ $errors->first() }}</p>@endif
            <button class="primary" type="submit">Entrar al sistema <span aria-hidden="true">→</span></button>
        </form>
        <p class="fine">Acceso interno. Solicita tu cuenta al administrador.</p>
    </section>
</x-layout>
