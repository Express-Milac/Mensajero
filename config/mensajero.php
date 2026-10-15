<?php

$ips = array_values(array_filter(array_map('trim', explode(',', (string) env('MENSAJERO_IPS_ADMINISTRACION', '127.0.0.1,::1')))));

return [
    'zona_horaria_presentacion' => env('APP_TIMEZONE', 'America/Mexico_City'),
    'bytes_libres_minimos' => (int) env('MENSAJERO_BYTES_LIBRES_MINIMOS', 1073741824),
    'proveedor_whatsapp' => env('MENSAJERO_PROVEEDOR_WHATSAPP', 'web_autohospedado'),
    'url_puerta_enlace_whatsapp' => env('MENSAJERO_URL_PUERTA_ENLACE_WHATSAPP'),
    'secreto_puerta_enlace_whatsapp' => env('MENSAJERO_SECRETO_PUERTA_ENLACE_WHATSAPP'),
    'ia_habilitada' => filter_var(env('IA_HABILITADA', false), FILTER_VALIDATE_BOOL),
    'modelo_gemini' => env('GEMINI_MODELO'),
    'clave_api_gemini' => env('GEMINI_CLAVE_API'),

    'autenticacion' => [
        // deshabilitado: acceso interno temporal sin login.
        // local: autenticación provisional para desarrollo y pruebas.
        // corporativo: reservado para la futura integración MILAC y falla cerrado.
        'modo' => env('MENSAJERO_MODO_AUTENTICACION', 'deshabilitado'),
        'ips_administracion' => $ips,
    ],

    'base_datos' => [
        'prefijo_tablas' => 'mensajero_',
    ],
];
