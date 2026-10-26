<x-layout title="Control de Gemini">
<div data-gemini-panel data-gemini-listo="{{ $disponible && $listo ? '1' : '0' }}">
<section class="catalogo-encabezado">
  <div><span class="centro-hero-marca">MENSAJERO MILAC / IA</span><h1>Control de Gemini</h1>
    <p>Configura la interpretación de mensajes desde la aplicación. La captura de WhatsApp no cambia.</p></div>
  <a class="catalogo-volver" href="{{ route('tablero') }}">Volver al tablero</a>
</section>
@if(session('estado'))<div class="notice success">{{ session('estado') }}</div>@endif
@if($errors->any())<div class="notice error-box">{{ $errors->first() }}</div>@endif
<p class="control-gemini-aviso" data-gemini-aviso role="status" aria-live="polite"></p>
<section class="catalogo-seccion control-gemini">
  <div class="catalogo-titulo"><div><h2>Interruptor general</h2><p>Tiene prioridad sobre todos los grupos.</p></div>
    <span data-gemini-estado="0" class="catalogo-estado neutral">{{ $general['habilitado'] ? 'Encendido' : 'Apagado' }}</span></div>
  @if(! $disponible)<p role="alert">Falta instalar el control de SQL Server. El análisis permanece apagado.</p>@endif
  @if(! $listo)<p role="alert">La autorización técnica de Gemini no está completa.</p>@endif
  <form method="post" action="{{ route('gemini.configuracion.cambiar', 0) }}" class="control-gemini-form" data-gemini-form data-gemini-id="0" data-gemini-habilitado="{{ $general['habilitado'] ? '1' : '0' }}">
    @csrf @method('PATCH')
    <input type="hidden" name="habilitado" value="{{ $general['habilitado'] ? '0' : '1' }}">
    <label data-gemini-consentimiento @if($general['habilitado']) hidden @endif><input type="checkbox" name="confirmo_envio" value="1" @if(! $general['habilitado']) required @endif> Confirmo el procesamiento de mensajes nuevos en los grupos habilitados.</label>
    <button data-gemini-boton class="catalogo-aplicar" type="submit" disabled>
      {{ $general['habilitado'] ? 'Apagar Gemini' : 'Encender Gemini' }}</button>
  </form>
  <p class="control-gemini-nota">Al apagar se detienen nuevos análisis; una solicitud ya iniciada puede terminar. No se procesan mensajes anteriores a la última activación.</p>
</section>
<section class="catalogo-seccion control-gemini">
  <div class="catalogo-titulo"><div><h2>Interpretación por grupo</h2><p>Puedes habilitar cualquier grupo que ya estés monitoreando. La autorización se guarda en este panel, sin editar archivos ni enviar historial.</p></div></div>
  <div class="control-gemini-lista">
  @forelse($filas as $fila)
    @php($grupo = $fila['grupo'])
    @php($activo = $fila['estado']['habilitado'])
    <article class="control-gemini-fila">
      <div><h3>{{ $grupo->nombre_interno ?: ($grupo->nombre_grupo ?: 'Grupo sin nombre') }}</h3>
        <p>Grupo #{{ $grupo->id }} · Mensajes conservados de forma independiente</p></div>
      <div class="control-gemini-accion-grupo">
        <strong data-gemini-estado="{{ $grupo->id }}">{{ $activo ? ($general['habilitado'] ? 'Activo' : 'Pausado') : 'Apagado' }}</strong>
        <form method="post" action="{{ route('gemini.configuracion.cambiar', $grupo->id) }}" class="control-gemini-form" data-gemini-form data-gemini-id="{{ $grupo->id }}" data-gemini-habilitado="{{ $activo ? '1' : '0' }}">
          @csrf @method('PATCH')
          <input type="hidden" name="habilitado" value="{{ $activo ? '0' : '1' }}">
          @if(! $activo)
            <label><input type="checkbox" name="confirmo_envio" value="1" required> Autorizo enviar a Google Gemini los mensajes nuevos de este grupo a partir de ahora.</label>
          @endif
          <button data-gemini-boton type="submit" class="catalogo-aplicar" disabled>{{ $activo ? 'Desactivar' : 'Activar' }}</button>
        </form>
      </div>
    </article>
  @empty<p>Sin grupos monitorizados.</p>
  @endforelse
  </div>
</section>
<p class="control-gemini-nota">Máximo configurado: {{ $maximo }} solicitudes por grupo y día UTC. Los cambios se auditan y los mensajes originales permanecen intactos.</p>
</div>
<script src="{{ asset('js/control-gemini.js') }}?v={{ filemtime(public_path('js/control-gemini.js')) }}" defer></script>
</x-layout>
