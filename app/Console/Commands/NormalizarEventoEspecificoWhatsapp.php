<?php

namespace App\Console\Commands;

use App\Models\EventoCrudoWhatsapp;
use App\Services\Normalizacion\NormalizarEventoWhatsapp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ensayo controlado: solo un evento y solo en una BD SQL Server de pruebas.
 * Por omisión muestra información mínima y no escribe en la base.
 */
final class NormalizarEventoEspecificoWhatsapp extends Command
{
    protected $signature = 'mensajero:normalizar-evento
        {evento : Identificador numérico de un evento crudo}
        {--aplicar : Ejecuta la normalización de este único evento en la base de pruebas}';

    protected $description = 'Previsualiza o normaliza un único evento del POC sin Redis';

    public function handle(NormalizarEventoWhatsapp $normalizador): int
    {
        if (app()->environment(['local', 'testing']) === false
            || config('database.default') !== 'sqlsrv'
            || str_ends_with((string) config('database.connections.sqlsrv.database'), '_test') === false) {
            $this->error('Operación bloqueada: requiere entorno local/de pruebas y base SQL Server terminada en _test.');

            return self::FAILURE;
        }

        $identificador = filter_var($this->argument('evento'), FILTER_VALIDATE_INT);

        if ($identificador === false || $identificador < 1) {
            $this->error('El evento debe ser un identificador entero positivo.');

            return self::FAILURE;
        }

        $evento = EventoCrudoWhatsapp::query()
            ->with('chat')
            ->find($identificador);

        if ($evento === null) {
            $this->error('No existe ese evento en la base de pruebas.');

            return self::FAILURE;
        }

        if ($evento->tipo_evento !== 'mensaje') {
            $this->error('El ensayo solo procesa mensajes originales; los eventos de edición y eliminación requieren tratamiento semántico.');

            return self::FAILURE;
        }

        if ($evento->chat === null || $evento->chat->es_monitoreado === false || $evento->chat->es_activo === false) {
            $this->error('El grupo debe permanecer activo y autorizado para el ensayo.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Evento #%d | Grupo #%d | Tipo: %s | Estado actual: %s',
            $identificador,
            (int) $evento->chat_monitoreado_id,
            $evento->tipo_mensaje ?: 'sin clasificar',
            $evento->estado_procesamiento,
        ));

        if ($this->option('aplicar') === false) {
            $this->line('Vista previa: no se ha modificado ningún registro. Para procesar exclusivamente este evento, añade --aplicar.');

            return self::SUCCESS;
        }

        try {
            $resultado = $normalizador->ejecutar($identificador);
        } catch (Throwable $excepcion) {
            // No emitir el contenido de eventos ni los mensajes internos de excepciones.
            Log::warning('Fallo al normalizar evento específico del POC.', [
                'evento_id' => $identificador,
                'tipo_error' => class_basename($excepcion),
            ]);
            $this->error('El procesamiento no concluyó; revise el registro técnico protegido del servidor.');

            return self::FAILURE;
        }

        $this->info('Resultado: '.$resultado.'. Evento #'.$identificador);

        return self::SUCCESS;
    }
}
