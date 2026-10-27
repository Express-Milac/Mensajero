<?php

namespace App\Console\Commands;

use App\Models\MensajeWhatsapp;
use App\Services\IA\ConstruirDialogoGemini;
use App\Services\IA\PoliticaEnvioGemini;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Explica por qué una interpretación fue omitida, reproduciendo las condiciones
 * del procesador sin transmitir texto, consultar Google ni modificar SQL Server.
 */
final class DiagnosticarLoteGemini extends Command
{
    protected $signature = 'mensajero:diagnosticar-lote-gemini
        {--grupo=10197 : Identificador interno del grupo}
        {--ids= : Hasta cinco identificadores internos separados por coma}';

    protected $description = 'Audita elegibilidad, contexto, reserva y configuración para IDs reales, solo lectura.';

    public function handle(PoliticaEnvioGemini $politica, ConstruirDialogoGemini $constructor): int
    {
        $grupoId = filter_var($this->option('grupo'), FILTER_VALIDATE_INT);
        $cadena = trim((string) $this->option('ids'));
        if ($grupoId === false || $grupoId < 1
            || ! preg_match('/\A[1-9][0-9]*(?:,[1-9][0-9]*){0,4}\z/D', $cadena)) {
            $this->error('Indica un grupo positivo y 1-5 IDs positivos separados por comas.');
            return self::FAILURE;
        }

        $ids = array_map('intval', explode(',', $cadena));
        if (count($ids) !== count(array_unique($ids))) {
            $this->error('No se admiten IDs repetidos.');
            return self::FAILURE;
        }

        $inicio = $politica->fechaInicioGrupo($grupoId);
        $politicaCompleta = $politica->configuracionCompleta();

        $this->info('Diagnóstico local de elegibilidad Gemini (solo lectura).');
        $this->line('Grupo: '.$grupoId);
        $this->line('Política completa: '.($politicaCompleta ? 'SI' : 'NO'));
        $control = app(\App\Services\IA\ControlInterpretacionGemini::class);
        $this->line('Grupo habilitado en panel: '.($control->estado($grupoId)['habilitado'] ? 'SI' : 'NO'));
        $this->line('Contexto conversacional autorizado: '.(config('mensajero.ia.solo_mensaje_objetivo', true) === false ? 'SI' : 'NO'));
        $this->line('Corte efectivo: '.($inicio !== null ? $inicio->format(\DateTimeInterface::ATOM) : 'NO VALIDO'));
        $this->warn('Este comando comprueba la configuración de esta ejecución; no puede reconstruir la configuración anterior del trabajador Redis.');

        try {
            foreach ($ids as $id) {
                $mensaje = MensajeWhatsapp::query()->with('chat')->find($id);

                if ($mensaje === null || (int) $mensaje->chat_monitoreado_id !== $grupoId) {
                    $this->warn('Mensaje #'.$id.': no encontrado o pertenece a otro grupo.');
                    continue;
                }

                $chat = $mensaje->chat;
                $chatPermitido = $chat !== null && $chat->tipo_chat === 'grupo'
                    && $chat->es_activo && $chat->es_monitoreado && $chat->es_visible_tablero;
                $normalizado = $mensaje->estado_captura === 'normalizado';
                $texto = $mensaje->tipo_mensaje === 'texto' ? $mensaje->contenido_texto : $mensaje->pie_multimedia;
                $tipoPermitido = $mensaje->tipo_mensaje === 'texto'
                    || in_array($mensaje->tipo_mensaje, ['foto', 'video', 'documento'], true);
                $tieneTexto = $tipoPermitido && trim((string) $texto) !== '';
                $permiso = $mensaje->recibido_en !== null
                    && $politica->autoriza($grupoId, $mensaje->recibido_en)
                    && $control->permite($grupoId, $mensaje->recibido_en);

                $estadoPublicacion = Schema::hasTable('mensajero_control_publicaciones_gemini')
                    ? (string) (DB::table('mensajero_control_publicaciones_gemini')
                        ->where('mensaje_whatsapp_id', $id)->value('estado') ?? 'sin_registro')
                    : 'sin_tabla';
                $motivoRegistrado = Schema::hasTable('mensajero_control_publicaciones_gemini')
                    ? (string) (DB::table('mensajero_control_publicaciones_gemini')
                        ->where('mensaje_whatsapp_id', $id)->value('error_tipo') ?? 'no_registrado')
                    : 'sin_tabla';
                $resultadoExiste = Schema::hasTable('mensajero_analisis_operativos_ia')
                    && DB::table('mensajero_analisis_operativos_ia')
                        ->where('mensaje_whatsapp_id', $id)->exists();

                $contextoValido = false;
                if ($chatPermitido && $normalizado && $tieneTexto && $permiso) {
                    $consultaContexto = MensajeWhatsapp::query()
                        ->where('chat_monitoreado_id', $grupoId)
                        ->where('estado_captura', 'normalizado')
                        ->where(function ($consulta): void {
                            $consulta->where('tipo_mensaje', 'texto')
                                ->orWhere(function ($media): void {
                                    $media->whereIn('tipo_mensaje', ['foto', 'video', 'documento'])
                                        ->whereNotNull('pie_multimedia');
                                });
                        });

                    if (config('mensajero.ia.solo_mensaje_objetivo', true) !== false) {
                        $consultaContexto->whereKey($id);
                    } else {
                        $corteUi = $control->corteEfectivoGrupo($grupoId);
                        $fechaInicioUtc = max(
                            $inicio->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
                            $corteUi->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
                        );
                        $fechaUtc = $mensaje->recibido_en->utc()->format('Y-m-d H:i:s.u');
                        $inicioVentana = $mensaje->recibido_en->copy()->subMinutes(5)->format('Y-m-d H:i:s.u');
                        $consultaContexto->where('recibido_en', '>=', max($fechaInicioUtc, $inicioVentana))
                            ->where(function ($consulta) use ($fechaUtc, $id): void {
                                $consulta->where('recibido_en', '<', $fechaUtc)
                                    ->orWhere(function ($empate) use ($fechaUtc, $id): void {
                                        $empate->where('recibido_en', '=', $fechaUtc)->where('id', '<=', $id);
                                    });
                            });
                    }

                    $conversacion = $consultaContexto->orderByDesc('recibido_en')
                        ->orderByDesc('id')->limit(6)
                        ->get(['id', 'tipo_mensaje', 'contenido_texto', 'pie_multimedia', 'contacto_remitente_id', 'mensaje_citado_id']);

                    $contexto = $constructor->ejecutar($conversacion->reverse()->all());
                    $contextoValido = $contexto !== [] && $contexto[array_key_last($contexto)]['id'] === $id;
                }

                $razonActual = ! $chatPermitido ? 'chat_no_habilitado'
                    : (! $normalizado ? 'mensaje_no_normalizado'
                        : (! $tieneTexto ? 'sin_texto_util'
                            : (! $permiso ? 'politica_deniega'
                                : (! $contextoValido ? 'sin_texto_contexto' : 'elegible_en_este_proceso'))));

                $this->table(['Campo', 'Valor'], [
                    ['ID', (string) $id],
                    ['Tipo', (string) $mensaje->tipo_mensaje],
                    ['Recibido UTC', (string) $mensaje->recibido_en],
                    ['Chat habilitado', $chatPermitido ? 'SI' : 'NO'],
                    ['Normalizado', $normalizado ? 'SI' : 'NO'],
                    ['Texto o leyenda no vacío', $tieneTexto ? 'SI' : 'NO'],
                    ['Autorizado por política actual', $permiso ? 'SI' : 'NO'],
                    ['Objetivo incluido en contexto', $contextoValido ? 'SI' : 'NO'],
                    ['Resultado Gemini guardado', $resultadoExiste ? 'SI' : 'NO'],
                    ['Control publicación', $estadoPublicacion],
                    ['Motivo guardado', $motivoRegistrado],
                    ['Diagnóstico actual', $razonActual],
                ]);
            }
        } catch (Throwable $error) {
            $this->error('Error al consultar: '.class_basename($error));
            return self::FAILURE;
        }

        $this->comment('No se mostraron contenidos de mensajes, no se publicaron trabajos y no se contactó a Google.');
        return self::SUCCESS;
    }
}
