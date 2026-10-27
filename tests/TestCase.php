<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
     * Laravel 8 carece de Http::preventStrayRequests().
     * La última respuesta comodín rechaza CUALQUIER URL no simulada.
     * El orden de reglas es importante: primero la URL autorizada, luego '*'.
     *
     * @param array<string, mixed> $respuestas
     */
    protected function simularHttpSinRed(array $respuestas = []): void
    {
        // Ninguna prueba de Mensajero debe contactar Google ni otras API reales.
        unset($respuestas['*']);
        $respuestas['*'] = static function (Request $solicitud) {
            throw new RuntimeException('Petición HTTP no simulada durante pruebas de Mensajero.');
        };

        Http::fake($respuestas);
    }
}
