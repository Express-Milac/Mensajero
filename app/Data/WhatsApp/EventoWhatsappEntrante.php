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
        public ?ArchivoWhatsappEntrante $archivo = null,
        public ?string $remitenteProveedorId = null,
        public ?string $mensajeCitadoProveedorId = null,
        public ?string $mensajeObjetivoProveedorId = null,
    ) {}

    public function hashContenidoSha256(): string
    {
        return hash('sha256', $this->contenidoCrudo);
    }

    public function claveDeduplicacion(): string
    {
        if ($this->tipoEvento === 'mensaje') {
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

        return hash('sha256', implode('|', [
            'evento_mutable',
            (string) $this->conexionId,
            $this->chatId,
            $this->tipoEvento,
            $this->eventoExternoId ?? '',
            $this->mensajeProveedorId ?? '',
            $this->mensajeObjetivoProveedorId ?? '',
            $this->hashContenidoSha256(),
        ]));
    }
}
