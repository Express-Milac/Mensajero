# Tareas de continuidad

Estado: 15/10/2026. `[x]` significa código entregado y validado en el alcance indicado; no equivale a certificación en producción.

## Entregado y validado
- [x] Arquitectura base sobre SQL Server corporativo, Redis y almacenamiento privado configurable.
- [x] Decisiones de negocio registradas: WhatsApp Business, grupos seleccionados, servidor MILAC y autenticación pospuesta.
- [x] Nomenclatura de negocio migrada al español en tablas, columnas propias, clases, rutas, estados y pruebas principales.
- [x] Conexión, chats monitoreados, contactos, evento crudo, buzón de salida y mensajes originales modelados.
- [x] Lista permitida por defecto denegada, descarte de pegatinas e idempotencia.
- [x] Agentes de arquitectura, creación, revisión, pruebas, seguridad, UI/UX y calidad definidos y aplicados.
- [x] Corrección del tablero sin usuario, validación de administrador y bloqueo de diagnóstico administrativo cuando no hay autenticación.
- [x] `Usuario`, fábrica, migración, comando y pruebas alineados con `nombre`/marcas de tiempo propias en español.
- [x] Estructura propia de autenticación movida de `Auth`/`auth` a `Autenticacion`/`autenticacion`.
- [x] Puerta local completa en PHP 8.4: Composer, Pint, rutas, vistas y Vite aprobados.
- [x] Runtime local confirmado con `mbstring`, `sqlsrv`, `pdo_sqlsrv` y ODBC Driver 18.
- [x] Base de administración de grupos descubiertos con lista permitida denegada por defecto.
- [x] Auditoría versionada de cambios de monitoreo y comando de consola para la etapa sin autenticación administrativa.
- [x] Incremento de grupos validado: Pint 59 archivos, PHPUnit 20 pruebas/57 aserciones y SQL Server 4 pruebas/56 aserciones.
- [x] Contrato interno HMAC `/api/pasarela/v1`, ventana temporal, identificador único y protección antirrepetición persistente.
- [x] HMAC validado: PHPUnit 26 pruebas/65 aserciones y SQL Server 5 pruebas/84 aserciones.
- [x] Suite SQL Server repetible: 5 pruebas/84 aserciones aprobadas dos veces consecutivas sobre la misma `mensajero_test`.

## Regla de integración con WhatsApp
- [x] Se descarta el uso de Baileys, WPPConnect, whatsapp-web.js y cualquier automatización no oficial de WhatsApp Web/protocolo privado.
- [x] Se documentó la decisión en `docs/decision-integracion-whatsapp-oficial.md`.
- [x] La integración deberá ser directa con la plataforma oficial de Meta/WhatsApp, sin BSP/SaaS intermediario.
- [ ] Confirmar que la cuenta empresarial de MILAC cumple los requisitos de Official Business Account (OBA) si se pretende utilizar Groups API.
- [ ] Confirmar tamaño y naturaleza de los grupos operativos que se desean capturar.
- [ ] Validar si el proceso real puede adaptarse a Groups API oficial: grupos por invitación y límite documentado de 8 participantes.
- [ ] Si el caso encaja, implementar Webhooks/Cloud API oficiales mediante código propio de MILAC y adaptar los eventos al núcleo ya existente.
- [ ] Si el caso no encaja, documentar formalmente la incompatibilidad del requisito con la interfaz oficial de WhatsApp y replantear el proceso operativo; no sustituirlo por ingeniería inversa.

## Incidencia de infraestructura de CI
- [ ] Resolver `startup_failure` de GitHub Actions a nivel repositorio/organización/runner. El fallo ocurre antes de crear jobs y no constituye un fallo de PHPUnit/Pint.
- [ ] Revisar desde GitHub la configuración administrativa de permisos/uso de Actions; el conector disponible no expone esa configuración.

## Después de definir el canal oficial
- [ ] Implementar relevo recuperable del buzón de salida → Redis y normalizador idempotente.
- [ ] Descargar foto/video/audio/documento únicamente después de confirmar que el chat está autorizado.
- [ ] Guardar multimedia en almacenamiento privado configurable; SQL Server conserva ruta y metadatos, no binarios.
- [ ] Construir vista de mensajes originales por grupo, remitente, fecha y tipo.
- [ ] Probar recuperación ante reinicios y pérdida temporal de conectividad.
- [ ] Integrar al servidor MILAC.

## Fase 2
- [ ] Integrar Gemini sobre mensajes normalizados, manteniendo trazabilidad hacia el mensaje original.

## Regla de despliegue
No ejecutar `php artisan migrate` contra la base corporativa productiva desde desarrollo. Primero certificar cada incremento en una base `_test`, revisar nombres, permisos y plan de reversión. No vincular ni registrar el número empresarial en una integración no oficial.
