## Cambio
Describe el incremento y el motivo.

## Validación
Registra únicamente comandos realmente ejecutados y su resultado. No confundas `startup_failure` de CI con pruebas ejecutadas.

## Base de datos
- [ ] Si toca esquema, se probó exclusivamente contra SQL Server desechable `_test`.
- [ ] No se ejecutaron migraciones contra producción.

## Seguridad y datos
- [ ] Sin secretos, sesiones de WhatsApp, payloads privados ni binarios operativos en Git.
- [ ] Lista permitida y permisos revisados.

## Revisión multidisciplinaria
- [ ] Arquitectura
- [ ] Creación
- [ ] Revisión
- [ ] Pruebas
- [ ] Seguridad
- [ ] UI/UX
