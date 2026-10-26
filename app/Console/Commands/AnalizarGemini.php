<?php

namespace App\Console\Commands;

use App\Jobs\AnalizarMensajeGeminiTrabajo;
use App\Models\ChatMonitoreado;
use App\Models\MensajeWhatsapp;
use App\Services\IA\PoliticaEnvioGemini;
use App\Services\IA\ConciliarPublicacionesGemini;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AnalizarGemini extends Command
{
    protected $signature = 'mensajero:analizar-gemini
        {--grupo=10020 : Identificador interno del grupo autorizado}
        {--limite=2 : Máximo de mensajes nuevos a seleccionar (1-5)}
        {--mensaje-id= : Mensaje exacto a analizar (obligatorio para aplicar durante el piloto normal)}
        {--lote-real : Seleccionar hasta cinco mensajes REALES existentes del grupo autorizado, con corte propio}
        {--aplicar : Publicar trabajos en Redis; sin esta opción solo previsualiza}';

    protected $description = 'Previsualiza o solicita interpretación Gemini de mensajes nuevos en un grupo permitido.';

    public function handle(PoliticaEnvioGemini $politica): int
    {
        $grupoId = filter_var($this->option('grupo'), FILTER_VALIDATE_INT);
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT);
        $mensajeIdSolicitado = $this->option('mensaje-id');
        $mensajeId = $mensajeIdSolicitado === null ? null : filter_var($mensajeIdSolicitado, FILTER_VALIDATE_INT);

        if ($grupoId === false || $grupoId < 1 ||
            $limite === false || $limite < 1 || $limite > 5 ||
            ($mensajeIdSolicitado !== null && ($mensajeId === false || $mensajeId < 1))) {
            $this->error('El grupo debe ser un entero y el límite debe estar entre 1 y 5.');
            return self::FAILURE;
        }

        $loteReal = (bool) $this->option('lote-real');
        if ($this->option('aplicar') && $mensajeId === null && ! $loteReal) {
            $this->error('Para el piloto normal usa --mensaje-id=ID; para un lote real autorizado indica --lote-real.');
            return self::FAILURE;
        }

        if ($loteReal && $mensajeId !== null) {
            $this->error('Selecciona --lote-real o --mensaje-id, pero no ambos.');
            return self::FAILURE;
        }

        // La fecha individual ahora se asigna al activar el grupo desde UI.
        // La publicación manual continúa protegida por la reserva SQL.
        $grupo = ChatMonitoreado::query()
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)
            ->where('es_activo', true)
            ->find($grupoId);

        if ($grupo === null || ! app(\App\Services\IA\ControlInterpretacionGemini::class)->estado($grupoId)['habilitado']) {
            $this->error('Grupo inexistente, inactivo o no habilitado desde Control de Gemini.');
            return self::FAILURE;
        }

        $inicio = $politica->fechaInicioGrupo($grupoId);
        if ($inicio === null) {
            $this->error('Configura MENSAJERO_IA_FECHA_INICIO en formato ISO-8601 con zona horaria.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('mensajero_analisis_operativos_ia')) {
            $this->error('Falta la migración de análisis IA. Ejecuta php artisan migrate:status.');
            return self::FAILURE;
        }

        $corteUi = app(\App\Services\IA\ControlInterpretacionGemini::class)->corteEfectivoGrupo($grupoId);
        if ($corteUi === null) {
            $this->error('Interpretación general o individual apagada. Actívala desde Control de Gemini.');
            return self::FAILURE;
        }
        $fechaUtc = max(
            $inicio->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            $corteUi->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        );

        $consultaMensajes = MensajeWhatsapp::query()
            ->where('chat_monitoreado_id', $grupoId)
            ->where('estado_captura', 'normalizado')
            ->where(function ($consulta): void {
                $consulta->where(function ($texto): void {
                    $texto->where('tipo_mensaje', 'texto')
                        ->whereNotNull('contenido_texto');
                })->orWhere(function ($leyenda): void {
                    $leyenda->whereIn('tipo_mensaje', ['foto', 'video', 'documento'])
                        ->whereNotNull('pie_multimedia');
                });
            })
            ->where('recibido_en', '>=', $fechaUtc)
            ->whereNotIn(
                'id',
                DB::table('mensajero_analisis_operativos_ia')->select('mensaje_whatsapp_id')
            );

        if ($mensajeId !== null) {
            $consultaMensajes->whereKey($mensajeId);
        }

        if ($loteReal) {
            $consultaMensajes->where('recibido_en', '>=', now('UTC')->subDay()->format('Y-m-d H:i:s.u'));
        }

        if ($loteReal && Schema::hasTable('mensajero_control_publicaciones_gemini')) {
            $consultaMensajes->whereNotExists(function ($consulta): void {
                $consulta->selectRaw('1')
                    ->from('mensajero_control_publicaciones_gemini as p')
                    ->whereColumn('p.mensaje_whatsapp_id', 'mensajero_mensajes_whatsapp.id');
            });
        }

        // Con lote real se trabaja con los últimos mensajes capturados;
        // el procesamiento de esos ID se muestra luego en orden cronológico.
        $consultaMensajes = $loteReal
            ? $consultaMensajes->orderByDesc('recibido_en')->orderByDesc('id')
            : $consultaMensajes->orderBy('recibido_en')->orderBy('id');

        $mensajes = $consultaMensajes
            ->limit($limite)
            ->get(['id', 'recibido_en']);
        if ($loteReal) {
            $mensajes = $mensajes->reverse()->values();
        }

        $this->info('Grupo: '.$grupoId.' | Mensajes nuevos elegibles: '.$mensajes->count());
        foreach ($mensajes as $mensaje) {
            $this->line('Mensaje interno #'.$mensaje->id.' | '.$mensaje->recibido_en?->format('Y-m-d H:i:s').' UTC');
        }

        if (! $this->option('aplicar')) {
            $this->comment('Simulación: no se enviaron datos a Google ni trabajos a Redis.');
            return self::SUCCESS;
        }

        if (! $politica->configuracionCompleta()) {
            $this->error('IA no autorizada. Revisa habilitación, consentimiento, servicio pagado y clave.');
            return self::FAILURE;
        }

        if ($loteReal && $mensajes->isNotEmpty()) {
            $this->warn('Esto enviará contenido REAL del grupo '.$grupoId.' a Google mediante Redis.');
            $this->warn('Máximo en esta ejecución: '.$mensajes->count().' mensajes. Su contexto puede incluir hasta 6 textos del mismo grupo.');
            $respuesta = trim((string) $this->ask('Escribe AUTORIZO LOTE REAL para publicar los ID mostrados'));
            if ($respuesta !== 'AUTORIZO LOTE REAL') {
                $this->warn('Cancelado: ningún trabajo publicado.');
                return self::FAILURE;
            }
        }

        foreach ($mensajes as $mensaje) {
            if (! $politica->autoriza($grupoId, $mensaje->recibido_en)) {
                continue;
            }

            if (Schema::hasTable('mensajero_control_publicaciones_gemini')) {
                $estado = app(ConciliarPublicacionesGemini::class)
                    ->publicarManual((int) $mensaje->id, $grupoId);
                $this->info('Mensaje #'.$mensaje->id.' | Control de publicación: '.$estado);
            } else {
                // Compatibilidad con instalaciones aún sin migración nueva.
                AnalizarMensajeGeminiTrabajo::dispatch((int) $mensaje->id);
                $this->info('Publicado trabajo de análisis para mensaje #'.$mensaje->id);
            }
        }

        $this->comment('Los trabajos se procesan exclusivamente con queue:work redis --queue=mensajero_ia.');
        return self::SUCCESS;
    }
}
