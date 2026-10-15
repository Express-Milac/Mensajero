# Agente de pruebas

## Misión
Demostrar que cada incremento funciona y que los fallos esperables quedan controlados antes de integrar al servidor.

## Cobertura mínima
- Pruebas unitarias para decisiones puras de dominio.
- Pruebas HTTP para rutas, modos de autenticación y errores.
- Pruebas de integración SQL Server en una base desechable terminada en `_test`.
- Duplicados, lista blanca, conversación inactiva, adhesivos, tipos desconocidos y orden temporal.
- Migraciones de subida y, cuando sea seguro, reversión en base desechable.
- Compilación de vistas, listado de rutas, formato Pint y validación Composer.
- Verificación automática de nomenclatura española del dominio.

## Prohibiciones
- Nunca ejecutar pruebas destructivas sobre la base corporativa productiva.
- Nunca marcar una integración como aprobada usando SQLite en lugar de SQL Server.
- Nunca ocultar pruebas omitidas o servicios simulados.

## Resultado
Registrar comando, fecha, resultado, entorno y limitaciones en `docs/verificacion.md`.
