# Tareas de continuidad

Estado: 2026-10-15. `[x]` significa código entregado en el alcance indicado; no equivale a
certificación en producción.

## Entregado

- [x] M0-01 Arquitectura, SQL Server, storage, contrato de ingestión y análisis proveedor.
- [x] M1-00 Base Laravel 13/PHP 8.4, Redis/storage/health y autenticación local provisional.
- [x] DEC-01 Registrar decisiones: WhatsApp Business, grupos 30+, gateway autohospedado,
  servidor MILAC, BD corporativa existente y login pospuesto.
- [x] DB-01 Aislar tablas propias/framework con prefijo `mensajero_` y migraciones separadas.
- [x] AUTH-00 Introducir `MENSAJERO_AUTH_MODE`; `disabled` temporal y `corporate` fail-closed.
- [x] M2-CORE Modelo SQL y dominio inicial de allowlist default deny.
- [x] M3-CORE RAW + outbox transaccional, deduplicación y política de stickers.

## Prioridad inmediata

- [ ] M2-UI Descubrimiento de grupos/chats y pantalla para activar/desactivar monitoreo.
- [ ] M2-AUDIT Auditoría de cambios de allowlist e incremento de `authorization_version`.
- [ ] M3-API Endpoint interno versionado gateway → backend con HMAC, nonce, ventana anti-replay
  y respuestas 202/200/204/422/401/503 según contrato.
- [ ] M3-RELAY Relay recuperable outbox → Redis y normalizador idempotente hacia mensajes.
- [ ] M3-SQL Ejecutar migraciones y tests únicamente contra SQL Server desechable `_test`.

## Gateway propio

- [ ] M4-01 Crear `services/whatsapp-gateway` Node.js/TypeScript.
- [ ] M4-02 Implementar `WhatsAppProvider` neutral y adaptador WhatsApp Web autohospedado.
- [ ] M4-03 Guardar sesión fuera de Git, restringir sincronización histórica y no persistir
  contenido de chats no autorizados.
- [ ] M4-04 Probar grupos reales de laboratorio (30+ participantes cuando sea posible),
  texto, cita, foto, audio, video, documento, reinicio, desconexión y reconexión.
- [ ] M4-05 Solo después de la prueba controlada evaluar vincular el número empresarial.

## Después

- [ ] M5–M6 Storage portable y multimedia asíncrona.
- [ ] M7–M8 Dashboard/timeline RAW, filtros, búsqueda y tiempo real.
- [ ] M9 Ensayo A/B/C + sticker, fallos y recuperación.
- [ ] M10–M14 Gemini, contexto incremental, eventos N:M y revisión humana.
- [ ] AUTH-FUTURE Integración definitiva del login corporativo cuando MILAC estabilice su
  mecanismo de identidad; retirar modo `disabled` antes de exposición no controlada.

## Regla de despliegue

No ejecutar `php artisan migrate` contra la BD corporativa productiva desde desarrollo. Primero
certificar las migraciones en una base SQL Server desechable `_test` y revisar nombres/permiso.
