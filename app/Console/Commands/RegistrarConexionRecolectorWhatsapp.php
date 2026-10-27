<?php

namespace App\Console\Commands;

use App\Models\ConexionWhatsapp;
use Illuminate\Console\Command;

class RegistrarConexionRecolectorWhatsapp extends Command
{
    protected $signature = 'mensajero:registrar-conexion-recolector
        {clave : Clave estable del número recolector dedicado}
        {--nombre= : Nombre visible para identificar el recolector}';

    protected $description = 'Registra una conexión aislada para el recolector de grupos de WhatsApp';

    public function handle(): int
    {
        $clave = trim((string) $this->argument('clave'));

        if (! preg_match('/^[A-Za-z0-9._-]{3,64}$/', $clave)) {
            $this->error('La clave debe tener entre 3 y 64 caracteres y usar solo letras, números, punto, guion o guion bajo.');

            return self::FAILURE;
        }

        $conexion = ConexionWhatsapp::query()->firstOrNew(['clave_conexion' => $clave]);
        $conexion->proveedor = 'recolector_whatsapp';
        $conexion->nombre_visible = $this->option('nombre') !== null
            ? trim((string) $this->option('nombre')) ?: null
            : $conexion->nombre_visible;

        if (! $conexion->exists) {
            $conexion->estado = 'sin_configurar';
        }

        $conexion->save();

        $this->info(sprintf(
            'Conexión recolectora %s registrada con ID %d y estado %s.',
            $conexion->clave_conexion,
            $conexion->id,
            $conexion->estado,
        ));

        return self::SUCCESS;
    }
}
