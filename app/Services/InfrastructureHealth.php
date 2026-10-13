<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InfrastructureHealth
{
    public function check(): array
    {
        return [
            'application' => 'ok',
            'sql_server' => $this->probe(fn () => DB::connection('sqlsrv')->select('SELECT 1 AS healthy')),
            'redis' => $this->probe(fn () => Redis::connection()->ping()),
            'storage' => $this->probe(function () {
                $diskName = config('filesystems.default');
                $disk = Storage::disk($diskName);
                $path = '.health/'.Str::uuid();
                $value = Str::random(32);
                try {
                    if (! $disk->put($path, $value) || $disk->get($path) !== $value) {
                        throw new RuntimeException('Storage read/write failure');
                    }
                    if (config("filesystems.disks.$diskName.driver") === 'local') {
                        $free = disk_free_space($disk->path(''));
                        if ($free === false || $free < config('mensajero.min_free_bytes')) {
                            throw new RuntimeException('Insufficient storage');
                        }
                    }
                } finally {
                    $disk->delete($path);
                }
            }),
            'whatsapp' => 'not_implemented',
            'gemini' => config('mensajero.ai_enabled') ? 'not_implemented' : 'disabled',
        ];
    }

    private function probe(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
