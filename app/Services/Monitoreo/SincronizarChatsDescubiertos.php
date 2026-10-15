<?php

namespace App\Services\Monitoreo;

use App\Models\ChatMonitoreado;
use App\Models\ConexionWhatsapp;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

final class SincronizarChatsDescubiertos
{
    /**
     * @param  array<int, array<string, mixed>>  $chats
     * @return array{creados:int, actualizados:int, sin_cambios:int}
     */
    public function ejecutar(ConexionWhatsapp $conexion, array $chats, ?CarbonImmutable $vistoEn = null): array
    {
        $vistoEn ??= CarbonImmutable::now();

        return DB::transaction(function () use ($conexion, $chats, $vistoEn) {
            $resultado = ['creados' => 0, 'actualizados' => 0, 'sin_cambios' => 0];

            foreach ($chats as $chatDescubierto) {
                $idProveedor = trim((string) ($chatDescubierto['id_chat_proveedor'] ?? ''));
                $tipoChat = trim((string) ($chatDescubierto['tipo_chat'] ?? 'grupo'));

                if ($idProveedor === '' || strlen($idProveedor) > 191) {
                    throw new InvalidArgumentException('Cada chat descubierto debe tener un identificador de proveedor válido.');
                }

                if ($tipoChat === '' || strlen($tipoChat) > 32) {
                    throw new InvalidArgumentException('Cada chat descubierto debe tener un tipo válido.');
                }

                $registro = ChatMonitoreado::query()
                    ->where('conexion_id', $conexion->id)
                    ->where('id_chat_proveedor', $idProveedor)
                    ->first();

                $nombreGrupo = isset($chatDescubierto['nombre_grupo'])
                    ? trim((string) $chatDescubierto['nombre_grupo'])
                    : null;
                $nombreGrupo = $nombreGrupo !== '' ? $nombreGrupo : null;

                if ($registro === null) {
                    ChatMonitoreado::create([
                        'conexion_id' => $conexion->id,
                        'id_chat_proveedor' => $idProveedor,
                        'tipo_chat' => $tipoChat,
                        'nombre_grupo' => $nombreGrupo,
                        'es_monitoreado' => false,
                        'es_activo' => array_key_exists('es_activo', $chatDescubierto) ? (bool) $chatDescubierto['es_activo'] : true,
                        'version_autorizacion' => 0,
                        'visto_primera_vez_en' => $vistoEn,
                        'visto_ultima_vez_en' => $vistoEn,
                    ]);
                    $resultado['creados']++;

                    continue;
                }

                $registro->tipo_chat = $tipoChat;
                $registro->nombre_grupo = $nombreGrupo;
                $registro->visto_ultima_vez_en = $vistoEn;

                if (array_key_exists('es_activo', $chatDescubierto)) {
                    $registro->es_activo = (bool) $chatDescubierto['es_activo'];
                }

                if ($registro->isDirty()) {
                    $registro->save();
                    $resultado['actualizados']++;
                } else {
                    $resultado['sin_cambios']++;
                }
            }

            return $resultado;
        });
    }
}
