<?php

namespace App\Console\Commands;

use App\Services\Operacion\RegistrarAcontecimientoOperativo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProyectarOperacion extends Command
{
    protected $signature = 'mensajero:proyectar-operacion
        {--grupo=10020 : ID del grupo autorizado}
        {--limite=50 : Límite de resultados existentes (1-100)}
        {--aplicar : Crear proyecciones locales; sin esta opción solo previsualiza}';

    protected $description = 'Proyecta resultados de Gemini ya guardados como acontecimientos sin llamar de nuevo a Google.';

    public function handle(RegistrarAcontecimientoOperativo $registrar): int
    {
        $grupo = filter_var($this->option('grupo'), FILTER_VALIDATE_INT);
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);

        if ($grupo === false || $grupo <= 0 || $limite === false || $limite < 1 || $limite > 100
            || ! in_array($grupo, (array) config('mensajero.ia.grupos_autorizados', []), true)) {
            $this->error('Grupo o límite no autorizado; no se realizó ningún cambio.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('mensajero_acontecimientos_operativos')) {
            $this->error('Falta la migración de acontecimientos operativos.');
            return self::FAILURE;
        }

        $registros = DB::table('mensajero_analisis_operativos_ia as a')
            ->where('a.chat_monitoreado_id', $grupo)
            ->where('a.estado', 'pendiente_revision')
            ->whereNotExists(function ($consulta): void {
                $consulta->selectRaw('1')->from('mensajero_acontecimientos_operativos as o')
                    ->whereColumn('o.analisis_ia_id', 'a.id');
            })
            ->orderBy('a.id')->limit($limite)->get(['a.id', 'a.mensaje_whatsapp_id']);

        $this->info('Análisis previos sin acontecimiento: '.$registros->count());
        foreach ($registros as $fila) {
            $this->line('Análisis #'.$fila->id.' | Mensaje #'.$fila->mensaje_whatsapp_id);
        }

        if (! $this->option('aplicar')) {
            $this->comment('Simulación: no se cambió SQL Server y no se consultó Google.');
            return self::SUCCESS;
        }

        $creados = 0;
        foreach ($registros as $fila) {
            if ($registrar->desdeAnalisis((int) $fila->id) !== null) {
                $creados++;
            }
        }
        $this->info('Acontecimientos creados o reconciliados: '.$creados.'. Sin llamadas a Gemini.');
        return self::SUCCESS;
    }
}
