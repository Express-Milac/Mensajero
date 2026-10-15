<x-layout title="Acceso">
    <section class="acceso tarjeta">
        <p class="sobrelinea">BIENVENIDO A MENSAJERO</p>
        <h1>La operación,<br>con contexto.</h1>
        <p class="atenuado">Acceso local disponible únicamente para desarrollo y pruebas.</p>
        <form method="post" action="{{ route('acceso.guardar') }}">
            @csrf
            <label for="correo">Correo electrónico</label>
            <input id="correo" name="correo" type="email" value="{{ old('correo') }}" required autocomplete="username" autofocus maxlength="254">
            <label for="clave">Contraseña</label>
            <input id="clave" name="clave" type="password" required autocomplete="current-password" maxlength="1024">
            @if($errors->any())
                <p role="alert" class="error">{{ $errors->first() }}</p>
            @endif
            <button class="principal" type="submit">Entrar al sistema <span aria-hidden="true">→</span></button>
        </form>
        <p class="detalle">El acceso corporativo de MILAC se integrará en una etapa posterior.</p>
    </section>
</x-layout>
