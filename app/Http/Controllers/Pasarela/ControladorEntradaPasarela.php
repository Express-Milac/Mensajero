<?php

namespace App\Http\Controllers\Pasarela;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Http\Controllers\Controller;
use App\Models\ConexionWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\Monitoreo\SincronizarChatsDescubiertos;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControladorEntradaPasarela extends Controller
{
    public function sincronizarChats(Request $request, SincronizarChatsDescubiertos $sincronizar): JsonResponse
    {
        $datos = $request->validate([
            'clave_conexion' => ['required', 'string', 'max:64'],
            'chats' => ['required', 'array', 'max:500'],
            'chats.*.id_chat_proveedor' => ['required', 'string', 'max:191'],
            'chats.*.tipo_chat' => ['required', 'string', 'max:32'],
            'chats.*.nombre_grupo' => ['nullable', 'string', 'max:255'],
            'chats.*.es_activo' => ['sometimes', 'boolean'],
        ]);

        $conexion = $this->conexion((string) $datos['clave_conexion']);

        if ($conexion === null) {
            return response()->json(['mensaje' => 'Conexión de WhatsApp desconocida.'], 404);
        }

        $resultado = $sincronizar->ejecutar($conexion, $datos['chats']);

        return response()->json([
            'estado' => 'aceptado',
            'resultado' => $resultado,
        ]);
    }

    public function capturarEvento(Request $request, CapturarEventoWhatsappEntrante $capturar): JsonResponse
    {
        $datos = $request->validate([
            'clave_conexion' => ['required', 'string', 'max:64'],
            'chat_id' => ['required', 'string', 'max:191'],
            'tipo_evento' => ['required', 'string', 'max:64'],
            'tipo_mensaje' => ['required', 'string', 'max:64'],
            'evento_externo_id' => ['nullable', 'string', 'max:191'],
            'mensaje_proveedor_id' => ['nullable', 'string', 'max:191'],
            'contenido_crudo' => ['required', 'string'],
            'recibido_en' => ['required', 'date'],
            'version_contrato' => ['sometimes', 'string', 'max:32'],
        ]);

        $conexion = $this->conexion((string) $datos['clave_conexion']);

        if ($conexion === null) {
            return response()->json(['mensaje' => 'Conexión de WhatsApp desconocida.'], 404);
        }

        $resultado = $capturar->ejecutar(new EventoWhatsappEntrante(
            conexionId: (int) $conexion->id,
            chatId: (string) $datos['chat_id'],
            tipoEvento: (string) $datos['tipo_evento'],
            tipoMensaje: (string) $datos['tipo_mensaje'],
            eventoExternoId: $datos['evento_externo_id'] ?? null,
            mensajeProveedorId: $datos['mensaje_proveedor_id'] ?? null,
            contenidoCrudo: (string) $datos['contenido_crudo'],
            recibidoEn: CarbonImmutable::parse((string) $datos['recibido_en']),
            versionContrato: (string) ($datos['version_contrato'] ?? 'v1'),
        ));

        return response()->json([
            'estado' => $resultado->estado,
            'disposicion' => $resultado->disposicion->value,
            'evento_crudo_id' => $resultado->eventoCrudoId,
        ]);
    }

    private function conexion(string $clave): ?ConexionWhatsapp
    {
        return ConexionWhatsapp::query()->where('clave_conexion', $clave)->first();
    }
}
