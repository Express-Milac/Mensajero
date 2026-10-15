<?php

namespace Tests\Funcionales;

use App\Servicios\SaludInfraestructura;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\CasoPrueba;

class SaludInfraestructuraTest extends CasoPrueba
{
    public function test_salud_no_expone_secretos_y_limpia_archivo_temporal(): void
    {
        DB::shouldReceive('connection')->with('sqlsrv')->andReturnSelf();
        DB::shouldReceive('select')->andThrow(new RuntimeException('password=secreto'));
        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('ping')->andReturn('PONG');
        Storage::fake('mensajero_local');
        config(['mensajero.bytes_libres_minimos' => 0]);

        $comprobaciones = app(SaludInfraestructura::class)->verificar();

        $this->assertSame('fallo', $comprobaciones['sql_server']);
        $this->assertSame('correcto', $comprobaciones['redis']);
        $this->assertSame('correcto', $comprobaciones['almacenamiento']);
        $this->assertSame('no_implementado', $comprobaciones['whatsapp']);
        $this->assertStringNotContainsString('secreto', json_encode($comprobaciones));
        $this->assertSame([], Storage::disk('mensajero_local')->allFiles());
    }
}
