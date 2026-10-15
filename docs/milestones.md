# Plan de entregas

No dar por terminado un milestone solo porque exista su diseño.

| Hito | Entrega verificable | Estado 15/10/2026 |
|---|---|---|
| 0 | Arquitectura, dominio, storage, contrato y análisis proveedor | Documentado; decisión autohospedada confirmada |
| 1 | Laravel, SQL Server, Redis, health y frontera de acceso | Base implementada; login corporativo pospuesto |
| 2 | Allowlist auditada + descubrimiento + fixtures | Core SQL/política implementado; UI/auditoría pendientes |
| 3 | RAW + outbox + idempotencia + descarte | Core transaccional implementado; API/relay y SQL real pendientes |
| 4 | Gateway propio y prueba real de grupos/reconexión | Pendiente |
| 5 | Storage privado configurable y portabilidad | Base configurable existente; pruebas futuras pendientes |
| 6 | Jobs multimedia y fallos | Pendiente |
| 7 | Dashboard, navegación y timeline RAW | Pendiente |
| 8 | Tiempo real, búsqueda y filtros | Pendiente |
| 9 | Ensayo A/B/C + sticker, recuperación y carga | Pendiente |
| 10 | Gemini desacoplado | Pendiente |
| 11 | Contexto incremental | Pendiente |
| 12 | Eventos + fuentes N:M | Pendiente |
| 13 | Vista IA + RAW permanente | Pendiente |
| 14 | Revisión humana auditada | Pendiente |

## Cambio de orden por decisión empresarial

La autenticación corporativa ya no bloquea la captura. Mientras MILAC redefine el login,
`MENSAJERO_AUTH_MODE=disabled` permite desarrollar dentro de un entorno controlado. La capa de
acceso permanece en middleware para activar después el modo corporativo sin modificar dominio.

Mensajero comparte una base existente: todos los objetos nuevos deben usar `mensajero_*`.
Milestone 4 no puede usar un proveedor SaaS de WhatsApp; el gateway deberá ejecutarse en
infraestructura MILAC.
