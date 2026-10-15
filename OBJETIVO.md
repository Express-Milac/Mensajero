# Objetivo de Mensajero

Convertir conversaciones de WhatsApp de la operación de transporte en evidencia organizada
y, posteriormente, eventos operativos interpretados por Gemini y verificables por personas.

El número es WhatsApp Business y es compartido con conversaciones ajenas. Solo los 5–6 grupos
seleccionados deben generar almacenamiento y procesamiento. Esos grupos ya existen y tienen
al menos alrededor de 30 participantes, por lo que no se diseñará el producto suponiendo que
la Groups API oficial pueda reemplazarlos.

## Principios

WhatsApp es la fuente. RAW conserva evidencia. Gemini interpreta. Mensajero presenta.
El humano verifica. Primero capturar, conservar y trazar; después interpretar y presentar.

- Allowlist `default deny` antes de RAW, multimedia o IA.
- Stickers se descartan incluso en grupos autorizados.
- Tipos desconocidos de chats autorizados conservan RAW para evolución futura.
- SQL Server guarda datos, metadata y rutas; archivos viven en storage privado portable.
- RAW + outbox se confirman en una misma transacción antes de considerar durable el evento.
- Redis y Gemini nunca son condición para aceptar RAW.
- Cada evento IA deberá enlazar exactamente a sus mensajes fuente.
- La vista Mensajes Originales será permanente.
- El gateway WhatsApp será autohospedado por MILAC; no se utilizará un SaaS de terceros para
  capturar los grupos operativos.
- El dominio no conocerá la librería concreta del gateway: todo pasa por `WhatsAppProvider`.

## Infraestructura confirmada

Mensajero se desplegará en servidor propio de MILAC. Sus tablas vivirán en una base SQL Server
corporativa ya existente, aisladas mediante nombres `mensajero_*`; no se crea una base nueva
por defecto.

Temporalmente no existe login. `MENSAJERO_AUTH_MODE=disabled` permite avanzar en la captura.
La aplicación conserva una frontera de autorización para integrar después el login corporativo
sin reescribir rutas o lógica de negocio. El modo `corporate` falla cerrado hasta implementarse.

## Primer objetivo funcional

Captura RAW controlada: conexión, descubrimiento de grupos, allowlist administrable, recepción,
remitente, fecha UTC, citas, contenido original, multimedia fuera de SQL, consulta paginada,
búsqueda, filtros y actualización en tiempo real.

Aceptación mínima:

- Grupo A/B monitoreados: texto y multimedia se conservan.
- Grupo C ignorado: no crea RAW, no descarga archivos y no llega a IA.
- Sticker de un grupo monitoreado: no se almacena.
- Repetir un evento: no duplica RAW ni outbox.
- Desactivar un grupo: detiene nuevas capturas sin borrar evidencia previa.
- Caída de Redis/Gemini: no impide persistir RAW en SQL.
