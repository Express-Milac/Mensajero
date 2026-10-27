<?php

namespace App\Services\IA;

use App\Jobs\AnalizarMensajeGeminiTrabajo;
use App\Services\IA\ControlInterpretacionGemini;
use App\Models\MensajeWhatsapp;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Reparación conservadora de trabajos omitidos:
 * - no modifica evidencias;
 * - nunca vuelve a enviar un ID ya reservado;
 * - no recupera historia anterior al consentimiento nuevo;
 * - no reintenta automáticamente cuando el resultado de Google es incierto.
 */
final class ConciliarPublicacionesGemini
{
    private PoliticaEnvioGemini $politica;
    private PresupuestoPublicacionesGemini $presupuesto;

    public function __construct(PoliticaEnvioGemini $politica, PresupuestoPublicacionesGemini $presupuesto)
    {
        $this->politica = $politica;
        $this->presupuesto = $presupuesto;
    }

    public function disponible(): bool
    {
        return Schema::hasTable('mensajero_control_publicaciones_gemini')
            && Schema::hasTable('mensajero_analisis_operativos_ia');
    }

    public function habilitada(): bool
    {
        return config('mensajero.ia.conciliacion_automatica') === true
            && $this->politica->configuracionCompleta()
            && $this->corteConciliacion() !== null
            && $this->maximoDiario() > 0;
    }

    /** @return array<int, MensajeWhatsapp> */
    public function candidatos(int $grupoId, int $limite, bool $soloNuevos = true): array
    {
        if (! $this->disponible() || ! $this->grupoPermitido($grupoId) || $limite < 1 || $limite > 20) {
            return [];
        }

        $inicioGeneral = $this->politica->fechaInicioGrupo($grupoId);
        $inicioGrupo = app(ControlInterpretacionGemini::class)->corteEfectivoGrupo($grupoId);
        if ($inicioGeneral === null || $inicioGrupo === null) {
            return [];
        }

        $inicioUtc = max(
            $inicioGeneral->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            $inicioGrupo->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        );

        if ($soloNuevos) {
            $corte = $this->corteConciliacion();
            if ($corte === null) {
                return [];
            }

            $inicioUtc = max($inicioUtc, $corte->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'));
            $horas = max(1, min(24, (int) config('mensajero.ia.conciliacion_horas_maximas', 4)));
            $inicioUtc = max($inicioUtc, now('UTC')->subHours($horas)->format('Y-m-d H:i:s.u'));
        }

        $antiguedad = max(120, min(3600, (int) config('mensajero.ia.conciliacion_antiguedad_segundos', 180)));
        $hasta = now('UTC')->subSeconds($antiguedad)->format('Y-m-d H:i:s.u');

        $mensajes = MensajeWhatsapp::query()
            ->where('chat_monitoreado_id', $grupoId)
            ->where('estado_captura', 'normalizado')
            ->where('recibido_en', '>=', $inicioUtc)
            ->where('recibido_en', '<=', $hasta)
            ->where(function (Builder $consulta): void {
                $consulta->where(function (Builder $texto): void {
                    $texto->where('tipo_mensaje', 'texto')
                        ->whereNotNull('contenido_texto')->where('contenido_texto', '<>', '');
                })->orWhere(function (Builder $leyenda): void {
                    $leyenda->whereIn('tipo_mensaje', ['foto', 'video', 'documento'])
                        ->whereNotNull('pie_multimedia')->where('pie_multimedia', '<>', '');
                });
            })
            ->whereNotExists(function ($consulta): void {
                $consulta->selectRaw('1')->from('mensajero_analisis_operativos_ia as a')
                    ->whereColumn('a.mensaje_whatsapp_id', 'mensajero_mensajes_whatsapp.id');
            })
            ->whereNotExists(function ($consulta): void {
                $consulta->selectRaw('1')->from('mensajero_control_publicaciones_gemini as p')
                    ->whereColumn('p.mensaje_whatsapp_id', 'mensajero_mensajes_whatsapp.id');
            })
            ->when(Schema::hasTable('mensajero_mensajes_lote_gemini'), function (Builder $consulta): void {
                $consulta->whereNotExists(function ($subconsulta): void {
                    $subconsulta->selectRaw('1')->from('mensajero_mensajes_lote_gemini as lote')
                        ->whereColumn('lote.mensaje_whatsapp_id', 'mensajero_mensajes_whatsapp.id');
                });
            })
            ->orderBy('recibido_en')->orderBy('id')
            ->limit($limite)->get(['id', 'tipo_mensaje', 'contenido_texto', 'pie_multimedia', 'chat_monitoreado_id', 'recibido_en']);

        return $mensajes->filter(static function (MensajeWhatsapp $mensaje): bool {
            $contenido = $mensaje->tipo_mensaje === 'texto'
                ? $mensaje->contenido_texto : $mensaje->pie_multimedia;

            return trim((string) $contenido) !== '';
        })->all();
    }

    public function publicar(int $mensajeId, int $grupoId): string
    {
        return $this->publicarInterno($mensajeId, $grupoId, 'conciliacion');
    }

    public function publicarNuevo(int $mensajeId, int $grupoId): string
    {
        return $this->publicarInterno($mensajeId, $grupoId, 'normalizacion');
    }

    public function publicarManual(int $mensajeId, int $grupoId): string
    {
        return $this->publicarInterno($mensajeId, $grupoId, 'manual');
    }

    private function publicarInterno(int $mensajeId, int $grupoId, string $origen): string
    {
        if (($origen === 'conciliacion' && ! $this->habilitada())
            || ! $this->politica->configuracionCompleta()
            || ! $this->disponible()
            || ! $this->grupoPermitido($grupoId)) {
            return 'deshabilitado';
        }

        // La reserva única y el límite diario se calculan bajo bloqueo del
        // grupo en SQL Server. Redis/Google NUNCA se llaman dentro de la transacción.
        $resultado = DB::transaction(function () use ($mensajeId, $grupoId, $origen): string {
            $grupo = DB::table('mensajero_chats_monitoreados')
                ->where('id', $grupoId)->where('tipo_chat', 'grupo')
                ->where('es_activo', true)->where('es_monitoreado', true)
                ->where('es_visible_tablero', true)->lockForUpdate()->first(['id']);

            if ($grupo === null) {
                return 'grupo_no_permitido';
            }

            $mensaje = MensajeWhatsapp::query()
                ->whereKey($mensajeId)->where('chat_monitoreado_id', $grupoId)
                ->where('estado_captura', 'normalizado')->first();

            if ($mensaje === null || $mensaje->recibido_en === null
                || ! $this->politica->autoriza($grupoId, $mensaje->recibido_en)
                || ! app(ControlInterpretacionGemini::class)->permite($grupoId, $mensaje->recibido_en, true)) {
                return 'no_elegible';
            }

            if ($origen === 'conciliacion') {
                $inicio = $this->corteConciliacion();
                if ($inicio === null || $mensaje->recibido_en->getTimestamp() < $inicio->getTimestamp()) {
                    return 'fuera_del_corte';
                }

                $edad = now('UTC')->getTimestamp() - $mensaje->recibido_en->getTimestamp();
                $horas = max(1, min(24, (int) config('mensajero.ia.conciliacion_horas_maximas', 4)));
                $antiguedad = max(120, min(3600, (int) config('mensajero.ia.conciliacion_antiguedad_segundos', 180)));
                if ($edad < $antiguedad || $edad > $horas * 3600) {
                    return 'fuera_de_ventana';
                }
            }

            $contenido = $mensaje->tipo_mensaje === 'texto'
                ? $mensaje->contenido_texto : $mensaje->pie_multimedia;
            if (($mensaje->tipo_mensaje !== 'texto'
                    && ! in_array($mensaje->tipo_mensaje, ['foto', 'video', 'documento'], true))
                || trim((string) $contenido) === '') {
                return 'sin_texto';
            }

            if (DB::table('mensajero_analisis_operativos_ia')
                ->where('mensaje_whatsapp_id', $mensajeId)->exists()
                || DB::table('mensajero_control_publicaciones_gemini')
                    ->where('mensaje_whatsapp_id', $mensajeId)->exists()
                || (Schema::hasTable('mensajero_mensajes_lote_gemini')
                    && DB::table('mensajero_mensajes_lote_gemini')
                        ->where('mensaje_whatsapp_id', $mensajeId)->exists())) {
                return 'registrado';
            }

            // Cupo compartido: una reserva de lote también consume
            // una de las solicitudes máximas autorizadas del grupo.
            if (! $this->presupuesto->permiteReserva($grupoId)) {
                return 'limite_diario';
            }

            DB::table('mensajero_control_publicaciones_gemini')->insert([
                'mensaje_whatsapp_id' => $mensajeId,
                'chat_monitoreado_id' => $grupoId,
                'estado' => 'reservado',
                'origen' => $origen,
                'reservado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);

            return 'reservado';
        }, 3);

        if ($resultado !== 'reservado') {
            return $resultado;
        }

        try {
            AnalizarMensajeGeminiTrabajo::dispatch($mensajeId);
            DB::table('mensajero_control_publicaciones_gemini')
                ->where('mensaje_whatsapp_id', $mensajeId)
                ->where('estado', 'reservado')
                ->update([
                    'estado' => 'publicado',
                    'publicado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
                ]);

            return 'publicado';
        } catch (Throwable $error) {
            // Aunque Redis haya aceptado el trabajo, una excepción posterior
            // podría dejar el resultado incierto. Requiere revisión MANUAL.
            DB::table('mensajero_control_publicaciones_gemini')
                ->where('mensaje_whatsapp_id', $mensajeId)
                ->update([
                    'estado' => 'requiere_revision',
                    'error_tipo' => substr(get_class($error), 0, 120),
                ]);
            throw new RuntimeException('Publicación IA de resultado incierto; no reintentar automáticamente.', 0, $error);
        }
    }

    private function grupoPermitido(int $grupoId): bool
    {
        return app(ControlInterpretacionGemini::class)->estado($grupoId)['habilitado']
            && app(ControlInterpretacionGemini::class)->estado(0)['habilitado']
            && DB::table('mensajero_chats_monitoreados')
                ->where('id', $grupoId)->where('tipo_chat', 'grupo')
                ->where('es_activo', true)->where('es_monitoreado', true)
                ->where('es_visible_tablero', true)->exists();
    }

    private function corteConciliacion(): ?DateTimeImmutable
    {
        $valor = trim((string) config('mensajero.ia.conciliacion_fecha_inicio', ''));
        if ($valor === '') {
            return null;
        }

        $fecha = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $valor);
        return $fecha === false ? null : $fecha;
    }

    private function maximoDiario(): int
    {
        return $this->presupuesto->limiteDiario();
    }
}
