<?php

namespace Tests\Funcionales;

use App\Servicios\SaludInfraestructura;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\CasoPrueba;

class FallosSaludTest extends CasoPrueba
{
    public function test_fallos_de_almacenamiento_y_redis_se_reportan_de_forma_independiente(): void
    {
        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('select')->andReturn([['saludable' => 1]]);
        Redis::shouldReceive('connection')->andThrow(new RuntimeException('no disponible'));
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->andThrow(new RuntimeException('disco lleno'));
        Storage::shouldReceive('delete')->once()->andReturn(true);

        $comprobaciones = app(SaludInfraestructura::class)->verificar();

        $this->assertSame('correcto', $comprobaciones['sql_server']);
        $this->assertSame('fallo', $comprobaciones['redis']);
        $this->assertSame('fallo', $comprobaciones['almacenamiento']);
    }
}
