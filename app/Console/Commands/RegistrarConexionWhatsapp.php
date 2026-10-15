<?php

namespace App\Console\Commands;

use App\Models\ConexionWhatsapp;
use Illuminate\Console\Command;

class RegistrarConexionWhatsapp extends Command
{
    protected $signature = 'mensajero:registrar-conexion-whatsapp
        {clave : Clave estable que usará la pasarela}
        {--nombre= : Nombre visible de la cuenta o sesión}
        {--proveedor=web_autohospedado : Identificador técnico del proveedor}';

    protected $description = 'Registra o actualiza una conexión de WhatsApp para la pasarela autohospedada';

    public function handle(): int
    {
        $clave = trim((string) $this->argument('clave'));

        if (! preg_match('/^[A-Za-z0-9._-]{3,64}$/', $clave)) {
            $this->error('La clave debe tener entre 3 y 64 caracteres y usar solo letras, números, punto, guion o guion bajo.');

            return self::FAILURE;
        }

        $conexion = ConexionWhatsapp::query()->firstOrNew(['clave_conexion' => $clave]);
        $conexion->proveedor = trim((string) $this->option('proveedor')) ?: 'web_autohospedado';
        $conexion->nombre_visible = $this->option('nombre') !== null
            ? trim((string) $this->option('nombre')) ?: null
            : $conexion->nombre_visible;

        if (! $conexion->exists) {
            $conexion->estado = 'sin_configurar';
        }

        $conexion->save();

        $this->info(sprintf(
            'Conexión %s registrada con ID %d y estado %s.',
            $conexion->clave_conexion,
            $conexion->id,
            $conexion->estado,
        ));

        return self::SUCCESS;
    }
}
