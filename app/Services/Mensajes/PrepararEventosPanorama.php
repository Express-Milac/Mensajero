<?php

namespace App\Services\Mensajes;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lectura de acontecimientos para el explorador: jamás publica en Redis,
 * invoca a Gemini, altera evidencias ni expone rutas físicas de archivos.
 */
final class PrepararEventosPanorama
{
    private const MAXIMO_POR_GRUPO = 8;
    private const MAXIMO_MINIATURAS = 2;

    /**
     * @param Collection<int, \App\Models\ChatMonitoreado> $grupos
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function ejecutar(Collection $grupos): array
    {
        $resultado = [];
        foreach ($grupos as $grupo) {
            $resultado[(int) $grupo->id] = [];
        }

        if ($resultado === [] ||
            ! Schema::hasTable('mensajero_acontecimientos_operativos') ||
            ! Schema::hasTable('mensajero_evidencias_acontecimientos')) {
            return $resultado;
        }

        $autorizados = array_map('intval', (array) config('mensajero.ia.grupos_autorizados', []));
        $identificadores = array_values(array_intersect(array_keys($resultado), $autorizados));
        if ($identificadores === []) {
            return $resultado;
        }

        // Ocho registros por grupo, no un volcado completo de la bitácora.
        $acontecimientos = collect();
        foreach ($identificadores as $grupoId) {
            $filas = DB::table('mensajero_acontecimientos_operativos as a')
                ->join('mensajero_mensajes_whatsapp as origen', 'origen.id', '=', 'a.mensaje_objetivo_id')
                ->where('a.chat_monitoreado_id', $grupoId)
                ->where('origen.chat_monitoreado_id', $grupoId)
                ->where('a.estado', '<>', 'descartado')
                ->select([
                    'a.id', 'a.chat_monitoreado_id', 'a.categoria', 'a.titulo',
                    'a.resumen', 'a.creado_en', 'origen.evento_crudo_id as evento_origen_id',
                ])
                ->orderByDesc('a.creado_en')
                ->orderByDesc('a.id')
                ->limit(self::MAXIMO_POR_GRUPO)
                ->get();

            $acontecimientos = $acontecimientos->concat($filas);
        }

        if ($acontecimientos->isEmpty()) {
            return $resultado;
        }

        // Una sola lectura de evidencias; jamás se mezclan fuentes entre grupos.
        $fuentes = DB::table('mensajero_evidencias_acontecimientos as e')
            ->join('mensajero_acontecimientos_operativos as a', 'a.id', '=', 'e.acontecimiento_id')
            ->join('mensajero_mensajes_whatsapp as m', 'm.id', '=', 'e.mensaje_whatsapp_id')
            ->leftJoin('mensajero_archivos_whatsapp as f', 'f.evento_crudo_id', '=', 'm.evento_crudo_id')
            ->whereIn('e.acontecimiento_id', $acontecimientos->pluck('id')->all())
            ->whereIn('a.chat_monitoreado_id', $identificadores)
            ->whereColumn('m.chat_monitoreado_id', 'a.chat_monitoreado_id')
            ->select([
                'e.acontecimiento_id', 'e.vinculo', 'm.tipo_mensaje',
                'm.evento_crudo_id', 'f.id as archivo_id', 'f.estado as archivo_estado',
                'f.disco as archivo_disco',
            ])
            ->orderBy('e.id')
            ->get()
            ->groupBy('acontecimiento_id');

        foreach ($acontecimientos as $acontecimiento) {
            $evidencias = $fuentes->get($acontecimiento->id, collect());
            $fotografias = $evidencias->where('tipo_mensaje', 'foto');
            $miniaturas = $fotografias
                ->filter(static fn ($fuente): bool => $fuente->archivo_id !== null
                    && $fuente->archivo_estado === 'verificado'
                    && $fuente->archivo_disco === 'mensajero_local')
                ->take(self::MAXIMO_MINIATURAS)
                ->map(static fn ($fuente): array => [
                    'archivo_id' => (int) $fuente->archivo_id,
                    'vinculo' => (string) $fuente->vinculo,
                ])
                ->values()->all();

            $resultado[(int) $acontecimiento->chat_monitoreado_id][] = [
                'id' => (int) $acontecimiento->id,
                'categoria' => (string) $acontecimiento->categoria,
                'titulo' => (string) $acontecimiento->titulo,
                'resumen' => (string) $acontecimiento->resumen,
                'fecha' => CarbonImmutable::parse((string) $acontecimiento->creado_en, 'UTC'),
                'evento_origen_id' => (int) $acontecimiento->evento_origen_id,
                'fotografias' => $fotografias->count(),
                'documentos' => $evidencias->where('tipo_mensaje', 'documento')->count(),
                'videos' => $evidencias->where('tipo_mensaje', 'video')->count(),
                'audios' => $evidencias->where('tipo_mensaje', 'audio')->count(),
                'fuentes' => $evidencias->count(),
                'miniaturas' => $miniaturas,
                'adjuntos_tentativos' => $evidencias->where('vinculo', 'posible_adjunta')->count(),
            ];
        }

        return $resultado;
    }
}
