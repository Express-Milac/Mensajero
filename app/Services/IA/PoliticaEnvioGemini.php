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
        $inicio = $this->fechaInicioGrupo($grupoId);

        return $this->configuracionCompleta()
            && $inicio !== null
            && in_array($grupoId, (array) config('mensajero.ia.grupos_autorizados', []), true)
            && $recibidoEn->getTimestamp() >= $inicio->getTimestamp();
    }

    /**
     * Cada grupo real puede tener un límite temporal adicional y más reciente
     * que el consentimiento global. Nunca permite retroceder el global.
     */
    public function fechaInicioGrupo(int $grupoId): ?DateTimeImmutable
    {
        $global = $this->fechaInicio();
        if ($global === null || $grupoId < 1) {
            return null;
        }

        $fechas = config('mensajero.ia.fechas_inicio_grupos', []);
        if (! is_array($fechas) || ! array_key_exists((string) $grupoId, $fechas)) {
            return $global;
        }

        $valor = $fechas[(string) $grupoId];
        if (! is_string($valor)) {
            return null; // Falla cerrado si la autorización adicional es inválida.
        }

        $grupo = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $valor);
        if ($grupo === false) {
            return null;
        }

        return $grupo->getTimestamp() > $global->getTimestamp() ? $grupo : $global;
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
