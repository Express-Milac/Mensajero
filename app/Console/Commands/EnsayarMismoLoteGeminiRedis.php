<?php

namespace App\Console\Commands;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Jobs\AnalizarLoteGeminiTrabajo;
use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\Normalizacion\NormalizarEventoWhatsapp;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Ensayo REAL de dos consumidores Redis del MISMO lote sintético en SQL Server _test.
 * HTTP Gemini se sustituye por una respuesta falsa en CADA proceso hijo.
 * No contiene ruta para activar el flujo normal ni escribir fuera de _test.
 */
final class EnsayarMismoLoteGeminiRedis extends Command
{
    protected $signature = 'mensajero:ensayar-mismo-lote-gemini-redis
        {--aplicar : Crear un lote sintético y publicar dos trabajos para él}
        {--trabajador : Uso interno del ensayo}
        {--cola= : Cola aleatoria exclusiva, uso interno}
        {--lote= : ID de lote sintético, uso interno}
        {--contador= : Contador Redis aislado, uso interno}';

    protected $description = 'Ensaya doble entrega de un lote real SQL con dos trabajadores y Gemini simulado';

    public function handle(): int
    {
        if (! app()->environment('testing')
            || config('database.default') !== 'sqlsrv'
            || config('database.connections.sqlsrv.database') !== 'mensajero_integracion_test'
            || config('mensajero.ia.habilitada') !== false
            || config('mensajero.ia.envio_autorizado') !== false
            || config('mensajero.ia.lotes_habilitados') !== false) {
            $this->error('Bloqueado: se exige testing, base SQL exclusiva y Gemini apagado.');
            return self::FAILURE;
        }

        try {
            if (DB::selectOne('SELECT DB_NAME() AS nombre')->nombre !== 'mensajero_integracion_test') {
                throw new RuntimeException('La base SQL real no es la base de integración.');
            }
        } catch (Throwable $error) {
            $this->error('Error al verificar base de datos: '.class_basename($error));
            return self::FAILURE;
        }

        if ($this->option('trabajador')) {
            return $this->trabajarFicticio();
        }

        if (! $this->option('aplicar')) {
            $this->components->info('Ensayo de un lote sintético contra dos trabajadores Redis independientes.');
            $this->line('Sólo SQL Server mensajero_integracion_test. Dos trabajos con MISMO lote.');
            $this->line('Cada proceso instala su simulación HTTP ANTES de habilitar controles de prueba.');
            $this->line('Se exigirá un único intento HTTP simulado y un lote finalizado.');
            $this->line('La limpieza es exclusiva de los registros sintéticos y la cola aleatoria.');
            return self::SUCCESS;
        }

        return $this->coordinar();
    }

    private function trabajarFicticio(): int
    {
        $colaNombre = (string) $this->option('cola');
        $contador = (string) $this->option('contador');
        $loteId = filter_var($this->option('lote'), FILTER_VALIDATE_INT);

        if (! preg_match('/\Amensajero_mismo_lote_ensayo_[0-9a-f]{24}\z/D', $colaNombre)
            || ! preg_match('/\Amensajero_mismo_lote_contador_[0-9a-f]{24}\z/D', $contador)
            || $loteId === false || $loteId < 1) {
            $this->error('Parámetros de ensayo interno inválidos.');
            return self::FAILURE;
        }

        $lote = DB::table('mensajero_lotes_gemini as lote')
            ->join('mensajero_chats_monitoreados as grupo', 'grupo.id', '=', 'lote.chat_monitoreado_id')
            ->where('lote.id', $loteId)
            ->where('grupo.id_chat_proveedor', 'like', 'ensayo-mismo-lote-%@g.us')
            ->first(['lote.id']);

        if ($lote === null) {
            $this->error('El lote no pertenece a este ensayo sintético.');
            return self::FAILURE;
        }

        // Barrera crítica: ninguna solicitud puede salir a red. Instalar ANTES de
        // activar las banderas temporales para que el servicio real sólo vea HTTP falso.
        Http::preventStrayRequests();
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => function (Request $solicitud) use ($contador) {
                if ($solicitud->method() !== 'POST') {
                    throw new RuntimeException('Método HTTP inesperado.');
                }

                Redis::connection('default')->incr($contador);
                usleep(150000); // Permite que el otro trabajador compita por SQL.
                return Http::response([
                    'candidates' => [[
                        'finishReason' => 'STOP',
                        'content' => ['parts' => [['text' => '{"acontecimientos":[]}']]],
                    ]],
                    'usageMetadata' => [
                        'promptTokenCount' => 20,
                        'candidatesTokenCount' => 8,
                    ],
                ], 200);
            },
        ]);

        $corte = CarbonImmutable::now('UTC')->subHours(2)->format('Y-m-d\TH:i:sP');
        config()->set('mensajero.ia.habilitada', true);
        config()->set('mensajero.ia.envio_autorizado', true);
        config()->set('mensajero.ia.servicio_pagado_confirmado', true);
        config()->set('mensajero.ia.clave_api', 'clave-falsa-ensayo-concurrencia-sin-red');
        config()->set('mensajero.ia.fecha_inicio', $corte);
        config()->set('mensajero.ia.lotes_habilitados', true);
        config()->set('mensajero.ia.lotes_fecha_inicio', $corte);
        config()->set('mensajero.ia.lotes_maximo_dia_grupo', 3);
        config()->set('mensajero.ia.modelo', 'gemini-3.5-flash-lite');
        config()->set('mensajero.tiempo_real.habilitado', false);

        $procesados = 0;
        $fallos = 0;
        Queue::after(function (JobProcessed $evento) use (&$procesados, $colaNombre): void {
            if ($evento->connectionName === 'redis'
                && $evento->job->getQueue() === $colaNombre
                && ($evento->job->payload()['data']['commandName'] ?? null) === AnalizarLoteGeminiTrabajo::class) {
                $procesados++;
            }
        });
        Queue::failing(function (JobFailed $evento) use (&$fallos, $colaNombre): void {
            if ($evento->connectionName === 'redis' && $evento->job->getQueue() === $colaNombre) {
                $fallos++;
            }
        });

        $salida = Artisan::call('queue:work', [
            'connection' => 'redis',
            '--queue' => $colaNombre,
            '--once' => true,
            '--tries' => 1,
            '--timeout' => 10,
            '--sleep' => 0,
        ]);

        if ($salida !== 0 || $procesados !== 1 || $fallos !== 0) {
            $this->error(sprintf('Trabajador interno sin confirmación: %d/%d/%d.',
                $salida, $procesados, $fallos));
            return self::FAILURE;
        }

        $this->line('TRABAJADOR_MISMO_LOTE_OK');
        return self::SUCCESS;
    }

    private function coordinar(): int
    {
        $sufijo = bin2hex(random_bytes(12));
        $colaNombre = 'mensajero_mismo_lote_ensayo_'.$sufijo;
        $contador = 'mensajero_mismo_lote_contador_'.$sufijo;
        $ids = ['conexion' => null, 'grupo' => null, 'evento' => null, 'mensaje' => null, 'lote' => null];
        $procesos = [];
        $globalAnterior = null;
        $globalCambiado = false;
        $correcto = false;
        $limpiezaCorrecta = true;

        // No notificar el tablero y no publicar el circuito individual.
        config()->set('mensajero.tiempo_real.habilitado', false);
        try {
            $globalAnterior = DB::table('mensajero_control_interpretacion_ia')
                ->where('chat_monitoreado_id', 0)->first();
            if ($globalAnterior === null || (bool) $globalAnterior->habilitado) {
                throw new RuntimeException('Control global SQL inexistente o encendido; no iniciar ensayo.');
            }

            $clave = 'ensayo-mismo-lote-'.$sufijo;
            $conexion = ConexionWhatsapp::query()->create([
                'clave_conexion' => $clave,
                'proveedor' => 'recolector_whatsapp',
                'estado' => 'conectado',
            ]);
            $ids['conexion'] = (int) $conexion->id;

            $grupo = ChatMonitoreado::query()->create([
                'conexion_id' => $conexion->id,
                'id_chat_proveedor' => $clave.'@g.us',
                'tipo_chat' => 'grupo',
                'es_monitoreado' => true,
                'es_visible_tablero' => true,
                'es_activo' => true,
            ]);
            $ids['grupo'] = (int) $grupo->id;

            $evento = app(CapturarEventoWhatsappEntrante::class)->ejecutar(
                new EventoWhatsappEntrante(
                    conexionId: (int) $conexion->id,
                    chatId: $grupo->id_chat_proveedor,
                    tipoEvento: 'mensaje',
                    tipoMensaje: 'texto',
                    eventoExternoId: $clave,
                    mensajeProveedorId: $clave,
                    contenidoCrudo: json_encode([
                        'message' => ['conversation' => 'Mensaje ficticio para ensayo de concurrencia'],
                    ], JSON_THROW_ON_ERROR),
                    recibidoEn: CarbonImmutable::now('UTC')->subMinutes(10),
                    versionContrato: 'v3-recolector',
                )
            );

            if ($evento->estado !== 'aceptado' || $evento->eventoCrudoId === null) {
                throw new RuntimeException('No se pudo capturar el evento de ensayo.');
            }
            $ids['evento'] = (int) $evento->eventoCrudoId;

            if (app(NormalizarEventoWhatsapp::class)->ejecutar($ids['evento']) !== 'normalizado') {
                throw new RuntimeException('No se pudo normalizar el evento sintético.');
            }
            $ids['mensaje'] = (int) MensajeWhatsapp::query()
                ->where('evento_crudo_id', $ids['evento'])->value('id');
            if ($ids['mensaje'] < 1) {
                throw new RuntimeException('No existe mensaje sintético normalizado.');
            }
            if (DB::table('mensajero_control_publicaciones_gemini')
                ->where('mensaje_whatsapp_id', $ids['mensaje'])->exists()) {
                throw new RuntimeException('El circuito individual publicó el mensaje de ensayo.');
            }

            $corte = CarbonImmutable::now('UTC')->subHours(2)->format('Y-m-d H:i:s.u');
            DB::transaction(function () use ($ids, $corte, &$globalCambiado): void {
                DB::table('mensajero_control_interpretacion_ia')
                    ->where('chat_monitoreado_id', 0)
                    ->update(['habilitado' => true, 'inicio_en' => $corte]);
                $globalCambiado = true;
                DB::table('mensajero_control_interpretacion_ia')->insert([
                    'chat_monitoreado_id' => $ids['grupo'],
                    'habilitado' => true,
                    'inicio_en' => $corte,
                    'actualizado_en' => $corte,
                ]);
            });

            $ids['lote'] = (int) DB::table('mensajero_lotes_gemini')->insertGetId([
                'chat_monitoreado_id' => $ids['grupo'],
                'hash_mensajes' => hash('sha256', $sufijo),
                'estado' => 'publicado',
                'reservado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
                'publicado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);
            DB::table('mensajero_mensajes_lote_gemini')->insert([
                'lote_id' => $ids['lote'],
                'mensaje_whatsapp_id' => $ids['mensaje'],
                'posicion' => 1,
            ]);

            Redis::connection('default')->set($contador, 0);
            $cola = Queue::connection('redis');
            if ($cola->size($colaNombre) !== 0) {
                throw new RuntimeException('Cola aleatoria inesperadamente no vacía.');
            }
            for ($i = 0; $i < 2; $i++) {
                $cola->push((new AnalizarLoteGeminiTrabajo($ids['lote']))->onQueue($colaNombre),
                    '', $colaNombre);
            }

            $entorno = [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'sqlsrv',
                'DB_DATABASE' => 'mensajero_integracion_test',
                'DB_URL' => '',
                'IA_HABILITADA' => 'false',
                'MENSAJERO_IA_ENVIO_AUTORIZADO' => 'false',
                'MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO' => 'false',
                'MENSAJERO_IA_LOTES_HABILITADOS' => 'false',
                'MENSAJERO_NORMALIZACION_HABILITADA' => 'false',
            ];
            for ($i = 0; $i < 2; $i++) {
                $proceso = new Process([
                    PHP_BINARY, base_path('artisan'),
                    'mensajero:ensayar-mismo-lote-gemini-redis',
                    '--trabajador', '--cola='.$colaNombre,
                    '--lote='.$ids['lote'], '--contador='.$contador,
                ], base_path(), $entorno);
                $proceso->setTimeout(25);
                $proceso->start();
                $procesos[] = $proceso;
            }

            foreach ($procesos as $indice => $proceso) {
                $proceso->wait();
                if ($proceso->getExitCode() !== 0
                    || ! str_contains($proceso->getOutput(), 'TRABAJADOR_MISMO_LOTE_OK')) {
                    $this->error('Trabajador '.($indice + 1).' sin confirmación.');
                    throw new RuntimeException('Uno de los trabajadores no terminó correctamente.');
                }
                $this->line('Trabajador '.($indice + 1).': completado.');
            }

            $intentos = (int) Redis::connection('default')->get($contador);
            $estado = DB::table('mensajero_lotes_gemini')->where('id', $ids['lote'])->value('estado');
            $pendientes = $cola->size($colaNombre);
            $resultados = DB::table('mensajero_resultados_lote_gemini')
                ->where('lote_id', $ids['lote'])->count();

            $this->line(sprintf('Solicitudes HTTP falsas: %d; estado: %s; cola: %d; resultados: %d.',
                $intentos, (string) $estado, $pendientes, $resultados));

            if ($intentos !== 1 || $estado !== 'finalizado' || $pendientes !== 0
                || $resultados !== 0) {
                throw new RuntimeException('No se alcanzó el criterio de una sola solicitud falsa.');
            }
            $correcto = true;
        } catch (Throwable $error) {
            $this->error('Ensayo mismo lote no aprobado: '.class_basename($error));
        } finally {
            foreach ($procesos as $proceso) {
                if ($proceso->isRunning()) {
                    $proceso->stop(1);
                }
            }

            try {
                $cola = Queue::connection('redis');
                for ($i = 0; $i < 2; $i++) {
                    $pendiente = $cola->pop($colaNombre);
                    if ($pendiente === null) {
                        break;
                    }
                    $pendiente->delete();
                }
                Redis::connection('default')->del($contador);
            } catch (Throwable $error) {
                $limpiezaCorrecta = false;
                $this->warn('Revisar cola aleatoria: '.$colaNombre);
            }

            // No borrar ni modificar datos no identificados explícitamente como sintéticos.
            try {
                DB::transaction(function () use ($ids, $globalAnterior, $globalCambiado): void {
                    if ($ids['lote'] !== null) {
                        $resultadosIds = DB::table('mensajero_resultados_lote_gemini')
                            ->where('lote_id', $ids['lote'])->pluck('id')->all();
                        if ($resultadosIds !== []) {
                            DB::table('mensajero_evidencias_resultado_lote_gemini')
                                ->whereIn('resultado_lote_id', $resultadosIds)->delete();
                        }
                        DB::table('mensajero_resultados_lote_gemini')
                            ->where('lote_id', $ids['lote'])->delete();
                        DB::table('mensajero_mensajes_lote_gemini')
                            ->where('lote_id', $ids['lote'])->delete();
                        DB::table('mensajero_lotes_gemini')->where('id', $ids['lote'])->delete();
                    }
                    if ($ids['grupo'] !== null) {
                        DB::table('mensajero_control_interpretacion_ia')
                            ->where('chat_monitoreado_id', $ids['grupo'])->delete();
                    }
                    if ($globalCambiado && $globalAnterior !== null) {
                        DB::table('mensajero_control_interpretacion_ia')
                            ->where('chat_monitoreado_id', 0)->update([
                                'habilitado' => $globalAnterior->habilitado,
                                'inicio_en' => $globalAnterior->inicio_en,
                                'actualizado_en' => $globalAnterior->actualizado_en,
                            ]);
                    }
                    if ($ids['mensaje'] !== null) {
                        DB::table('mensajero_mensajes_whatsapp')
                            ->where('id', $ids['mensaje'])->delete();
                    }
                    if ($ids['evento'] !== null) {
                        DB::table('mensajero_buzon_salida')
                            ->where('tipo_entidad', 'evento_crudo_whatsapp')
                            ->where('entidad_id', $ids['evento'])->delete();
                        DB::table('mensajero_eventos_crudos_whatsapp')
                            ->where('id', $ids['evento'])->delete();
                    }
                    if ($ids['grupo'] !== null) {
                        DB::table('mensajero_chats_monitoreados')
                            ->where('id', $ids['grupo'])->delete();
                    }
                    if ($ids['conexion'] !== null) {
                        DB::table('mensajero_conexiones_whatsapp')
                            ->where('id', $ids['conexion'])->delete();
                    }
                });
            } catch (Throwable $error) {
                $limpiezaCorrecta = false;
                $this->warn('No se pudo completar la limpieza SQL sintética: '.class_basename($error));
                $this->line('Grupo ficticio para diagnóstico: '.(string) $ids['grupo']);
            }
        }

        if (! $correcto || ! $limpiezaCorrecta) {
            $this->error('El ensayo no pasó o requiere revisar su limpieza.');
            return self::FAILURE;
        }
        $this->components->info('Mismo lote con dos trabajadores: solo UNA llamada HTTP falsa.');
        $this->line('SQL y Redis de ensayo limpiados; nunca se llamó a Google.');
        return self::SUCCESS;
    }
}
