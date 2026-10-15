<?php

return [
    'auth' => [
        // disabled: acceso interno temporal sin login.
        // local: autenticación Laravel provisional para desarrollo/pruebas.
        // corporate: reservado para la integración corporativa; falla cerrado hasta implementarla.
        'mode' => env('MENSAJERO_AUTH_MODE', 'disabled'),
    ],

    'database' => [
        'table_prefix' => 'mensajero_',
    ],

    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'self_hosted_web'),
        'gateway_url' => env('WHATSAPP_GATEWAY_URL'),
        'gateway_secret' => env('WHATSAPP_GATEWAY_SECRET'),
    ],
];
