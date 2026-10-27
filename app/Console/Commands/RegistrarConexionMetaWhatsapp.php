<?php

namespace App\Console\Commands;

use App\Models\ConexionWhatsapp;
use Illuminate\Console\Command;

class RegistrarConexionMetaWhatsapp extends Command
{
    protected $signature = 'mensajero:registrar-conexion-meta
        {clave : Clave estable interna de la conexión}
        {id_numero : Phone Number ID oficial de Meta}
        {--waba= : WhatsApp Business Account ID}
        {--nombre= : Nombre visible para Mensajero}';

    protected $description = 'Registra una conexión directa con WhatsApp Business Platform Cloud API';

    public function handle(): int
    {
        $clave = trim((string) $this->argument('clave'));
        $idNumero = trim((string) $this->argument('id_numero'));

        if (! preg_match('/^[A-Za-z0-9._-]{3,64}$/', $clave)) {
            $this->error('La clave debe tener entre 3 y 64 caracteres y usar letras, números, punto, guion o guion bajo.');

            return self::FAILURE;
        }

        if (! preg_match('/^[0-9]{5,64}$/', $idNumero)) {
            $this->error('El Phone Number ID de Meta debe ser numérico.');

            return self::FAILURE;
        }

        $conexion = ConexionWhatsapp::query()->firstOrNew(['clave_conexion' => $clave]);

        $duplicada = ConexionWhatsapp::query()
            ->where('proveedor', 'meta_cloud_api')
            ->where('id_cuenta_externa', $idNumero)
            ->when($conexion->exists, fn ($consulta) => $consulta->where('id', '<>', $conexion->getKey()))
            ->exists();

        if ($duplicada) {
            $this->error('Ese Phone Number ID ya está asociado a otra conexión de Mensajero.');

            return self::FAILURE;
        }

        $metadatos = is_array($conexion->metadatos) ? $conexion->metadatos : [];
        $waba = trim((string) $this->option('waba'));

        $metadatos['integracion'] = 'meta_cloud_api';
        $metadatos['waba_id'] = $waba !== '' ? $waba : ($metadatos['waba_id'] ?? null);

        $conexion->proveedor = 'meta_cloud_api';
        $conexion->id_cuenta_externa = $idNumero;
        $conexion->metadatos = $metadatos;
        $conexion->estado = $conexion->exists ? $conexion->estado : 'configurado';

        if ($this->option('nombre') !== null) {
            $nombre = trim((string) $this->option('nombre'));
            $conexion->nombre_visible = $nombre !== '' ? $nombre : null;
        }

        $conexion->save();

        $this->info(sprintf(
            'Conexión Meta %s registrada con ID %d y Phone Number ID %s.',
            $conexion->clave_conexion,
            $conexion->id,
            $conexion->id_cuenta_externa,
        ));

        return self::SUCCESS;
    }
}
