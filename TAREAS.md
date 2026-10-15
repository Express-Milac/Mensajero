# Tareas de Mensajero

Estado: 2026-10-15. No considerar terminado un hito hasta contar con código, pruebas y documentación coherentes.

## En curso — preparación previa al servidor

- [x] Política de nomenclatura del dominio en español.
- [x] Agentes especializados de creación, revisión, pruebas, seguridad y UI/UX.
- [x] Modelos y núcleo de captura renombrados al español.
- [x] Tablas y columnas propias de Mensajero en español.
- [x] Acceso temporal sin login con administración restringida por IP.
- [x] Tablero compatible con ausencia de usuario autenticado.
- [x] Pruebas unitarias de lista blanca y nomenclatura.
- [x] Pruebas HTTP de acceso temporal, seguridad y modo corporativo cerrado.
- [x] Pruebas de integración SQL Server preparadas sobre base `_test`.
- [x] Validación continua con SQL Server temporal.
- [x] Revisión final de código, seguridad, UI/UX y documentación previa al despliegue.
- [ ] Ejecutar la validación completa en un entorno donde GitHub Actions o el validador local puedan iniciar los procesos.
- [ ] Corregir cualquier fallo que aparezca durante la ejecución real de PHPUnit, Pint, vistas, rutas, Composer y SQL Server.
- [ ] Confirmar suite completa en verde.

### Bloqueo externo de validación

GitHub Actions termina actualmente como `startup_failure` antes de crear jobs, incluso después de volver a disparar el flujo desde una rama limpia. Esto impide certificar la suite desde GitHub, pero no indica un fallo de PHPUnit ni de SQL Server porque esos procesos no llegan a ejecutarse.

Mientras ese bloqueo exista, usar `herramientas/validar.sh` o `herramientas/validar.ps1` en un entorno autorizado. La integración SQL Server debe ejecutarse únicamente contra una base desechable terminada en `_test`.

## Después de la validación

- [ ] Integrar el código aprobado a la rama principal.
- [ ] Preparar despliegue en servidor propio de MILAC sin conectar aún el número empresarial.
- [ ] Validar PHP 8.4, extensiones SQL Server, Redis, permisos y almacenamiento en servidor.
- [ ] Ejecutar migraciones primero en una base o entorno autorizado de prueba.
- [ ] Implementar administración visual de conversaciones monitoreadas.
- [ ] Implementar API interna firmada entre puerta de enlace y Laravel.
- [ ] Implementar relé de bandeja de salida hacia Redis.
- [ ] Construir la puerta de enlace WhatsApp autohospedada.
- [ ] Probar con sesión controlada antes del número empresarial.

## Regla

Nunca ejecutar pruebas destructivas, migraciones experimentales o conexión de WhatsApp como efecto secundario de un cambio de código.
