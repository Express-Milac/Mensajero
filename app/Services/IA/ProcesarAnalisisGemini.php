<?php

namespace App\Services\IA;

use App\Models\ChatMonitoreado;
use App\Models\MensajeWhatsapp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ProcesarAnalisisGemini
{
    public function __construct(
        private readonly PoliticaEnvioGemini $politica,
        private readonly PrepararContextoGemini $preparador,
        private readonly ClienteGemini $cliente,
        private readonly ValidarResultadoGemini $validador,
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
            $mensaje->tipo_mensaje !== 'texto' || $mensaje->estado_captura !== 'normalizado' ||
            $mensaje->recibido_en === null ||
            ! $this->politica->autoriza((int) $mensaje->chat_monitoreado_id, $mensaje->recibido_en)) {
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
            ->where('tipo_mensaje', 'texto');

        if (config('mensajero.ia.solo_mensaje_objetivo', true) !== false) {
            $consultaContexto->whereKey($mensajeId);
        } else {
            // Incluso si se autoriza el contexto, nunca consultar mensajes
            // previos a la fecha de consentimiento explícito de Gemini.
            $inicio = $this->politica->fechaInicio();
            if ($inicio === null) {
                return 'no_elegible';
            }

            $fechaInicio = $inicio->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
            $fecha = $mensaje->recibido_en->utc()->format('Y-m-d H:i:s.u');

            $consultaContexto
                ->where('recibido_en', '>=', $fechaInicio)
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
            ->get(['id', 'contenido_texto']);

        $contexto = [];
        foreach ($anteriores->reverse() as $anterior) {
            $texto = $this->preparador->limpiar((string) $anterior->contenido_texto);
            if ($texto !== '') {
                $contexto[] = ['id' => (int) $anterior->id, 'texto' => $texto, 'tipo' => 'texto'];
            }
        }

        if ($contexto === [] || $contexto[array_key_last($contexto)]['id'] !== $mensajeId) {
            return 'sin_texto';
        }

        // Ninguna operación de red ocurre dentro de una transacción SQL Server.
        $respuesta = $this->cliente->analizar($contexto);
        $validado = $this->validador->validar(
            $respuesta['resultado'],
            array_column($contexto, 'id'),
            $mensajeId
        );
        $huella = hash('sha256', json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($mensaje, $mensajeId, $validado, $respuesta, $huella): string {
            $grupo = ChatMonitoreado::query()
                ->whereKey($mensaje->chat_monitoreado_id)
                ->where('tipo_chat', 'grupo')
                ->where('es_activo', true)
                ->where('es_monitoreado', true)
                ->where('es_visible_tablero', true)
                ->lockForUpdate()
                ->first();

            // Revalidar el permiso después de la llamada externa.
            if ($grupo === null || $mensaje->recibido_en === null ||
                ! $this->politica->autoriza((int) $grupo->id, $mensaje->recibido_en)) {
                return 'no_elegible';
            }

            if (DB::table('mensajero_analisis_operativos_ia')
                ->where('mensaje_whatsapp_id', $mensajeId)->exists()) {
                return 'duplicado';
            }

            DB::table('mensajero_analisis_operativos_ia')->insert([
                'mensaje_whatsapp_id' => $mensajeId,
                'chat_monitoreado_id' => (int) $grupo->id,
                'estado' => $validado['es_evento'] ? 'pendiente_revision' : 'sin_evento',
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

            return $validado['es_evento'] ? 'pendiente_revision' : 'sin_evento';
        }, 3);
    }
}
