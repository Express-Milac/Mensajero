<?php

namespace App\Http\Middleware;

use App\Services\Meta\ValidadorFirmaMeta;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarFirmaMeta
{
    private ValidadorFirmaMeta $validador;

    public function __construct(ValidadorFirmaMeta $validador)
    {
        $this->validador = $validador;
    }

    public function handle(Request $request, Closure $siguiente): Response
    {
        $secreto = (string) config('mensajero.meta_whatsapp.secreto_aplicacion', '');

        if (strlen($secreto) < 16) {
            return $this->error('La firma oficial de Meta no está configurada.', 503);
        }

        $firma = trim((string) $request->header('X-Hub-Signature-256', ''));

        if (! $this->validador->validar($request->getContent(), $firma, $secreto)) {
            return $this->error('Webhook de Meta no autorizado.', 401);
        }

        return $siguiente($request);
    }

    private function error(string $mensaje, int $estado): JsonResponse
    {
        return response()->json(['mensaje' => $mensaje], $estado);
    }
}
