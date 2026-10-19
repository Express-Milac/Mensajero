<?php

namespace App\Console\Commands;

use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeBuzonSalida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recuperación conservadora de una publicación potencialmente perdida en Redis.
 * Solo se habilita manualmente para un evento en la base SQL Server de pruebas.
 */
final class RecuperarPublicacionWhatsapp extends Command
{
    protected $signature = 'mensajero:recuperar-publicacion
        {evento : Identificador exacto del evento crudo}
        {--minutos=30 : Antigüedad mínima desde su publicación (30 a 1440 minutos)}
        {--aplicar : Restablece el buzón del evento para una futura republicación}';

    protected $description = 'Previsualiza o recupera un único aviso del buzón publicado, pero no normalizado';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlsrv'
            || ! str_ends_with((string) config('database.connections.sqlsrv.database'), '_test')) {
            $this->error('Bloqueado: se requiere entorno local/de pruebas con SQL Server _test.');

            return self::FAILURE;
        }

        $eventoId = filter_var($this->argument('evento'), FILTER_VALIDATE_INT);
        $minutos = filter_var($this->option('minutos'), FILTER_VALIDATE_INT);

        if ($eventoId === false || $eventoId < 1
            || $minutos === false || $minutos < 30 || $minutos > 1440) {
            $this->error('Indique un ID positivo y una antigüedad de 30 a 1440 minutos.');

            return self::FAILURE;
        }

        $aplicar = $this->option('aplicar') === true;

        $recuperable = DB::transaction(function () use ($eventoId, $minutos, $aplicar): bool {
            $evento = EventoCrudoWhatsapp::query()
                ->whereKey($eventoId)
                ->lockForUpdate()
                ->first();

            if ($evento === null || $evento->estado_procesamiento !== 'capturado') {
                return false;
            }

            $grupoAutorizado = $evento->chat()
                ->where('tipo_chat', 'grupo')
                ->where('es_monitoreado', true)
                ->where('es_activo', true)
                ->exists();

            if (! $grupoAutorizado) {
                return false;
            }

            $buzon = MensajeBuzonSalida::query()
                ->where('tema', 'whatsapp.crudo.aceptado')
                ->where('tipo_entidad', 'evento_crudo_whatsapp')
                ->where('entidad_id', $eventoId)
                ->lockForUpdate()
                ->first();

            $ahora = now('UTC');

            if ($buzon === null
                || $buzon->enviado_en === null
                || $buzon->enviado_en->greaterThan($ahora->copy()->subMinutes($minutos))
                || ($buzon->reservado_hasta !== null && $buzon->reservado_hasta->greaterThan($ahora))) {
                return false;
            }

            if ($aplicar) {
                // El mensaje crudo no se modifica; vuelve a quedar pendiente
                // la notificación. Si Redis conservó una copia, el
                // normalizador idempotente evita proyecciones duplicadas.
                $buzon->enviado_en = null;
                $buzon->reservado_hasta = null;
                $buzon->disponible_en = $ahora;
                $buzon->ultimo_error = null;
                $buzon->save();
            }

            return true;
        }, 3);

        if (! $recuperable) {
            $this->error('No se cumple la antigüedad, autorización o estado pendiente requerido; no se cambió el buzón.');

            return self::FAILURE;
        }

        if (! $aplicar) {
            $this->components->info('Evento #'.$eventoId.': aviso elegible para recuperación controlada.');
            $this->line('Vista previa de solo lectura: use --aplicar únicamente tras confirmar la incidencia.');

            return self::SUCCESS;
        }

        $this->components->info('Evento #'.$eventoId.': aviso restablecido en SQL Server para futura publicación.');
        $this->line('No se ha conectado a Redis ni se ha normalizado el evento.');

        return self::SUCCESS;
    }
}
