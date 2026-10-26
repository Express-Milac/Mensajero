<?php

namespace App\Services\IA;

use RuntimeException;

/**
 * Contrato de múltiples acontecimientos. No persiste ni acepta referencias
 * a mensajes fuera del lote. No equivale a confirmar los hechos reportados.
 */
final class ValidarAcontecimientosLoteGemini
{
    /**
     * @param array<string,mixed> $respuesta
     * @param array<int,int> $idsPermitidos
     * @return array<int,array{categoria:string,titulo:string,resumen:string,mensaje_ids:array<int,int>}>
     */
    public function validar(array $respuesta, array $idsPermitidos): array
    {
        $acontecimientos = $respuesta['acontecimientos'] ?? null;
        if (! is_array($acontecimientos) || ! array_is_list($acontecimientos)
            || count($acontecimientos) > 6 || $idsPermitidos === []
            || count($idsPermitidos) > AgruparMensajesGemini::MAXIMO_MENSAJES
            || count(array_unique($idsPermitidos)) !== count($idsPermitidos)) {
            throw new RuntimeException('Respuesta o referencias del lote inválidas.');
        }

        foreach ($idsPermitidos as $id) {
            if (! is_int($id) || $id < 1) {
                throw new RuntimeException('Identificador del lote inválido.');
            }
        }

        $validados = [];
        $huellas = [];

        foreach ($acontecimientos as $evento) {
            if (! is_array($evento)
                || ! is_string($evento['categoria'] ?? null)
                || ! in_array($evento['categoria'], ['logistica', 'incidencia', 'acuerdo', 'solicitud', 'otro'], true)
                || ! is_string($evento['titulo'] ?? null)
                || mb_strlen(trim($evento['titulo'])) < 5
                || mb_strlen($evento['titulo']) > 180
                || ! is_string($evento['resumen'] ?? null)
                || mb_strlen(trim($evento['resumen'])) < 12
                || mb_strlen($evento['resumen']) > 1500
                || ! is_array($evento['mensaje_ids'] ?? null)
                || ! array_is_list($evento['mensaje_ids'])
                || count($evento['mensaje_ids']) < 1
                || count($evento['mensaje_ids']) > 6) {
                throw new RuntimeException('Acontecimiento fuera del contrato permitido.');
            }

            foreach ($evento['mensaje_ids'] as $id) {
                if (! is_int($id) || ! in_array($id, $idsPermitidos, true)) {
                    throw new RuntimeException('El acontecimiento cita mensajes ajenos al lote.');
                }
            }

            $referencias = array_values(array_unique($evento['mensaje_ids']));
            $texto = $evento['titulo'].' '.$evento['resumen'];
            if (preg_match('/[\\w.+-]+@[\\w.-]+\\.[a-z]{2,}/iu', $texto) === 1
                || preg_match('/(?<!\\w)(?:\\+?\\d[\\s().-]*){10,15}(?!\\w)/u', $texto) === 1) {
                throw new RuntimeException('El acontecimiento contiene información privada.');
            }

            $titulo = trim($evento['titulo']);
            $resumen = trim($evento['resumen']);
            $huella = hash('sha256', json_encode(
                [$evento['categoria'], mb_strtolower($titulo), $referencias],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ));
            if (isset($huellas[$huella])) {
                throw new RuntimeException('Gemini repitió un acontecimiento dentro del lote.');
            }
            $huellas[$huella] = true;

            $validados[] = [
                'categoria' => $evento['categoria'],
                'titulo' => $titulo,
                'resumen' => $resumen,
                'mensaje_ids' => $referencias,
            ];
        }

        return $validados;
    }
}
