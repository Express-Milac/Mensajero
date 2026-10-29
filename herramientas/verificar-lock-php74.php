<?php

/*
 * Guardián de solo lectura para la compatibilidad de dependencias.
 * No invoca Composer, Artisan, Redis, SQL Server ni servicios externos.
 * Se ejecuta en la copia aislada, después de generar el nuevo composer.lock.
 */
$base = dirname(__DIR__);
$requisitos = json_decode((string) file_get_contents($base.'/composer.json'), true);
$lock = json_decode((string) file_get_contents($base.'/composer.lock'), true);

if (! is_array($requisitos) || ! is_array($lock)) {
    fwrite(STDERR, "ERROR: faltan composer.json o composer.lock válidos.\n");
    exit(2);
}

$plataforma = $requisitos['config']['platform']['php'] ?? null;
if ($plataforma !== '7.4.13') {
    fwrite(STDERR, "ERROR: Composer no está fijado a PHP 7.4.13.\n");
    exit(1);
}

$versionLaravel = null;
foreach (($lock['packages'] ?? []) as $paquete) {
    if (($paquete['name'] ?? null) === 'laravel/framework') {
        $versionLaravel = $paquete['version'] ?? null;
        break;
    }
}

if (! is_string($versionLaravel) || preg_match('/^v?8\./', $versionLaravel) !== 1) {
    fwrite(STDERR, "ERROR: composer.lock NO incluye Laravel 8; no instalarlo en Mensajero MILAC.\n");
    exit(1);
}

fwrite(STDOUT, 'OK: composer.lock incluye Laravel '.$versionLaravel.' y plataforma PHP '.$plataforma.".\n");
fwrite(STDOUT, "Aún debe verificarse la plataforma física mediante Composer 2 y las pruebas de aplicación.\n");
