<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SaludInfraestructura
{
    public function verificar(): array
    {
        return [
            'aplicacion' => 'correcto',
            'sql_server' => $this->probar(fn () => DB::connection('sqlsrv')->select('SELECT 1 AS saludable')),
            'redis' => $this->probar(fn () => Redis::connection()->ping()),
            'almacenamiento' => $this->probar(function () {
                $nombreDisco = config('filesystems.default');
                $disco = Storage::disk($nombreDisco);
                $ruta = '.salud/'.Str::uuid();
                $valor = Str::random(32);
                try {
                    if (! $disco->put($ruta, $valor) || $disco->get($ruta) !== $valor) {
                        throw new RuntimeException('Fallo de lectura o escritura en almacenamiento');
                    }
                    if (config("filesystems.disks.$nombreDisco.driver") === 'local') {
                        $libre = disk_free_space($disco->path(''));
                        if ($libre === false || $libre < config('mensajero.bytes_libres_minimos')) {
                            throw new RuntimeException('Espacio de almacenamiento insuficiente');
                        }
                    }
                } finally {
                    $disco->delete($ruta);
                }
            }),
            'whatsapp' => 'no_implementado',
            'gemini' => config('mensajero.ia_habilitada') ? 'no_implementado' : 'deshabilitado',
        ];
    }

    private function probar(callable $prueba): string
    {
        try {
            $prueba();

            return 'correcto';
        } catch (Throwable) {
            return 'fallo';
        }
    }
}
