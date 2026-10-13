<?php

return [
    'default' => env('DB_CONNECTION', 'sqlsrv'),
    'connections' => [
        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'mensajero'),
            'username' => env('DB_USERNAME', 'mensajero_app'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true,
            'encrypt' => env('DB_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => ['prefix' => env('REDIS_PREFIX', 'mensajero:')],
        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'), 'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379), 'database' => env('REDIS_DB', 0),
            'timeout' => 2, 'read_write_timeout' => 2,
        ],
        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'), 'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379), 'database' => env('REDIS_CACHE_DB', 1),
            'timeout' => 2, 'read_write_timeout' => 2,
        ],
    ],
];
