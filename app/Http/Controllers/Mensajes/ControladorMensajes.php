<?php

namespace App\Http\Controllers\Mensajes;

use App\Http\Controllers\Controller;
use App\Models\ChatMonitoreado;
use App\Models\EventoCrudoWhatsapp;
use App\Models\MensajeWhatsapp;
use App\Models\PeriodoIntegridadWhatsapp;
use App\Services\Normalizacion\ExtraerContenidoWhatsapp;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;

final class ControladorMensajes extends Controller
{
    public function indice(): View
    {
        $grupos = ChatMonitoreado::query()
            ->with('conexion')
            ->withCount('eventosCrudos')
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_activo', true)
            ->orderByDesc('eventos_crudos_count')
            ->orderBy('id')
            ->get();

        $identificadores = $grupos->pluck('id');
        $conexiones = $grupos->pluck('conexion_id')->unique();

        return view('mensajes.indice', [
            'grupos' => $grupos,
            'cantidadEventos' => (int) $grupos->sum('eventos_crudos_count'),
            'cantidadNormalizados' => $identificadores->isEmpty()
                ? 0
                : MensajeWhatsapp::query()->whereIn('chat_monitoreado_id', $identificadores)->count(),
            'cantidadPeriodos' => $conexiones->isEmpty()
                ? 0
                : PeriodoIntegridadWhatsapp::query()->whereIn('conexion_id', $conexiones)->count(),
        ]);
    }

    public function grupo(
        Request $request,
        int $chat,
        ExtraerContenidoWhatsapp $extraer,
    ): View {
        $grupo = ChatMonitoreado::query()
            ->with('conexion')
            ->where('tipo_chat', 'grupo')
            ->where('es_monitoreado', true)
            ->where('es_activo', true)
            ->findOrFail($chat);

        $datos = $request->validate([
            'tipo' => ['nullable', 'in:mensaje,edicion,eliminacion,reaccion,otro'],
        ]);
        $tipo = $datos['tipo'] ?? '';

        $consulta = EventoCrudoWhatsapp::query()
            ->with(['mensaje.remitente', 'archivo'])
            ->where('chat_monitoreado_id', $grupo->id);

        if ($tipo !== '') {
            $consulta->where('tipo_evento', $tipo);
        }

        $eventos = $consulta
            ->orderByDesc('recibido_en')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $eventos->getCollection()->transform(fn (EventoCrudoWhatsapp $evento) => [
            'evento' => $evento,
            'resumen' => $this->resumen($evento, $extraer),
            'remitente' => $this->etiquetaRemitente($evento),
        ]);

        $periodosConexion = PeriodoIntegridadWhatsapp::query()
            ->where('conexion_id', $grupo->conexion_id);

        $totalPeriodos = (clone $periodosConexion)->count();
        $periodos = $periodosConexion
            ->orderByDesc('inicio_en')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('mensajes.grupo', compact('grupo', 'eventos', 'periodos', 'totalPeriodos', 'tipo'));
    }

    public function evento(
        int $evento,
        ExtraerContenidoWhatsapp $extraer,
    ): View {
        $registro = EventoCrudoWhatsapp::query()
            ->with(['chat', 'mensaje.remitente', 'archivo'])
            ->whereHas('chat', function ($consulta) {
                $consulta->where('tipo_chat', 'grupo')
                    ->where('es_monitoreado', true)
                    ->where('es_activo', true);
            })
            ->findOrFail($evento);

        $resumen = $this->resumen($registro, $extraer);
        $contenido = $registro->contenido;
        $truncado = mb_strlen($contenido) > 100_000;

        if ($truncado) {
            $contenido = mb_substr($contenido, 0, 100_000);
        }

        // Solo presentación: el contenido íntegro permanece en SQL Server.
        $json = json_decode($contenido, true);
        if (! $truncado && json_last_error() === JSON_ERROR_NONE) {
            $contenido = json_encode(
                $json,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ) ?: $contenido;
        }

        return view('mensajes.evento', compact('registro', 'resumen', 'contenido', 'truncado'));
    }

    private function resumen(EventoCrudoWhatsapp $evento, ExtraerContenidoWhatsapp $extraer): string
    {
        if ($evento->tipo_evento !== 'mensaje') {
            return match ($evento->tipo_evento) {
                'edicion' => 'Edición registrada. El mensaje original no ha sido reemplazado.',
                'eliminacion' => 'Eliminación registrada como evidencia.',
                'reaccion' => 'Reacción registrada como evidencia.',
                default => 'Evento registrado sin interpretación adicional.',
            };
        }

        if ($evento->mensaje !== null) {
            $texto = $evento->mensaje->contenido_texto ?: $evento->mensaje->pie_multimedia;
            if (is_string($texto) && $texto !== '') {
                return $texto;
            }
        }

        try {
            $datos = $extraer->ejecutar($evento->contenido);
            $texto = $datos['texto'] ?: $datos['pie_multimedia'];

            if (is_string($texto) && $texto !== '') {
                return $texto;
            }
        } catch (JsonException) {
            return 'Contenido no interpretable; se conserva la evidencia original.';
        }

        return match ($evento->tipo_mensaje) {
            'foto' => 'Fotografía recibida.',
            'video' => 'Video recibido.',
            'audio' => 'Audio recibido.',
            'documento' => 'Documento recibido.',
            'ubicacion' => 'Ubicación compartida.',
            'contacto' => 'Contacto compartido.',
            default => 'Mensaje recibido sin texto visible.',
        };
    }

    private function etiquetaRemitente(EventoCrudoWhatsapp $evento): string
    {
        $contacto = $evento->mensaje?->remitente;
        $nombre = $contacto?->nombre_visible ?: $contacto?->nombre_notificacion;

        if (is_string($nombre) && $nombre !== '') {
            return $nombre;
        }

        $id = $evento->id_remitente_proveedor;

        return is_string($id) && $id !== ''
            ? 'Remitente '.strtoupper(substr(hash('sha256', $id), 0, 8))
            : 'Remitente no identificado';
    }
}
