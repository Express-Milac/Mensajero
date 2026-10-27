<x-layout title="Identificar remitente">
    <nav class="breadcrumbs" aria-label="Ruta de navegación">
        <a href="{{ route('remitentes.indice') }}">Remitentes</a>
        <span>/</span>
        <span>Asignar nombre</span>
    </nav>
    <section class="catalogo-encabezado">
        <div>
            <span class="centro-hero-marca">MENSAJERO MILAC / IDENTIFICACIÓN</span>
            <h1>Identificar remitente</h1>
            <p>Asigna un nombre corporativo a esta persona. Se usará para todos los mensajes asociados al mismo identificador de WhatsApp y a esta conexión.</p>
        </div>
    </section>

    @if(session('estado'))<div class="notice success" role="status">{{ session('estado') }}</div>@endif
    @if($errors->any())<div class="notice error-box" role="alert">{{ $errors->first() }}</div>@endif

    <section class="catalogo-tarjetas identificacion-contenido">
        <article class="catalogo-tarjeta">
            <div class="catalogo-tarjeta-encabezado">
                <span class="catalogo-grupo-icono" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg></span>
                <div class="catalogo-grupo-titulo"><h3>{{ $nombre }}</h3><span>{{ optional($registro->chat)->nombre_interno ?: (optional($registro->chat)->nombre_grupo ?: 'Grupo sin nombre') }}</span></div>
            </div>
            <div class="catalogo-tarjeta-cuerpo">
                <p><span>Nombre disponible de WhatsApp</span><strong>{{ $nombreWhatsapp ?? 'No informado' }}</strong></p>
                <p><span>Nombre interno asignado</span><strong>{{ $nombreAsignado ?? 'Sin asignar' }}</strong></p>
                <p><span>Teléfono del remitente</span><strong>{{ $telefono ? '+'.$telefono : 'WhatsApp no proporcionó un número verificable' }}</strong></p>
                <p><span>Origen del nombre recibido</span><strong>{{ $fuenteNombre === 'contacto' ? 'Agenda del recolector' : ($fuenteNombre === 'perfil' ? 'Perfil de WhatsApp' : 'No informado') }}</strong></p>
                <p><span>Último mensaje consultado</span><strong>{{ ($registro->recibido_en !== null ? $registro->recibido_en->timezone(config('app.timezone'))->format('d/m/Y H:i') : '') }}</strong></p>
                <a class="centro-ver-evidencia" href="{{ route('mensajes.evento', $registro->id) }}">Consultar evidencia original ↗</a>
            </div>
        </article>
        <article class="catalogo-tarjeta">
            <div class="catalogo-tarjeta-encabezado"><div class="catalogo-grupo-titulo"><h3>Nombre corporativo</h3><span>Asignación manual, sin alterar mensajes originales</span></div></div>
            <div class="catalogo-tarjeta-cuerpo">
                @php($puedeEditar = (config('mensajero.autenticacion.modo') === 'local' && auth()->check() && auth()->user()->can('administrar')) || (config('mensajero.autenticacion.modo') === 'deshabilitado' && app()->environment(['local', 'testing']) && config('mensajero.consulta_local_sin_autenticacion', false) && config('mensajero.gestion_local_remitentes_habilitada', false) && in_array(request()->ip(), ['127.0.0.1', '::1'], true)))
                @if($puedeEditar)
                    <form class="identificacion-formulario" method="post" action="{{ route('remitentes.actualizar', $registro->id) }}">
                        @csrf
                        @method('PATCH')
                        <label for="nombre_interno">Nombre de la persona</label>
                        <input id="nombre_interno" name="nombre_interno" type="text" maxlength="120" required autocomplete="off" placeholder="Ejemplo: Juan Pérez — Tráfico" value="{{ old('nombre_interno', $nombreAsignado ?? $nombreWhatsapp ?? '') }}">
                        <p>El nombre interno tiene preferencia sobre el nombre de perfil de WhatsApp. Revisa su identidad antes de asignarlo.</p>
                        <button type="submit" class="catalogo-aplicar">Guardar nombre</button>
                    </form>
                @else
                    <div class="catalogo-aviso" role="note">La asignación está protegida. Necesitas ser administrador o habilitar expresamente el permiso temporal de identificación en localhost.</div>
                @endif
            </div>
        </article>
    </section>
</x-layout>
