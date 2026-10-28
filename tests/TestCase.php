<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel 8 exige iniciar la aplicación con el Console Kernel.
     * No modifica SQL Server ni inicia trabajadores Redis.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Laravel 8 carece de Http::preventStrayRequests(). Además evalúa
     * los callbacks del arreglo de Http::fake antes de elegir la respuesta:
     * un comodín final que lance excepción también se ejecuta cuando
     * otra URL ya coincidió. Usar UN SOLO callback, con retorno temprano.
     *
     * @param array<string, mixed> $respuestas
     */
    protected function simularHttpSinRed(array $respuestas = []): void
    {
        // Solo los patrones concretos pueden responder. Ninguna petición
        // desconocida puede continuar hacia la red, con o sin datos falsos.
        unset($respuestas['*']);

        Http::fake(static function (Request $solicitud) use ($respuestas) {
            foreach ($respuestas as $patron => $respuesta) {
                if (! is_string($patron) || $patron === '') {
                    continue;
                }

                // Exigir HTTPS y host inicial exacto; el comodín solo aplica
                // a la ruta final. Así evitamos simular hosts parecidos.
                $patronHttps = strpos($patron, 'https://') === 0
                    ? $patron : 'https://'.$patron;

                if (Str::is($patronHttps, $solicitud->url())) {
                    return $respuesta instanceof \Closure
                        ? $respuesta($solicitud) : $respuesta;
                }
            }

            throw new RuntimeException(
                'Petición HTTP no simulada durante pruebas de Mensajero.'
            );
        });
    }
}
