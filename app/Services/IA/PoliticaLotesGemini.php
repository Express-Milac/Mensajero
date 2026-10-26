<?php

namespace App\Services\IA;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Autorización técnica independiente para compartir contexto entre mensajes.
 * Nunca reemplaza el consentimiento general o el permiso por grupo en SQL.
 */
final class PoliticaLotesGemini
{
    public function habilitada(): bool
    {
        return config('mensajero.ia.lotes_habilitados') === true
            && $this->corte() !== null
            && $this->maximoDiario() > 0;
    }

    public function corte(): ?DateTimeImmutable
    {
        $texto = trim((string) config('mensajero.ia.lotes_fecha_inicio', ''));
        if ($texto === '') {
            return null;
        }

        $fecha = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $texto);
        return $fecha === false ? null : $fecha;
    }

    public function maximoDiario(): int
    {
        $valor = (int) config('mensajero.ia.lotes_maximo_dia_grupo', 10);
        return $valor >= 1 && $valor <= 30 ? $valor : 0;
    }

    public function posteriorAlCorte(DateTimeInterface $fecha): bool
    {
        $corte = $this->corte();

        return $this->habilitada()
            && $corte !== null
            && $fecha->getTimestamp() >= $corte->getTimestamp();
    }
}
