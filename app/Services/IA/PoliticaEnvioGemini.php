<?php

namespace App\Services\IA;

use DateTimeImmutable;
use DateTimeInterface;

final class PoliticaEnvioGemini
{
    public function configuracionCompleta(): bool
    {
        return config('mensajero.ia.habilitada') === true
            && config('mensajero.ia.envio_autorizado') === true
            && config('mensajero.ia.servicio_pagado_confirmado') === true
            && strlen((string) config('mensajero.ia.clave_api', '')) >= 10
            && $this->fechaInicio() !== null
            && count((array) config('mensajero.ia.grupos_autorizados', [])) > 0;
    }

    public function autoriza(int $grupoId, DateTimeInterface $recibidoEn): bool
    {
        $inicio = $this->fechaInicio();

        return $this->configuracionCompleta()
            && $inicio !== null
            && in_array($grupoId, (array) config('mensajero.ia.grupos_autorizados', []), true)
            && $recibidoEn->getTimestamp() >= $inicio->getTimestamp();
    }

    public function fechaInicio(): ?DateTimeImmutable
    {
        $valor = trim((string) config('mensajero.ia.fecha_inicio', ''));

        if ($valor === '') {
            return null;
        }

        $fecha = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $valor);

        return $fecha === false ? null : $fecha;
    }
}
