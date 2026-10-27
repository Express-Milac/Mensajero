<?php

return [
    // Las claves de conexión son contratos de Laravel; la nomenclatura persistida de Mensajero es española.
    'default' => env('DB_CONNECTION', 'sqlsrv'),
    'connections' => [
        // Solo se usa si DB_CONNECTION=sqlite en la suite aislada de pruebas.
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],
        'sqlsrv' => [
            'driver' => 'sqlsrv', 'host' => env('DB_HOST', '127.0.0.1'), 'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', ''), 'username' => env('DB_USERNAME', ''), 'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'encrypt' => env('DB_ENCRYPT', 'yes'),
            'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],
    ],
    // Mensajero comparte una base corporativa y aísla también su historial de migraciones.
    'migrations' => 'mensajero_migraciones',
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => ['prefix' => env('REDIS_PREFIX', 'mensajero:')],
        'default' => ['host' => env('REDIS_HOST', '127.0.0.1'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', 6379), 'database' => env('REDIS_DB', 0), 'timeout' => 2, 'read_write_timeout' => 2],
        'cache' => ['host' => env('REDIS_HOST', '127.0.0.1'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', 6379), 'database' => env('REDIS_CACHE_DB', 1), 'timeout' => 2, 'read_write_timeout' => 2],
    ],
];
