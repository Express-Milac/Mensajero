<?php

namespace App\Console\Commands;

use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeBuzonSalida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ensayo de conciliación de avisos potencialmente perdidos.
 *
 * Se restringe a conexiones sintéticas y SQL Server _test. No accede a
 * Redis ni publica trabajos: reabrir el buzón es un paso independiente.
 */
final class ConciliarPublicacionesWhatsapp extends Command
{
    protected $signature = 'mensajero:conciliar-publicaciones
        {--minutos=60 : Antigüedad mínima del aviso enviado, entre 30 y 1440 minutos}
        {--limite=10 : Número máximo de eventos examinados, entre 1 y 20}
        {--aplicar : Reabrir únicamente los avisos elegibles, sin publicar Redis}';

    protected $description = 'Detecta y concilia un lote pequeño de avisos ficticios no normalizados';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlsrv'
            || ! str_ends_with((string) config('database.connections.sqlsrv.database'), '_test')
            || config('mensajero.normalizacion.habilitada', false) !== false) {
            $this->error('Conciliación bloqueada: requiere entorno local, SQL Server _test y normalización automática desactivada.');

            return self::FAILURE;
        }

        $minutos = filter_var($this->option('minutos'), FILTER_VALIDATE_INT);
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);

        if ($minutos === false || $minutos < 30 || $minutos > 1440
            || $limite === false || $limite < 1 || $limite > 20) {
            $this->error('Indique --minutos de 30 a 1440 y --limite de 1 a 20.');

            return self::FAILURE;
        }

        $ahora = now('UTC');
        $umbral = $ahora->copy()->subMinutes($minutos)->format('Y-m-d H:i:s.u');
        $ahoraSql = $ahora->format('Y-m-d H:i:s.u');

        // Filtrado acotado por SQL. La elegibilidad se revisa nuevamente bajo
        // bloqueo antes de escribir: una vista previa no reserva trabajos.
        $identificadores = MensajeBuzonSalida::query()
            ->join('mensajero_eventos_crudos_whatsapp as evento', 'evento.id', '=', 'mensajero_buzon_salida.entidad_id')
            ->join('mensajero_conexiones_whatsapp as conexion', 'conexion.id', '=', 'evento.conexion_id')
            ->join('mensajero_chats_monitoreados as grupo', 'grupo.id', '=', 'evento.chat_monitoreado_id')
            ->where('mensajero_buzon_salida.tema', 'whatsapp.crudo.aceptado')
            ->where('mensajero_buzon_salida.tipo_entidad', 'evento_crudo_whatsapp')
            ->whereNotNull('mensajero_buzon_salida.enviado_en')
            ->where('mensajero_buzon_salida.enviado_en', '<=', $umbral)
            ->where(function ($consulta) use ($ahoraSql) {
                $consulta->whereNull('mensajero_buzon_salida.reservado_hasta')
                    ->orWhere('mensajero_buzon_salida.reservado_hasta', '<=', $ahoraSql);
            })
            ->where('evento.estado_procesamiento', 'capturado')
            ->where('evento.tipo_evento', 'mensaje')
            ->where('evento.tipo_mensaje', 'texto')
            ->where('evento.version_contrato', 'v3-recolector')
            ->where('conexion.clave_conexion', 'like', 'ensayo-redis-%')
            ->where('grupo.tipo_chat', 'grupo')
            ->where('grupo.es_monitoreado', true)
            ->where('grupo.es_activo', true)
            ->whereColumn('grupo.conexion_id', 'evento.conexion_id')
            ->orderBy('mensajero_buzon_salida.id')
            ->limit($limite)
            ->pluck('evento.id');

        $elegibles = 0;
        $restablecidos = 0;
        $aplicar = $this->option('aplicar') === true;

        foreach ($identificadores as $id) {
            $esElegible = DB::transaction(function () use ($id, $minutos, $aplicar): bool {
                $evento = EventoCrudoWhatsapp::query()
                    ->whereKey((int) $id)
                    ->lockForUpdate()
                    ->first();

                if ($evento === null || $evento->estado_procesamiento !== 'capturado'
                    || $evento->tipo_evento !== 'mensaje' || $evento->tipo_mensaje !== 'texto'
                    || $evento->version_contrato !== 'v3-recolector') {
                    return false;
                }

                $clave = $evento->conexion?->clave_conexion;
                if (! is_string($clave)
                    || preg_match('/^ensayo-redis-([0-9a-f]{16})$/D', $clave, $partes) !== 1
                    || $evento->id_evento_externo !== $clave
                    || $evento->chat?->id_chat_proveedor !== $clave.'@g.us'
                    || ! $evento->chat?->es_monitoreado
                    || ! $evento->chat?->es_activo
                    || $evento->chat?->tipo_chat !== 'grupo'
                    || (int) $evento->chat?->conexion_id !== (int) $evento->conexion_id) {
                    return false;
                }

                $avisos = MensajeBuzonSalida::query()
                    ->where('tema', 'whatsapp.crudo.aceptado')
                    ->where('tipo_entidad', 'evento_crudo_whatsapp')
                    ->where('entidad_id', (int) $id)
                    ->lockForUpdate()
                    ->get();

                if ($avisos->count() !== 1) {
                    return false;
                }

                $aviso = $avisos->first();
                $ahora = now('UTC');

                if ($aviso->enviado_en === null
                    || $aviso->enviado_en->greaterThan($ahora->copy()->subMinutes($minutos))
                    || ($aviso->reservado_hasta !== null && $aviso->reservado_hasta->greaterThan($ahora))) {
                    return false;
                }

                if ($aplicar) {
                    $aviso->enviado_en = null;
                    $aviso->reservado_hasta = null;
                    $aviso->disponible_en = $ahora;
                    $aviso->ultimo_error = null;
                    $aviso->save();
                }

                return true;
            }, 3);

            if (! $esElegible) {
                continue;
            }

            $elegibles++;

            if ($aplicar) {
                $restablecidos++;
                $this->line('Evento ficticio #'.$id.': aviso restablecido.');
            } else {
                $this->line('Evento ficticio #'.$id.': aviso elegible (sin cambios).');
            }
        }

        $this->components->info(sprintf(
            'Examinados: %d. Elegibles: %d. Restablecidos: %d.',
            $identificadores->count(),
            $elegibles,
            $restablecidos,
        ));
        $this->line('No se ha publicado ni consumido ningún trabajo Redis.');

        return self::SUCCESS;
    }
}
