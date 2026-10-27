<?php

namespace App\Data\WhatsApp;

final class ArchivoWhatsappEntrante
{
    public string $identificadorArchivo;
    public string $rutaRelativa;
    public string $hashSha256;
    public int $tamanoBytes;
    public ?string $tipoMime;
    public ?string $nombreOriginal;
    public ?string $extension;

    public function __construct(
        string $identificadorArchivo,
        string $rutaRelativa,
        string $hashSha256,
        int $tamanoBytes,
        ?string $tipoMime,
        ?string $nombreOriginal,
        ?string $extension
    ) {
        $this->identificadorArchivo = $identificadorArchivo;
        $this->rutaRelativa = $rutaRelativa;
        $this->hashSha256 = $hashSha256;
        $this->tamanoBytes = $tamanoBytes;
        $this->tipoMime = $tipoMime;
        $this->nombreOriginal = $nombreOriginal;
        $this->extension = $extension;
    }
}
