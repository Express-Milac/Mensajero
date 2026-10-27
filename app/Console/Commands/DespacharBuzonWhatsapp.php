<?php

namespace App\Console\Commands;

use App\Models\EventoCrudoWhatsapp;
use App\Services\Normalizacion\DespacharBuzonWhatsapp as ServicioDespacharBuzon;
use Illuminate\Console\Command;

final class DespacharBuzonWhatsapp extends Command
{
    protected $signature = 'mensajero:despachar-buzon
        {--limite=100 : Máximo de elementos por ejecución (1 a 500)}
        {--evento-id= : Procesar únicamente un evento crudo, útil para diagnóstico}
        {--cola-ensayo= : Cola única de un ensayo sintético; obligatoria si la normalización automática está apagada}';

    protected $description = 'Publica eventos crudos pendientes de SQL Server a la cola Redis de normalización';

    public function handle(ServicioDespacharBuzon $despachador): int
    {
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);
        $evento = $this->option('evento-id');
        $idEvento = $evento === null ? null : filter_var($evento, FILTER_VALIDATE_INT);

        if ($limite === false || $limite < 1 || $limite > 500
            || ($evento !== null && ($idEvento === false || $idEvento < 1))) {
            $this->error('Los parámetros deben ser enteros positivos; límite máximo: 500.');

            return self::FAILURE;
        }

        $colaEnsayo = trim((string) $this->option('cola-ensayo'));

        // Sin activación automática, impedir publicar el histórico o mensajes
        // reales, aunque el operador proporcione un ID válido.
        if (config('mensajero.normalizacion.habilitada', false) !== true) {
            if (! app()->environment(['local', 'testing'])
                || config('database.default') !== 'sqlsrv'
                || ! str_ends_with((string) config('database.connections.sqlsrv.database'), '_test')
                || $idEvento === null
                || $limite !== 1
                || preg_match('/^mensajero_ensayo_[0-9a-f]{16}$/D', $colaEnsayo) !== 1) {
                $this->error('Publicación bloqueada: requiere SQL Server _test, --limite=1, --evento-id y una --cola-ensayo exclusiva.');

                return self::FAILURE;
            }

            $seleccionado = EventoCrudoWhatsapp::query()
                ->with('conexion')
                ->whereKey($idEvento)
                ->where('estado_procesamiento', 'capturado')
                ->whereHas('chat', function ($consulta) {
                    $consulta->where('tipo_chat', 'grupo')
                        ->where('es_monitoreado', true)
                        ->where('es_activo', true);
                })
                ->first();

            $claveConexion = $seleccionado?->conexion?->clave_conexion;
            $sufijo = is_string($claveConexion)
                && preg_match('/^ensayo-redis-([0-9a-f]{16})$/D', $claveConexion, $coincidencias) === 1
                    ? $coincidencias[1]
                    : null;

            if ($sufijo === null || ! hash_equals('mensajero_ensayo_'.$sufijo, $colaEnsayo)) {
                $this->error('Solo se permite el evento ficticio del ensayo y su cola exclusiva correspondiente.');

                return self::FAILURE;
            }

            // Se modifica únicamente la configuración de ESTA ejecución, no .env.
            config(['mensajero.normalizacion.cola' => $colaEnsayo]);
        } elseif ($colaEnsayo !== '') {
            $this->error('La opción --cola-ensayo no está disponible en modo automático.');

            return self::FAILURE;
        }

        $resultado = $despachador->ejecutar($limite, $idEvento);

        $this->components->info(sprintf(
            'Publicados: %d. Fallidos: %d.',
            $resultado['publicados'],
            $resultado['fallidos'],
        ));

        return $resultado['fallidos'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
