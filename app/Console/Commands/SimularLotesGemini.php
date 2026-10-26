<?php

namespace App\Console\Commands;

use App\Models\MensajeWhatsapp;
use App\Services\IA\AgruparMensajesGemini;
use App\Services\IA\ControlInterpretacionGemini;
use App\Services\IA\PoliticaEnvioGemini;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SimularLotesGemini extends Command
{
    protected $signature = 'mensajero:simular-lotes-gemini
        {--grupo= : Identificador del grupo autorizado}
        {--limite=60 : Cantidad de mensajes recientes para simular (1-100)}';

    protected $description = 'Agrupa de forma simulada mensajes elegibles; solo lectura, sin Redis ni Google.';

    public function handle(
        PoliticaEnvioGemini $politica,
        ControlInterpretacionGemini $control,
        AgruparMensajesGemini $agrupador
    ): int {
        $grupoId = filter_var($this->option('grupo'), FILTER_VALIDATE_INT);
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);

        if ($grupoId === false || $grupoId < 1
            || $limite === false || $limite < 1 || $limite > 100) {
            $this->error('Indique un grupo positivo y un límite entre 1 y 100.');
            return self::FAILURE;
        }

        if (! $politica->configuracionCompleta()
            || ! $control->disponible()
            || ! Schema::hasTable('mensajero_mensajes_whatsapp')
            || ! Schema::hasTable('mensajero_analisis_operativos_ia')
            || ! Schema::hasTable('mensajero_control_publicaciones_gemini')) {
            $this->error('La política, la autorización o las tablas necesarias no están disponibles.');
            return self::FAILURE;
        }

        $general = $politica->fechaInicioGrupo($grupoId);
        $individual = $control->corteEfectivoGrupo($grupoId);
        if ($general === null || $individual === null
            || ! DB::table('mensajero_chats_monitoreados')
                ->where('id', $grupoId)->where('tipo_chat', 'grupo')
                ->where('es_activo', true)->where('es_monitoreado', true)
                ->where('es_visible_tablero', true)->exists()) {
            $this->error('Grupo no autorizado para la interpretación.');
            return self::FAILURE;
        }

        // Simulación conservadora: exclusivamente mensajes recientes y
        // posteriores a todos los cortes de consentimiento. No leer históricos.
        $inicio = max(
            $general->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            $individual->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            now('UTC')->subHours(4)->format('Y-m-d H:i:s.u')
        );
        $hasta = now('UTC')->subSeconds(180)->format('Y-m-d H:i:s.u');

        $mensajes = MensajeWhatsapp::query()
            ->where('chat_monitoreado_id', $grupoId)
            ->where('estado_captura', 'normalizado')
            ->whereBetween('recibido_en', [$inicio, $hasta])
            ->where(function (Builder $q): void {
                $q->where(function (Builder $texto): void {
                    $texto->where('tipo_mensaje', 'texto')
                        ->whereNotNull('contenido_texto')->where('contenido_texto', '<>', '');
                })->orWhere(function (Builder $medio): void {
                    $medio->whereIn('tipo_mensaje', ['foto', 'video', 'documento'])
                        ->whereNotNull('pie_multimedia')->where('pie_multimedia', '<>', '');
                });
            })
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('mensajero_analisis_operativos_ia as a')
                    ->whereColumn('a.mensaje_whatsapp_id', 'mensajero_mensajes_whatsapp.id');
            })
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('mensajero_control_publicaciones_gemini as p')
                    ->whereColumn('p.mensaje_whatsapp_id', 'mensajero_mensajes_whatsapp.id');
            })
            ->orderBy('recibido_en')->orderBy('id')
            ->limit($limite)->get(['id', 'chat_monitoreado_id', 'recibido_en',
                'tipo_mensaje', 'contenido_texto', 'pie_multimedia']);

        $datos = [];
        foreach ($mensajes as $mensaje) {
            $texto = $mensaje->tipo_mensaje === 'texto'
                ? $mensaje->contenido_texto : $mensaje->pie_multimedia;
            if (trim((string) $texto) === '' || ! $mensaje->recibido_en instanceof DateTimeInterface) {
                continue;
            }
            $datos[] = [
                'id' => (int) $mensaje->id,
                'grupo_id' => (int) $mensaje->chat_monitoreado_id,
                'recibido_en' => $mensaje->recibido_en,
                'texto' => (string) $texto,
            ];
        }

        $lotes = $agrupador->agrupar($datos);
        $this->info('Mensajes elegibles para simulación: '.count($datos));
        $this->info('Lotes propuestos: '.count($lotes));

        foreach ($lotes as $indice => $ids) {
            $this->line('Lote '.($indice + 1).' | '.count($ids).' mensajes | IDs: '.implode(',', $ids));
        }

        $this->comment('Solo simulación: no se escribieron datos, no se publicaron trabajos y no se llamó a Google.');
        $this->warn('Agrupar por proximidad temporal NO garantiza una misma operación.');
        return self::SUCCESS;
    }
}
