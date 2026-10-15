# Ingesta durable de mensajes

## Contrato interno previsto
La pasarela entregará un objeto versionado con conexión, chat, tipo de evento, identificador externo, identificador del mensaje, remitente, fecha, contenido y descriptor de multimedia. No enviará binarios en base64 dentro del cuerpo principal.

## Núcleo implementado
`EventoWhatsappEntrante` representa el evento recibido y `CapturarEventoWhatsappEntrante` aplica:

1. localizar y bloquear el chat;
2. aplicar `FiltroMensajeEntrante`;
3. rechazar chat no autorizado o pegatina;
4. calcular clave SHA-256 de deduplicación;
5. crear `mensajero_eventos_crudos_whatsapp` y `mensajero_buzon_salida` en la misma transacción;
6. devolver `aceptado`, `duplicado` o `ignorado`.

## Punto de entrada pendiente
La API interna pasarela → Laravel deberá usar TLS y HMAC SHA-256 con fecha, identificador único y ventana antirrepetición. La confirmación significará “persistido de forma durable”, no “procesado completamente”.

El relevo del buzón de salida hacia Redis sigue pendiente y deberá tolerar entrega repetida con consumidores idempotentes.
