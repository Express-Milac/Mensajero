# Ingesta durable de mensajes

## Entrada oficial

Los mensajes reales llegan desde WhatsApp Business Platform mediante el webhook oficial de Meta. El POST se autentica con `X-Hub-Signature-256` sobre el cuerpo crudo antes de interpretar JSON.

`ControladorWebhookWhatsapp` adapta el payload oficial a `EventoWhatsappEntrante`. Si el evento contiene `group_id`, el grupo se sincroniza como descubierto antes de aplicar la lista permitida. Los chats 1:1 desconocidos no se registran automáticamente como chats monitoreados.

## Núcleo implementado

`EventoWhatsappEntrante` representa el evento recibido y `CapturarEventoWhatsappEntrante` aplica:

1. localizar y bloquear el chat;
2. aplicar `FiltroMensajeEntrante`;
3. rechazar chat no autorizado o pegatina;
4. calcular clave SHA-256 de deduplicación;
5. crear `mensajero_eventos_crudos_whatsapp` y `mensajero_buzon_salida` en la misma transacción;
6. devolver `aceptado`, `duplicado` o `ignorado`.

La idempotencia externa se apoya en el identificador estable de mensaje de Meta (`wamid`) cuando existe.

## Contrato interno

El contrato HMAC `/api/pasarela/v1` se conserva para servicios propios de MILAC y no debe utilizarse como sustituto de los webhooks oficiales de Meta.

El relevo del buzón de salida hacia Redis sigue pendiente y deberá tolerar entrega repetida con consumidores idempotentes.
