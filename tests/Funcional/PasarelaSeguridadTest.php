<?php

namespace Tests\Funcional;

use Tests\TestCase;

class PasarelaSeguridadTest extends TestCase
{
    public function test_pasarela_falla_cerrado_sin_secreto(): void
    {
        config(['mensajero.pasarela_secreto' => '']);

        $this->postJson('/api/pasarela/v1/chats-descubiertos', [
            'clave_conexion' => 'prueba',
            'chats' => [],
        ])->assertStatus(503)
            ->assertJsonPath('mensaje', 'La firma de pasarela no está configurada.');
    }

    public function test_pasarela_rechaza_firma_invalida_antes_de_consultar_dominio(): void
    {
        config([
            'mensajero.pasarela_secreto' => str_repeat('s', 32),
            'mensajero.seguridad_pasarela.ventana_segundos' => 300,
        ]);

        $this->withHeaders([
            'X-Mensajero-Id' => 'solicitud-funcional-0001',
            'X-Mensajero-Marca-Tiempo' => (string) now()->getTimestamp(),
            'X-Mensajero-Firma' => 'sha256='.str_repeat('0', 64),
        ])->postJson('/api/pasarela/v1/chats-descubiertos', [
            'clave_conexion' => 'prueba',
            'chats' => [],
        ])->assertUnauthorized()
            ->assertJsonPath('mensaje', 'Solicitud de pasarela no autorizada.');
    }
}
