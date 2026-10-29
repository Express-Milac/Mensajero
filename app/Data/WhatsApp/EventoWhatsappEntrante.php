<?php

namespace App\Data\WhatsApp;

use Carbon\CarbonImmutable;

final class EventoWhatsappEntrante
{
    public int $conexionId;
    public string $chatId;
    public string $tipoEvento;
    public string $tipoMensaje;
    public ?string $eventoExternoId;
    public ?string $mensajeProveedorId;
    public string $contenidoCrudo;
    public CarbonImmutable $recibidoEn;
    public string $versionContrato;
    public ?ArchivoWhatsappEntrante $archivo;
    public ?string $remitenteProveedorId;
    public ?string $mensajeCitadoProveedorId;
    public ?string $mensajeObjetivoProveedorId;
    public ?string $nombreRemitente;
    public ?string $fuenteNombreRemitente;
    public ?string $telefonoRemitente;

    public function __construct(
        int $conexionId,
        string $chatId,
        string $tipoEvento,
        string $tipoMensaje,
        ?string $eventoExternoId,
        ?string $mensajeProveedorId,
        string $contenidoCrudo,
        CarbonImmutable $recibidoEn,
        string $versionContrato = 'v1',
        ?ArchivoWhatsappEntrante $archivo = null,
        ?string $remitenteProveedorId = null,
        ?string $mensajeCitadoProveedorId = null,
        ?string $mensajeObjetivoProveedorId = null,
        ?string $nombreRemitente = null,
        ?string $fuenteNombreRemitente = null,
        ?string $telefonoRemitente = null 
    ) {
        $this->conexionId = $conexionId;
        $this->chatId = $chatId;
        $this->tipoEvento = $tipoEvento;
        $this->tipoMensaje = $tipoMensaje;
        $this->eventoExternoId = $eventoExternoId;
        $this->mensajeProveedorId = $mensajeProveedorId;
        $this->contenidoCrudo = $contenidoCrudo;
        $this->recibidoEn = $recibidoEn;
        $this->versionContrato = $versionContrato;
        $this->archivo = $archivo;
        $this->remitenteProveedorId = $remitenteProveedorId;
        $this->mensajeCitadoProveedorId = $mensajeCitadoProveedorId;
        $this->mensajeObjetivoProveedorId = $mensajeObjetivoProveedorId;
        $this->nombreRemitente = $nombreRemitente;
        $this->fuenteNombreRemitente = $fuenteNombreRemitente;
        $this->telefonoRemitente = $telefonoRemitente;
    }


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
