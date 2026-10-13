<?php

return [
    'display_timezone' => env('APP_TIMEZONE', 'America/Mexico_City'),
    'min_free_bytes' => (int) env('MENSAJERO_MIN_FREE_BYTES', 1073741824),
    'whatsapp_provider' => env('WHATSAPP_PROVIDER', 'unconfigured'),
    'gateway_url' => env('WHATSAPP_GATEWAY_URL'),
    'gateway_secret' => env('WHATSAPP_GATEWAY_SECRET'),
    'ai_enabled' => (bool) env('AI_ENABLED', false),
    'gemini_model' => env('GEMINI_MODEL'),
    'gemini_api_key' => env('GEMINI_API_KEY'),
];
