<?php

namespace App\Services\IA;

use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptores operativos persistentes y visibles por todos los trabajadores.
 * El control externo (.env) sigue siendo un límite adicional y NO se modifica.
 * Activar nunca permite recuperar mensajes anteriores al nuevo corte.
 */
final class ControlInterpretacionGemini
{
    private const TABLA = 'mensajero_control_interpretacion_ia';

    public function disponible(): bool
    {
        return Schema::hasTable(self::TABLA);
    }

    /** @return array{habilitado:bool,inicio_en:?string} */
    public function estado(int $grupoId): array
    {
        if (! $this->disponible()) {
            return ['habilitado' => false, 'inicio_en' => null];
        }

        $registro = DB::table(self::TABLA)
            ->where('chat_monitoreado_id', $grupoId)->first(['habilitado', 'inicio_en']);

        return [
            'habilitado' => $registro !== null && (bool) $registro->habilitado,
            'inicio_en' => $registro?->inicio_en === null ? null : (string) $registro->inicio_en,
        ];
    }

    /**
     * Se consulta también en cada trabajo antes de llamar a Google. En la
     * reserva SQL Server se puede solicitar bloqueo para serializar apagados.
     */
    public function permite(int $grupoId, DateTimeInterface $recibidoEn, bool $bloquear = false): bool
    {
        if ($grupoId < 1 || ! $this->disponible()
            || ! in_array($grupoId, (array) config('mensajero.ia.grupos_autorizados', []), true)) {
            return false;
        }

        $global = DB::table(self::TABLA)->where('chat_monitoreado_id', 0);
        $grupo = DB::table(self::TABLA)->where('chat_monitoreado_id', $grupoId);
        if ($bloquear) {
            $global->lockForUpdate();
            $grupo->lockForUpdate();
        }

        $general = $global->first(['habilitado', 'inicio_en']);
        $individual = $grupo->first(['habilitado', 'inicio_en']);
        if ($general === null || $individual === null
            || ! (bool) $general->habilitado || ! (bool) $individual->habilitado
            || $general->inicio_en === null || $individual->inicio_en === null) {
            return false;
        }

        // SQL Server almacena UTC sin offset; no usar zona del servidor.
        $marca = $recibidoEn->getTimestamp();
        $corteGeneral = strtotime((string) $general->inicio_en.' UTC');
        $corteGrupo = strtotime((string) $individual->inicio_en.' UTC');

        return $corteGeneral !== false && $corteGrupo !== false
            && $marca >= max($corteGeneral, $corteGrupo);
    }

    /**
     * 0: control global. Un grupo solo se puede habilitar si ya está autorizado
     * para Gemini en configuración, activo y monitoreado.
     */
    public function cambiar(int $grupoId, bool $habilitar, string $actor, ?int $actorId): void
    {
        if (! $this->disponible()) {
            throw new DomainException('Falta instalar el control de interpretación en SQL Server.');
        }
        if ($grupoId < 0) {
            throw new DomainException('Grupo inválido.');
        }
        if ($habilitar && ! app(PoliticaEnvioGemini::class)->configuracionCompleta()) {
            throw new DomainException('Antes de habilitar Gemini deben existir consentimiento, clave y servicio autorizado.');
        }
        if ($grupoId > 0 && ! in_array($grupoId, (array) config('mensajero.ia.grupos_autorizados', []), true)) {
            throw new DomainException('Este grupo todavía no tiene autorización para enviar datos a Google.');
        }

        DB::transaction(function () use ($grupoId, $habilitar, $actor, $actorId): void {
            if ($grupoId > 0) {
                $chat = DB::table('mensajero_chats_monitoreados')
                    ->where('id', $grupoId)->where('tipo_chat', 'grupo')
                    ->lockForUpdate()->first(['id', 'es_activo', 'es_monitoreado', 'es_visible_tablero']);
                if ($chat === null) {
                    throw new DomainException('El grupo no está registrado.');
                }
                if ($habilitar && (! (bool) $chat->es_activo
                    || ! (bool) $chat->es_monitoreado || ! (bool) $chat->es_visible_tablero)) {
                    throw new DomainException('El grupo debe estar activo, monitoreado y visible para habilitar Gemini.');
                }
            }

            $tabla = DB::table(self::TABLA);
            $actual = (clone $tabla)->where('chat_monitoreado_id', $grupoId)
                ->lockForUpdate()->first();

            if ($actual !== null && (bool) $actual->habilitado === $habilitar) {
                return;
            }

            $ahora = now('UTC')->format('Y-m-d H:i:s.u');
            if ($actual === null) {
                if ($grupoId === 0) {
                    throw new DomainException('No existe el control global de Gemini.');
                }
                $tabla->insert([
                    'chat_monitoreado_id' => $grupoId,
                    'habilitado' => $habilitar,
                    'inicio_en' => $habilitar ? $ahora : null,
                    'actualizado_en' => $ahora,
                ]);
            } else {
                (clone $tabla)->where('chat_monitoreado_id', $grupoId)->update([
                    'habilitado' => $habilitar,
                    // Reiniciar el corte al ENCENDER, sin backfill ni publicación histórica.
                    'inicio_en' => $habilitar ? $ahora : $actual->inicio_en,
                    'actualizado_en' => $ahora,
                ]);
            }

            DB::table('mensajero_auditoria_interpretacion_ia')->insert([
                'chat_monitoreado_id' => $grupoId,
                'habilitado_anterior' => $actual !== null && (bool) $actual->habilitado,
                'habilitado_nuevo' => $habilitar,
                'actor_tipo' => substr($actor, 0, 40),
                'actor_id' => $actorId,
                'registrado_en' => $ahora,
            ]);
        }, 3);
    }
}
