<?php

namespace App\Enums;

enum Role: string
{
    case Administrator = 'administrator';
    case Supervisor = 'supervisor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::Supervisor => 'Supervisor',
            self::Viewer => 'Visualizador',
        };
    }
}
