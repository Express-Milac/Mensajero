<?php

namespace App\Http\Controllers\Mensajes;

use App\Http\Controllers\Controller;
use App\Models\ArchivoWhatsapp;
use App\Models\ChatMonitoreado;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Entrega evidencia multimedia privada únicamente a usuarios autorizados.
 * Nunca publica una ruta absoluta ni utiliza enlaces de almacenamiento público.
 */
final class ControladorArchivosWhatsapp extends Controller
{
    private const TIPOS_VISUALIZABLES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav',
        'video/mp4', 'video/webm',
    ];

    public function ver(int $archivo): BinaryFileResponse
    {
        return $this->entregar($archivo, false);
    }

    public function descargar(int $archivo): BinaryFileResponse
    {
        return $this->entregar($archivo, true);
    }

    private function entregar(int $archivoId, bool $descargar): BinaryFileResponse
    {
        $archivo = ArchivoWhatsapp::query()
            ->with('eventoCrudo')
            ->findOrFail($archivoId);

        $evento = $archivo->eventoCrudo;

        // La autorización nunca depende de una relación cargada previamente
        // ni de la autorización existente cuando se capturó el archivo.
        // Se consulta en SQL Server para cada solicitud, incluso descargas.
        $grupoAutorizado = ChatMonitoreado::query()
            ->whereKey($archivo->chat_monitoreado_id)
            ->where('conexion_id', $archivo->conexion_id)
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_activo', true)
            ->exists();

        if (! $grupoAutorizado
            || $evento === null
            || (int) $evento->chat_monitoreado_id !== (int) $archivo->chat_monitoreado_id
            || (int) $evento->conexion_id !== (int) $archivo->conexion_id
            || $archivo->estado !== 'verificado'
            || $archivo->disco !== 'mensajero_local') {
            abort(404);
        }

        $ruta = (string) $archivo->ruta_relativa;
        $identificador = (string) $archivo->identificador_archivo;
        $extension = (string) ($archivo->extension ?: 'bin');

        // Formato creado por el recolector, no una ruta arbitraria de SQL.
        if (preg_match('/^[a-f0-9]{64}$/D', $identificador) !== 1
            || preg_match('/^[a-z0-9]{1,16}$/D', $extension) !== 1
            || preg_match(
                '#^multimedia/whatsapp/[A-Za-z0-9_-]{1,64}/[0-9]{4}/(?:0[1-9]|1[0-2])/[a-f0-9]{64}\\.[a-z0-9]{1,16}$#D',
                $ruta,
            ) !== 1
            || basename($ruta) !== $identificador.'.'.$extension) {
            abort(404);
        }

        $disco = Storage::disk('mensajero_local');
        $raiz = realpath($disco->path(''));
        $rutaReal = realpath($disco->path($ruta));

        // Bloquea enlaces simbólicos que escapen del almacenamiento privado.
        if ($raiz === false || $rutaReal === false
            || ! str_starts_with($rutaReal, rtrim($raiz, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || ! is_file($rutaReal)
            || ! is_readable($rutaReal)) {
            abort(404);
        }

        $tamano = filesize($rutaReal);
        $limite = (int) config('mensajero.multimedia.limite_consulta_bytes', 262144000);

        if ($tamano === false || $tamano !== (int) $archivo->tamano_bytes
            || $tamano > $limite || $limite < 1) {
            abort(404);
        }

        $huella = hash_file('sha256', $rutaReal);

        if (! is_string($huella)
            || preg_match('/^[a-f0-9]{64}$/D', (string) $archivo->hash_sha256) !== 1
            || ! hash_equals((string) $archivo->hash_sha256, $huella)) {
            abort(404);
        }

        $tipoReal = (new \finfo(FILEINFO_MIME_TYPE))->file($rutaReal);
        $visualizable = ! $descargar
            && is_string($tipoReal)
            && in_array($tipoReal, self::TIPOS_VISUALIZABLES, true);

        $nombreSeguro = 'evidencia-mensajero-'.$archivo->id.'.'.$extension;
        $disposicion = HeaderUtils::makeDisposition(
            $visualizable ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
            $nombreSeguro,
        );

        return response()->file($rutaReal, [
            'Content-Type' => $visualizable ? $tipoReal : 'application/octet-stream',
            'Content-Disposition' => $disposicion,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
