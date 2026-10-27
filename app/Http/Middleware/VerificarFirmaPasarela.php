<?php

namespace App\Http\Middleware;

use App\Models\SolicitudPasarela;
use App\Services\Seguridad\ValidadorFirmaPasarela;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarFirmaPasarela
{
    private ValidadorFirmaPasarela $validador;

    public function __construct(ValidadorFirmaPasarela $validador)
    {
        $this->validador = $validador;
    }

    public function handle(Request $request, Closure $siguiente, string $protegerRepeticion = 'si'): Response
    {
        $secreto = (string) config('mensajero.pasarela_secreto', '');
        $ventana = (int) config('mensajero.seguridad_pasarela.ventana_segundos', 300);

        if (strlen($secreto) < 32) {
            return $this->error('La firma de pasarela no está configurada.', 503);
        }

        $identificador = trim((string) $request->header('X-Mensajero-Id', ''));
        $marcaTiempo = trim((string) $request->header('X-Mensajero-Marca-Tiempo', ''));
        $firma = trim((string) $request->header('X-Mensajero-Firma', ''));
        $cuerpo = $request->getContent();

        if (! $this->validador->validar($identificador, $marcaTiempo, $cuerpo, $firma, $secreto, $ventana)) {
            return $this->error('Solicitud de pasarela no autorizada.', 401);
        }

        if ($protegerRepeticion !== 'no') {
            try {
                SolicitudPasarela::create([
                    'identificador_solicitud' => $identificador,
                    'marca_tiempo_pasarela' => (int) $marcaTiempo,
                    'hash_cuerpo_sha256' => hash('sha256', $cuerpo),
                    'ruta' => '/'.$request->path(),
                    'recibido_en' => now(),
                ]);
            } catch (QueryException $excepcion) {
                if ($this->esDuplicado($excepcion)) {
                    return $this->error('Solicitud de pasarela repetida.', 409);
                }

                throw $excepcion;
            }
        }

        $request->attributes->set('mensajero_identificador_solicitud', $identificador);

        return $siguiente($request);
    }

    private function esDuplicado(QueryException $excepcion): bool
    {
        return in_array((int) ($excepcion->errorInfo[1] ?? 0), [2601, 2627], true);
    }

    private function error(string $mensaje, int $estado): JsonResponse
    {
        return response()->json(['mensaje' => $mensaje], $estado);
    }
}
