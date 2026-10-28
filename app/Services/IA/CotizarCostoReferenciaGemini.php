<?php

namespace App\Services\IA;

/**
 * Referencia de cálculo exclusivamente para el cliente de texto generateContent.
 *
 * NO autoriza gastos, NO ejecuta Google, NO reserva SQL y NO decide activaciones.
 * Debe contrastarse con las tarifas oficiales el día de la autorización.
 * La entrada se cotiza hasta el límite FÍSICO del modelo para no depender de
 * equivalencias inventadas entre bytes, caracteres y tokens.
 *
 * Fuentes consultadas el 28/10/2026:
 * https://ai.google.dev/gemini-api/docs/pricing
 * https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash-lite
 * https://ai.google.dev/gemini-api/docs/thinking
 */
final class CotizarCostoReferenciaGemini
{
    public const MODELO = 'gemini-3.5-flash-lite';

    public const FECHA_TARIFA = '2026-10-28';

    public const LIMITE_ENTRADA_TOKENS = 1_048_576;

    // Son los maxOutputTokens explícitos de los clientes existentes.
    public const LIMITE_SALIDA_INDIVIDUAL = 900;

    public const LIMITE_SALIDA_LOTE = 1800;

    // USD 0.30 y USD 2.50 por millón de tokens, respectivamente.
    // Los importes se expresan en MICROUSD y se calculan solo con enteros.
    private const ENTRADA_MICROUSD_POR_MILLON = 300_000;

    private const SALIDA_MICROUSD_POR_MILLON = 2_500_000;

    private const TOKENS_POR_MILLON = 1_000_000;

    /**
     * @return array{
     *   modelo:string, circuito:string, tarifa_fecha:string,
     *   maximo_entrada_tokens:int, maximo_salida_tokens:int,
     *   reserva_entrada_microusd:int, reserva_salida_microusd:int,
     *   importe_referencia_microusd:int, solo_referencia:bool
     * }|null
     */
    public function estimar(string $modelo, string $circuito): ?array
    {
        // Nunca aplicar la tarifa de un modelo a otro, ni a una vía de
        // procesamiento que pudiera utilizar herramientas o caché.
        if ($modelo !== self::MODELO
            || ! in_array($circuito, ['mensaje', 'lote'], true)) {
            return null;
        }

        $salida = $circuito === 'mensaje'
            ? self::LIMITE_SALIDA_INDIVIDUAL
            : self::LIMITE_SALIDA_LOTE;

        // Precio de salida incluye pensamiento según la documentación.
        // maxOutputTokens limita todos esos tokens en generateContent.
        $entradaMicro = $this->redondearArriba(
            self::LIMITE_ENTRADA_TOKENS,
            self::ENTRADA_MICROUSD_POR_MILLON,
        );
        $salidaMicro = $this->redondearArriba(
            $salida,
            self::SALIDA_MICROUSD_POR_MILLON,
        );

        return [
            'modelo' => $modelo,
            'circuito' => $circuito,
            'tarifa_fecha' => self::FECHA_TARIFA,
            'maximo_entrada_tokens' => self::LIMITE_ENTRADA_TOKENS,
            'maximo_salida_tokens' => $salida,
            'reserva_entrada_microusd' => $entradaMicro,
            'reserva_salida_microusd' => $salidaMicro,
            'importe_referencia_microusd' => $entradaMicro + $salidaMicro,
            'solo_referencia' => true,
        ];
    }

    private function redondearArriba(int $tokens, int $tarifaMicroPorMillon): int
    {
        return intdiv(
            $tokens * $tarifaMicroPorMillon + self::TOKENS_POR_MILLON - 1,
            self::TOKENS_POR_MILLON,
        );
    }
}
