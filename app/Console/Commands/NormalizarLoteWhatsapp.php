<?php

namespace App\Console\Commands;

use App\Models\ChatMonitoreado;
use App\Models\EventoCrudoWhatsapp;
use App\Services\Normalizacion\NormalizarEventoWhatsapp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Normalización manual, limitada y sin Redis para el POC.
 *
 * La vista previa no escribe nada. Para ejecutar, el operador debe proporcionar
 * explícitamente los identificadores, nunca basta con indicar un límite.
 */
final class NormalizarLoteWhatsapp extends Command
{
    protected $signature = 'mensajero:normalizar-lote
        {grupo : ID del grupo autorizado}
        {--limite=3 : Cantidad máxima de mensajes, de 1 a 5}
        {--ids= : IDs exactos separados por comas, necesarios al aplicar}
        {--aplicar : Permite escribir exclusivamente los IDs indicados}';

    protected $description = 'Previsualiza o normaliza hasta cinco textos originales de un grupo en SQL Server de pruebas';

    public function handle(NormalizarEventoWhatsapp $normalizador): int
    {
        // El comando nunca debe disponer de un modo de escritura en producción.
        if (app()->environment(['local', 'testing']) === false
            || config('database.default') !== 'sqlsrv'
            || str_ends_with((string) config('database.connections.sqlsrv.database'), '_test') === false) {
            $this->error('Bloqueado: requiere entorno local/de pruebas y base SQL Server terminada en _test.');

            return self::FAILURE;
        }

        $grupoId = filter_var($this->argument('grupo'), FILTER_VALIDATE_INT);
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);

        if ($grupoId === false || $grupoId < 1 || $limite === false || $limite < 1 || $limite > 5) {
            $this->error('Indique un grupo válido y un límite de 1 a 5 mensajes.');

            return self::FAILURE;
        }

        $grupo = ChatMonitoreado::query()
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_activo', true)
            ->find($grupoId);

        if ($grupo === null) {
            $this->error('El grupo no existe o no está activo y autorizado.');

            return self::FAILURE;
        }

        $aplicar = $this->option('aplicar') === true;
        $idsTexto = trim((string) $this->option('ids'));

        if ($aplicar === false) {
            if ($idsTexto !== '') {
                $this->error('La vista previa no acepta --ids. Indíquelo solo junto con --aplicar.');

                return self::FAILURE;
            }

            $ids = EventoCrudoWhatsapp::query()
                ->where('chat_monitoreado_id', $grupo->id)
                ->where('tipo_evento', 'mensaje')
                ->where('tipo_mensaje', 'texto')
                ->where('version_contrato', 'v3-recolector')
                ->where('estado_procesamiento', 'capturado')
                ->orderBy('id')
                ->limit($limite)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $this->info(sprintf('Vista previa del grupo #%d: %d mensaje(s) elegibles.', $grupoId, count($ids)));
            $this->line('IDs: '.($ids === [] ? '(ninguno)' : implode(',', $ids)));
            $this->line('No se ha modificado ningún registro. Para aplicar, indique --ids con los IDs confirmados y --aplicar.');

            return self::SUCCESS;
        }

        // Exigir IDs explícitos evita transformar un lote distinto al previsualizado.
        if (preg_match('/^[1-9][0-9]*(,[1-9][0-9]*){0,4}$/D', $idsTexto) !== 1) {
            $this->error('Para aplicar, indique entre 1 y 5 IDs exactos: --ids=123,124 --aplicar.');

            return self::FAILURE;
        }

        $ids = [];

        foreach (explode(',', $idsTexto) as $idTexto) {
            $idValidado = filter_var($idTexto, FILTER_VALIDATE_INT);

            if ($idValidado === false || $idValidado < 1) {
                $this->error('Un identificador supera el rango admitido o es inválido.');

                return self::FAILURE;
            }

            $ids[] = $idValidado;
        }

        if (count($ids) > $limite || count($ids) !== count(array_unique($ids))) {
            $this->error('El lote contiene demasiados IDs, valores repetidos o identificadores inválidos.');

            return self::FAILURE;
        }

        $eventos = EventoCrudoWhatsapp::query()
            ->whereIn('id', $ids)
            ->where('chat_monitoreado_id', $grupoId)
            ->where('tipo_evento', 'mensaje')
            ->where('tipo_mensaje', 'texto')
            ->where('version_contrato', 'v3-recolector')
            ->whereIn('estado_procesamiento', ['capturado', 'normalizado'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($eventos) !== count($ids)) {
            $this->error('La lista incluye un evento inexistente, de otro grupo, no elegible o con un estado no permitido. No se procesó el lote.');

            return self::FAILURE;
        }

        $normalizados = 0;
        $duplicados = 0;

        foreach ($ids as $id) {
            // Revalidar el permiso del grupo entre mensajes. Un fallo interrumpe el lote.
            if (ChatMonitoreado::query()
                ->whereKey($grupoId)
                ->where('es_monitoreado', true)
                ->where('es_activo', true)
                ->exists() === false) {
                $this->error('Se suspendió la autorización del grupo. No se procesaron los eventos restantes.');

                return self::FAILURE;
            }

            try {
                $resultado = $normalizador->ejecutar($id);
            } catch (Throwable $error) {
                Log::warning('Fallo en normalización controlada del POC.', [
                    'evento_id' => $id,
                    'tipo_error' => class_basename($error),
                ]);

                $this->error('El lote se interrumpió en el evento #'.$id.'. Los anteriores pueden haberse normalizado; consulte su estado y reintente solo si procede.');

                return self::FAILURE;
            }

            if ($resultado === 'normalizado') {
                $normalizados++;
            } elseif ($resultado === 'duplicado') {
                $duplicados++;
            } else {
                $this->error('Resultado inesperado: se detuvo el lote después del evento #'.$id.'.');

                return self::FAILURE;
            }
        }

        $this->info(sprintf(
            'Grupo #%d: %d normalizados, %d ya normalizados, %d IDs evaluados.',
            $grupoId,
            $normalizados,
            $duplicados,
            count($ids),
        ));

        return self::SUCCESS;
    }
}
