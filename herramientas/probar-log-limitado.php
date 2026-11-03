<?php

/**
 * Prueba aislada del canal de registros: NO abre Laravel, SQL, Redis ni Gemini.
 * Ejecutar: php7.4 herramientas/probar-log-limitado.php
 */
require dirname(__DIR__).'/vendor/autoload.php';

use App\Logging\ArchivoLimitadoHandler;
use Monolog\Logger;

function comprobar(bool $condicion, string $mensaje): void
{
    if (! $condicion) {
        throw new RuntimeException('FALLO: '.$mensaje);
    }
}

$rutaTemporal = sys_get_temp_dir().'/mensajero-log-ensayo-'.bin2hex(random_bytes(8));
if (! mkdir($rutaTemporal, 0700)) {
    throw new RuntimeException('No fue posible preparar directorio de prueba.');
}

try {
    $ruta = $rutaTemporal.'/laravel.log';
    $logger = new Logger('prueba');
    $logger->pushHandler(new ArchivoLimitadoHandler($ruta, 1000, 5242880));

    for ($i = 1; $i <= 1005; $i++) {
        $logger->warning('Evento '.$i, ['numero' => $i]);
    }

    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    comprobar(count($lineas) === 1000, 'deben conservarse exactamente 1000 eventos');
    comprobar(
        json_decode($lineas[0], true)['context']['numero'] === 6,
        'debe descartarse el mas antiguo'
    );
    comprobar(
        json_decode($lineas[999], true)['context']['numero'] === 1005,
        'debe mantenerse el evento mas reciente'
    );
    comprobar(filesize($ruta) <= 5242880, 'tamano maximo de 5 MB');

    $limitadoBytes = $rutaTemporal.'/bytes.log';
    $loggerBytes = new Logger('prueba-bytes');
    $loggerBytes->pushHandler(new ArchivoLimitadoHandler($limitadoBytes, 1000, 8192));

    for ($i = 1; $i <= 100; $i++) {
        $loggerBytes->warning('Mensaje '.$i.' '.str_repeat('A', 512));
    }

    $lineasBytes = file($limitadoBytes, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    comprobar(count($lineasBytes) < 100, 'deben descartarse eventos por limite de bytes');
    comprobar(filesize($limitadoBytes) <= 8192, 'limite de bytes estricto');
    foreach ($lineasBytes as $linea) {
        comprobar(is_array(json_decode($linea, true)), 'cada linea debe ser un evento JSON valido');
    }

    $loggerBytes->error(str_repeat('X', 100000));
    $lineasBytes = file($limitadoBytes, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $ultimo = json_decode(end($lineasBytes), true);
    comprobar(! empty($ultimo['registro_abreviado']), 'evento gigante debe abreviarse');
    comprobar(filesize($limitadoBytes) <= 8192, 'el evento gigante no debe romper el limite');

    echo "CORRECTO: 1000 eventos, 5 MB, JSONL, descarte de antiguos y evento gigante.\n";
} finally {
    foreach (glob($rutaTemporal.'/*') ?: [] as $archivo) {
        @unlink($archivo);
    }
    @rmdir($rutaTemporal);
}
