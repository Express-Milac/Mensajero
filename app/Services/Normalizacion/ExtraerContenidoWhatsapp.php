<?php

namespace App\Services\Normalizacion;

use Carbon\CarbonImmutable;
use JsonException;

/**
 * Extrae campos consultables; el evento crudo sigue siendo la evidencia fuente.
 * No inventa texto ni marcas temporales cuando WhatsApp no las proporciona.
 */
final class ExtraerContenidoWhatsapp
{
    /**
     * @return array{texto: ?string, pie_multimedia: ?string, enviado_en: ?CarbonImmutable}
     *
     * @throws JsonException
     */
    public function ejecutar(string $contenidoCrudo): array
    {
        $valor = json_decode($contenidoCrudo, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($valor)) {
            throw new JsonException('El contenido crudo debe representar un objeto JSON.');
        }

        $mensaje = $valor['message'] ?? $valor;

        for ($indice = 0; $indice < 5 && is_array($mensaje); $indice++) {
            $envuelto = $mensaje['ephemeralMessage']['message']
                ?? $mensaje['viewOnceMessage']['message']
                ?? $mensaje['viewOnceMessageV2']['message']
                ?? $mensaje['documentWithCaptionMessage']['message']
                ?? null;

            if (! is_array($envuelto)) {
                break;
            }

            $mensaje = $envuelto;
        }

        $texto = null;
        $pie = null;

        if (is_array($mensaje)) {
            $texto = $this->cadena($mensaje['conversation'] ?? null)
                ?? $this->cadena($mensaje['extendedTextMessage']['text'] ?? null)
                ?? $this->cadena($mensaje['text']['body'] ?? null)
                ?? $this->cadena($mensaje['text'] ?? null);

            $pie = $this->cadena($mensaje['imageMessage']['caption'] ?? null)
                ?? $this->cadena($mensaje['videoMessage']['caption'] ?? null)
                ?? $this->cadena($mensaje['documentMessage']['caption'] ?? null);
        }

        return [
            'texto' => $texto,
            'pie_multimedia' => $pie,
            'enviado_en' => $this->fechaProveedor($valor['messageTimestamp'] ?? $valor['timestamp'] ?? null),
        ];
    }

    private function cadena(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    private function fechaProveedor(mixed $marca): ?CarbonImmutable
    {
        if (is_array($marca)) {
            $marca = $marca['low'] ?? $marca['seconds'] ?? null;
        }

        if (! is_int($marca) && ! (is_string($marca) && ctype_digit($marca))) {
            return null;
        }

        $segundos = (int) $marca;

        // Descarta marcas absurdas en vez de convertirlas en fechas aparentes.
        if ($segundos < 946684800 || $segundos > 4102444800) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC($segundos);
    }
}
