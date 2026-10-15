# Modelo SQL Server

Mensajero utiliza exclusivamente Microsoft SQL Server para datos estructurados. Las tablas viven dentro de una base corporativa existente y todas usan prefijo `mensajero_`.

## Tablas actuales

- `mensajero_migraciones`
- `mensajero_usuarios`
- `mensajero_conexiones_whatsapp`
- `mensajero_conversaciones_monitoreadas`
- `mensajero_contactos_whatsapp`
- `mensajero_eventos_crudos_whatsapp`
- `mensajero_bandeja_salida`
- `mensajero_mensajes_whatsapp`

Redis se usa para caché, sesiones temporales y colas, por lo que no se crean tablas SQL de framework para esas funciones.

## Reglas

- Identificadores internos: `bigint identity`.
- Identificadores externos: cadenas opacas; nunca asumir que el teléfono es la clave primaria.
- Fechas operativas: UTC y precisión suficiente mediante `datetime2`.
- Contenido JSON/crudo: `NVARCHAR(MAX)`; no usar JSONB.
- Relaciones de evidencia: evitar cascadas destructivas; preferir `NO ACTION`.
- Identidad cruda: clave de deduplicación SHA-256 por conexión.
- Mensajes: unicidad por conexión, conversación e ID del proveedor.
- Índices orientados a conversación/fecha, remitente/fecha y estados de procesamiento.

Las migraciones de integración solo se prueban contra una base desechable cuyo nombre termine en `_test`.
