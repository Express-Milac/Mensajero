<?php

namespace App\Services\Mensajes;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisos efímeros del tablero: solo identificadores, nunca contenido de mensajes.
 * SQL Server es la fuente de verdad; una notificación fallida no revierte capturas.
 */
final class NotificadorTiempoReal
{
    public function habilitado(): bool
    {
        return (bool) config('mensajero.tiempo_real.habilitado', false)
            && strlen((string) config('mensajero.pasarela_secreto', '')) >= 32;
    }

    public function credencial(): array
    {
        if (! $this->habilitado()) {
            throw new \RuntimeException('El servicio de tiempo real no está configurado.');
        }

        $url = rtrim((string) config('mensajero.tiempo_real.url_publica', ''), '/');
        if (! preg_match('~^https?://~i', $url)) {
            throw new \RuntimeException('URL del servicio de tiempo real no configurada.');
        }

        if (request()->isSecure() && ! str_starts_with($url, 'https://')) {
            throw new \RuntimeException('El canal en vivo requiere HTTPS cuando el tablero utiliza HTTPS.');
        }

        $caduca = time() + 90;
        $aleatorio = bin2hex(random_bytes(16));
        $firma = hash_hmac('sha256', $caduca.'.'.$aleatorio, (string) config('mensajero.pasarela_secreto'));

        return [
            'url' => $url.'/eventos?'.http_build_query([
                'caduca' => $caduca,
                'aleatorio' => $aleatorio,
                'firma' => $firma,
            ], '', '&', PHP_QUERY_RFC3986),
        ];
    }

    public function avisar(int $grupoId, int $eventoId): void
    {
        if (! $this->habilitado()) {
            return;
        }

        $cuerpo = json_encode([
            'grupo_id' => $grupoId,
            'evento_id' => $eventoId,
            'marca_tiempo' => time(),
        ], JSON_THROW_ON_ERROR);

        $firma = hash_hmac('sha256', $cuerpo, (string) config('mensajero.pasarela_secreto'));
        $direccion = 'http://127.0.0.1:'.(int) config('mensajero.tiempo_real.puerto_local', 8765).'/notificar';

        try {
            $respuesta = Http::connectTimeout(0.15)
                ->timeout(0.4)
                ->withHeaders(['X-Mensajero-Firma-TR' => $firma])
                ->withBody($cuerpo, 'application/json')
                ->post($direccion);

            if (! $respuesta->successful()) {
                Log::warning('No se entregó el aviso en vivo; el mensaje sigue almacenado.', [
                    'estado_http' => $respuesta->status(),
                ]);
            }
        } catch (Throwable $error) {
            Log::warning('Servicio de avisos en vivo no disponible; la captura se conservó.', [
                'tipo_error' => get_class($error),
            ]);
        }
    }
}
