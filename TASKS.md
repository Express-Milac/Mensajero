# Tareas de continuidad

Estado: 15/10/2026. `[x]` significa código entregado en el alcance indicado; no equivale a certificación en producción.

## Entregado
- [x] Arquitectura base sobre SQL Server corporativo, Redis y almacenamiento privado configurable.
- [x] Decisiones de negocio registradas: WhatsApp Business, grupos 30+, pasarela autohospedada, servidor MILAC y autenticación pospuesta.
- [x] Nomenclatura de negocio migrada al español en tablas, columnas, clases, rutas, estados y pruebas principales.
- [x] Conexión, chats monitoreados, contactos, evento crudo, buzón de salida y mensajes originales modelados.
- [x] Lista permitida por defecto denegada, descarte de pegatinas e idempotencia.
- [x] Agentes de arquitectura, creación, revisión, pruebas, seguridad, UI/UX y calidad definidos y aplicados.
- [x] Corrección del tablero sin usuario, validación de administrador y bloqueo de diagnóstico administrativo cuando no hay autenticación.

## Puerta inmediata antes de integración al servidor
- [ ] Ejecutar `composer install`/validación de Composer en un entorno con acceso al repositorio.
- [ ] Ejecutar PHPUnit de unidad y funcional.
- [ ] Ejecutar Pint.
- [ ] Compilar vistas y revisar rutas.
- [ ] Ejecutar migraciones y `phpunit.integracion.xml` exclusivamente contra SQL Server desechable `_test`.
- [ ] Corregir cualquier fallo detectado y actualizar `docs/revision-previa-integracion.md`.

## Después de aprobar la puerta
- [ ] Descubrimiento de grupos/chats y pantalla para activar/desactivar monitoreo.
- [ ] Auditoría de cambios de lista permitida e incremento de versión de autorización.
- [ ] Punto de entrada interno versionado pasarela → Laravel con HMAC, identificador único y ventana antirrepetición.
- [ ] Relevo recuperable del buzón de salida → Redis y normalizador idempotente.
- [ ] Crear pasarela Node.js/TypeScript autohospedada y adaptador neutral de WhatsApp.
- [ ] Probar una cuenta/sesión controlada antes de considerar el número empresarial.

## Regla de despliegue
No ejecutar `php artisan migrate` contra la base corporativa productiva desde desarrollo. Primero certificar en una base `_test` y revisar nombres, permisos y plan de reversión.
