<?php

namespace App\Console\Commands;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Crea exclusivamente evidencia ficticia para ensayar un trabajador Redis.
 * Nunca envía información a WhatsApp ni publica trabajos en Redis.
 */
final class PrepararEnsayoRedis extends Command
{
    protected $signature = 'mensajero:preparar-ensayo-redis
        {--aplicar : Crear un grupo ficticio y un único mensaje sintético en SQL Server _test}';

    protected $description = 'Prepara una captura ficticia aislada para un ensayo único con Redis';

    public function handle(CapturarEventoWhatsappEntrante $capturador): int
    {
        if (! app()->environment(['local', 'testing'])
            || config('database.default') !== 'sqlsrv'
            || ! str_ends_with((string) config('database.connections.sqlsrv.database'), '_test')
            || config('mensajero.normalizacion.habilitada', false) !== false) {
            $this->error('Bloqueado: requiere SQL Server _test, entorno local/de pruebas y normalización automática desactivada.');

            return self::FAILURE;
        }

        if ($this->option('aplicar') !== true) {
            $this->components->info('Vista previa: se creará una conexión de prueba, un grupo ficticio y un único evento de texto.');
            $this->line('Sin --aplicar no se modifican registros ni se utiliza Redis.');

            return self::SUCCESS;
        }

        $sufijo = bin2hex(random_bytes(8));
        $claveConexion = 'ensayo-redis-'.$sufijo;
        $cola = 'mensajero_ensayo_'.$sufijo;

        $resultado = DB::transaction(function () use ($capturador, $claveConexion, $sufijo): array {
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => $claveConexion,
                'proveedor' => 'recolector_whatsapp',
                'nombre_visible' => 'Conexión ficticia de ensayo Redis',
                'estado' => 'sin_configurar',
            ]);

            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => 'ensayo-redis-'.$sufijo.'@g.us',
                'nombre_interno' => 'Grupo sintético de ensayo Redis',
                'tipo_chat' => 'grupo',
                'es_monitoreado' => true,
                'es_activo' => true,
            ]);

            $captura = $capturador->ejecutar(new EventoWhatsappEntrante(
                conexionId: (int) $conexion->id,
                chatId: $grupo->id_chat_proveedor,
                tipoEvento: 'mensaje',
                tipoMensaje: 'texto',
                eventoExternoId: 'ensayo-redis-'.$sufijo,
                mensajeProveedorId: 'ensayo-redis-'.$sufijo,
                contenidoCrudo: json_encode([
                    'message' => ['conversation' => 'ENSAYO REDIS MILAC - MENSAJE FICTICIO'],
                ], JSON_THROW_ON_ERROR),
                recibidoEn: CarbonImmutable::now('UTC'),
                versionContrato: 'v3-recolector',
            ));

            if ($captura->estado !== 'aceptado' || $captura->eventoCrudoId === null) {
                throw new RuntimeException('No se pudo preparar el evento sintético.');
            }

            return ['evento_id' => $captura->eventoCrudoId, 'grupo_id' => (int) $grupo->id];
        }, 3);

        $this->components->info('Ensayo preparado únicamente en SQL Server _test.');
        $this->line('Evento sintético: #'.$resultado['evento_id']);
        $this->line('Grupo ficticio: #'.$resultado['grupo_id']);
        $this->line('Cola exclusiva: '.$cola);
        $this->line('No se publicó ningún trabajo. Utilice el ID y la cola exactos en el ensayo posterior.');

        return self::SUCCESS;
    }
}
