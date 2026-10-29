<?php

namespace App\Services\Mensajes;

use App\Models\EventoCrudoWhatsapp;
use App\Services\Normalizacion\ExtraerContenidoWhatsapp;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Indicadores transparentes: detecta menciones literales, no crea
 * acuerdos, tareas ni califica la urgencia mediante IA.
 */
final class AnalizarPanoramaContenido
{
    private const TEMAS = [
        'Logística y entregas' => '/\b(entrega|entregas|entregar|entregado|entregada|ruta|rutas|destino|llegada|llegó|llego|arribo|descarga|descargar)\b/iu',
        'Unidades y operadores' => '/\b(unidad|unidades|camión|camion|tractocamión|tractocamion|operador|chofer|placas|remolque|caja)\b/iu',
        'Clientes y citas' => '/\b(cliente|clientes|cita|citas|atención al cliente|recepción)\b/iu',
        'Incidencias y demoras' => '/\b(incidencia|incidencias|demora|demoras|retraso|retrasos|falla|problema|problemas)\b/iu',
        'Documentación y evidencia' => '/\b(documento|documentos|factura|facturas|evidencia|comprobante|guía|guia|archivo|archivos)\b/iu',
    ];

    private const ACUERDOS_EXPLICITOS = '/\b(acordamos|acordado|se acuerda|quedamos en|confirmamos|confirmado|autorizado|aprobado)\b/iu';

    private const REVISION = '/\b(pendiente|pendientes|demora|demoras|retraso|retrasos|urgente|falta|faltan|incidencia|incidencias|sin respuesta)\b/iu';

    /**
     * @param  Collection<int, EventoCrudoWhatsapp>  $eventos
     * @return array<string, mixed>
     */
    public function ejecutar(Collection $eventos, ExtraerContenidoWhatsapp $extraer, ?IdentificarRemitente $identificador = null): array
    {
        $temas = [];
        foreach (self::TEMAS as $nombre => $patron) {
            $temas[$nombre] = ['nombre' => $nombre, 'total' => 0, 'grupos' => []];
        }

        $acuerdos = [];
        $revision = [];
        $totalAcuerdos = 0;
        $totalRevision = 0;
        $remitentes = [];

        if ($identificador !== null) {
            $identificador->precargar($eventos);
        }

        foreach ($eventos as $evento) {
            try {
                $datos = $extraer->ejecutar((string) $evento->contenido);
                $texto = trim((string) ($datos['texto'] ?? $datos['pie_multimedia'] ?? ''));
            } catch (Throwable $error) {
                // El contenido original sigue intacto; una carga ilegible
                // no debe bloquear el tablero ni generar inferencias.
                continue;
            }

            $remitente = $evento->id_remitente_proveedor;
            if (is_string($remitente) && $remitente !== '') {
                $clave = hash('sha256', (string) $evento->conexion_id.'|'.$remitente);
                if (isset($remitentes[$clave]) === false) {
                    $remitentes[$clave] = ['clave' => $clave, 'nombre' => ($identificador !== null ? $identificador->nombre($evento) : 'Persona sin identificar'), 'mensajes' => 0];
                }
                $remitentes[$clave]['mensajes']++;
                if ($identificador !== null && $remitentes[$clave]['nombre'] === 'Persona sin identificar') {
                    $remitentes[$clave]['nombre'] = $identificador->nombre($evento);
                }
            }

            if ($texto === '') {
                continue;
            }

            foreach (self::TEMAS as $nombre => $patron) {
                if (preg_match($patron, $texto) === 1) {
                    $temas[$nombre]['total']++;
                    $temas[$nombre]['grupos'][(int) $evento->chat_monitoreado_id] = true;
                }
            }

            $registro = [
                'evento_id' => (int) $evento->id,
                'grupo_id' => (int) $evento->chat_monitoreado_id,
                'texto' => mb_strimwidth($texto, 0, 115, '…', 'UTF-8'),
                'fecha' => $evento->recibido_en,
            ];

            if (preg_match(self::ACUERDOS_EXPLICITOS, $texto) === 1) {
                $totalAcuerdos++;
                if (count($acuerdos) < 4) {
                    $acuerdos[] = $registro;
                }
            }

            if (preg_match(self::REVISION, $texto) === 1) {
                $totalRevision++;
                if (count($revision) < 4) {
                    $revision[] = $registro;
                }
            }
        }

        $temasVisibles = collect($temas)
            ->filter(fn (array $tema): bool => $tema['total'] > 0)
            ->map(fn (array $tema): array => [
                'nombre' => $tema['nombre'],
                'total' => $tema['total'],
                'grupos' => count($tema['grupos']),
            ])
            ->sortByDesc('total')
            ->take(4)
            ->values()
            ->all();

        return [
            'temas' => $temasVisibles,
            'acuerdos' => $acuerdos,
            'revision' => $revision,
            'totalRevision' => $totalRevision,
            'totalAcuerdos' => $totalAcuerdos,
            'participantesMuestra' => count($remitentes),
            'remitentes' => collect($remitentes)->sortByDesc('mensajes')->take(4)->values()->all(),
            'mensajesRevisados' => $eventos->count(),
        ];
    }
}
