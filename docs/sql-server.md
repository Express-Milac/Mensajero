# Modelo SQL Server

Motor operativo exclusivo: Microsoft SQL Server. Mensajero **no tendrá una base dedicada por
defecto**: sus objetos vivirán dentro de una base corporativa existente configurada en `.env`.

## Aislamiento obligatorio

Para no interferir con otros sistemas:

- historial Laravel: `mensajero_migrations`;
- framework: `mensajero_users`, `mensajero_sessions`, `mensajero_cache`, `mensajero_jobs`, etc.;
- dominio: todas las tablas comienzan con `mensajero_`.

No configurar `prefix` global en la conexión SQL porque en el futuro Mensajero podría requerir
lecturas controladas de tablas corporativas existentes; el aislamiento se realiza mediante
nombres explícitos.

## Tablas implementadas

- `mensajero_whatsapp_connections`
- `mensajero_monitored_chats`
- `mensajero_whatsapp_contacts`
- `mensajero_whatsapp_raw_events`
- `mensajero_outbox`
- `mensajero_whatsapp_messages`

La allowlist es `default deny`: `is_monitored` nace en `false`.

IDs internos: bigint identity. IDs de WhatsApp: cadenas opacas con collation binaria; no
suponer que el teléfono es la identidad primaria. Texto largo/JSON serializado: NVARCHAR(MAX).
Tiempos operativos: datetime2/UTC.

RAW contiene `deduplication_key`, `payload_sha256`, payload original y estado independiente.
Outbox vive en la misma transacción que RAW y tiene disponibilidad, lease, intentos y despacho.
Mensajes tienen identidad única por conexión/chat/provider_message_id, referencia a RAW y
estados separados de captura, media e IA.

## Validación

Las migraciones nuevas no se certifican con SQLite. Antes de producción:

1. usar SQL Server desechable cuyo nombre termine en `_test`;
2. ejecutar `php artisan migrate --force`;
3. correr `phpunit.integration.xml`;
4. revisar constraints e índices reales;
5. solo entonces preparar instalación en la BD corporativa.

Nunca ejecutar las pruebas de integración contra la base productiva.
