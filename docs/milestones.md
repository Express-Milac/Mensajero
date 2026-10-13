# Plan de entregas

No dar por terminado un milestone solo porque exista su diseño.

| Hito | Entrega verificable | Estado inicial |
|---|---|---|
| 0 | Arquitectura, dominio, modelo, storage, contrato, análisis proveedor | Documentado; compatibilidad de cuenta real pendiente |
| 1 | Laravel, SQL Server, login/roles, Redis, health y pruebas | Base implementada; 9 tests aislados pasan; SQL/Redis reales pendientes |
| 2 | Administración de allowlist auditada + fixtures | Pendiente |
| 3 | RAW + outbox + idempotencia + pruebas de descarte | Pendiente |
| 4 | Gateway neutral y prueba real de grupos/reconexión | Pendiente |
| 5 | Storage privado configurable y pruebas de portabilidad | Pendiente |
| 6 | Jobs de multimedia y pruebas de fallos | Pendiente |
| 7 | Dashboard, navegación y timeline RAW paginada | Pendiente |
| 8 | Broadcasting privado, búsqueda y filtros | Pendiente |
| 9 | Ensayo A/B/C + sticker, recuperación, backups y carga | Pendiente |
| 10 | Gemini desacoplado, schema, retries y versionado | Pendiente |
| 11 | Contexto incremental, citas y mensajes tardíos | Pendiente |
| 12 | Eventos operativos con fuentes N:M verificadas | Pendiente |
| 13 | Vista IA + RAW permanente y fuentes exactas | Pendiente |
| 14 | Confirmar/corregir/descartar/fusionar/dividir auditado | Pendiente |

Cada entrega incorpora código, documentación actualizada y evidencia de pruebas. Antes de
milestone 4 se requiere una sesión de prueba y comprobar capacidades reales del proveedor.
