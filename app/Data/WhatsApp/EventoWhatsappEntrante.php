<?php

namespace App\Data\WhatsApp;

use Carbon\CarbonImmutable;

final readonly class EventoWhatsappEntrante
{
    public function __construct(
        public int $conexionId,
        public string $chatId,
        public string $tipoEvento,
        public string $tipoMensaje,
        public ?string $eventoExternoId,
        public ?string $mensajeProveedorId,
        public string $contenidoCrudo,
        public CarbonImmutable $recibidoEn,
        public string $versionContrato = 'v1',
    ) {}

    public function hashContenidoSha256(): string
    {
        return hash('sha256', $this->contenidoCrudo);
    }

    public function claveDeduplicacion(): string
    {
        if ($this->eventoExternoId !== null && $this->eventoExternoId !== '') {
            return hash('sha256', 'evento:'.$this->eventoExternoId);
        }

        return hash('sha256', implode('|', [
            'respaldo',
            (string) $this->conexionId,
            $this->chatId,
            $this->tipoEvento,
            $this->mensajeProveedorId ?? '',
            $this->hashContenidoSha256(),
        ]));
    }
}
