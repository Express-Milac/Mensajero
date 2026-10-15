<?php

namespace Tests\Funcional;

use App\Services\DiagnosticoInfraestructura;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DiagnosticoInfraestructuraTest extends TestCase
{
    public function test_diagnostico_prueba_almacenamiento_privado_sin_exponer_errores(): void
    {
        DB::shouldReceive('connection')->with('sqlsrv')->andReturnSelf();
        DB::shouldReceive('select')->andThrow(new RuntimeException('password=secreto'));
        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('ping')->andReturn('PONG');
        Storage::fake('mensajero_local');
        config(['mensajero.bytes_libres_minimos' => 0]);

        $comprobaciones = app(DiagnosticoInfraestructura::class)->comprobar();

        $this->assertSame('fallo', $comprobaciones['sql_server']);
        $this->assertSame('correcto', $comprobaciones['redis']);
        $this->assertSame('correcto', $comprobaciones['almacenamiento']);
        $this->assertSame('no_implementado', $comprobaciones['whatsapp']);
        $this->assertStringNotContainsString('secreto', json_encode($comprobaciones));
        $this->assertSame([], Storage::disk('mensajero_local')->allFiles());
    }
}
