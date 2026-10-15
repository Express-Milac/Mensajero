<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        'sync' => ['driver' => 'sync'],
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'predeterminada'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 180),
            'block_for' => null,
            'after_commit' => true,
        ],
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlsrv'),
        'table' => 'mensajero_lotes_trabajo',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'file'),
        'database' => env('DB_CONNECTION', 'sqlsrv'),
        'table' => 'mensajero_trabajos_fallidos',
    ],
];
