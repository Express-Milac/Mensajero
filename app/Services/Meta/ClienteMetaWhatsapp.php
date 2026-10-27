<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ClienteMetaWhatsapp
{
    /**
     * @return array<string, mixed>
     */
    public function obtenerInformacionNumero(?string $idNumero = null): array
    {
        $idNumero ??= (string) config('mensajero.meta_whatsapp.id_numero', '');

        if ($idNumero === '') {
            throw new RuntimeException('META_WHATSAPP_PHONE_NUMBER_ID no está configurado.');
        }

        return $this->solicitud()
            ->get($this->url($idNumero), [
                'fields' => 'display_phone_number,verified_name,quality_rating',
            ])
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerDatosMedio(string $idMedio, ?string $idNumero = null): array
    {
        $idMedio = trim($idMedio);

        if ($idMedio === '') {
            throw new RuntimeException('El identificador de medio de Meta es obligatorio.');
        }

        $idNumero ??= (string) config('mensajero.meta_whatsapp.id_numero', '');

        return $this->solicitud()
            ->get($this->url($idMedio), array_filter([
                'phone_number_id' => $idNumero !== '' ? $idNumero : null,
            ]))
            ->throw()
            ->json();
    }

    private function solicitud(): PendingRequest
    {
        $token = (string) config('mensajero.meta_whatsapp.token_acceso', '');

        if ($token === '') {
            throw new RuntimeException('META_WHATSAPP_ACCESS_TOKEN no está configurado.');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(10);
    }

    private function url(string $recurso): string
    {
        $version = trim((string) config('mensajero.meta_whatsapp.version_graph', 'v26.0'));

        if (! preg_match('/^v[0-9]+\.[0-9]+$/', $version)) {
            throw new RuntimeException('La versión de Graph API configurada no es válida.');
        }

        return 'https://graph.facebook.com/'.$version.'/'.rawurlencode($recurso);
    }
}
