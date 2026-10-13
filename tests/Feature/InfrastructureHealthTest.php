<?php

namespace Tests\Feature;

use App\Services\InfrastructureHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class InfrastructureHealthTest extends TestCase
{
    public function test_health_checks_private_storage_without_disclosing_errors(): void
    {
        DB::shouldReceive('connection')->with('sqlsrv')->andReturnSelf();
        DB::shouldReceive('select')->andThrow(new RuntimeException('password=secret'));
        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('ping')->andReturn('PONG');
        Storage::fake('mensajero_local');
        config(['mensajero.min_free_bytes' => 0]);
        $checks = app(InfrastructureHealth::class)->check();
        $this->assertSame('failed', $checks['sql_server']);
        $this->assertSame('ok', $checks['redis']);
        $this->assertSame('ok', $checks['storage']);
        $this->assertSame('not_implemented', $checks['whatsapp']);
        $this->assertStringNotContainsString('secret', json_encode($checks));
        $this->assertSame([], Storage::disk('mensajero_local')->allFiles());
    }
}
