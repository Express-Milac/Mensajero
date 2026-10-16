<?php

namespace App\Services\Meta;

final class ValidadorFirmaMeta
{
    public function generar(string $cuerpo, string $secreto): string
    {
        return 'sha256='.hash_hmac('sha256', $cuerpo, $secreto);
    }

    public function validar(string $cuerpo, string $firma, string $secreto): bool
    {
        if ($secreto === '' || ! preg_match('/^sha256=[a-f0-9]{64}$/i', $firma)) {
            return false;
        }

        return hash_equals(
            strtolower($this->generar($cuerpo, $secreto)),
            strtolower($firma),
        );
    }
}
