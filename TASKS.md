# Tareas de continuidad

Estado: 16/10/2026. `[x]` significa código entregado y validado en el alcance indicado; no equivale a certificación en producción.

## Entregado y validado
- [x] Arquitectura base sobre SQL Server corporativo, Redis y almacenamiento privado configurable.
- [x] Nomenclatura de negocio en español en tablas, columnas propias, clases, rutas, estados y pruebas principales.
- [x] Conexión, chats monitoreados, contactos, evento crudo, buzón de salida y mensajes originales modelados.
- [x] Lista permitida por defecto denegada, descarte de pegatinas e idempotencia.
- [x] Administración y auditoría versionada de grupos descubiertos.
- [x] Incremento de grupos validado: Pint 59 archivos, PHPUnit 20 pruebas/57 aserciones y SQL Server 4 pruebas/56 aserciones.
- [x] Contrato interno HMAC, ventana temporal, identificador único y protección antirrepetición persistente.
- [x] HMAC validado: PHPUnit 26 pruebas/65 aserciones y SQL Server 5 pruebas/84 aserciones.
- [x] Suite SQL Server repetible: 5 pruebas/84 aserciones aprobadas dos veces consecutivas sobre la misma `mensajero_test`.

## Regla de integración con WhatsApp
- [x] No usar Baileys, WPPConnect, whatsapp-web.js ni automatización no oficial de WhatsApp Web/protocolo privado.
- [x] No usar BSP/SaaS intermediario para la captura cuando MILAC puede integrarse directamente con Meta.
- [x] La dependencia externa permitida es la plataforma oficial Meta/WhatsApp mediante APIs y webhooks oficiales.
- [x] Decisión documentada en `docs/decision-integracion-whatsapp-oficial.md`.

## Incremento actual: Meta Cloud API oficial
- [x] Configuración para Graph API, App Secret, Verify Token, Access Token, WABA ID y Phone Number ID.
- [x] GET de verificación del webhook oficial.
- [x] Validación de `X-Hub-Signature-256` sobre el cuerpo crudo antes de procesar JSON.
- [x] POST oficial de webhook adaptado al núcleo de captura existente.
- [x] Resolución de conexión por `proveedor=meta_cloud_api` + Phone Number ID.
- [x] Descubrimiento de grupos oficiales cuando Meta entrega `group_id` o eventos `group_*`.
- [x] Grupos descubiertos nacen no monitoreados; la autorización existente se conserva en redescubrimientos.
- [x] Chats 1:1 desconocidos no se convierten automáticamente en chats monitoreados.
- [x] Cliente Graph API propio para consultar número y metadatos de medios.
- [x] Comando `mensajero:registrar-conexion-meta`.
- [x] Índice SQL Server para resolver conexiones Meta por proveedor + identificador externo.
- [x] Pruebas unitarias, funcionales e integración SQL Server agregadas para la ruta oficial.
- [ ] Ejecutar Pint y PHPUnit normal sobre el nuevo incremento.
- [ ] Ejecutar `phpunit.integracion.xml` dos veces consecutivas.
- [ ] Confirmar rutas GET/POST `/api/meta/whatsapp/webhook`.
- [ ] Crear/configurar aplicación en Meta Developers con número de prueba oficial.
- [ ] Verificar webhook real mediante HTTPS y suscribirse al campo `messages`.
- [ ] Probar un evento real oficial antes de registrar el número empresarial.

## Groups API
- [ ] Confirmar elegibilidad de la cuenta empresarial para Groups API.
- [ ] Confirmar tamaño y naturaleza de los grupos operativos requeridos.
- [ ] Validar si los grupos de trabajo pueden migrarse al modelo oficial de Groups API.
- [ ] No asumir que grupos normales preexistentes de WhatsApp Business App son accesibles desde Cloud API.

## Después de la primera recepción oficial
- [ ] Descargar foto/video/audio/documento solo después de confirmar que el chat está autorizado.
- [ ] Guardar multimedia en almacenamiento privado configurable; SQL Server conserva ruta y metadatos, no binarios.
- [ ] Implementar relevo recuperable del buzón de salida → Redis y normalizador idempotente.
- [ ] Construir vista de mensajes originales por grupo, remitente, fecha y tipo.
- [ ] Probar recuperación ante reinicios y pérdida temporal de conectividad.
- [ ] Integrar al servidor MILAC.

## Incidencia de infraestructura de CI
- [ ] Resolver `startup_failure` de GitHub Actions a nivel repositorio/organización/runner. El fallo ocurre antes de crear jobs y no constituye un fallo de PHPUnit/Pint.

## Fase 2
- [ ] Integrar Gemini sobre mensajes normalizados, manteniendo trazabilidad hacia el mensaje original.

## Regla de despliegue
No ejecutar `php artisan migrate` contra la base corporativa productiva desde desarrollo. Primero certificar cada incremento en una base `_test`, revisar nombres, permisos y plan de reversión. No registrar el número empresarial en una integración no oficial.
