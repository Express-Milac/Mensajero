<?php

namespace App\Services\Capture;

use App\Data\WhatsApp\ArchivoWhatsappEntrante;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class VerificarArchivoWhatsappEntrante
{
    public const DISCO = 'mensajero_local';

    public function verificar(ArchivoWhatsappEntrante $archivo): void
    {
        $this->validarRuta($archivo);

        $disco = Storage::disk(self::DISCO);

        if (! $disco->exists($archivo->rutaRelativa)) {
            throw ValidationException::withMessages([
                'multimedia.ruta_relativa' => 'El archivo multimedia no existe en el almacenamiento privado.',
            ]);
        }

        $tamano = $disco->size($archivo->rutaRelativa);

        if ($tamano !== $archivo->tamanoBytes) {
            throw ValidationException::withMessages([
                'multimedia.tamano_bytes' => 'El tamaño del archivo multimedia no coincide con el contenido almacenado.',
            ]);
        }

        $rutaAbsoluta = $disco->path($archivo->rutaRelativa);
        $hash = hash_file('sha256', $rutaAbsoluta);

        if (! is_string($hash) || ! hash_equals($archivo->hashSha256, $hash)) {
            throw ValidationException::withMessages([
                'multimedia.hash_sha256' => 'El hash del archivo multimedia no coincide con el contenido almacenado.',
            ]);
        }
    }

    private function validarRuta(ArchivoWhatsappEntrante $archivo): void
    {
        $ruta = $archivo->rutaRelativa;

        if (
            ! str_starts_with($ruta, 'multimedia/whatsapp/')
            || str_contains($ruta, '..')
            || str_contains($ruta, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $ruta) === 1
            || preg_match('#^multimedia/whatsapp/[A-Za-z0-9._/-]+$#', $ruta) !== 1
        ) {
            throw ValidationException::withMessages([
                'multimedia.ruta_relativa' => 'La ruta multimedia no es válida.',
            ]);
        }

        $extension = $archivo->extension ?: 'bin';
        $nombreEsperado = $archivo->identificadorArchivo.'.'.$extension;

        if (basename($ruta) !== $nombreEsperado) {
            throw ValidationException::withMessages([
                'multimedia.ruta_relativa' => 'La ruta multimedia no corresponde con su identificador.',
            ]);
        }
    }
}
