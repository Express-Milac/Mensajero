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

    public function test_prioriza_nombre_y_celular_enviados_por_el_recolector_sin_modificar_json(): void
    {
        $crudo = '{"key":{"participant":"99332211@lid"},"message":{"conversation":"Entregado"}}';
        $evento = new EventoCrudoWhatsapp([
            'conexion_id' => 10,
            'id_remitente_proveedor' => '99332211@lid',
            'contenido' => $crudo,
            'nombre_remitente' => 'Rosa - Operaciones',
            'fuente_nombre_remitente' => 'contacto',
            'telefono_remitente' => '5214611234567',
        ]);

        $identificador = new IdentificarRemitente;

        $this->assertSame('Rosa - Operaciones', $identificador->nombreWhatsapp($evento));
        $this->assertSame('Rosa - Operaciones', $identificador->nombre($evento));
        $this->assertSame('5214611234567', $identificador->telefono($evento));
        $this->assertSame($crudo, $evento->contenido);
    }

    public function test_nombre_de_perfil_prevalece_si_no_hay_agenda_pero_el_celular_no_se_infiere_de_lid(): void
    {
        $evento = new EventoCrudoWhatsapp([
            'conexion_id' => 10,
            'id_remitente_proveedor' => '99332211@lid',
            'contenido' => '{"key":{"participant":"99332211@lid"},"message":{"conversation":"Hola"}}',
            'nombre_remitente' => 'Perfil Laura',
            'fuente_nombre_remitente' => 'perfil',
        ]);

        $identificador = new IdentificarRemitente;
        $this->assertSame('Perfil Laura', $identificador->nombre($evento));
        $this->assertNull($identificador->telefono($evento));
    }

    public function test_utiliza_telefono_asociado_a_lid_solo_si_la_clave_lo_confirma(): void
    {
        $evento = new EventoCrudoWhatsapp([
            'conexion_id' => 1,
            'id_remitente_proveedor' => '81234567890@lid',
            'contenido' => json_encode([
                'key' => [
                    'participant' => '81234567890@lid',
                    'participantPn' => '5214611234567@s.whatsapp.net',
                ],
                'message' => ['conversation' => 'Saludos'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $identificador = new IdentificarRemitente;
        $this->assertSame('Teléfono +5214611234567', $identificador->nombre($evento));

        $evento->contenido = json_encode([
            'key' => [
                'participant' => 'otro@lid',
                'participantPn' => '5214611234567@s.whatsapp.net',
            ],
            'message' => ['conversation' => 'Saludos'],
        ], JSON_THROW_ON_ERROR);

        $this->assertSame('Persona sin identificar', $identificador->nombre($evento));
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
