# Estado de Mensajero

Actualizado: 15/10/2026.

## Implementado en la rama de preparación

- Laravel 13 / PHP 8.4 y SQL Server como motor operativo.
- Redis para caché, sesiones temporales y colas.
- Almacenamiento privado configurable con Flysystem.
- Acceso temporal sin login; administración restringida por IP configurable.
- Modo local de autenticación conservado únicamente para desarrollo/pruebas.
- Modo corporativo preparado para fallar cerrado hasta implementar el adaptador definitivo.
- Tablas y columnas propias en español y prefijo `mensajero_`.
- Modelos de conexión, conversación monitoreada, contacto, evento crudo, bandeja de salida y mensaje.
- Lista blanca con denegación por defecto y descarte de adhesivos.
- Captura de evidencia cruda + bandeja de salida en una transacción SQL Server.
- Deduplicación e idempotencia.
- Tablero compatible con ausencia de usuario autenticado.
- Agentes de creación, revisión, pruebas, seguridad y UI/UX documentados y aplicados al refactor.
- Pruebas unitarias, HTTP y de integración SQL Server preparadas.
- Validación continua configurada para calidad e integración SQL Server temporal.

## Hallazgos corregidos por los agentes

1. El tablero anterior asumía que siempre existía un usuario autenticado y podía provocar error 500 con login deshabilitado.
2. El modo sin login permitía también rutas administrativas; ahora estas exigen una IP incluida en la lista administrativa temporal.
3. La base inicial creaba nombres genéricos/ingleses y tablas de framework innecesarias dentro de una base corporativa; se reemplazaron por tablas propias en español y Redis para caché/sesiones/colas.
4. Las clases inglesas del dominio anterior fueron retiradas para evitar que pruebas o código siguieran usando accidentalmente el modelo viejo.
5. Se agregó una prueba automática que falla si reaparecen nombres antiguos o tablas propias sin prefijo `mensajero_`.

## Pendiente antes del servidor

- Conseguir que la validación continua quede completamente en verde.
- Corregir cualquier hallazgo que surja de PHPUnit, Pint, vistas, rutas, Composer o SQL Server temporal.
- Cerrar la revisión documental y de seguridad.
- Integrar el cambio aprobado a la rama principal.

## Aún no implementado

- Administración visual completa de conversaciones monitoreadas.
- API interna firmada puerta de enlace ↔ Laravel.
- Relé de bandeja de salida hacia Redis.
- Puerta de enlace WhatsApp autohospedada.
- Descarga real de multimedia.
- Línea de tiempo de mensajes originales.
- Tiempo real operativo.
- Gemini y eventos de IA.
- Revisión humana de eventos.
- Login corporativo definitivo.

No se ha conectado el número empresarial, no se han ejecutado migraciones productivas y no se ha desplegado esta rama al servidor de MILAC.
