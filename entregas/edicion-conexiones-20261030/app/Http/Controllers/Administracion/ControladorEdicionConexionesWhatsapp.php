<?php

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Models\ConexionWhatsapp;
use App\Services\WhatsApp\ClienteGestorWhatsapp;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Administración acotada: renombrar líneas o eliminar registros que nunca se vincularon.
 * Nunca elimina sesiones, mensajes, chats ni archivos de WhatsApp.
 */
class ControladorEdicionConexionesWhatsapp extends Controller
{
    public function actualizar(Request $solicitud, int $conexion): RedirectResponse
    {
        $datos = $solicitud->validate([
            'nombre_visible' => ['required', 'string', 'min:3', 'max:120'],
        ]);
        $nombre = trim((string) $datos['nombre_visible']);
        if (mb_strlen($nombre) < 3) {
            throw ValidationException::withMessages([
                'nombre_visible' => 'El nombre debe contener al menos tres caracteres.',
            ]);
        }

        $registro = ConexionWhatsapp::query()
            ->where('proveedor', 'recolector_whatsapp')
            ->findOrFail($conexion);
        $registro->nombre_visible = $nombre;
        $registro->save();

        Log::info('mensajero.whatsapp.conexion_renombrada', [
            'conexion_id' => $conexion,
            'usuario_id' => auth()->id(),
        ]);

        return redirect()->route('whatsapp.conexiones.indice')
            ->with('estado', 'Se actualizó el nombre de la línea '.$conexion.'.');
    }

    public function eliminar(Request $solicitud, int $conexion, ClienteGestorWhatsapp $gestor): RedirectResponse
    {
        $datos = $solicitud->validate([
            'confirmacion' => ['required', 'string', 'max:32'],
        ]);
        if ($datos['confirmacion'] !== 'ELIMINAR '.$conexion) {
            throw ValidationException::withMessages([
                'confirmacion' => 'La confirmación debe coincidir exactamente con ELIMINAR '.$conexion.'.',
            ]);
        }

        // Exige el gestor vivo para descartar un trabajador activo.
        try {
            $estado = $gestor->enviar('GET', '/v1/conexiones/'.$conexion);
        } catch (RuntimeException $excepcion) {
            throw ValidationException::withMessages([
                'eliminacion' => 'No se puede comprobar el gestor. No se eliminó la conexión.',
            ]);
        }
        if (($estado['estado'] ?? null) !== 'sin_iniciar') {
            throw ValidationException::withMessages([
                'eliminacion' => 'Primero detén la línea. No se puede eliminar mientras haya una sesión activa.',
            ]);
        }

        try {
            DB::transaction(function () use ($conexion): void {
                $registro = ConexionWhatsapp::query()
                    ->where('proveedor', 'recolector_whatsapp')
                    ->lockForUpdate()
                    ->findOrFail($conexion);
                $metadatos = is_array($registro->metadatos) ? $registro->metadatos : [];

                // Solo registros nuevos nunca activados: no hay sesiones que retirar ni historial que preservar.
                $sinHistorial = $registro->estado === 'sin_configurar'
                    && ($metadatos['vinculacion_habilitada'] ?? false) !== true
                    && empty($registro->id_cuenta_externa)
                    && $registro->conectado_en === null
                    && $registro->ultimo_latido_en === null
                    && $registro->ultima_desconexion_en === null
                    && ! $registro->chats()->exists();

                if (! $sinHistorial) {
                    throw ValidationException::withMessages([
                        'eliminacion' => 'La línea ya se utilizó o tiene chats asociados. Solo pueden eliminarse registros nunca vinculados y sin historial.',
                    ]);
                }
                $registro->delete();
            }, 3);
        } catch (QueryException $excepcion) {
            // Una FK existente también debe impedir el borrado; no mostrar detalles de SQL Server.
            report($excepcion);
            throw ValidationException::withMessages([
                'eliminacion' => 'La conexión tiene referencias en la base de datos y no puede eliminarse.',
            ]);
        }

        Log::info('mensajero.whatsapp.conexion_eliminada', [
            'conexion_id' => $conexion,
            'usuario_id' => auth()->id(),
        ]);

        return redirect()->route('whatsapp.conexiones.indice')
            ->with('estado', 'Se eliminó el registro de la línea '.$conexion.'.');
    }
}