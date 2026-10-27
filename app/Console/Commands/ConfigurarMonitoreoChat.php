<?php

namespace App\Console\Commands;

use App\Services\Monitoreo\CambiarMonitoreoChat;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConfigurarMonitoreoChat extends Command
{
    protected $signature = 'mensajero:monitoreo-chat
        {chat : ID interno del chat descubierto}
        {estado : activar o desactivar}
        {--motivo= : Motivo opcional del cambio}';

    protected $description = 'Activa o desactiva un chat en la lista permitida de Mensajero';

    public function handle(CambiarMonitoreoChat $cambiarMonitoreo): int
    {
        $estado = mb_strtolower(trim((string) $this->argument('estado')));

        if (! in_array($estado, ['activar', 'desactivar'], true)) {
            $this->error('El estado debe ser activar o desactivar.');

            return self::FAILURE;
        }

        try {
            $chat = $cambiarMonitoreo->ejecutar(
                (int) $this->argument('chat'),
                $estado === 'activar',
                'consola',
                null,
                $this->option('motivo') !== null ? (string) $this->option('motivo') : null,
            );
        } catch (ModelNotFoundException) {
            $this->error('No existe el chat indicado.');

            return self::FAILURE;
        } catch (DomainException $excepcion) {
            $this->error($excepcion->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Chat %d: monitoreo %s; versión de autorización %d.',
            $chat->id,
            $chat->es_monitoreado ? 'activado' : 'desactivado',
            $chat->version_autorizacion,
        ));

        return self::SUCCESS;
    }
}
