<?php

return [
    'zona_horaria_visual' => env('APP_TIMEZONE', 'America/Mexico_City'),
    'bytes_libres_minimos' => (int) env('MENSAJERO_BYTES_LIBRES_MINIMOS', 1073741824),
    'proveedor_whatsapp' => env('PROVEEDOR_WHATSAPP', 'meta_cloud_api'),
    'pasarela_url' => env('WHATSAPP_PASARELA_URL'),
    'pasarela_secreto' => env('WHATSAPP_PASARELA_SECRETO'),
    'ia_habilitada' => (bool) env('IA_HABILITADA', false),
    'modelo_gemini' => env('MODELO_GEMINI'),
    'clave_api_gemini' => env('CLAVE_API_GEMINI'),

    'meta_whatsapp' => [
        'version_graph' => env('META_WHATSAPP_GRAPH_VERSION', 'v26.0'),
        'secreto_aplicacion' => env('META_WHATSAPP_APP_SECRET'),
        'token_verificacion' => env('META_WHATSAPP_VERIFY_TOKEN'),
        'token_acceso' => env('META_WHATSAPP_ACCESS_TOKEN'),
        'waba_id' => env('META_WHATSAPP_WABA_ID'),
        'id_numero' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
    ],

    'seguridad_pasarela' => [
        'ventana_segundos' => (int) env('MENSAJERO_VENTANA_FIRMA_SEGUNDOS', 300),
    ],

    'autenticacion' => [
        // deshabilitado: acceso interno temporal sin inicio de sesión.
        // local: autenticación Laravel provisional para desarrollo y pruebas.
        // corporativo: reservado para identidad MILAC; falla cerrado hasta implementarse.
        'modo' => env('MENSAJERO_MODO_AUTENTICACION', 'deshabilitado'),
    ],

    // Consultas de conversaciones sin iniciar sesión: únicamente POC local explícito.
    'consulta_local_sin_autenticacion' => (bool) env('MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION', false),

    'normalizacion' => [
        // No procesar datos históricos hasta aprobar integración SQL Server.
        'habilitada' => (bool) env('MENSAJERO_NORMALIZACION_HABILITADA', false),
        'cola' => env('MENSAJERO_COLA_NORMALIZACION', 'mensajero_normalizacion'),
    ],

    'multimedia' => [
        // Tope de descarga/visualización: 250 MiB. Sin enlaces públicos.
        'limite_consulta_bytes' => (int) env('MENSAJERO_LIMITE_CONSULTA_MULTIMEDIA_BYTES', 262144000),
    ],

    'base_datos' => [
        'prefijo_tablas' => 'mensajero_',
    ],
];
