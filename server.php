<?php

/**
 * Enrutador del servidor integrado de PHP para desarrollo local.
 *
 * Laravel 8 invoca este archivo al ejecutar artisan serve. Las rutas
 * operativas se resuelven en public/index.php. Solo se sirven archivos
 * existentes dentro de public/; los archivos privados nunca se exponen.
 */
$publico = realpath(__DIR__.'/public');
$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$ruta = is_string($ruta) ? rawurldecode($ruta) : '/';

$archivo = realpath($publico.DIRECTORY_SEPARATOR.ltrim($ruta, '/\\'));
$prefijoPublico = $publico.DIRECTORY_SEPARATOR;

if ($ruta !== '/'
    && $archivo !== false
    && strpos($archivo, $prefijoPublico) === 0
    && is_file($archivo)) {
    return false;
}

require $publico.DIRECTORY_SEPARATOR.'index.php';
