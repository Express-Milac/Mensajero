# Tareas de continuidad

Estado: 15/10/2026. `[x]` significa código entregado y validado en el alcance indicado; no equivale a certificación en producción.

## Entregado
- [x] Arquitectura base sobre SQL Server corporativo, Redis y almacenamiento privado configurable.
- [x] Decisiones de negocio registradas: WhatsApp Business, grupos 30+, pasarela autohospedada, servidor MILAC y autenticación pospuesta.
- [x] Nomenclatura de negocio migrada al español en tablas, columnas propias, clases, rutas, estados y pruebas principales.
- [x] Conexión, chats monitoreados, contactos, evento crudo, buzón de salida y mensajes originales modelados.
- [x] Lista permitida por defecto denegada, descarte de pegatinas e idempotencia.
- [x] Agentes de arquitectura, creación, revisión, pruebas, seguridad, UI/UX y calidad definidos y aplicados.
- [x] Corrección del tablero sin usuario, validación de administrador y bloqueo de diagnóstico administrativo cuando no hay autenticación.
- [x] `Usuario`, fábrica, migración, comando y pruebas alineados con `nombre`/marcas de tiempo propias en español.
- [x] Estructura propia de autenticación movida de `Auth`/`auth` a `Autenticacion`/`autenticacion`.
- [x] Flujo de CI definido para Composer/PHPUnit/Pint/rutas/vistas y YAML validado estructuralmente.
- [x] Puerta local completa en PHP 8.4: Composer, Pint, rutas, vistas y Vite aprobados.
- [x] Runtime local confirmado con `mbstring`, `sqlsrv`, `pdo_sqlsrv` y ODBC Driver 18.
- [x] Base de administración de grupos descubiertos con lista permitida denegada por defecto.
- [x] Auditoría versionada de cambios de monitoreo y comando de consola para la etapa sin autenticación administrativa.
- [x] Incremento de grupos validado: Pint 59 archivos, PHPUnit 20 pruebas/57 aserciones y SQL Server 4 pruebas/56 aserciones.

## Incidencia de infraestructura de CI
- [ ] Resolver `startup_failure` de GitHub Actions a nivel repositorio/organización/runner. El fallo ocurre antes de crear jobs y no constituye un fallo de PHPUnit/Pint.
- [ ] Revisar desde GitHub la configuración administrativa de permisos/uso de Actions; el conector disponible no expone esa configuración.

## Puerta previa a integración al servidor
- [x] `composer validate --strict` y `composer install`.
- [x] PHPUnit de unidad y funcional.
- [x] Pint.
- [x] Rutas, vistas y construcción Vite.
- [x] Migraciones y `phpunit.integracion.xml` contra SQL Server desechable `mensajero_test`.
- [x] `APP_TIMEZONE` respetado desde configuración y entorno local separado de producción.

## Incremento actual: pasarela segura
- [x] Contrato versionado `/api/pasarela/v1` para chats descubiertos y eventos.
- [x] Firma HMAC-SHA256 sobre identificador, marca de tiempo y hash del cuerpo.
- [x] Ventana temporal configurable y secreto externo al repositorio.
- [x] Persistencia SQL Server del identificador de solicitud para protección antirrepetición resistente a reinicios.
- [x] La lista permitida permanece como segunda barrera y continúa denegada por defecto.
- [ ] Ejecutar Pint, PHPUnit y la nueva prueba `PasarelaSeguraSqlServerTest` contra `mensajero_test` y ajustar cualquier fallo.

## Después de certificar la pasarela
- [ ] Implementar relevo recuperable del buzón de salida → Redis y normalizador idempotente.
- [ ] Crear pasarela Node.js/TypeScript autohospedada y adaptador neutral de WhatsApp.
- [ ] Conectar descubrimiento real de grupos/chats al servicio ya preparado.
- [ ] Probar una cuenta/sesión controlada antes de considerar el número empresarial.

## Regla de despliegue
No ejecutar `php artisan migrate` contra la base corporativa productiva desde desarrollo. Primero certificar cada incremento en una base `_test`, revisar nombres, permisos y plan de reversión.
