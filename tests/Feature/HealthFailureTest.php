<?php

namespace Tests\Feature;

use App\Services\InfrastructureHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class HealthFailureTest extends TestCase
{
    public function test_storage_failure_is_reported_and_redis_failure_is_independent(): void
    {
        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('select')->andReturn([['healthy' => 1]]);
        Redis::shouldReceive('connection')->andThrow(new RuntimeException('unavailable'));
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->andThrow(new RuntimeException('disk full'));
        Storage::shouldReceive('delete')->once()->andReturn(true);
        $checks = app(InfrastructureHealth::class)->check();
        $this->assertSame('ok', $checks['sql_server']);
        $this->assertSame('failed', $checks['redis']);
        $this->assertSame('failed', $checks['storage']);
    }
}
