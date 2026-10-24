<?php

namespace App\Console\Commands;

use App\Jobs\AnalizarMensajeGeminiTrabajo;
use App\Models\ChatMonitoreado;
use App\Models\MensajeWhatsapp;
use App\Services\IA\PoliticaEnvioGemini;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AnalizarGemini extends Command
{
    protected $signature = 'mensajero:analizar-gemini
        {--grupo=10020 : Identificador interno del grupo autorizado}
        {--limite=2 : Máximo de mensajes nuevos a seleccionar (1-5)}
        {--mensaje-id= : Mensaje exacto a analizar (obligatorio para aplicar durante el piloto)}
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

        if ($this->option('aplicar') && $mensajeId === null) {
            $this->error('Para el piloto debes indicar --mensaje-id=ID y evitar enviar varios mensajes a Google.');
            return self::FAILURE;
        }

        $grupo = ChatMonitoreado::query()
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)
            ->where('es_activo', true)
            ->find($grupoId);

        if ($grupo === null || ! in_array($grupoId, (array) config('mensajero.ia.grupos_autorizados', []), true)) {
            $this->error('Grupo inexistente, inactivo o no incluido en la autorización de IA.');
            return self::FAILURE;
        }

        $inicio = $politica->fechaInicio();
        if ($inicio === null) {
            $this->error('Configura MENSAJERO_IA_FECHA_INICIO en formato ISO-8601 con zona horaria.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('mensajero_analisis_operativos_ia')) {
            $this->error('Falta la migración de análisis IA. Ejecuta php artisan migrate:status.');
            return self::FAILURE;
        }

        $fechaUtc = $inicio->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

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

        $mensajes = $consultaMensajes
            ->orderBy('recibido_en')->orderBy('id')
            ->limit($limite)
            ->get(['id', 'recibido_en']);

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

        foreach ($mensajes as $mensaje) {
            if (! $politica->autoriza($grupoId, $mensaje->recibido_en)) {
                continue;
            }

            AnalizarMensajeGeminiTrabajo::dispatch((int) $mensaje->id);
            $this->info('Publicado trabajo de análisis para mensaje #'.$mensaje->id);
        }

        $this->comment('Los trabajos se procesan exclusivamente con queue:work redis --queue=mensajero_ia.');
        return self::SUCCESS;
    }
}
