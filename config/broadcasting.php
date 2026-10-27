<?php

return [
    // Compatibilidad Laravel 8: no difundir eventos a servicios externos.
    'default' => env('BROADCAST_DRIVER', env('BROADCAST_CONNECTION', 'log')),
    'connections' => [
        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
