<?php

namespace App\Services\Seguridad;

use JsonException;

/**
 * Produce una vista de diagnóstico, no una copia de la evidencia.
 * El contenido íntegro y su hash permanecen exclusivamente en SQL Server.
 */
final class PrepararContenidoEvidencia
{
    public const MARCA_OCULTA = '[OCULTO EN LA VISTA]';

    /**
     * @return array{contenido: string, truncado: bool}
     */
    public function ejecutar(string $contenidoCrudo, int $maximoCaracteres = 100_000): array
    {
        // Nunca renderizar JSON truncado: podría dejar un valor secreto visible a medias.
        if (mb_strlen($contenidoCrudo, 'UTF-8') > $maximoCaracteres) {
            return [
                'contenido' => 'Vista previa omitida por tamaño. El registro original permanece íntegro en SQL Server.',
                'truncado' => true,
            ];
        }

        try {
            $datos = json_decode($contenidoCrudo, true, 64, JSON_THROW_ON_ERROR);

            if (! is_array($datos)) {
                return [
                    'contenido' => 'Vista previa no disponible para contenido sin estructura JSON.',
                    'truncado' => false,
                ];
            }

            $preparado = $this->ocultar($datos);

            return [
                'contenido' => json_encode(
                    $preparado,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                ),
                'truncado' => false,
            ];
        } catch (JsonException) {
            return [
                'contenido' => 'Vista previa no disponible: formato JSON no interpretable. Consulte la evidencia conservada en SQL Server.',
                'truncado' => false,
            ];
        }
    }

    /**
     * @param  array<array-key, mixed>  $datos
     * @return array<array-key, mixed>
     */
    private function ocultar(array $datos): array
    {
        foreach ($datos as $clave => $valor) {
            if (is_string($clave) && $this->esCampoRestringido($clave)) {
                $datos[$clave] = self::MARCA_OCULTA;

                continue;
            }

            if (is_array($valor)) {
                $datos[$clave] = $this->ocultar($valor);
            }
        }

        return $datos;
    }

    private function esCampoRestringido(string $campo): bool
    {
        $normalizado = preg_replace('/[^a-z0-9]/', '', strtolower($campo)) ?? '';

        return preg_match(
            '/secret|token|password|passphrase|credential|privatekey|apikey|mediakey|auth|session|cookie|bearer|signature|cipher|directpath|thumbnail|participant|jid|phonenumber|phone|senderpn|senderlid|recipient|deviceid|^url$|^iv$/',
            $normalizado,
        ) === 1;
    }
}
