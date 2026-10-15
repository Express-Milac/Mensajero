# Estrategia de pruebas

Mensajero debe superar cuatro niveles antes de instalarse en el servidor de MILAC:

1. **Unitarias:** decisiones de lista blanca, adhesivos, idempotencia y utilidades puras.
2. **HTTP:** acceso temporal sin login, restricción administrativa, modo local de prueba y modo corporativo cerrado.
3. **Calidad:** PHPUnit, Pint, compilación de vistas, listado de rutas, Composer y nomenclatura del dominio.
4. **Integración SQL Server:** migraciones y flujo de captura sobre una base desechable cuyo nombre termina en `_test`.

La validación continua en `.github/workflows/validacion.yml` ejecuta los niveles disponibles automáticamente y crea un SQL Server temporal para la integración.

No sustituir SQL Server por SQLite para certificar el sistema. No ejecutar la suite de integración contra bases empresariales productivas.
