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
        {--todos : Procesar únicamente grupos habilitados expresamente para Gemini}
        {--limite=50 : Límite de resultados existentes (1-100)}
        {--aplicar : Crear proyecciones locales; sin esta opción solo previsualiza}';

    protected $description = 'Proyecta resultados de Gemini ya guardados como acontecimientos sin llamar de nuevo a Google.';

    public function handle(RegistrarAcontecimientoOperativo $registrar): int
    {
        $grupo = filter_var($this->option('grupo'), FILTER_VALIDATE_INT);
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);

        if ($grupo === false || $grupo <= 0 || $limite === false || $limite < 1 || $limite > 100) {
            $this->error('Grupo o límite incorrecto.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('mensajero_acontecimientos_operativos')
            || ! Schema::hasTable('mensajero_analisis_operativos_ia')) {
            $this->error('Faltan las migraciones de análisis o acontecimientos.');
            return self::FAILURE;
        }

        $autorizados = app(\App\Services\IA\ControlInterpretacionGemini::class)->gruposHabilitados();
        $grupos = $this->option('todos') ? $autorizados : [$grupo];
        if ($grupos === [] || count($grupos) > 6) {
            $this->error('No existen grupos habilitados válidos.');
            return self::FAILURE;
        }

        foreach ($grupos as $idGrupo) {
            if (! in_array($idGrupo, $autorizados, true)
                || ! DB::table('mensajero_chats_monitoreados')
                    ->where('id', $idGrupo)->where('tipo_chat', 'grupo')
                    ->where('es_activo', true)->where('es_monitoreado', true)
                    ->where('es_visible_tablero', true)->exists()) {
                $this->error('Grupo no autorizado: '.$idGrupo);
                return self::FAILURE;
            }
        }

        $creados = 0;
        foreach ($grupos as $idGrupo) {
            $registros = DB::table('mensajero_analisis_operativos_ia as a')
                ->where('a.chat_monitoreado_id', $idGrupo)
                ->whereIn('a.estado', ['identificado_ia', 'pendiente_revision'])
                ->whereNotExists(function ($consulta): void {
                    $consulta->selectRaw('1')->from('mensajero_acontecimientos_operativos as o')
                        ->whereColumn('o.analisis_ia_id', 'a.id');
                })
                ->orderBy('a.id')->limit($limite)->get(['a.id', 'a.mensaje_whatsapp_id']);

            $this->info('Grupo '.$idGrupo.' | análisis sin acontecimiento: '.$registros->count());
            foreach ($registros as $fila) {
                $this->line('Análisis #'.$fila->id.' | Mensaje #'.$fila->mensaje_whatsapp_id);
            }

            if (! $this->option('aplicar')) {
                $this->comment('Simulación: no se cambió SQL Server y no se consultó Google.');
                continue;
            }

            foreach ($registros as $fila) {
                if ($registrar->desdeAnalisis((int) $fila->id) !== null) {
                    $creados++;
                }
            }
        }
        $this->info('Acontecimientos creados o reconciliados: '.$creados.'. Sin llamadas a Gemini.');
        return self::SUCCESS;
    }
}
