<?php

namespace Tests\Funcional;

use App\Services\DiagnosticoInfraestructura;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class FalloDiagnosticoTest extends TestCase
{
    public function test_fallo_almacenamiento_se_reporta_y_fallo_redis_es_independiente(): void
    {
        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('select')->andReturn([['saludable' => 1]]);
        Redis::shouldReceive('connection')->andThrow(new RuntimeException('no disponible'));
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->andThrow(new RuntimeException('disco lleno'));
        Storage::shouldReceive('delete')->once()->andReturn(true);

        $comprobaciones = app(DiagnosticoInfraestructura::class)->comprobar();

        $this->assertSame('correcto', $comprobaciones['sql_server']);
        $this->assertSame('fallo', $comprobaciones['redis']);
        $this->assertSame('fallo', $comprobaciones['almacenamiento']);
    }
}
