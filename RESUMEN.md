# Estado y traspaso a otra IA

Actualizado: 2026-10-15.

## Decisiones confirmadas por negocio

- Número: WhatsApp Business.
- Grupos operativos existentes: al menos ~30 participantes.
- Captura WhatsApp: sin SaaS/proveedor administrado; gateway autohospedado por MILAC.
- Base: SQL Server corporativa existente, no una base nueva dedicada.
- Hosting: servidor propio de MILAC.
- Login: pospuesto. El sistema funciona temporalmente sin login y debe quedar preparado para
  integrar la identidad corporativa después.

## Implementado

- Laravel 13 / PHP 8.4, Livewire, Redis y Flysystem configurables.
- `MENSAJERO_AUTH_MODE=disabled|local|corporate`; `corporate` falla cerrado hasta implementar
  el adaptador, por lo que no puede convertirse accidentalmente en bypass.
- Todas las tablas propias y de framework usan prefijo `mensajero_`; el historial de
  migraciones usa `mensajero_migrations`, evitando colisiones en la BD compartida.
- Esquema inicial SQL Server para conexión WhatsApp, allowlist, contactos, RAW, outbox y
  mensajes originales.
- `InboundMessageGate` aplica default deny y descarte de stickers.
- `CaptureIncomingWhatsappEvent` persiste RAW + outbox en una sola transacción, deduplica
  reintentos y conserva tipos desconocidos cuando el chat sí está autorizado.
- Tests aislados para política de captura y tests SQL opt-in para esquema/pipeline.

## No implementado todavía

- Gateway Node/TypeScript real y sesión WhatsApp Business.
- Descubrimiento real de grupos y pantalla para activar/desactivar monitoreo.
- API interna HMAC gateway ↔ Laravel.
- Relay outbox → Redis y workers de normalización.
- Descarga/visualización multimedia.
- Timeline RAW, búsqueda, filtros y broadcasting.
- Gemini y revisión humana.
- Login corporativo definitivo.

## Riesgo WhatsApp

Los grupos existentes son grandes y no debe asumirse compatibilidad con la Groups API oficial.
El camino elegido es un gateway autohospedado de WhatsApp Web detrás de `WhatsAppProvider`.
Una librería open-source puede usarse como dependencia local, pero no se permitirá que el
dominio dependa de ella. Antes de usar el número empresarial se probará con una sesión/cuenta
controlada: grupos, multimedia, citas, reinicio, desconexión, deduplicación y reconexión.

## Siguiente paso

1. Completar Milestone 2 con descubrimiento/administración de allowlist.
2. Exponer contrato interno HMAC y conectar el servicio RAW existente.
3. Implementar gateway autohospedado en `services/whatsapp-gateway`.
4. Probar con una cuenta/sesión controlada antes del número productivo.
