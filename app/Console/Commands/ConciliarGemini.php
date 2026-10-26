<?php

namespace App\Console\Commands;

use App\Services\IA\ConciliarPublicacionesGemini;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Conciliación conservadora: simulación por defecto.
 * La aplicación real exige doble control: banderas de autorización y corte.
 */
final class ConciliarGemini extends Command
{
    protected $signature = 'mensajero:conciliar-gemini
        {--grupo=10020 : Identificador del grupo previamente autorizado}
        {--todos : Examinar exclusivamente los grupos expresamente autorizados para Gemini}
        {--limite=2 : Máximo de publicaciones nuevas por grupo en una ejecución (1-10)}
        {--aplicar : Reservar en SQL y publicar trabajos Redis con límites; de lo contrario, solo consultar}';

    protected $description = 'Detecta mensajes recientes sin IA y opcionalmente publica trabajos únicos y limitados.';

    public function handle(ConciliarPublicacionesGemini $conciliador): int
    {
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);
        $grupoId = filter_var($this->option('grupo'), FILTER_VALIDATE_INT);

        if ($limite === false || $limite < 1 || $limite > 10 || $grupoId === false || $grupoId < 1) {
            $this->error('Identificador de grupo y límite incorrectos (máximo 10).');
            return self::FAILURE;
        }

        if (! $conciliador->disponible()) {
            $this->error('Falta la migración de control de publicaciones IA. No se hizo nada.');
            return self::FAILURE;
        }

        $idsAutorizados = app(\App\Services\IA\ControlInterpretacionGemini::class)->gruposHabilitados();

        $grupos = $this->option('todos') ? $idsAutorizados : [$grupoId];
        if ($grupos === [] || count($grupos) > 6) {
            $this->error('No existe una lista válida de hasta seis grupos activados desde el panel.');
            return self::FAILURE;
        }

        foreach ($grupos as $id) {
            if (! in_array($id, $idsAutorizados, true)) {
                $this->error('Grupo '.$id.' no activado desde Control de Gemini.');
                return self::FAILURE;
            }
        }

        if ($this->option('aplicar') && ! $conciliador->habilitada()) {
            $this->error('Conciliación automática deshabilitada o incompleta. No se publicó nada.');
            $this->warn('Exige autorización previa, corte nuevo y límite diario. Consulte docs/conciliacion-gemini.md.');
            return self::FAILURE;
        }

        if (! $this->option('aplicar') && ! $conciliador->habilitada()) {
            $this->warn('Modo de lectura. La conciliación automática todavía NO está habilitada.');
            $this->warn('Al no existir corte autorizado, no habrá candidatos nuevos que publicar.');
        }

        $publicados = 0;
        foreach ($grupos as $grupo) {
            try {
                $candidatos = $conciliador->candidatos($grupo, $limite);
                $this->info('Grupo '.$grupo.' | mensajes recientes pendientes y elegibles: '.count($candidatos));

                foreach ($candidatos as $mensaje) {
                    $this->line('  ID #'.$mensaje->id.' | tipo '.$mensaje->tipo_mensaje.' | recibido UTC '
                        .$mensaje->recibido_en?->format('Y-m-d H:i:s'));

                    if ($this->option('aplicar')) {
                        $estado = $conciliador->publicar((int) $mensaje->id, $grupo);
                        $this->line('    Publicación: '.$estado);
                        if ($estado === 'publicado') {
                            $publicados++;
                        }
                    }
                }

                $inciertos = DB::table('mensajero_control_publicaciones_gemini')
                    ->where('chat_monitoreado_id', $grupo)
                    ->where('estado', 'requiere_revision')->count();
                if ($inciertos > 0) {
                    $this->warn('Grupo '.$grupo.': '.$inciertos.' publicaciones con estado incierto; REVISIÓN MANUAL, sin reintento.');
                }

            } catch (Throwable $error) {
                $this->error('Falló la conciliación del grupo '.$grupo.' ('.class_basename($error).'). Sin reintento automático.');
                return self::FAILURE;
            }
        }

        if ($this->option('aplicar')) {
            $this->info('Publicados: '.$publicados.' trabajo(s). No se invocó Google desde este comando.');
        } else {
            $this->comment('Simulación terminada: ningún trabajo publicado, ninguna llamada a Google y ninguna escritura.');
        }

        return self::SUCCESS;
    }
}
