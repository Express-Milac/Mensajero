<?php

namespace App\Services\Seguridad;

use Carbon\CarbonImmutable;

final class ValidadorFirmaPasarela
{
    public const VERSION = 'v1';

    public function generar(string $identificador, string $marcaTiempo, string $cuerpo, string $secreto): string
    {
        $canonico = implode("\n", [
            self::VERSION,
            $identificador,
            $marcaTiempo,
            hash('sha256', $cuerpo),
        ]);

        return 'sha256='.hash_hmac('sha256', $canonico, $secreto);
    }

    public function validar(
        string $identificador,
        string $marcaTiempo,
        string $cuerpo,
        string $firma,
        string $secreto,
        int $ventanaSegundos,
        ?CarbonImmutable $ahora = null) : bool {
        if (! preg_match('/^[A-Za-z0-9._:-]{16,128}$/', $identificador)) {
            return false;
        }

        if (! preg_match('/^[0-9]{10,11}$/', $marcaTiempo)) {
            return false;
        }

        if (! preg_match('/^sha256=[a-f0-9]{64}$/i', $firma)) {
            return false;
        }

        $ahora ??= CarbonImmutable::now();
        $diferencia = abs($ahora->getTimestamp() - (int) $marcaTiempo);

        if ($ventanaSegundos < 1 || $diferencia > $ventanaSegundos) {
            return false;
        }

        $esperada = $this->generar($identificador, $marcaTiempo, $cuerpo, $secreto);

        return hash_equals(strtolower($esperada), strtolower($firma));
    }
}
