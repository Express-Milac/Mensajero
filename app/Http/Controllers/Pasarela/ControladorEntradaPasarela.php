<?php

namespace App\Http\Controllers\Pasarela;

use App\Data\WhatsApp\ArchivoWhatsappEntrante;
use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Http\Controllers\Controller;
use App\Models\ConexionWhatsapp;
use App\Models\PeriodoIntegridadWhatsapp;
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

    public function chatsAutorizados(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'clave_conexion' => ['required', 'string', 'max:64'],
        ]);

        $conexion = $this->conexion((string) $datos['clave_conexion']);

        if ($conexion === null) {
            return response()->json(['mensaje' => 'Conexión de WhatsApp desconocida.'], 404);
        }

        $chats = $conexion->chats()
            ->where('es_monitoreado', true)
            ->where('es_visible_tablero', true)
            ->where('es_activo', true)
            ->orderBy('id')
            ->get(['id_chat_proveedor', 'tipo_chat', 'nombre_grupo', 'version_autorizacion'])
            ->map(fn ($chat) => [
                'id_chat_proveedor' => $chat->id_chat_proveedor,
                'tipo_chat' => $chat->tipo_chat,
                'nombre_grupo' => $chat->nombre_grupo,
                'version_autorizacion' => (int) $chat->version_autorizacion,
            ])
            ->values();

        return response()->json([
            'estado' => 'aceptado',
            'chats' => $chats,
        ]);
    }

    public function actualizarEstado(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'clave_conexion' => ['required', 'string', 'max:64'],
            'estado' => ['required', 'string', 'in:conectando,requiere_vinculacion,conectado,desconectado,error'],
            'id_cuenta_externa' => ['nullable', 'string', 'max:191'],
            'nombre_visible' => ['nullable', 'string', 'max:255'],
            'metadatos' => ['sometimes', 'array'],
        ]);

        $conexion = $this->conexion((string) $datos['clave_conexion']);

        if ($conexion === null) {
            return response()->json(['mensaje' => 'Conexión de WhatsApp desconocida.'], 404);
        }

        $ahora = now('UTC');
        $conexion->estado = (string) $datos['estado'];

        if (array_key_exists('id_cuenta_externa', $datos)) {
            $conexion->id_cuenta_externa = $datos['id_cuenta_externa'];
        }

        if (array_key_exists('nombre_visible', $datos)) {
            $conexion->nombre_visible = $datos['nombre_visible'];
        }

        if (array_key_exists('metadatos', $datos)) {
            $conexion->metadatos = array_merge($conexion->metadatos ?? [], $datos['metadatos']);
        }

        if ($conexion->estado === 'conectado') {
            if ($conexion->conectado_en === null) {
                $conexion->conectado_en = $ahora;
            }

            $conexion->ultimo_latido_en = $ahora;
        }

        if (in_array($conexion->estado, ['desconectado', 'error'], true)) {
            $conexion->ultima_desconexion_en = $ahora;
        }

        $conexion->save();

        return response()->json([
            'estado' => 'aceptado',
            'conexion' => [
                'id' => (int) $conexion->id,
                'clave_conexion' => $conexion->clave_conexion,
                'estado' => $conexion->estado,
            ],
        ]);
    }

    /**
     * Latido efímero firmado: no altera el estado del proveedor y no requiere
     * almacenar una solicitud de deduplicación por cada intervalo.
     */
    public function registrarLatido(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'clave_conexion' => ['required', 'string', 'max:64'],
        ]);

        $conexion = $this->conexion((string) $datos['clave_conexion']);

        if ($conexion === null) {
            return response()->json(['mensaje' => 'Conexión de WhatsApp desconocida.'], 404);
        }

        if ($conexion->estado !== 'conectado') {
            return response()->json([
                'estado' => 'ignorado',
                'motivo' => 'conexion_no_conectada',
            ]);
        }

        $conexion->ultimo_latido_en = now('UTC');
        $conexion->save();

        return response()->json(['estado' => 'aceptado']);
    }

    public function registrarIntegridad(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'clave_conexion' => ['required', 'string', 'max:64'],
            'identificador_periodo' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'inicio_en' => ['required', 'date'],
            'fin_en' => ['required', 'date', 'after_or_equal:inicio_en'],
            'causa' => ['required', 'string', 'max:64'],
            'detalles' => ['sometimes', 'array'],
        ]);

        $conexion = $this->conexion((string) $datos['clave_conexion']);

        if ($conexion === null) {
            return response()->json(['mensaje' => 'Conexión de WhatsApp desconocida.'], 404);
        }

        $periodo = PeriodoIntegridadWhatsapp::query()->firstOrCreate(
            [
                'conexion_id' => $conexion->id,
                'identificador_periodo' => $datos['identificador_periodo'],
            ],
            [
                'inicio_en' => CarbonImmutable::parse((string) $datos['inicio_en']),
                'fin_en' => CarbonImmutable::parse((string) $datos['fin_en']),
                'causa' => $datos['causa'],
                'detalles' => $datos['detalles'] ?? null,
            ],
        );

        return response()->json([
            'estado' => 'aceptado',
            'periodo_integridad_id' => (int) $periodo->id,
            'existia' => ! $periodo->wasRecentlyCreated,
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
            'id_remitente_proveedor' => ['nullable', 'string', 'max:191'],
            'nombre_remitente' => ['nullable', 'string', 'max:120'],
            'fuente_nombre_remitente' => ['nullable', 'string', 'in:contacto,perfil'],
            'telefono_remitente' => ['nullable', 'string', 'regex:/^[0-9]{8,15}$/'],
            'id_mensaje_citado_proveedor' => ['nullable', 'string', 'max:191'],
            'id_mensaje_objetivo_proveedor' => ['nullable', 'string', 'max:191'],
            'contenido_crudo' => ['required', 'string'],
            'recibido_en' => ['required', 'date'],
            'version_contrato' => ['sometimes', 'string', 'max:32'],
            'multimedia' => ['nullable', 'array'],
            'multimedia.identificador_archivo' => ['required_with:multimedia', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'multimedia.ruta_relativa' => ['required_with:multimedia', 'string', 'max:1024'],
            'multimedia.hash_sha256' => ['required_with:multimedia', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'multimedia.tamano_bytes' => ['required_with:multimedia', 'integer', 'min:0'],
            'multimedia.tipo_mime' => ['nullable', 'string', 'max:191'],
            'multimedia.nombre_original' => ['nullable', 'string', 'max:255'],
            'multimedia.extension' => ['nullable', 'string', 'max:16', 'regex:/^[a-z0-9]{1,16}$/'],
        ]);

        $conexion = $this->conexion((string) $datos['clave_conexion']);

        if ($conexion === null) {
            return response()->json(['mensaje' => 'Conexión de WhatsApp desconocida.'], 404);
        }

        $archivo = null;

        if (isset($datos['multimedia']) && is_array($datos['multimedia'])) {
            $multimedia = $datos['multimedia'];
            $archivo = new ArchivoWhatsappEntrante(
                identificadorArchivo: (string) $multimedia['identificador_archivo'],
                rutaRelativa: (string) $multimedia['ruta_relativa'],
                hashSha256: (string) $multimedia['hash_sha256'],
                tamanoBytes: (int) $multimedia['tamano_bytes'],
                tipoMime: isset($multimedia['tipo_mime']) ? (string) $multimedia['tipo_mime'] : null,
                nombreOriginal: isset($multimedia['nombre_original']) ? (string) $multimedia['nombre_original'] : null,
                extension: isset($multimedia['extension']) ? (string) $multimedia['extension'] : null,
            );
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
            archivo: $archivo,
            remitenteProveedorId: $datos['id_remitente_proveedor'] ?? null,
            nombreRemitente: $datos['nombre_remitente'] ?? null,
            fuenteNombreRemitente: $datos['fuente_nombre_remitente'] ?? null,
            telefonoRemitente: $datos['telefono_remitente'] ?? null,
            mensajeCitadoProveedorId: $datos['id_mensaje_citado_proveedor'] ?? null,
            mensajeObjetivoProveedorId: $datos['id_mensaje_objetivo_proveedor'] ?? null,
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
