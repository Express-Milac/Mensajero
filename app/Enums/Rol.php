<?php

namespace App\Enums;

enum Rol: string
{
    case Administrador = 'administrador';
    case Supervisor = 'supervisor';
    case Visualizador = 'visualizador';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Supervisor => 'Supervisor',
            self::Visualizador => 'Visualizador',
        };
    }
}
