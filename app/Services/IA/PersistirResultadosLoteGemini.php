<?php

namespace App\Services\IA;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Guarda un resultado ya obtenido de Google de forma atómica e idempotente.
 *
 * No llama Google, no despacha Redis y no crea aún tarjetas operativas.
 * El mismo lote finalizado no se procesa dos veces.
 */
final class PersistirResultadosLoteGemini
{
    public function __construct(private readonly ValidarAcontecimientosLoteGemini $validador) {}

    /**
     * @param array{acontecimientos:array,modelo:string,tokens_entrada:?int,tokens_salida:?int} $respuesta
     * @return string finalizado | ya_finalizado
     */
    public function guardar(int $loteId, array $respuesta): string
    {
        if ($loteId < 1
            || ! is_string($respuesta['modelo'] ?? null)
            || ! preg_match('/\Agemini-[a-zA-Z0-9.-]+\z/D', $respuesta['modelo'])
            || strlen($respuesta['modelo']) > 90
            || ! array_key_exists('acontecimientos', $respuesta)
            || ! is_array($respuesta['acontecimientos'])) {
            throw new DomainException('Identificador o respuesta del lote inválida.');
        }

        foreach (['tokens_entrada', 'tokens_salida'] as $clave) {
            $valor = $respuesta[$clave] ?? null;
            if ($valor !== null && (! is_int($valor) || $valor < 0 || $valor > 4294967295)) {
                throw new DomainException('Los contadores de tokens deben ser enteros no negativos.');
            }
        }

        return DB::transaction(function () use ($loteId, $respuesta): string {
            $lote = DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)->lockForUpdate()
                ->first(['id', 'chat_monitoreado_id', 'estado']);

            if ($lote === null) {
                throw new DomainException('Lote no encontrado.');
            }
            if ($lote->estado === 'finalizado') {
                return 'ya_finalizado';
            }
            if ($lote->estado !== 'publicado') {
                // Nunca volver a publicar una reserva o resultado incierto.
                throw new DomainException('El lote no está confirmado como publicado.');
            }

            $ids = DB::table('mensajero_mensajes_lote_gemini')
                ->where('lote_id', $loteId)
                ->orderBy('posicion')
                ->pluck('mensaje_whatsapp_id')
                ->map(static fn ($id): int => (int) $id)->all();

            if ($ids === [] || count($ids) > AgruparMensajesGemini::MAXIMO_MENSAJES
                || count(array_unique($ids)) !== count($ids)) {
                throw new DomainException('Lote sin mensajes únicos válidos.');
            }

            $mismoGrupo = DB::table('mensajero_mensajes_whatsapp')
                ->where('chat_monitoreado_id', $lote->chat_monitoreado_id)
                ->whereIn('id', $ids)
                ->count();
            if ($mismoGrupo !== count($ids)) {
                throw new DomainException('El lote contiene mensajes de otro grupo.');
            }

            $eventos = $this->validador->validar(
                ['acontecimientos' => $respuesta['acontecimientos']], $ids
            );

            if (DB::table('mensajero_resultados_lote_gemini')
                ->where('lote_id', $loteId)->exists()) {
                // Si hay datos parciales preexistentes se requiere revisión;
                // no adivinar ni sobrescribir hallazgos previos.
                throw new DomainException('Resultados previos inconsistentes; requiere revisión.');
            }

            $ahora = now('UTC')->format('Y-m-d H:i:s.u');
            foreach ($eventos as $posicion => $evento) {
                $resultadoId = DB::table('mensajero_resultados_lote_gemini')->insertGetId([
                    'lote_id' => $loteId,
                    'posicion' => $posicion + 1,
                    'categoria' => $evento['categoria'],
                    'titulo' => $evento['titulo'],
                    'resumen' => $evento['resumen'],
                    'estado' => 'identificado_ia',
                    'creado_en' => $ahora,
                ]);

                foreach ($evento['mensaje_ids'] as $mensajeId) {
                    DB::table('mensajero_evidencias_resultado_lote_gemini')->insert([
                        'resultado_lote_id' => $resultadoId,
                        'mensaje_whatsapp_id' => $mensajeId,
                    ]);
                }
            }

            DB::table('mensajero_lotes_gemini')
                ->where('id', $loteId)
                ->where('estado', 'publicado')
                ->update([
                    'estado' => 'finalizado',
                    'modelo' => $respuesta['modelo'],
                    'tokens_entrada' => $respuesta['tokens_entrada'] ?? null,
                    'tokens_salida' => $respuesta['tokens_salida'] ?? null,
                    'finalizado_en' => $ahora,
                ]);

            return 'finalizado';
        }, 3);
    }
}
