<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * Canal de Laravel 8 compatible con PHP 7.4, sin rotacion por fecha.
 */
final class CrearCanalLogLimitado
{
    /**
     * @param array<string, mixed> $configuracion
     */
    public function __invoke(array $configuracion): Logger
    {
        $nivel = Logger::toMonologLevel($configuracion['level'] ?? 'warning');
        $manejador = new ArchivoLimitadoHandler(
            (string) $configuracion['path'],
            (int) ($configuracion['max_records'] ?? 1000),
            (int) ($configuracion['max_bytes'] ?? 5242880),
            $nivel
        );

        $logger = new Logger('mensajero', [$manejador]);
        $logger->pushProcessor(new PsrLogMessageProcessor());

        return $logger;
    }
}
