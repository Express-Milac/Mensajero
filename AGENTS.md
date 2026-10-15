# Mensajero: instrucciones del proyecto

## Lectura obligatoria

Leer en orden: README.md, OBJETIVO.md, RESUMEN.md, TASKS.md, docs/milestones.md,
docs/architecture.md, docs/sql-server.md, docs/whatsapp-provider.md y
docs/message-ingestion.md.

## Decisiones empresariales vigentes

- WhatsApp Business.
- Grupos existentes con al menos ~30 participantes.
- Sin proveedor/SaaS de terceros para captura WhatsApp.
- Gateway Node/TypeScript autohospedado por MILAC detrás de `WhatsAppProvider`.
- Servidor propio de MILAC.
- Tablas dentro de una BD SQL Server corporativa existente, siempre `mensajero_*`.
- Login pospuesto: `MENSAJERO_AUTH_MODE=disabled`. Conservar frontera para integración futura.
  `corporate` debe fallar cerrado mientras el adaptador no esté implementado.

Estas decisiones sustituyen la prioridad anterior de implementar el login corporativo antes
de la captura.

## Responsabilidades

- Arquitectura: monolito modular, contratos versionados y cambios reemplazables.
- Desarrollo: incrementos funcionales, idempotentes y probados.
- Datos: constraints, índices, transacciones, SQL Server real y evidencia inmutable.
- Infraestructura: configuración portable, workers, backups, health y despliegue.
- Seguridad: secretos fuera de Git, gateway autenticado, mínimo privilegio y auditoría.

## Protocolo

1. Revisar estado Git y TASKS antes de modificar.
2. Trabajar por milestone; no implementar Gemini antes de estabilizar RAW.
3. Resolver decisiones menores técnicamente.
4. Ejecutar validaciones pertinentes y registrar lo realmente ejecutado.
5. Actualizar RESUMEN/TASKS/milestones.
6. No desplegar, migrar producción ni vincular el número empresarial como efecto secundario
   de publicar código.
7. Revisar secretos, sesiones, payloads y evidencia antes de commit.

## Invariantes

- SQL Server único motor operativo.
- No PostgreSQL ni SQLite como sustituto de certificación SQL Server.
- Todas las tablas de Mensajero usan prefijo `mensajero_`.
- Allowlist default deny antes de RAW, descargas o IA.
- Sticker se descarta.
- Tipo desconocido de chat autorizado conserva RAW.
- RAW + outbox antes de ACK.
- Redis y Gemini no son condición para RAW.
- Archivos privados mediante Flysystem; SQL guarda metadata/rutas, nunca binarios.
- El gateway es el único componente acoplado a la librería concreta de WhatsApp.
- No conectar el número empresarial hasta completar prueba controlada de Milestone 4.
- Login deshabilitado solo es aceptable durante esta etapa/control de red; antes de una
  exposición no controlada debe integrarse autenticación definitiva.
