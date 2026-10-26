<?php

namespace App\Services\IA;

use App\Models\MensajeWhatsapp;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva local atómica de un lote. NO publica en Redis ni llama a Gemini.
 * Comparte presupuesto con el piloto de mensaje individual para no
 * multiplicar consumo de Google por dos circuitos concurrentes.
 */
final class ReservarLoteGemini
{
    public function __construct(
        private readonly PoliticaEnvioGemini $politica,
        private readonly PoliticaLotesGemini $politicaLotes,
        private readonly ControlInterpretacionGemini $control,
        private readonly PresupuestoPublicacionesGemini $presupuesto,
    ) {}

    /**
     * @param array<int,int> $mensajeIds
     * @return int Identificador único de la reserva SQL.
     */
    public function reservar(int $grupoId, array $mensajeIds): int
    {
        if ($grupoId < 1 || $mensajeIds === []
            || count($mensajeIds) > AgruparMensajesGemini::MAXIMO_MENSAJES
            || ! array_is_list($mensajeIds)
            || count(array_unique($mensajeIds)) !== count($mensajeIds)) {
            throw new DomainException('El lote requiere IDs únicos de uno a seis mensajes.');
        }
        foreach ($mensajeIds as $id) {
            if (! is_int($id) || $id < 1) {
                throw new DomainException('El lote contiene un ID inválido.');
            }
        }

        if (! $this->politicaLotes->habilitada()
            || ! $this->politica->configuracionCompleta()
            || ! $this->control->disponible()
            || ! Schema::hasTable('mensajero_lotes_gemini')
            || ! Schema::hasTable('mensajero_mensajes_lote_gemini')
            || ! Schema::hasTable('mensajero_control_publicaciones_gemini')
            || ! Schema::hasTable('mensajero_analisis_operativos_ia')) {
            throw new DomainException('Procesamiento por lotes no autorizado o tablas ausentes.');
        }

        return DB::transaction(function () use ($grupoId, $mensajeIds): int {
            // Bloqueo igual al del publicador del piloto. Serializa cupos por grupo.
            $grupo = DB::table('mensajero_chats_monitoreados')
                ->where('id', $grupoId)->where('tipo_chat', 'grupo')
                ->where('es_activo', true)->where('es_monitoreado', true)
                ->where('es_visible_tablero', true)->lockForUpdate()->first(['id']);

            if ($grupo === null) {
                throw new DomainException('Grupo no habilitado para lotes.');
            }

            $corteGeneral = $this->politica->fechaInicioGrupo($grupoId);
            $corteGrupo = $this->control->corteEfectivoGrupo($grupoId);
            $corteLote = $this->politicaLotes->corte();
            if ($corteGeneral === null || $corteGrupo === null || $corteLote === null) {
                throw new DomainException('Sin consentimiento efectivo para el lote.');
            }

            $corteUtc = max(
                $corteGeneral->getTimestamp(),
                $corteGrupo->getTimestamp(),
                $corteLote->getTimestamp(),
                now('UTC')->subHours(4)->getTimestamp()
            );
            $hasta = now('UTC')->subSeconds(180)->getTimestamp();

            $mensajes = MensajeWhatsapp::query()
                ->where('chat_monitoreado_id', $grupoId)
                ->where('estado_captura', 'normalizado')
                ->whereIn('id', $mensajeIds)
                ->orderBy('recibido_en')->orderBy('id')
                ->get(['id', 'chat_monitoreado_id', 'tipo_mensaje',
                    'estado_captura', 'contenido_texto', 'pie_multimedia', 'recibido_en']);

            if ($mensajes->count() !== count($mensajeIds)) {
                throw new DomainException('Faltan mensajes normalizados del lote.');
            }

            $ordenados = [];
            $primeraMarca = null;
            $marcaAnterior = null;
            foreach ($mensajes as $mensaje) {
                $fecha = $mensaje->recibido_en;
                $texto = $mensaje->tipo_mensaje === 'texto'
                    ? $mensaje->contenido_texto : $mensaje->pie_multimedia;

                if (! $fecha instanceof DateTimeInterface
                    || $fecha->getTimestamp() < $corteUtc
                    || $fecha->getTimestamp() > $hasta
                    || ! in_array($mensaje->tipo_mensaje, ['texto', 'foto', 'video', 'documento'], true)
                    || trim((string) $texto) === ''
                    || ! $this->politica->autoriza($grupoId, $fecha)
                    || ! $this->control->permite($grupoId, $fecha, true)) {
                    throw new DomainException('Lote fuera de cortes, con contenido inválido o desautorizado.');
                }
                $marca = $fecha->getTimestamp();
                if (($primeraMarca !== null && $marca - $primeraMarca > AgruparMensajesGemini::MAXIMO_VENTANA_SEGUNDOS)
                    || ($marcaAnterior !== null && $marca - $marcaAnterior > AgruparMensajesGemini::MAXIMO_SEPARACION_SEGUNDOS)) {
                    throw new DomainException('El lote supera la ventana o pausa máxima entre mensajes.');
                }
                $primeraMarca ??= $marca;
                $marcaAnterior = $marca;
                $ordenados[] = (int) $mensaje->id;
            }

            if (DB::table('mensajero_analisis_operativos_ia')
                ->whereIn('mensaje_whatsapp_id', $ordenados)->exists()
                || DB::table('mensajero_control_publicaciones_gemini')
                    ->whereIn('mensaje_whatsapp_id', $ordenados)->exists()
                || DB::table('mensajero_mensajes_lote_gemini')
                    ->whereIn('mensaje_whatsapp_id', $ordenados)->exists()) {
                throw new DomainException('El lote contiene mensajes ya reservados o analizados.');
            }

            if (! $this->presupuesto->permiteReserva($grupoId)) {
                throw new DomainException('Límite diario compartido de Gemini alcanzado.');
            }

            $ahora = now('UTC')->format('Y-m-d H:i:s.u');
            $loteId = (int) DB::table('mensajero_lotes_gemini')->insertGetId([
                'chat_monitoreado_id' => $grupoId,
                'hash_mensajes' => hash('sha256', implode(',', $ordenados)),
                'estado' => 'reservado',
                'origen' => 'planificacion',
                'reservado_en' => $ahora,
            ]);

            foreach ($ordenados as $posicion => $mensajeId) {
                DB::table('mensajero_mensajes_lote_gemini')->insert([
                    'lote_id' => $loteId,
                    'mensaje_whatsapp_id' => $mensajeId,
                    'posicion' => $posicion + 1,
                ]);
            }

            return $loteId;
        }, 3);
    }
}
