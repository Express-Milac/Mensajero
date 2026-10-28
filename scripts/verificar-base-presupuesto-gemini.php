<?php

/**
 * Prevalidación del destino físico SQL, utilizada ANTES de migrar.
 * Ejecutar con APP_ENV=testing y DB_DATABASE=mensajero_integracion_test.
 */
require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    if (! app()->environment('testing')
        || config('database.default') !== 'sqlsrv'
        || config('database.connections.sqlsrv.database') !== 'mensajero_integracion_test'
        || \Illuminate\Support\Facades\DB::connection('sqlsrv')->getDriverName() !== 'sqlsrv') {
        throw new \RuntimeException('Configuración de SQL Server de prueba inválida.');
    }

    $bd = \Illuminate\Support\Facades\DB::connection('sqlsrv')
        ->selectOne('SELECT DB_NAME() AS nombre')->nombre ?? null;

    if ($bd !== 'mensajero_integracion_test') {
        throw new \RuntimeException('La base de datos física no es mensajero_integracion_test.');
    }

    if (config('mensajero.ia.habilitada') !== false
        || config('mensajero.ia.envio_autorizado') !== false
        || config('mensajero.ia.lotes_habilitados') !== false
        || config('mensajero.ia.presupuesto_monetario.habilitado') !== false) {
        throw new \RuntimeException('Controles de IA o presupuesto encendidos.');
    }

    echo "BASE_SQL_PRESUPUESTO_VERIFICADA\n";
} catch (\Throwable $error) {
    fwrite(STDERR, "BASE_SQL_PRESUPUESTO_BLOQUEADA: ".class_basename($error)."\n");
    exit(1);
}
