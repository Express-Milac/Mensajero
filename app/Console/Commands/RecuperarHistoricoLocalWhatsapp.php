<?php

namespace App\Console\Commands;

use App\Models\ChatMonitoreado;
use App\Models\MensajeBuzonSalida;
use App\Services\IA\ControlInterpretacionGemini;
use App\Services\IA\PoliticaEnvioGemini;
use App\Services\Normalizacion\DespacharBuzonWhatsapp;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recupera exclusivamente la proyeccion LOCAL de mensajes anteriores al corte
 * de consentimiento Gemini. Por defecto solo ofrece vista previa.
 * Nunca modifica las fechas de autorizacion ni publica trabajos de Gemini.
 */
final class RecuperarHistoricoLocalWhatsapp extends Command
{
    protected $signature = 'mensajero:recuperar-historico-local
        {grupo : ID interno del grupo monitoreado}
        {--limite=20 : Maximo de eventos por ejecucion, de 1 a 20}
        {--aplicar : Publica exclusivamente trabajos de normalizacion local}';

    protected $description = 'Recupera la normalizacion local anterior al consentimiento Gemini, con vista previa por defecto';

    public function handle(
        DespacharBuzonWhatsapp $despachador,
        PoliticaEnvioGemini $politica,
        ControlInterpretacionGemini $control
    ): int {
        $grupoId = filter_var($this->argument('grupo'), FILTER_VALIDATE_INT);
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);

        if ($grupoId === false || $grupoId < 1 || $limite === false || $limite < 1 || $limite > 20) {
            $this->error('Indique un grupo valido y un limite entre 1 y 20.');
            return self::FAILURE;
        }

        if (config('mensajero.normalizacion.habilitada', false) !== true
            || config('database.default') !== 'sqlsrv') {
            $this->error('La normalizacion o la conexion SQL Server no estan habilitadas.');
            return self::FAILURE;
        }

        $grupo = ChatMonitoreado::query()
            ->whereKey($grupoId)
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)
            ->where('es_activo', true)
            ->first();

        if ($grupo === null) {
            $this->error('Grupo inexistente, inactivo o no autorizado para el tablero.');
            return self::FAILURE;
        }

        $cortePolitica = $politica->fechaInicioGrupo($grupoId);
        $corteControl = $control->corteEfectivoGrupo($grupoId);
        if ($cortePolitica === null || $corteControl === null) {
            $this->error('No hay un corte Gemini valido y consentido para este grupo; se rechaza la recuperacion.');
            return self::FAILURE;
        }

        // El limite superior estricto excluye todo mensaje que pueda ser elegible
        // para Gemini. No permitir fechas manuales ni retroceder autorizaciones.
        $corte = $cortePolitica >= $corteControl ? $cortePolitica : $corteControl;
        $antesUtc = $corte->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

        $candidatos = MensajeBuzonSalida::query()
            ->where('tema', 'whatsapp.crudo.aceptado')
            ->where('tipo_entidad', 'evento_crudo_whatsapp')
            ->whereNotNull('entidad_id')
            ->whereNull('enviado_en')
            ->whereIn('entidad_id', function ($consulta) use ($grupoId, $antesUtc): void {
                $consulta->select('id')
                    ->from('mensajero_eventos_crudos_whatsapp')
                    ->where('chat_monitoreado_id', $grupoId)
                    ->where('estado_procesamiento', 'capturado')
                    ->where('recibido_en', '<', $antesUtc);
            })
            ->count();

        $this->info('Grupo #'.$grupoId.' | corte de exclusion Gemini UTC: '.$antesUtc);
        $this->info('Pendientes de normalizacion local anteriores al corte: '.$candidatos);
        $this->line('Lote maximo solicitado: '.$limite);

        if ($this->option('aplicar') !== true) {
            $this->line('VISTA PREVIA: no se publico ningun trabajo ni se consulto Gemini.');
            return self::SUCCESS;
        }

        if ($candidatos < 1) {
            $this->line('No hay eventos historicos elegibles.');
            return self::SUCCESS;
        }

        // La seleccion definitiva se repite dentro de la reserva transaccional
        // y cada trabajo lleva la barrera explícita soloLocal.
        $resultado = $despachador->ejecutar($limite, null, $grupoId, null, true, $antesUtc);
        $this->info('Publicados en Redis (solo local): '.$resultado['publicados']);
        $this->info('Fallidos: '.$resultado['fallidos']);

        return $resultado['fallidos'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
