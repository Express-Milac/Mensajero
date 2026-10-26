<?php

namespace App\Services\IA;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Planificador local, sin SQL, Redis ni Google.
 *
 * Un lote es solamente una ventana cronológica, NO prueba que todos sus
 * mensajes se refieran a la misma operación logística. Esa distinción
 * corresponde al análisis y debe conservar referencias a cada mensaje.
 */
final class AgruparMensajesGemini
{
    public const MAXIMO_MENSAJES = 6;
    public const MAXIMO_VENTANA_SEGUNDOS = 300;
    public const MAXIMO_SEPARACION_SEGUNDOS = 90;

    /**
     * @param array<int,array{id:int,grupo_id:int,recibido_en:DateTimeInterface,texto:string}> $mensajes
     * @return array<int,array<int,int>> IDs por lote, en orden cronológico.
     */
    public function agrupar(array $mensajes): array
    {
        $vistos = [];
        $ordenados = [];
        foreach ($mensajes as $mensaje) {
            $id = $mensaje['id'] ?? null;
            $grupo = $mensaje['grupo_id'] ?? null;
            $fecha = $mensaje['recibido_en'] ?? null;
            $texto = $mensaje['texto'] ?? null;

            if (! is_int($id) || $id < 1 || isset($vistos[$id])
                || ! is_int($grupo) || $grupo < 1
                || ! $fecha instanceof DateTimeInterface
                || ! is_string($texto) || trim($texto) === '') {
                throw new InvalidArgumentException('El lote contiene un mensaje inválido o duplicado.');
            }

            $vistos[$id] = true;
            $ordenados[] = [
                'id' => $id,
                'grupo_id' => $grupo,
                'marca' => $fecha->getTimestamp(),
            ];
        }

        // Primero por grupo: mensajes intercalados de grupos diferentes
        // no deben fragmentar una conversación dentro de su propio grupo.
        usort($ordenados, static fn (array $a, array $b): int =>
            ($a['grupo_id'] <=> $b['grupo_id'])
                ?: (($a['marca'] <=> $b['marca']) ?: ($a['id'] <=> $b['id']))
        );

        $lotes = [];
        $actual = [];
        $grupoActual = null;
        $primero = null;
        $anterior = null;

        foreach ($ordenados as $mensaje) {
            $separar = $actual !== [] && (
                $mensaje['grupo_id'] !== $grupoActual
                || count($actual) >= self::MAXIMO_MENSAJES
                || $mensaje['marca'] - $primero > self::MAXIMO_VENTANA_SEGUNDOS
                || $mensaje['marca'] - $anterior > self::MAXIMO_SEPARACION_SEGUNDOS
            );

            if ($separar) {
                $lotes[] = $actual;
                $actual = [];
            }

            if ($actual === []) {
                $grupoActual = $mensaje['grupo_id'];
                $primero = $mensaje['marca'];
            }

            $actual[] = $mensaje['id'];
            $anterior = $mensaje['marca'];
        }

        if ($actual !== []) {
            $lotes[] = $actual;
        }

        return $lotes;
    }
}
