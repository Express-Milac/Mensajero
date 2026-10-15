# Contrato e ingestión durable

## Contrato v1 previsto

JSON UTF-8 con conexión confiable, `event_id`, `chat_id`, `event_type`, `message_id`, remitente,
timestamp, tipo, texto/caption, cita, descriptor de media y objeto RAW original.

No enviar binarios/base64 dentro del request. Sticker se rechaza antes de RAW. Un tipo
desconocido de un chat autorizado se conserva.

## Core implementado

`IncomingWhatsappEvent` representa el evento interno aceptado por captura.

`CaptureIncomingWhatsappEvent` ejecuta:

```text
buscar chat + lock
    ↓
InboundMessageGate
    ↓
¿autorizado?
   no ──► ignored
   sí
    ↓
deduplicación
    ↓
RAW + OUTBOX
misma transacción SQL
```

Resultados: `accepted`, `duplicate` o `ignored`.

La clave de deduplicación es SHA-256. SQL Server tiene constraints únicos como última defensa
ante carreras.

## API interna pendiente

Previstas: `POST /api/internal/v1/events`, `GET /api/internal/v1/allowlist` y
`POST /api/internal/v1/chats`.

Seguridad: TLS + HMAC SHA-256 con timestamp, nonce, método, ruta y hash del body; ventana
temporal y protección anti-replay.

Semántica: 202 durable, 200 duplicado, 204 ignorado, 401 firma, 422 contrato, 429 backoff y
503 no confirmado. ACK significa durable, no normalizado.

## Outbox

El relay a Redis sigue pendiente. Publicar y marcar son operaciones separadas, por lo que el
relay puede entregar dos veces; los consumidores posteriores deben ser idempotentes. Redis no
es la copia primaria de trabajo pendiente.
