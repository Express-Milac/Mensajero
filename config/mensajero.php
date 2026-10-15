<?php

return [
    'zona_horaria_visual' => env('APP_TIMEZONE', 'America/Mexico_City'),
    'bytes_libres_minimos' => (int) env('MENSAJERO_BYTES_LIBRES_MINIMOS', 1073741824),
    'proveedor_whatsapp' => env('PROVEEDOR_WHATSAPP', 'web_autohospedado'),
    'pasarela_url' => env('WHATSAPP_PASARELA_URL'),
    'pasarela_secreto' => env('WHATSAPP_PASARELA_SECRETO'),
    'ia_habilitada' => (bool) env('IA_HABILITADA', false),
    'modelo_gemini' => env('MODELO_GEMINI'),
    'clave_api_gemini' => env('CLAVE_API_GEMINI'),

    'seguridad_pasarela' => [
        'ventana_segundos' => (int) env('MENSAJERO_VENTANA_FIRMA_SEGUNDOS', 300),
    ],

    'autenticacion' => [
        // deshabilitado: acceso interno temporal sin inicio de sesión.
        // local: autenticación Laravel provisional para desarrollo y pruebas.
        // corporativo: reservado para identidad MILAC; falla cerrado hasta implementarse.
        'modo' => env('MENSAJERO_MODO_AUTENTICACION', 'deshabilitado'),
    ],

    'base_datos' => [
        'prefijo_tablas' => 'mensajero_',
    ],
];
