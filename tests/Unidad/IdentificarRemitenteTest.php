<?php

namespace Tests\Unidad;

use App\Models\EventoCrudoWhatsapp;
use App\Services\Mensajes\IdentificarRemitente;
use PHPUnit\Framework\TestCase;

final class IdentificarRemitenteTest extends TestCase
{
    public function test_usa_nombre_de_whatsapp_sin_presentarlo_como_identidad_verificada(): void
    {
        $evento = new EventoCrudoWhatsapp([
            'conexion_id' => 12,
            'id_remitente_proveedor' => '1234567890@lid',
            'contenido' => json_encode([
                'key' => ['participant' => '1234567890@lid'],
                'pushName' => '  Laura — Operaciones  ',
                'message' => ['conversation' => 'Ya llegamos'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $identificador = new IdentificarRemitente;

        $this->assertSame('Laura — Operaciones', $identificador->nombreWhatsapp($evento));
        $this->assertSame('Laura — Operaciones', $identificador->nombre($evento));
    }

    public function test_reconoce_telefono_real_y_no_confunde_lid_con_telefono(): void
    {
        $identificador = new IdentificarRemitente;

        $telefono = new EventoCrudoWhatsapp([
            'conexion_id' => 1,
            'id_remitente_proveedor' => '5214611234567@s.whatsapp.net',
            'contenido' => '{"message":{"conversation":"Hola"}}',
        ]);

        $lid = new EventoCrudoWhatsapp([
            'conexion_id' => 1,
            'id_remitente_proveedor' => '81234567890@lid',
            'contenido' => '{"message":{"conversation":"Hola"}}',
        ]);

        $this->assertSame('Teléfono +5214611234567', $identificador->nombre($telefono));
        $this->assertSame('Persona sin identificar', $identificador->nombre($lid));
    }

    public function test_no_utiliza_nombres_incrustados_en_el_texto_ni_identificadores_falsos(): void
    {
        $evento = new EventoCrudoWhatsapp([
            'conexion_id' => 4,
            'id_remitente_proveedor' => null,
            'contenido' => '{"message":{"conversation":"Soy el director de la empresa."}}',
        ]);

        $identificador = new IdentificarRemitente;
        $this->assertNull($identificador->nombreWhatsapp($evento));
        $this->assertSame('Persona sin identificar', $identificador->nombre($evento));
    }
}
