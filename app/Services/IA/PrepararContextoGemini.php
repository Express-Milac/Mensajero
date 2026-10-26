<?php

namespace App\Services\IA;

final class PrepararContextoGemini
{
    public function limpiar(string $texto): string
    {
        $texto = trim(strip_tags($texto));
        $texto = preg_replace('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/iu', '[correo omitido]', $texto) ?? $texto;
        $texto = preg_replace('/(?<!\w)(?:\+?\d[\s().-]*){10,15}(?!\w)/u', '[teléfono omitido]', $texto) ?? $texto;
        $texto = preg_replace('/\b[A-Z]{4}\d{6}[HM][A-Z]{5}[0-9A-Z]\d\b/iu', '[identificador omitido]', $texto) ?? $texto;
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return mb_substr(trim($texto), 0, 650, 'UTF-8');
    }
}
