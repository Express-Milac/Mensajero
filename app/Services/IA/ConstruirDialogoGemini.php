<?php

namespace App\Services\IA;

use App\Models\MensajeWhatsapp;

/**
 * Texto y relaciones conversacionales mínimas; sin JID, teléfonos, nombre,
 * imágenes ni contenido crudo. Los alias solo existen dentro de esta petición.
 */
final class ConstruirDialogoGemini
{
    private PrepararContextoGemini $preparador;

    public function __construct(PrepararContextoGemini $preparador)
    {
        $this->preparador = $preparador;
    }

    /**
     * @param array<int, MensajeWhatsapp> $mensajes
     * @return array<int, array{id:int,texto:string,tipo:string,emisor:string,responde_a:?int}>
     */
    public function ejecutar(array $mensajes): array
    {
        $ids = [];
        foreach ($mensajes as $mensaje) {
            $ids[(int) $mensaje->id] = true;
        }

        $seudonimos = [];
        $secuencia = 0;
        $contexto = [];

        foreach ($mensajes as $mensaje) {
            $contenido = $mensaje->tipo_mensaje === 'texto'
                ? $mensaje->contenido_texto
                : $mensaje->pie_multimedia;

            $texto = $this->preparador->limpiar((string) $contenido);
            if ($texto === '') {
                continue;
            }

            $contacto = $mensaje->contacto_remitente_id;
            if ($contacto !== null) {
                $clave = 'remitente:'.(int) $contacto;
                if (! isset($seudonimos[$clave])) {
                    $secuencia++;
                    $seudonimos[$clave] = 'participante_'.$secuencia;
                }
                $emisor = $seudonimos[$clave];
            } else {
                // Un remitente desconocido NO se presume idéntico a otro.
                $secuencia++;
                $emisor = 'participante_desconocido_'.$secuencia;
            }

            $citado = $mensaje->mensaje_citado_id;
            $contexto[] = [
                'id' => (int) $mensaje->id,
                'texto' => $texto,
                'tipo' => $mensaje->tipo_mensaje === 'texto' ? 'texto' : 'leyenda_'.$mensaje->tipo_mensaje,
                'emisor' => $emisor,
                'responde_a' => $citado !== null && isset($ids[(int) $citado]) ? (int) $citado : null,
            ];
        }

        return $contexto;
    }
}
