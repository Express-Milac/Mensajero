{{-- Misma vista previa para historial y detalle; el servidor valida cada entrega. --}}
@if($archivo && $archivo->estado === 'verificado')
    @php($enlaceArchivo = route('mensajes.archivo.ver', $archivo->id))
    @php($tipoArchivo = strtolower(trim(explode(';', $archivo->tipo_mime ?? '')[0])))
    <div class="evidencia-multimedia" aria-label="Archivo multimedia privado">
        @if(in_array($tipoArchivo, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true))
            <a class="vista-imagen-multimedia" href="{{ route('mensajes.evento', $archivo->evento_crudo_id) }}" aria-label="Abrir evidencia de la fotografía">
                <img src="{{ $enlaceArchivo }}" alt="Fotografía adjunta al evento #{{ $archivo->evento_crudo_id }}" loading="lazy" referrerpolicy="no-referrer">
            </a>
        @elseif(in_array($tipoArchivo, ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav'], true))
            <audio controls preload="none" src="{{ $enlaceArchivo }}" aria-label="Audio adjunto">Tu navegador no puede reproducir este audio.</audio>
        @elseif(in_array($tipoArchivo, ['video/mp4', 'video/webm'], true))
            <video controls preload="none" playsinline src="{{ $enlaceArchivo }}" aria-label="Video adjunto">Tu navegador no puede reproducir este video.</video>
        @else
            <p class="archivo-adjunto">Documento o archivo adjunto disponible mediante descarga protegida.</p>
        @endif
        <a class="descarga-multimedia" href="{{ route('mensajes.archivo.descargar', $archivo->id) }}">Descargar archivo privado →</a>
    </div>
@elseif($archivo)
    <p class="fine">El archivo está registrado, pero todavía no está verificado para visualizarse o descargarse.</p>
@endif
