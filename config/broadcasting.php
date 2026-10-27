<?php

return [
    // Compatibilidad Laravel 8: no difundir eventos a servicios externos.
    'default' => env('BROADCAST_DRIVER', 'log'),
    'connections' => [
        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
