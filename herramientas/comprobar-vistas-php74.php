<?php

/*
 * Revisión estática de Blade para PHP 7.4.
 * No utiliza Laravel, datos empresariales, red, Redis ni credenciales.
 * No sustituye php artisan view:cache ni ejecutar la aplicación.
 */
if (PHP_VERSION_ID < 70413 || PHP_VERSION_ID >= 80000) {
    fwrite(STDERR, "Este control requiere PHP 7.4.13 a PHP 7.4.x.\n");
    exit(2);
}

$directorio = dirname(__DIR__).'/resources/views';
$iterador = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS)
);
$revisados = 0;
$errores = 0;

foreach ($iterador as $archivo) {
    if (! $archivo->isFile() || substr($archivo->getFilename(), -10) !== '.blade.php') {
        continue;
    }

    $revisados++;
    $contenido = (string) file_get_contents($archivo->getPathname());
    $patrones = [
        'operador nullsafe PHP 8' => '/\?->/',
        'expresión match PHP 8' => '/\bmatch\s*\(/',
        'atributos PHP 8' => '/#\[/',
    ];

    foreach ($patrones as $nombre => $patron) {
        if (preg_match($patron, $contenido) === 1) {
            $errores++;
            fwrite(STDERR, "Vista incompatible: ".$archivo->getPathname()." (".$nombre.").\n");
        }
    }
}

fwrite(STDOUT, "Vistas Blade revisadas: ".$revisados."; incompatibilidades detectadas: ".$errores.".\n");
if ($revisados < 1 || $errores > 0) {
    exit(1);
}
fwrite(STDOUT, "Sin operadores PHP 8 evidentes en vistas; aún es necesario compilar Blade con Laravel 8.\n");
