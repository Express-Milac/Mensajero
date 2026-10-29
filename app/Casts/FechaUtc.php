<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * SQL Server datetime/datetime2 no guarda zona horaria.
 * En Mensajero estos campos se persisten y recuperan siempre como UTC.
 */
final class FechaUtc implements CastsAttributes
{
    /**
     * Impide que Eloquent devuelva el objeto Carbon original tras set().
     * Siempre se debe leer el valor normalizado desde los atributos UTC.
     */
    public bool $withoutObjectCaching = true;

    public function get($model, $key, $value, $attributes): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        return CarbonImmutable::parse((string) $value, 'UTC')->utc();
    }

    public function set($model, $key, $value, $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $fecha = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse((string) $value, 'UTC');

        return $fecha->utc()->format('Y-m-d H:i:s.u');
    }
}
