<?php

namespace Tests\Integracion;

use App\Models\ConexionWhatsapp;
use App\Services\Seguridad\ValidadorFirmaPasarela;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LatidoRecolectorSqlServerTest extends TestCase
{
    private string $secreto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secreto = str_repeat('l', 48);
        config([
            'mensajero.pasarela_secreto' => $this->secreto,
            'mensajero.seguridad_pasarela.ventana_segundos' => 300,
        ]);
    }

    public function test_latido_firmado_actualiza_utc_solo_si_la_conexion_esta_conectada(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.sqlsrv.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        DB::beginTransaction();

        try {
            $sufijo = bin2hex(random_bytes(6));
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => 'latido-'.$sufijo,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
                'ultimo_latido_en' => now('UTC')->subMinutes(10),
            ]);

            $this->enviarFirmado(
                ['clave_conexion' => $conexion->clave_conexion],
                'latido-'.$sufijo.'-0001',
            )->assertOk()->assertJsonPath('estado', 'aceptado');

            $conexion->refresh();
            $this->assertSame('conectado', $conexion->estado);
            $this->assertSame('UTC', $conexion->ultimo_latido_en->timezoneName);
            $this->assertLessThan(10, abs($conexion->ultimo_latido_en->diffInSeconds(now('UTC'))));

            $latidoConectado = $conexion->getRawOriginal('ultimo_latido_en');
            $conexion->estado = 'desconectado';
            $conexion->save();

            $this->enviarFirmado(
                ['clave_conexion' => $conexion->clave_conexion],
                'latido-'.$sufijo.'-0002',
            )->assertOk()
                ->assertJsonPath('estado', 'ignorado')
                ->assertJsonPath('motivo', 'conexion_no_conectada');

            $conexion->refresh();
            $this->assertSame('desconectado', $conexion->estado);
            $this->assertSame($latidoConectado, $conexion->getRawOriginal('ultimo_latido_en'));

            $this->enviarFirmado(
                ['clave_conexion' => 'latido-desconocido-'.$sufijo],
                'latido-'.$sufijo.'-0003',
            )->assertNotFound();
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  array<string, mixed>  $contenido
     */
    private function enviarFirmado(array $contenido, string $identificador): TestResponse
    {
        $cuerpo = json_encode($contenido, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $marcaTiempo = (string) now()->getTimestamp();
        $firma = app(ValidadorFirmaPasarela::class)->generar(
            $identificador,
            $marcaTiempo,
            $cuerpo,
            $this->secreto,
        );

        return $this->call('POST', '/api/pasarela/v1/latido', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_MENSAJERO_ID' => $identificador,
            'HTTP_X_MENSAJERO_MARCA_TIEMPO' => $marcaTiempo,
            'HTTP_X_MENSAJERO_FIRMA' => $firma,
        ], $cuerpo);
    }
}
