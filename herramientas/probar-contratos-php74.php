<?php

/*
 * Prueba aislada de contratos esenciales: sin Composer, Laravel, SQL Server,
 * Redis, Google o servicio WhatsApp. Compatible con PHP 7.4.
 */
if (PHP_VERSION_ID < 70413 || PHP_VERSION_ID >= 80000) {
    fwrite(STDERR, "Esta prueba debe ejecutarse con PHP 7.4.13 a 7.4.x.\n");
    exit(2);
}

require __DIR__.'/../app/Enums/DisposicionEntrada.php';
require __DIR__.'/../app/Enums/Rol.php';
require __DIR__.'/../app/Data/WhatsApp/ArchivoWhatsappEntrante.php';
require __DIR__.'/../app/Data/WhatsApp/ResultadoCaptura.php';

use App\Enums\DisposicionEntrada;
use App\Enums\Rol;
use App\Data\WhatsApp\ArchivoWhatsappEntrante;
use App\Data\WhatsApp\ResultadoCaptura;

function comprobar(bool $condicion, string $mensaje): void
{
    if (! $condicion) {
        throw new RuntimeException('Contrato incompatible: '.$mensaje);
    }
}

comprobar(DisposicionEntrada::Aceptar === 'aceptar', 'valor del contrato de aceptación');
comprobar(DisposicionEntrada::IgnorarPegatina === 'ignorar_pegatina', 'pegatinas excluidas');
comprobar(Rol::Administrador === 'administrador', 'valor persistido de administrador');
comprobar(Rol::etiqueta(Rol::Supervisor) === 'Supervisor', 'etiqueta de rol');

$capturado = ResultadoCaptura::aceptado(123);
comprobar($capturado->estado === 'aceptado', 'estado de captura');
comprobar($capturado->disposicion === 'aceptar', 'disposición JSON');
comprobar($capturado->eventoCrudoId === 123, 'ID original');

$ignorado = ResultadoCaptura::ignorado(DisposicionEntrada::IgnorarNoMonitoreado);
comprobar($ignorado->estado === 'ignorado', 'estado de exclusión');
comprobar($ignorado->disposicion === 'ignorar_no_monitoreado', 'no enviar grupos desautorizados');

$archivo = new ArchivoWhatsappEntrante(
    'archivo_ficticio',
    'pruebas/archivo-ficticio.jpg',
    str_repeat('a', 64),
    120,
    'image/jpeg',
    'archivo.jpg',
    'jpg'
);
comprobar($archivo->tamanoBytes === 120, 'metadatos de archivos');
comprobar($archivo->rutaRelativa === 'pruebas/archivo-ficticio.jpg', 'ruta relativa');

fwrite(STDOUT, "OK: contratos base PHP 7.4 sin llamadas externas.\n");
