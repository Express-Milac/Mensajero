<?php

namespace Tests\Unitarias;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NomenclaturaEspanolTest extends TestCase
{
    public function test_no_quedan_nombres_antiguos_del_dominio_en_codigo_activo(): void
    {
        $prohibidos = [
            'mensajero_users',
            'mensajero_whatsapp_connections',
            'mensajero_monitored_chats',
            'mensajero_whatsapp_contacts',
            'mensajero_whatsapp_raw_events',
            'mensajero_outbox',
            'mensajero_whatsapp_messages',
            'MonitoredChat',
            'OutboxMessage',
            'WhatsappConnection',
            'WhatsappContact',
            'WhatsappRawEvent',
            'WhatsappMessage',
            'IncomingWhatsappEvent',
            'CaptureIncomingWhatsappEvent',
            'InboundDisposition',
            'InboundMessageGate',
            'RequireMensajeroAccess',
            'App\\Models\\',
            'App\\Services\\',
            'App\\Data\\',
            'App\\Enums\\',
        ];

        $rutas = [base_path('app'), base_path('database/migrations'), base_path('routes'), resource_path('views')];
        $hallazgos = [];

        foreach ($rutas as $ruta) {
            $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ruta));
            foreach ($archivos as $archivo) {
                if (! $archivo->isFile()) {
                    continue;
                }
                $contenido = file_get_contents($archivo->getPathname());
                foreach ($prohibidos as $prohibido) {
                    if (str_contains($contenido, $prohibido)) {
                        $hallazgos[] = $archivo->getPathname().': '.$prohibido;
                    }
                }
            }
        }

        $this->assertSame([], $hallazgos, implode(PHP_EOL, $hallazgos));
    }

    public function test_no_existen_directorios_antiguos_del_dominio(): void
    {
        foreach (['Models', 'Services', 'Data', 'Enums'] as $directorio) {
            $this->assertDirectoryDoesNotExist(base_path('app/'.$directorio));
        }
    }

    public function test_todas_las_tablas_propias_declaran_prefijo_mensajero(): void
    {
        $archivos = glob(database_path('migrations/*.php')) ?: [];
        foreach ($archivos as $archivo) {
            $contenido = file_get_contents($archivo);
            preg_match_all("/Schema::create\('([^']+)'/", $contenido, $coincidencias);
            foreach ($coincidencias[1] as $tabla) {
                $this->assertStringStartsWith('mensajero_', $tabla, "Tabla sin prefijo Mensajero: $tabla");
            }
        }
    }
}
