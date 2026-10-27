<?php

namespace App\Data\WhatsApp;

final readonly class ArchivoWhatsappEntrante
{
    public function __construct(
        public string $identificadorArchivo,
        public string $rutaRelativa,
        public string $hashSha256,
        public int $tamanoBytes,
        public ?string $tipoMime,
        public ?string $nombreOriginal,
        public ?string $extension,
    ) {}
}
