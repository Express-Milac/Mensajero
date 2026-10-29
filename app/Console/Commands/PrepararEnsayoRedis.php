<?php

namespace App\Console\Commands;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\MensajeBuzonSalida;
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
        {--aplicar : Crear un grupo ficticio y un único mensaje sintético en SQL Server _test}
        {--simular-perdida : Marcar solo el aviso ficticio como publicado hace 31 minutos, sin publicar nada en Redis}';

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

        $simularPerdida = $this->option('simular-perdida') === true;

        if ($this->option('aplicar') !== true) {
            $this->info('Vista previa: se creará una conexión de prueba, un grupo ficticio y un único evento de texto.');
            if ($simularPerdida) {
                $this->line('Se simulará un aviso perdido con fecha de publicación de hace 31 minutos; no se publicará nada en Redis.');
            }
            $this->line('Sin --aplicar no se modifican registros ni se utiliza Redis.');

            return self::SUCCESS;
        }

        $sufijo = bin2hex(random_bytes(8));
        $claveConexion = 'ensayo-redis-'.$sufijo;
        $cola = 'mensajero_ensayo_'.$sufijo;

        $resultado = DB::transaction(function () use ($capturador, $claveConexion, $sufijo, $simularPerdida): array {
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
                (int) $conexion->id,
                $grupo->id_chat_proveedor,
                'mensaje',
                'texto',
                'ensayo-redis-'.$sufijo,
                'ensayo-redis-'.$sufijo,
                json_encode([
                    'message' => ['conversation' => 'ENSAYO REDIS MILAC - MENSAJE FICTICIO'],
                ], JSON_THROW_ON_ERROR),
                CarbonImmutable::now('UTC'),
                'v3-recolector',
            ));

            if ($captura->estado !== 'aceptado' || $captura->eventoCrudoId === null) {
                throw new RuntimeException('No se pudo preparar el evento sintético.');
            }

            if ($simularPerdida) {
                // Únicamente el aviso del evento sintético recién creado.
                // Simulamos el estado "publicado sin consumo" sin conectarnos
                // a Redis ni borrar colas existentes.
                $aviso = MensajeBuzonSalida::query()
                    ->where('tema', 'whatsapp.crudo.aceptado')
                    ->where('tipo_entidad', 'evento_crudo_whatsapp')
                    ->where('entidad_id', $captura->eventoCrudoId)
                    ->whereNull('enviado_en')
                    ->lockForUpdate()
                    ->sole();

                $aviso->enviado_en = now('UTC')->subMinutes(31);
                $aviso->intentos = 1;
                $aviso->save();
            }

            return ['evento_id' => $captura->eventoCrudoId, 'grupo_id' => (int) $grupo->id];
        }, 3);

        $this->info('Ensayo preparado únicamente en SQL Server _test.');
        $this->line('Evento sintético: #'.$resultado['evento_id']);
        $this->line('Grupo ficticio: #'.$resultado['grupo_id']);
        $this->line('Cola exclusiva: '.$cola);
        if ($simularPerdida) {
            $this->line('Simulación: aviso marcado como enviado hace 31 minutos SOLO en SQL Server _test.');
            $this->line('No se publicó nada en Redis. Compruebe primero mensajero:recuperar-publicacion en vista previa.');
        } else {
            $this->line('No se publicó ningún trabajo. Utilice el ID y la cola exactos en el ensayo posterior.');
        }

        return self::SUCCESS;
    }
}
