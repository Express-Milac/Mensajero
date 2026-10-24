<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Inventario de autorizaciones de captura; no consulta mensajes ni Google.
 */
final class ComprobarGruposIa extends Command
{
    protected $signature = 'mensajero:comprobar-grupos-ia
        {--grupos= : Lista separada por comas de identificadores internos (hasta 6)}
        {--listar : Mostrar grupos habilitados y sus IDs sin leer contenido de WhatsApp}';

    protected $description = 'Comprueba que los grupos candidatos para Gemini están activos y habilitados en Mensajero. Solo lectura.';

    public function handle(): int
    {
        if ($this->option('listar')) {
            $disponibles = DB::table('mensajero_chats_monitoreados')
                ->where('tipo_chat', 'grupo')
                ->where('es_activo', true)->where('es_monitoreado', true)
                ->where('es_visible_tablero', true)
                ->orderBy('id')->limit(100)
                ->get(['id', 'nombre_interno', 'nombre_grupo']);
            $this->table(['ID interno', 'Grupo habilitado'], $disponibles->map(
                static fn ($g): array => [$g->id, $g->nombre_interno ?: ($g->nombre_grupo ?: '(sin nombre)')]
            )->all());
            $this->comment('Solo metadatos. No se transmitio contenido a Google.');
            return self::SUCCESS;
        }

        $cadena = trim((string) $this->option('grupos'));
        if (! preg_match('/\A[1-9][0-9]*(?:,[1-9][0-9]*){0,5}\z/D', $cadena)) {
            $this->error('Introduce de 1 a 6 IDs internos positivos, separados por comas y sin espacios.');
            return self::FAILURE;
        }

        $ids = array_map('intval', explode(',', $cadena));
        if (count(array_unique($ids)) !== count($ids) || in_array(0, $ids, true)) {
            $this->error('La lista contiene identificadores inválidos o duplicados.');
            return self::FAILURE;
        }

        $grupos = DB::table('mensajero_chats_monitoreados')
            ->whereIn('id', $ids)
            ->where('tipo_chat', 'grupo')
            ->where('es_activo', true)
            ->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)
            ->get(['id', 'nombre_interno', 'nombre_grupo']);

        $this->table(
            ['ID interno', 'Grupo habilitado'],
            $grupos->map(static fn ($g): array => [
                $g->id,
                $g->nombre_interno ?: ($g->nombre_grupo ?: '(sin nombre)'),
            ])->all()
        );

        if ($grupos->count() !== count($ids)) {
            $this->error('No todos los grupos existen y están habilitados para captura y tablero.');
            return self::FAILURE;
        }

        $this->info('Grupos válidos para solicitar autorización explícita de Gemini.');
        $this->comment('No se cambió ninguna política ni se enviaron datos a Google.');

        return self::SUCCESS;
    }
}
