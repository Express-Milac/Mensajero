<?php

namespace App\Http\Controllers\Meta;

use App\Data\WhatsApp\EventoWhatsappEntrante;
use App\Http\Controllers\Controller;
use App\Models\ConexionWhatsapp;
use App\Services\Capture\CapturarEventoWhatsappEntrante;
use App\Services\Monitoreo\SincronizarChatsDescubiertos;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ControladorWebhookWhatsapp extends Controller
{
    public function verificar(Request $request): Response
    {
        $tokenEsperado = (string) config('mensajero.meta_whatsapp.token_verificacion', '');

        if (strlen($tokenEsperado) < 16) {
            return response('Webhook oficial de Meta no configurado.', 503)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $modo = $this->parametroHub($request, 'hub.mode');
        $token = $this->parametroHub($request, 'hub.verify_token');
        $reto = $this->parametroHub($request, 'hub.challenge');

        if ($modo !== 'subscribe' || ! hash_equals($tokenEsperado, $token)) {
            return response('', 403);
        }

        return response($reto, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function recibir(
        Request $request,
        CapturarEventoWhatsappEntrante $capturar,
        SincronizarChatsDescubiertos $sincronizar) : JsonResponse {
        $carga = $request->json()->all();

        if (($carga['object'] ?? null) !== 'whatsapp_business_account') {
            return response()->json([
                'estado' => 'ignorado',
                'motivo' => 'objeto_no_soportado',
            ]);
        }

        $resultado = [
            'aceptados' => 0,
            'duplicados' => 0,
            'ignorados' => 0,
            'grupos_sincronizados' => 0,
            'conexiones_desconocidas' => 0,
        ];

        foreach ($carga['entry'] ?? [] as $entrada) {
            if (! is_array($entrada)) {
                continue;
            }

            foreach ($entrada['changes'] ?? [] as $cambio) {
                if (! is_array($cambio) || ! is_array($cambio['value'] ?? null)) {
                    continue;
                }

                $valor = $cambio['value'];
                $campo = (string) ($cambio['field'] ?? '');
                $conexion = $this->conexionDesdeValor($valor);

                if ($conexion === null) {
                    $resultado['conexiones_desconocidas']++;

                    continue;
                }

                if (str_starts_with($campo, 'group_')) {
                    $resultado['grupos_sincronizados'] += $this->sincronizarGrupos(
                        $conexion,
                        $valor['groups'] ?? [],
                        $sincronizar,
                    );

                    continue;
                }

                if ($campo !== 'messages') {
                    continue;
                }

                $resultado['grupos_sincronizados'] += $this->sincronizarGrupos(
                    $conexion,
                    $valor['groups'] ?? [],
                    $sincronizar,
                );

                foreach ($valor['messages'] ?? [] as $mensaje) {
                    if (! is_array($mensaje)) {
                        continue;
                    }

                    $idGrupo = trim((string) ($mensaje['group_id'] ?? ''));

                    if ($idGrupo !== '') {
                        $resultado['grupos_sincronizados'] += $this->sincronizarGrupos(
                            $conexion,
                            [[
                                'group_id' => $idGrupo,
                                'subject' => null,
                            ]],
                            $sincronizar,
                        );
                    }

                    $idChat = $idGrupo !== ''
                        ? $idGrupo
                        : trim((string) ($mensaje['from'] ?? ''));

                    if ($idChat === '') {
                        $resultado['ignorados']++;

                        continue;
                    }

                    $contenido = json_encode([
                        'origen' => 'meta_cloud_api',
                        'entrada_id' => $entrada['id'] ?? null,
                        'campo' => $campo,
                        'metadata' => $valor['metadata'] ?? null,
                        'contacts' => $valor['contacts'] ?? [],
                        'message' => $mensaje,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                    $capturado = $capturar->ejecutar(new EventoWhatsappEntrante(
                        (int) $conexion->id,
                        $idChat,
                        'mensaje',
                        $this->tipoMensaje((string) ($mensaje['type'] ?? 'desconocido')),
                        isset($mensaje['id']) ? (string) $mensaje['id'] : null,
                        isset($mensaje['id']) ? (string) $mensaje['id'] : null,
                        $contenido,
                        $this->fechaMensaje($mensaje['timestamp'] ?? null),
                        'meta-webhook-v1'
                    ));

                    if ($capturado->estado === 'aceptado') {
                        $claveResultado = 'aceptados';
                    } elseif ($capturado->estado === 'duplicado') {
                        $claveResultado = 'duplicados';
                    } else {
                        $claveResultado = 'ignorados';
                    }
                    $resultado[$claveResultado]++;
                }
            }
        }

        return response()->json([
            'estado' => 'aceptado',
            'resultado' => $resultado,
        ]);
    }

    /**
     * @param  array<string, mixed>  $valor
     */
    private function conexionDesdeValor(array $valor): ?ConexionWhatsapp
    {
        $idNumero = trim((string) data_get($valor, 'metadata.phone_number_id', ''));

        if ($idNumero === '') {
            return null;
        }

        return ConexionWhatsapp::query()
            ->where('proveedor', 'meta_cloud_api')
            ->where('id_cuenta_externa', $idNumero)
            ->first();
    }

    private function sincronizarGrupos(
        ConexionWhatsapp $conexion,
        $grupos,
        SincronizarChatsDescubiertos $sincronizar) : int {
        if (! is_array($grupos)) {
            return 0;
        }

        $descubiertos = [];

        foreach ($grupos as $grupo) {
            if (! is_array($grupo)) {
                continue;
            }

            $idGrupo = trim((string) ($grupo['group_id'] ?? ''));

            if ($idGrupo === '') {
                continue;
            }

            $descubiertos[] = [
                'id_chat_proveedor' => $idGrupo,
                'tipo_chat' => 'grupo',
                'nombre_grupo' => isset($grupo['subject']) ? (string) $grupo['subject'] : null,
                'es_activo' => (string) ($grupo['type'] ?? '') !== 'group_delete',
            ];
        }

        if ($descubiertos === []) {
            return 0;
        }

        $resultado = $sincronizar->ejecutar($conexion, $descubiertos);

        return $resultado['creados'] + $resultado['actualizados'] + $resultado['sin_cambios'];
    }

    private function tipoMensaje(string $tipo): string
    {
        switch ($tipo) {
            case 'text':
                return 'texto';
            case 'image':
                return 'foto';
            case 'video':
                return 'video';
            case 'audio':
                return 'audio';
            case 'document':
                return 'documento';
            case 'sticker':
                return 'sticker';
            default:
                return $tipo !== '' ? $tipo : 'desconocido';
        }
    }

    private function fechaMensaje($marcaTiempo): CarbonImmutable
    {
        if (is_numeric($marcaTiempo) && (int) $marcaTiempo > 0) {
            return CarbonImmutable::createFromTimestampUTC((int) $marcaTiempo);
        }

        return CarbonImmutable::now('UTC');
    }

    private function parametroHub(Request $request, string $nombre): string
    {
        $valor = $request->query($nombre);

        if ($valor === null) {
            $valor = $request->query(str_replace('.', '_', $nombre));
        }

        return is_scalar($valor) ? (string) $valor : '';
    }
}
