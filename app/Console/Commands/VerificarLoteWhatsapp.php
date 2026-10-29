<?php

namespace App\Console\Commands;

use App\Models\ChatMonitoreado;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeWhatsapp;
use Illuminate\Console\Command;

/**
 * Auditoría estrictamente de lectura de un lote manual de SQL Server de pruebas.
 * No muestra texto, remitentes, identificadores externos ni contenido original.
 */
final class VerificarLoteWhatsapp extends Command
{
    protected $signature = 'mensajero:verificar-lote
        {grupo : Identificador del grupo autorizado}
        {--ids= : Entre uno y cinco identificadores de eventos separados por comas}';

    protected $description = 'Comprueba proyecciones, citas e integridad SHA-256 sin modificar datos';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlsrv'
            || ! str_ends_with((string) config('database.connections.sqlsrv.database'), '_test')) {
            $this->error('Verificación bloqueada: requiere SQL Server de pruebas y entorno local o de pruebas.');

            return self::FAILURE;
        }

        $grupoId = filter_var($this->argument('grupo'), FILTER_VALIDATE_INT);
        $idsTexto = trim((string) $this->option('ids'));

        if ($grupoId === false || $grupoId < 1
            || preg_match('/^[1-9][0-9]*(,[1-9][0-9]*){0,4}$/D', $idsTexto) !== 1) {
            $this->error('Indique un grupo válido y entre uno y cinco IDs: --ids=10055,10056.');

            return self::FAILURE;
        }

        $ids = [];

        foreach (explode(',', $idsTexto) as $texto) {
            $id = filter_var($texto, FILTER_VALIDATE_INT);

            if ($id === false || $id < 1 || in_array($id, $ids, true)) {
                $this->error('La lista contiene IDs inválidos, repetidos o fuera de rango.');

                return self::FAILURE;
            }

            $ids[] = $id;
        }

        $grupo = ChatMonitoreado::query()
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_activo', true)
            ->find($grupoId);

        if ($grupo === null) {
            $this->error('El grupo no está activo o autorizado.');

            return self::FAILURE;
        }

        $eventos = EventoCrudoWhatsapp::query()
            ->where('chat_monitoreado_id', $grupoId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $mensajes = MensajeWhatsapp::query()
            ->whereIn('evento_crudo_id', $ids)
            ->get()
            ->groupBy('evento_crudo_id');

        $eventosPorProveedor = $eventos
            ->filter(fn (EventoCrudoWhatsapp $evento) => is_string($evento->id_evento_externo)
                && $evento->id_evento_externo !== '')
            ->keyBy('id_evento_externo');

        $aprobados = 0;
        $citasVerificadas = 0;
        $citasFueraDelLote = 0;

        foreach ($ids as $id) {
            $evento = $eventos->get($id);
            $proyecciones = $mensajes->get($id);
            $mensaje = ($proyecciones === null ? null : $proyecciones->first());
            $errores = [];

            if ($evento === null) {
                $errores[] = 'evento inexistente o de otro grupo';
            } else {
                if ($evento->estado_procesamiento !== 'normalizado'
                    || $evento->tipo_evento !== 'mensaje'
                    || $evento->tipo_mensaje !== 'texto'
                    || $evento->version_contrato !== 'v3-recolector') {
                    $errores[] = 'evento no normalizado o no elegible';
                }

                if (! is_string($evento->contenido)
                    || ! is_string($evento->hash_contenido_sha256)
                    || ! hash_equals($evento->hash_contenido_sha256, hash('sha256', $evento->contenido))) {
                    $errores[] = 'integridad SHA-256 incorrecta';
                }
            }

            if ($proyecciones === null || $proyecciones->count() !== 1 || $mensaje === null) {
                $errores[] = 'se requiere exactamente una proyección';
            } elseif ($evento !== null) {
                $identidad = $evento->id_evento_externo ?: 'sin_id_evento_crudo_'.$id;

                if ((int) $mensaje->conexion_id !== (int) $evento->conexion_id
                    || (int) $mensaje->chat_monitoreado_id !== (int) $grupoId
                    || (int) $mensaje->evento_crudo_id !== $id
                    || $mensaje->id_mensaje_proveedor !== $identidad
                    || $mensaje->tipo_mensaje !== 'texto'
                    || $mensaje->estado_captura !== 'normalizado'
                    || $mensaje->id_mensaje_citado_proveedor !== $evento->id_mensaje_citado_proveedor) {
                    $errores[] = 'proyección inconsistente con la evidencia';
                }

                $citado = $evento->id_mensaje_citado_proveedor;

                if (is_string($citado) && $citado !== '') {
                    $original = $eventosPorProveedor->get($citado);

                    if ($original === null) {
                        $citasFueraDelLote++;
                    } else {
                        $mensajeOriginal = ($mensajes->get($original->id) === null ? null : $mensajes->get($original->id)->first());

                        if ($mensajeOriginal === null
                            || (int) $mensaje->mensaje_citado_id !== (int) $mensajeOriginal->id
                            || (int) $mensajeOriginal->evento_crudo_id !== (int) $original->id) {
                            $errores[] = 'vínculo con mensaje citado incorrecto';
                        } else {
                            $citasVerificadas++;
                        }
                    }
                }
            }

            if ($errores !== []) {
                $this->error('Evento #'.$id.': FALLÓ ('.implode('; ', $errores).').');
            } else {
                $aprobados++;
                $this->line('Evento #'.$id.': correcto.');
            }
        }

        $this->line(sprintf(
            'Resumen: %d/%d eventos correctos; %d cita(s) verificadas; %d cita(s) fuera del lote sin verificar.',
            $aprobados,
            count($ids),
            $citasVerificadas,
            $citasFueraDelLote,
        ));
        $this->line('Consulta de solo lectura: no se modificaron registros.');

        return $aprobados === count($ids) ? self::SUCCESS : self::FAILURE;
    }
}
