# Arquitectura de Mensajero

Mensajero es un monolito modular Laravel/PHP con SQL Server corporativo como motor operativo y Redis para trabajo asíncrono. La única pieza separada prevista es la pasarela de WhatsApp autohospedada por MILAC.

## Flujo de captura
WhatsApp Business → pasarela MILAC → contrato neutral → lista permitida → evento crudo + buzón de salida en SQL Server → procesamiento posterior.

La lista permitida se evalúa antes de guardar contenido. Un chat desconocido, inactivo o no monitoreado se descarta. Las pegatinas se descartan. Un tipo futuro de mensaje sí puede conservarse cuando el chat está autorizado.

`CapturarEventoWhatsappEntrante` bloquea la fila de `mensajero_chats_monitoreados`, verifica autorización, deduplica y crea el evento crudo y el mensaje del buzón de salida dentro de una misma transacción. Redis y Gemini quedan fuera de esa transacción.

## Datos
Tablas principales: `mensajero_conexiones_whatsapp`, `mensajero_chats_monitoreados`, `mensajero_contactos_whatsapp`, `mensajero_eventos_crudos_whatsapp`, `mensajero_buzon_salida` y `mensajero_mensajes_whatsapp`.

Los archivos no se guardan como binarios en SQL Server. Se usa almacenamiento privado configurable y la base conserva metadatos/rutas.

## Acceso
`MENSAJERO_MODO_AUTENTICACION=deshabilitado` permite temporalmente el tablero ordinario dentro de una red controlada, pero no funciones administrativas. `corporativo` falla cerrado hasta implementar la identidad MILAC.
