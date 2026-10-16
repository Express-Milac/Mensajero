# Arquitectura de Mensajero

Mensajero es un monolito modular Laravel/PHP con SQL Server corporativo como motor operativo y Redis para trabajo asíncrono. Dispone de dos canales de entrada desacoplados: integración oficial Meta Cloud API para los flujos soportados oficialmente y un recolector aislado para el POC de grupos normales existentes.

## Flujo oficial
Meta/WhatsApp Cloud API → webhook firmado → lista permitida → evento crudo + buzón de salida en SQL Server → procesamiento posterior.

## Flujo de grupos normales
Número recolector corporativo dedicado → sesión multidispositivo aislada → `recolector-whatsapp` → cola durable local → contrato HMAC interno → lista permitida → evento crudo + buzón de salida.

El número principal de MILAC no se vincula al recolector no oficial. El recolector solo se habilita con un número secundario reemplazable y comienza como POC.

## Integridad
La lista permitida se evalúa antes de persistir eventos en Laravel. El recolector mantiene además una copia local de la lista permitida para descartar grupos no autorizados antes de transmitirlos.

Cada mensaje autorizado se persiste primero en una cola local en disco. Si Laravel, SQL Server o la red no responden, la tarea permanece y se reintenta. La idempotencia del núcleo evita duplicar evidencia.

Las desconexiones y reinicios no limpios se registran en `mensajero_periodos_integridad_whatsapp`. Un intervalo con integridad no confirmada no debe presentarse como completo en reportes directivos.

`CapturarEventoWhatsappEntrante` bloquea la fila de `mensajero_chats_monitoreados`, verifica autorización, deduplica y crea el evento crudo y el mensaje del buzón de salida dentro de una misma transacción. Redis y Gemini quedan fuera de esa transacción.

## Datos
Tablas principales: `mensajero_conexiones_whatsapp`, `mensajero_chats_monitoreados`, `mensajero_contactos_whatsapp`, `mensajero_eventos_crudos_whatsapp`, `mensajero_buzon_salida`, `mensajero_mensajes_whatsapp` y `mensajero_periodos_integridad_whatsapp`.

Los archivos no se guardan como binarios en SQL Server. Se usa almacenamiento privado configurable y la base conserva metadatos/rutas.

## Acceso
`MENSAJERO_MODO_AUTENTICACION=deshabilitado` permite temporalmente el tablero ordinario dentro de una red controlada, pero no funciones administrativas. `corporativo` falla cerrado hasta implementar la identidad MILAC.
