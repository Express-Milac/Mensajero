<?php

return [
    // Configuración existente consumida por health checks e infraestructura.
    'display_timezone' => env('APP_TIMEZONE', 'America/Mexico_City'),
    'min_free_bytes' => (int) env('MENSAJERO_MIN_FREE_BYTES', 1073741824),
    'whatsapp_provider' => env('WHATSAPP_PROVIDER', 'self_hosted_web'),
    'gateway_url' => env('WHATSAPP_GATEWAY_URL'),
    'gateway_secret' => env('WHATSAPP_GATEWAY_SECRET'),
    'ai_enabled' => (bool) env('AI_ENABLED', false),
    'gemini_model' => env('GEMINI_MODEL'),
    'gemini_api_key' => env('GEMINI_API_KEY'),

    'auth' => [
        // disabled: acceso interno temporal sin login.
        // local: autenticación Laravel provisional para desarrollo/pruebas.
        // corporate: reservado para la integración corporativa; falla cerrado hasta implementarla.
        'mode' => env('MENSAJERO_AUTH_MODE', 'disabled'),
    ],

    'database' => [
        // Las tablas se nombran explícitamente para convivir con la BD corporativa existente.
        'table_prefix' => 'mensajero_',
    ],
];
