# Tareas de continuidad

Estado: 15/10/2026. `[x]` significa código entregado y validado en el alcance indicado; no equivale a certificación en producción.

## Entregado y certificado
- [x] Arquitectura base sobre SQL Server corporativo, Redis y almacenamiento privado configurable.
- [x] Decisiones de negocio registradas: WhatsApp Business, grupos 30+, pasarela autohospedada, servidor MILAC y autenticación pospuesta.
- [x] Nomenclatura de negocio migrada al español en tablas, columnas propias, clases, rutas, estados y pruebas principales.
- [x] Conexión, chats monitoreados, contactos, evento crudo, buzón de salida y mensajes originales modelados.
- [x] Lista permitida por defecto denegada, descarte de pegatinas e idempotencia.
- [x] Agentes de arquitectura, creación, revisión, pruebas, seguridad, UI/UX y calidad definidos y aplicados.
- [x] Autenticación temporal desacoplada y preparada para futura identidad corporativa.
- [x] Puerta local completa en PHP 8.4: Composer, Pint, rutas, vistas y Vite aprobados.
- [x] Runtime local confirmado con `mbstring`, `sqlsrv`, `pdo_sqlsrv` y ODBC Driver 18.
- [x] Administración de grupos descubiertos con lista permitida denegada por defecto.
- [x] Auditoría versionada de cambios de monitoreo y comando de consola para etapa sin autenticación administrativa.
- [x] Incremento de grupos validado: Pint 59 archivos, PHPUnit 20 pruebas/57 aserciones y SQL Server 4 pruebas/56 aserciones.
- [x] Contrato HMAC `/api/pasarela/v1`, ventana temporal, identificador único y protección antirrepetición persistente.
- [x] HMAC certificado: PHPUnit 26 pruebas/65 aserciones y SQL Server 5 pruebas/84 aserciones.
- [x] Suite SQL Server repetible: 5 pruebas/84 aserciones aprobadas dos veces consecutivas sobre la misma `mensajero_test`.

## Incidencia de infraestructura de CI
- [ ] Resolver `startup_failure` de GitHub Actions a nivel repositorio/organización/runner. El fallo ocurre antes de crear jobs y no constituye un fallo de PHPUnit/Pint.
- [ ] Revisar desde GitHub la configuración administrativa de permisos/uso de Actions; el conector disponible no expone esa configuración.

## Incremento actual: primera conexión real controlada
- [x] Comando para registrar una conexión de WhatsApp antes de vincularla.
- [x] Rutas HMAC para estado de conexión y consulta de chats autorizados.
- [x] Consulta de lista permitida de solo lectura sin crecimiento de la tabla antirrepetición.
- [x] Paquete separado `pasarela-whatsapp/` en Node.js/TypeScript.
- [x] Adaptador neutral de WhatsApp con implementación inicial Baileys.
- [x] QR, persistencia local de sesión de prueba, reconexión y descubrimiento de grupos.
- [x] Filtro local de chats autorizados y descarte de stickers antes de llamar a Laravel.
- [x] Pruebas TypeScript para HMAC y clasificación de mensajes agregadas.
- [ ] Ejecutar `npm install`, `npm run verificar` y `npm run pruebas` dentro de `pasarela-whatsapp` y ajustar cualquier incompatibilidad real de dependencias.
- [ ] Ejecutar Pint, PHPUnit y `phpunit.integracion.xml`; la nueva suite debe incluir estado/lista autorizada.
- [ ] Vincular una cuenta controlada por QR y comprobar que sus grupos aparecen en `/grupos`.
- [ ] Autorizar un único grupo de prueba y comprobar que un mensaje real llega al evento crudo.

## Después de la primera recepción real
- [ ] Descargar foto/video/audio/documento únicamente después de confirmar que el chat está autorizado.
- [ ] Guardar multimedia en almacenamiento privado configurable; SQL Server conserva ruta y metadatos, no binarios.
- [ ] Implementar relevo recuperable del buzón de salida → Redis y normalizador idempotente.
- [ ] Construir vista de mensajes originales por grupo, remitente, fecha y tipo.
- [ ] Revisar almacenamiento de claves de sesión para producción antes de usar el número empresarial.
- [ ] Probar recuperación ante reinicio, pérdida temporal de Laravel y reconexión de WhatsApp.
- [ ] Integrar al servidor MILAC.

## Fase 2
- [ ] Integrar Gemini sobre mensajes normalizados, manteniendo trazabilidad hacia el mensaje original.

## Regla de despliegue
No ejecutar `php artisan migrate` contra la base corporativa productiva desde desarrollo. Primero certificar cada incremento en una base `_test`, revisar nombres, permisos y plan de reversión. No vincular el número empresarial hasta aprobar una cuenta/sesión controlada.
