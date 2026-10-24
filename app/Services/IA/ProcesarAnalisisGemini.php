<?php

namespace App\Services\IA;

use App\Models\ChatMonitoreado;
use App\Models\MensajeWhatsapp;
use App\Services\Operacion\RegistrarAcontecimientoOperativo;
use App\Services\Mensajes\NotificadorTiempoReal;
use App\Services\IA\ControlInterpretacionGemini;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ProcesarAnalisisGemini
{
    public function __construct(
        private readonly PoliticaEnvioGemini $politica,
        private readonly ConstruirDialogoGemini $constructorDialogo,
        private readonly ClienteGemini $cliente,
        private readonly ValidarResultadoGemini $validador,
        private readonly RegistrarAcontecimientoOperativo $registrar,
        private readonly NotificadorTiempoReal $notificador,
    ) {}

    public function ejecutar(int $mensajeId): string
    {
        if (! Schema::hasTable('mensajero_analisis_operativos_ia')) {
            throw new RuntimeException('Aplica la migración de análisis IA antes de procesar.');
        }

        $mensaje = MensajeWhatsapp::query()->with('chat')->find($mensajeId);
        if ($mensaje === null || $mensaje->chat === null ||
            $mensaje->chat->tipo_chat !== 'grupo' ||
            ! $mensaje->chat->es_activo || ! $mensaje->chat->es_monitoreado ||
            ! $mensaje->chat->es_visible_tablero ||
            ! $this->tieneTextoAnalizable($mensaje) || $mensaje->estado_captura !== 'normalizado' ||
            $mensaje->recibido_en === null ||
            ! $this->politica->autoriza((int) $mensaje->chat_monitoreado_id, $mensaje->recibido_en)
            || ! app(ControlInterpretacionGemini::class)->permite((int) $mensaje->chat_monitoreado_id, $mensaje->recibido_en)) {
            return 'no_elegible';
        }

        $tabla = DB::table('mensajero_analisis_operativos_ia');
        if ((clone $tabla)->where('mensaje_whatsapp_id', $mensajeId)->exists()) {
            return 'duplicado';
        }

        // En el piloto se transmite EXCLUSIVAMENTE el mensaje objetivo.
        // Los mensajes anteriores del mismo grupo pueden contener datos de
        // terceros y no se deben enviar como contexto de forma implícita.
        $consultaContexto = MensajeWhatsapp::query()
            ->where('chat_monitoreado_id', $mensaje->chat_monitoreado_id)
            ->where('estado_captura', 'normalizado')
            ->where(function ($consulta): void {
                $consulta->where('tipo_mensaje', 'texto')
                    ->orWhere(function ($multimedia): void {
                        $multimedia->whereIn('tipo_mensaje', ['foto', 'video', 'documento'])
                            ->whereNotNull('pie_multimedia');
                    });
            });

        if (config('mensajero.ia.solo_mensaje_objetivo', true) !== false) {
            $consultaContexto->whereKey($mensajeId);
        } else {
            // Incluso si se autoriza el contexto, nunca consultar mensajes
            // previos a la fecha de consentimiento explícito de Gemini.
            $inicio = $this->politica->fechaInicioGrupo((int) $mensaje->chat_monitoreado_id);
            if ($inicio === null) {
                return 'no_elegible';
            }

            $fechaInicio = $inicio->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
            $fecha = $mensaje->recibido_en->utc()->format('Y-m-d H:i:s.u');

            $desdeVentana = $mensaje->recibido_en->copy()->subMinutes(5)->format('Y-m-d H:i:s.u');
            $consultaContexto
                ->where('recibido_en', '>=', max($fechaInicio, $desdeVentana))
                ->where(function ($consulta) use ($fecha, $mensajeId): void {
                    $consulta->where('recibido_en', '<', $fecha)
                        ->orWhere(function ($empate) use ($fecha, $mensajeId): void {
                            $empate->where('recibido_en', '=', $fecha)->where('id', '<=', $mensajeId);
                        });
                });
        }

        $anteriores = $consultaContexto
            ->orderByDesc('recibido_en')->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'contenido_texto', 'pie_multimedia', 'tipo_mensaje',
                'contacto_remitente_id', 'mensaje_citado_id']);

        $contexto = $this->constructorDialogo->ejecutar($anteriores->reverse()->all());

        if ($contexto === [] || $contexto[array_key_last($contexto)]['id'] !== $mensajeId) {
            return 'sin_texto';
        }

        // Revalidar antes de la llamada pagada: un administrador pudo apagar
        // Gemini mientras se construía el diálogo en otro proceso.
        if (! app(ControlInterpretacionGemini::class)->permite(
            (int) $mensaje->chat_monitoreado_id, $mensaje->recibido_en
        )) {
            return 'no_elegible';
        }

        // Ninguna operación de red ocurre dentro de una transacción SQL Server.
        $respuesta = $this->cliente->analizar($contexto);
        $validado = $this->validador->validar(
            $respuesta['resultado'],
            array_column($contexto, 'id'),
            $mensajeId
        );
        $huella = hash('sha256', json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $estado = DB::transaction(function () use ($mensaje, $mensajeId, $validado, $respuesta, $huella): string {
            $grupo = ChatMonitoreado::query()
                ->whereKey($mensaje->chat_monitoreado_id)
                ->where('tipo_chat', 'grupo')
                ->where('es_activo', true)
                ->where('es_monitoreado', true)
                ->where('es_visible_tablero', true)
                ->lockForUpdate()
                ->first();

            // La llamada a Google YA ocurrió. Este rechazo no equivale a
            // un mensaje no elegible antes del envío y jamás se debe reintentar
            // automáticamente: puede haber generado consumo de API.
            if ($grupo === null || $mensaje->recibido_en === null ||
                ! $this->politica->autoriza((int) $grupo->id, $mensaje->recibido_en)) {
                return 'rechazado_despues_google';
            }

            if (DB::table('mensajero_analisis_operativos_ia')
                ->where('mensaje_whatsapp_id', $mensajeId)->exists()) {
                return 'duplicado';
            }

            DB::table('mensajero_analisis_operativos_ia')->insert([
                'mensaje_whatsapp_id' => $mensajeId,
                'chat_monitoreado_id' => (int) $grupo->id,
                'estado' => $validado['es_evento'] ? 'identificado_ia' : 'sin_evento',
                'categoria' => $validado['categoria'],
                'titulo' => $validado['titulo'],
                'resumen' => $validado['resumen'],
                'evidencias_json' => json_encode($validado['mensaje_ids'], JSON_THROW_ON_ERROR),
                'modelo' => $respuesta['modelo'],
                'hash_contexto' => $huella,
                'tokens_entrada' => $respuesta['tokens_entrada'],
                'tokens_salida' => $respuesta['tokens_salida'],
                'creado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);

            return $validado['es_evento'] ? 'identificado_ia' : 'sin_evento';
        }, 3);

        // La proyección operativa NO forma parte de la llamada a Google:
        // si falla, se recupera desde el análisis persistido, sin gastar
        // otra solicitud Gemini ni perder la evidencia cruda.
        if ($estado === 'identificado_ia') {
            try {
                $analisisId = DB::table('mensajero_analisis_operativos_ia')
                    ->where('mensaje_whatsapp_id', $mensajeId)->value('id');
                if ($analisisId !== null) {
                    $idAcontecimiento = $this->registrar->desdeAnalisis((int) $analisisId);
                    if ($idAcontecimiento !== null && $mensaje->evento_crudo_id !== null) {
                        // Actualizar el panorama inmediatamente cuando la IA
                        // publica un acontecimiento, sin esperar otro WhatsApp.
                        // El aviso lleva solo identificadores, no contenido.
                        $this->notificador->avisar(
                            (int) $mensaje->chat_monitoreado_id,
                            (int) $mensaje->evento_crudo_id,
                        );
                    }
                }
            } catch (Throwable $error) {
                Log::warning('Proyección operativa pendiente de recuperación.', [
                    'mensaje_id' => $mensajeId,
                    'tipo_error' => get_class($error),
                ]);
            }
        }

        return $estado;
    }

    private function tieneTextoAnalizable(MensajeWhatsapp $mensaje): bool
    {
        if ($mensaje->tipo_mensaje === 'texto') {
            return trim((string) $mensaje->contenido_texto) !== '';
        }

        return in_array($mensaje->tipo_mensaje, ['foto', 'video', 'documento'], true)
            && trim((string) $mensaje->pie_multimedia) !== '';
    }
}
