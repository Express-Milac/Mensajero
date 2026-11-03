<?php

namespace App\Console\Commands;

use App\Models\ChatMonitoreado;
use App\Models\MensajeBuzonSalida;
use App\Services\IA\ControlInterpretacionGemini;
use App\Services\IA\PoliticaEnvioGemini;
use App\Services\Normalizacion\DespacharBuzonWhatsapp;
use DateTimeZone;
use Illuminate\Console\Command;

/**
 * Recupera exclusivamente la proyeccion LOCAL de mensajes antiguos.
 * No necesita autorización Gemini para grupos autorizados para el tablero.
 * Por defecto solo ofrece vista previa y nunca modifica consentimientos.
 */
final class RecuperarHistoricoLocalWhatsapp extends Command
{
    protected $signature = 'mensajero:recuperar-historico-local
        {grupo : ID interno del grupo monitoreado}
        {--limite=20 : Maximo de eventos por ejecucion, de 1 a 20}
        {--aplicar : Publica exclusivamente trabajos de normalizacion local}';

    protected $description = 'Recupera eventos antiguos en modo exclusivamente local, sin enviarlos a Gemini';

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

        // Todos los grupos pueden normalizarse LOCALMENTE aunque no tengan
        // consentimiento para Gemini. Evitar eventos muy recientes para no
        // competir con el despachador protegido ni el flujo ordinario.
        $antesUtc = now('UTC')->subMinutes(10)->format('Y-m-d H:i:s.u');
        $corteControl = $control->corteEfectivoGrupo($grupoId);

        if ($corteControl !== null) {
            // En grupos con consentimiento vigente, recuperar solamente
            // anteriores al corte efectivo de IA. El corte más reciente de
            // política o control prevalece; nunca adelantarlo.
            $cortePolitica = $politica->fechaInicioGrupo($grupoId);
            $corte = $cortePolitica !== null && $cortePolitica > $corteControl
                ? $cortePolitica : $corteControl;

            $inicioIaUtc = $corte->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s.u');

            $antesUtc = min($antesUtc, $inicioIaUtc);
        }

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

        $this->info('Grupo #'.$grupoId.' | fecha maxima EXCLUSIVA de recuperacion local UTC: '.$antesUtc);
        $this->info('Pendientes elegibles para recuperacion local: '.$candidatos);
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
