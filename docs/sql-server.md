# Modelo SQL Server

Motor operativo exclusivo: Microsoft SQL Server. Laravel requiere `sqlsrv`, `pdo_sqlsrv`
y Microsoft ODBC Driver; ver [documentación Laravel](https://laravel.com/framework/docs/database/config).
No usar SQLite para certificar migraciones SQL Server ni JSONB. Texto largo y JSON:
NVARCHAR(MAX), con validación JSON de aplicación y CHECK ISJSON cuando corresponda.
Tiempos: datetime2 UTC. IDs internos bigint identity; referencias proveedor son strings
opacos (incluyendo identificadores LID), nunca números telefónicos como PK.

## Modelo objetivo (milestones 2–14)

| Tabla | Claves, relaciones y datos relevantes |
|---|---|
| whatsapp_connections | proveedor, cuenta externa, estado, heartbeat, sin credenciales |
| connection_states | conexión, estado, fecha y código de error sanitizado |
| monitored_chats | conexión + ID externo únicos; tipo, nombres, monitored=false, active=true, versión de autorización |
| whatsapp_groups | chat único, metadata mínima, fechas; participantes solo si son necesarios |
| whatsapp_contacts | conexión + ID externo únicos, teléfono nullable, nombres, metadata |
| whatsapp_raw_events | chat, ID de evento, payload original, checksum SHA256, received_at, versión, estado y error |
| whatsapp_messages | chat, contacto, RAW, ID externo, tipo, texto, caption, sent_at/received_at/processed_at, ID citado externo y FK nullable |
| whatsapp_media | mensaje, ID proveedor, disk, path relativo, MIME, bytes, checksum, dimensiones, duración, estado separado |
| outbox | tipo, aggregate_id, payload mínimo, available_at, lease_until, attempts, dispatched_at |
| ai_runs | chat, ventana, cursor, modelo, prompt_version, processing_version, generated_at, entrada referenciada y respuesta original |
| ai_events | chat, run, tipo, título, resumen, fechas, entidades JSON, severidad, confianza, estado |
| ai_event_messages | PK compuesta evento/mensaje, rol de evidencia |
| ai_event_revisions | evento, original IA, cambio humano, actor, fecha, motivo, versión |
| chat_user | PK chat/usuario, autorización explícita futura |
| audit_entries | actor, acción, sujeto, cambios y fecha; append-only |

Identidad de mensajes: UNIQUE(connection_id, chat_id, provider_message_id). Identidad RAW:
UNIQUE(connection_id, external_event_id); si el proveedor no suministra evento estable, usar
hash canónico de identidad, tipo, versión de edición y contenido. Una edición no es duplicado
del mensaje inicial. Comparar IDs externos con collation binaria; no asumir case-insensitive.
Limitar longitud indexada y usar hash SHA256 si excede presupuesto de bytes del índice.

Índices: mensajes(chat_id, sent_at, id), mensajes(sender_id, sent_at),
raw(processing_status, received_at), media(download_status, updated_at),
outbox(dispatched_at, available_at), ai_events(chat_id, event_at, id).
Paginación keyset por (sent_at,id), búsqueda parametrizada inicial; full-text después de medir.
No cascadas múltiples: SQL Server puede rechazarlas. Preferir NO ACTION en evidencia.

## Milestone 1

Migraciones iniciales: usuarios, roles y tablas framework. No confundir este modelo objetivo
con tablas ya implementadas. Las migraciones operativas llegan por milestone, con pruebas
contra SQL Server real. Credenciales de test deben apuntar a base desechable independiente.
