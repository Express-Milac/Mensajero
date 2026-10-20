<?php

namespace App\Services\Mensajes;

use App\Models\EventoCrudoWhatsapp;
use App\Models\IdentidadRemitente;
use Illuminate\Support\Collection;
use JsonException;

/**
 * Resuelve nombres sobre la evidencia cruda y nombres asignados.
 * No requiere activar normalización ni altera el mensaje original.
 */
final class IdentificarRemitente
{
    /** @var array<string, string> */
    private array $nombresInternos = [];

    /**
     * @param  Collection<int, EventoCrudoWhatsapp>  $eventos
     */
    public function precargar(Collection $eventos): void
    {
        $identificadores = $eventos
            ->filter(fn (EventoCrudoWhatsapp $evento): bool => is_string($evento->id_remitente_proveedor)
                && trim($evento->id_remitente_proveedor) !== '')
            ->pluck('id_remitente_proveedor')
            ->unique()
            ->values()
            ->all();

        if ($identificadores === []) {
            return;
        }

        $conexiones = $eventos->pluck('conexion_id')->unique()->values()->all();

        $identidades = IdentidadRemitente::query()
            ->whereIn('conexion_id', $conexiones)
            ->whereIn('id_remitente_proveedor', $identificadores)
            ->get();

        foreach ($identidades as $identidad) {
            $this->nombresInternos[$this->clave((int) $identidad->conexion_id, $identidad->id_remitente_proveedor)] = $identidad->nombre_interno;
        }
    }

    public function nombre(EventoCrudoWhatsapp $evento): string
    {
        $id = $evento->id_remitente_proveedor;

        if (! is_string($id) || trim($id) === '') {
            return 'Persona sin identificar';
        }

        $interno = $this->nombresInternos[$this->clave((int) $evento->conexion_id, $id)] ?? null;
        if (is_string($interno) && trim($interno) !== '') {
            return $interno;
        }

        $nombre = $this->nombreWhatsapp($evento);
        if ($nombre !== null) {
            return $nombre;
        }

        $mensaje = $evento->relationLoaded('mensaje') ? $evento->getRelation('mensaje') : null;
        $contacto = $mensaje !== null && $mensaje->relationLoaded('remitente')
            ? $mensaje->getRelation('remitente')
            : null;
        $nombre = $contacto?->nombre_visible ?: $contacto?->nombre_notificacion;
        if (is_string($nombre) && trim($nombre) !== '') {
            return trim($nombre);
        }

        // Solo un JID telefónico auténtico permite presentar el número.
        // Nunca suponer que un identificador LID es un teléfono.
        if (preg_match('/^([0-9]{8,15})@s\\.whatsapp\\.net$/D', $id, $coincidencias) === 1) {
            return 'Teléfono +'.$coincidencias[1];
        }

        return 'Persona sin identificar';
    }

    public function nombreAsignado(EventoCrudoWhatsapp $evento): ?string
    {
        $id = $evento->id_remitente_proveedor;
        if (! is_string($id) || $id === '') {
            return null;
        }

        return $this->nombresInternos[$this->clave((int) $evento->conexion_id, $id)] ?? null;
    }

    public function nombreWhatsapp(EventoCrudoWhatsapp $evento): ?string
    {
        try {
            $crudo = json_decode((string) $evento->contenido, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($crudo)) {
            return null;
        }

        // pushName viene en el encabezado del WAMessage de Baileys.
        // Es un nombre de perfil elegido por quien envía, no una identidad verificada.
        $nombre = $crudo['pushName'] ?? null;

        if (! is_string($nombre) || trim($nombre) === '') {
            return null;
        }

        return mb_substr(trim($nombre), 0, 120);
    }

    private function clave(int $conexionId, string $identificador): string
    {
        return hash('sha256', $conexionId.'|'.$identificador);
    }
}
