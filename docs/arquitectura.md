# Arquitectura de Mensajero

Mensajero es un monolito modular Laravel/PHP con SQL Server corporativo como motor operativo y Redis para trabajo asíncrono. La integración de WhatsApp se realiza directamente desde Laravel contra WhatsApp Business Platform Cloud API y webhooks oficiales de Meta.

## Flujo de captura

WhatsApp / Meta → webhook oficial firmado → adaptador Meta en Laravel → lista permitida → evento crudo + buzón de salida en SQL Server → procesamiento posterior.

La autenticidad del webhook se verifica mediante `X-Hub-Signature-256` antes de interpretar el cuerpo. La firma oficial de Meta no concede autorización de negocio: la lista permitida se evalúa después y antes de guardar contenido.

Un chat desconocido, inactivo o no monitoreado se descarta. Las pegatinas se descartan. Un tipo futuro de mensaje sí puede conservarse cuando el chat está autorizado.

`CapturarEventoWhatsappEntrante` bloquea la fila de `mensajero_chats_monitoreados`, verifica autorización, deduplica y crea el evento crudo y el mensaje del buzón de salida dentro de una misma transacción. Redis y Gemini quedan fuera de esa transacción.

## Datos

Tablas principales: `mensajero_conexiones_whatsapp`, `mensajero_chats_monitoreados`, `mensajero_contactos_whatsapp`, `mensajero_eventos_crudos_whatsapp`, `mensajero_buzon_salida` y `mensajero_mensajes_whatsapp`.

`mensajero_conexiones_whatsapp.id_cuenta_externa` contiene el Phone Number ID oficial cuando `proveedor=meta_cloud_api`. Los detalles adicionales, como WABA ID, se conservan en metadatos.

Los archivos no se guardan como binarios en SQL Server. Se usa almacenamiento privado configurable y la base conserva metadatos/rutas.

## Fronteras

La Cloud API de Meta es el proveedor oficial externo. No se usa un BSP/SaaS intermediario ni una biblioteca que emule WhatsApp Web. El contrato HMAC interno existente permanece reservado para integraciones entre servicios propios de MILAC y no sustituye la firma oficial de Meta.

## Acceso

`MENSAJERO_MODO_AUTENTICACION=deshabilitado` permite temporalmente el tablero ordinario dentro de una red controlada, pero no funciones administrativas. `corporativo` falla cerrado hasta implementar la identidad MILAC.
