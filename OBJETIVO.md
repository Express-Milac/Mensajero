# Objetivo de Mensajero

Convertir conversaciones de WhatsApp de la operación de transporte en evidencia organizada
y, posteriormente, eventos operativos interpretados por Gemini y verificables por personas.

El número es compartido con conversaciones ajenas. Solo aproximadamente 5–6 grupos
seleccionados deben generar almacenamiento y procesamiento. Su selección será administrable
por ID real del proveedor, nunca por nombres o IDs hardcodeados.

## Principios

WhatsApp es la fuente. RAW conserva evidencia. Gemini interpreta. Mensajero presenta.
El humano verifica. Primero capturar, conservar y trazar; después interpretar y presentar.

- Allowlist default deny; ignorar chats no autorizados y stickers antes de persistencia.
- Conservar tipos desconocidos de chats autorizados para poder normalizarlos después.
- SQL Server almacena datos, metadata y rutas; archivos viven en storage privado portable.
- Confirmar recepción después de RAW + outbox durable; procesos reintentables e idempotentes.
- Mantener las vistas Mensajero IA y Mensajes originales permanentemente.
- Cada evento IA enlaza exactamente a sus mensajes fuente; nunca modifica la evidencia.
- Gemini caído no impide captura, multimedia ni consulta de originales.

## Primer objetivo funcional: captura RAW controlada

Login corporativo con permisos propios, estado de conexión, descubrimiento y selección de
grupos, recepción de mensajes, remitentes, fechas UTC, citas y contenido original. Fotografías,
videos, audio y documentos fuera de SQL. Consulta paginada, búsqueda, filtros, actualizaciones
en tiempo real y errores visibles. Stickers y chats no monitoreados no generan contenido,
descargas, procesamiento IA ni eventos.

## Aceptación operativa

Grupo A y B monitoreados: texto/foto y texto/audio se conservan con identidad, RAW y rutas
de archivos comprobables. Grupo C ignorado: texto/video no se guardan ni descargan. Sticker
de A: se descarta. Repetir un evento no duplica el mensaje. Mensajes tardíos se ordenan por
sent_at; respuestas conservan referencia. Cambiar root de storage no modifica servicios.

Después: contexto incremental, salida Gemini estructurada validada, eventos con fuentes N:M
y corrección humana auditada. Nunca considerar una maqueta como aceptación de captura real.

## Alcance tecnológico

Laravel/PHP 8.4+, SQL Server, Redis, Flysystem, Blade/Livewire/Alpine. Monolito modular con
gateway WhatsApp Node/TypeScript separado cuando corresponda. Evitar microservicios extra.
Los detalles y fases están en docs/architecture.md y docs/milestones.md.
