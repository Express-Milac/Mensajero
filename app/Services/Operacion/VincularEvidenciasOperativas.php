<?php

namespace App\Services\Operacion;

use App\Models\MensajeWhatsapp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sugerencias de adjuntos, NO corroboraciones del contenido fotográfico.
 * Jamás fusiona o modifica los mensajes crudos.
 */
final class VincularEvidenciasOperativas
{
    private const VENTANA_SEGUNDOS = 90;

    public function alNormalizar(int $mensajeId): void
    {
        if (! Schema::hasTable('mensajero_acontecimientos_operativos')) {
            return;
        }

        $mensaje = MensajeWhatsapp::query()->find($mensajeId);
        if ($mensaje !== null && in_array($mensaje->tipo_mensaje, ['foto', 'video', 'audio', 'documento'], true)) {
            $this->buscarVinculo($mensaje);
        }
    }

    public function alCrear(int $acontecimientoId): void
    {
        if (! Schema::hasTable('mensajero_evidencias_acontecimientos')) {
            return;
        }

        $acontecimiento = DB::table('mensajero_acontecimientos_operativos')->find($acontecimientoId);
        if ($acontecimiento === null) {
            return;
        }

        $origen = MensajeWhatsapp::query()->find($acontecimiento->mensaje_objetivo_id);
        if ($origen === null || $origen->contacto_remitente_id === null || $origen->recibido_en === null) {
            return;
        }

        $inicio = $origen->recibido_en->copy()->subSeconds(self::VENTANA_SEGUNDOS)->format('Y-m-d H:i:s.u');
        $fin = $origen->recibido_en->copy()->addSeconds(self::VENTANA_SEGUNDOS)->format('Y-m-d H:i:s.u');

        $adjuntos = MensajeWhatsapp::query()
            ->where('chat_monitoreado_id', $origen->chat_monitoreado_id)
            ->where('contacto_remitente_id', $origen->contacto_remitente_id)
            ->where('estado_captura', 'normalizado')
            ->whereIn('tipo_mensaje', ['foto', 'video', 'audio', 'documento'])
            ->whereBetween('recibido_en', [$inicio, $fin])
            ->where('id', '!=', $origen->id)
            ->orderBy('id')->limit(30)->get();

        foreach ($adjuntos as $adjunto) {
            $this->buscarVinculo($adjunto);
        }
    }

    private function buscarVinculo(MensajeWhatsapp $adjunto): void
    {
        if ($adjunto->contacto_remitente_id === null || $adjunto->recibido_en === null) {
            return;
        }

        $inicio = $adjunto->recibido_en->copy()->subSeconds(self::VENTANA_SEGUNDOS)->format('Y-m-d H:i:s.u');
        $fin = $adjunto->recibido_en->copy()->addSeconds(self::VENTANA_SEGUNDOS)->format('Y-m-d H:i:s.u');

        // Con dos acontecimientos próximos del mismo remitente NO adivinar
        // cuál corresponde al archivo: preferir omitir a vincular mal.
        $coincidencias = DB::table('mensajero_acontecimientos_operativos as a')
            ->join('mensajero_mensajes_whatsapp as origen', 'origen.id', '=', 'a.mensaje_objetivo_id')
            ->join('mensajero_chats_monitoreados as grupo', 'grupo.id', '=', 'a.chat_monitoreado_id')
            ->where('a.chat_monitoreado_id', $adjunto->chat_monitoreado_id)
            ->whereIn('a.estado', ['generado_ia', 'pendiente_revision'])
            ->where('origen.contacto_remitente_id', $adjunto->contacto_remitente_id)
            ->whereBetween('origen.recibido_en', [$inicio, $fin])
            ->where('grupo.es_activo', true)
            ->where('grupo.es_monitoreado', true)
            ->where('grupo.es_visible_tablero', true)
            ->limit(2)->pluck('a.id');

        if ($coincidencias->count() !== 1) {
            // Si aparece un segundo acontecimiento cercano, retirar
            // asociaciones tentativas previas que ya serían ambiguas.
            if ($coincidencias->count() > 1) {
                DB::table('mensajero_evidencias_acontecimientos')
                    ->where('mensaje_whatsapp_id', $adjunto->id)
                    ->where('vinculo', 'posible_adjunta')
                    ->whereIn('acontecimiento_id', $coincidencias->all())
                    ->delete();
            }
            return;
        }

        $acontecimientoId = (int) $coincidencias->first();

        // El mensaje que dio origen a la interpretación ya fue añadido
        // como sustento, y nunca puede degradarse a "posible adjunta".
        $tabla = DB::table('mensajero_evidencias_acontecimientos');
        $clave = ['acontecimiento_id' => $acontecimientoId, 'mensaje_whatsapp_id' => (int) $adjunto->id];
        if ((clone $tabla)->where($clave)->exists()) {
            return;
        }

        try {
            $tabla->insert($clave + [
                'vinculo' => 'posible_adjunta',
                'creado_en' => now('UTC')->format('Y-m-d H:i:s.u'),
            ]);
        } catch (QueryException $error) {
            if (! in_array((int) ($error->errorInfo[1] ?? 0), [2601, 2627], true)) {
                throw $error;
            }
        }
    }
}
