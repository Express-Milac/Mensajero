# Contrato y entrega

Contrato v1: JSON UTF-8, conexión conocida, event_id estable, chat_id opaco, event_type,
message_id, sender{id,phone?,display_name?}, timestamp RFC3339 con zona, type, text?, caption?,
quoted_message_id?, media{provider_media_id,mime_type?,size?}?, raw (objeto original).
No enviar binarios/base64 ni URL arbitraria de descarga. Tamaño máximo de request definido
antes de parsear. Unknown conserva el payload; sticker se rechaza antes de persistir.

API interna prevista: POST /api/internal/v1/events, GET /api/internal/v1/allowlist y
POST /api/internal/v1/chats para metadata de descubrimiento. TLS y HMAC SHA256 sobre
timestamp + nonce + método + ruta + SHA256(body), con comparación constante, ventana de
5 minutos y protección de replay. Cada reintento usa nonce nuevo y mismo event_id.
Secretos por conexión y rotación; no confiar en connection_id aportado sin vincular firma.

202: RAW/outbox confirmados; 200: duplicado durable; 204: ignorado; 422: contrato inválido;
401: firma; 429: backoff; 503: no confirmado, reintentar. ACK no significa normalizado.
Nunca registrar body ni Authorization en logs. Persistir bytes originales aceptados y hash;
normalización es una proyección separada, no reescritura de evidencia.

Transacción SQL: verificar allowlist bloqueada, insertar RAW y outbox. Relay reclama lote
con lease, publica Redis, marca envío; caída entre publicar y marcar duplica entrega,
no contenido. Workers con unique constraints y transacciones; citas fuera de orden se
resuelven al llegar el original. Ordenar sent_at,id; ausencia de sent_at se marca explícitamente.

Retries exponenciales con jitter, timeout menor que retry_after; límite y cola de fallos.
Reprocesar desde RAW por ID/version, con auditoría. Captura, media e IA tienen estados propios.
Alertas: edad del RAW más antiguo pendiente, retraso outbox, fallos, latencia, duplicados,
descartes agregados sin identificación personal y último heartbeat.
