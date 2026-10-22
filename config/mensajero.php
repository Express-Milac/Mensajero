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

    // Fase 2: circuito separado de la captura. Cerrado por defecto.
    'ia' => [
        'habilitada' => filter_var(env('IA_HABILITADA', false), FILTER_VALIDATE_BOOLEAN),
        'envio_autorizado' => filter_var(env('MENSAJERO_IA_ENVIO_AUTORIZADO', false), FILTER_VALIDATE_BOOLEAN),
        'servicio_pagado_confirmado' => filter_var(env('MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO', false), FILTER_VALIDATE_BOOLEAN),
        'ejecucion_automatica' => filter_var(env('MENSAJERO_IA_EJECUCION_AUTOMATICA', false), FILTER_VALIDATE_BOOLEAN),
        // Piloto: nunca incluir conversaciones anteriores por defecto.
        'solo_mensaje_objetivo' => filter_var(env('MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO', true), FILTER_VALIDATE_BOOLEAN),
        'grupos_autorizados' => array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) env('MENSAJERO_IA_GRUPOS_AUTORIZADOS', ''))),
            static fn (int $id): bool => $id > 0
        ))),
        'fecha_inicio' => env('MENSAJERO_IA_FECHA_INICIO', ''),
        'modelo' => env('MODELO_GEMINI', 'gemini-3.5-flash-lite'),
        'clave_api' => env('CLAVE_API_GEMINI', ''),
        'cola' => env('MENSAJERO_COLA_IA', 'mensajero_ia'),
    ],

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

    // Modificación manual de visibilidad en el POC local. Cerrada por defecto.
    // No habilita cambios de captura ni acceso por red compartida.
    'gestion_local_grupos_habilitada' => (bool) env('MENSAJERO_GESTION_LOCAL_GRUPOS_HABILITADA', false),

    // Asignación manual de nombres: permiso independiente, solo POC local.
    'gestion_local_remitentes_habilitada' => (bool) env('MENSAJERO_GESTION_LOCAL_REMITENTES_HABILITADA', false),

    // SSE entregado desde el recolector a través de un puerto local.
    // En producción publicar mediante proxy HTTPS y configurar URL pública.
    'tiempo_real' => [
        'habilitado' => filter_var(env('MENSAJERO_TIEMPO_REAL_HABILITADO', env('APP_ENV', 'production') === 'local'), FILTER_VALIDATE_BOOLEAN),
        'url_publica' => env('MENSAJERO_TIEMPO_REAL_URL', 'http://127.0.0.1:8765'),
        'puerto_local' => (int) env('MENSAJERO_TIEMPO_REAL_PUERTO', 8765),
    ],

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
