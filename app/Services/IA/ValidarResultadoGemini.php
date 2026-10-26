<?php

namespace App\Services\IA;

use RuntimeException;

final class ValidarResultadoGemini
{
    /**
     * @param array<string,mixed> $datos
     * @param array<int,int> $permitidos
     * @return array{es_evento:bool,categoria:?string,titulo:?string,resumen:?string,mensaje_ids:array<int,int>}
     */
    public function validar(array $datos, array $permitidos, int $objetivo): array
    {
        if (! isset($datos['es_evento']) || ! is_bool($datos['es_evento'])) {
            throw new RuntimeException('La IA devolvió un indicador de evento inválido.');
        }

        if ($datos['es_evento'] === false) {
            return [
                'es_evento' => false,
                'categoria' => null,
                'titulo' => null,
                'resumen' => null,
                'mensaje_ids' => [],
            ];
        }

        $categoria = $datos['categoria'] ?? null;
        $titulo = $datos['titulo'] ?? null;
        $resumen = $datos['resumen'] ?? null;
        $fuentes = $datos['mensaje_ids'] ?? null;

        if (! is_string($categoria) ||
            ! in_array($categoria, ['logistica', 'incidencia', 'acuerdo', 'solicitud', 'otro'], true) ||
            ! is_string($titulo) || mb_strlen(trim($titulo)) < 5 || mb_strlen($titulo) > 180 ||
            ! is_string($resumen) || mb_strlen(trim($resumen)) < 12 || mb_strlen($resumen) > 1500 ||
            ! is_array($fuentes) || ! array_is_list($fuentes) || count($fuentes) < 1 || count($fuentes) > 6) {
            throw new RuntimeException('Gemini devolvió un evento fuera del contrato permitido.');
        }

        foreach ($fuentes as $fuente) {
            if (! is_int($fuente) || ! in_array($fuente, $permitidos, true)) {
                throw new RuntimeException('Gemini citó un mensaje inexistente o de otro contexto.');
            }
        }

        $fuentes = array_values(array_unique($fuentes));
        if (! in_array($objetivo, $fuentes, true)) {
            throw new RuntimeException('El evento debe citar el mensaje nuevo que inició el análisis.');
        }

        // Defensa adicional: no persistir identificadores privados generados por la IA.
        $texto = $titulo.' '.$resumen;
        if (preg_match('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/iu', $texto) === 1 ||
            preg_match('/(?<!\w)(?:\+?\d[\s().-]*){10,15}(?!\w)/u', $texto) === 1) {
            throw new RuntimeException('Gemini incluyó información privada en el texto generado.');
        }

        return [
            'es_evento' => true,
            'categoria' => $categoria,
            'titulo' => trim($titulo),
            'resumen' => trim($resumen),
            'mensaje_ids' => $fuentes,
        ];
    }
}
