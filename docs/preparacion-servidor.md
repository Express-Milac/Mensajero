# Preparación para servidor

Este documento define la puerta de entrada a la siguiente etapa. No implica despliegue automático.

Antes de integrar al servidor propio de MILAC deben cumplirse:

- validación continua completa en verde;
- pruebas SQL Server sobre base desechable `_test` aprobadas;
- revisión de seguridad sin pendientes críticos;
- nombres de dominio, tablas y columnas en español;
- PHP 8.4 y extensiones `sqlsrv`/`pdo_sqlsrv` disponibles en runtime aislado;
- Redis accesible solo en red privada;
- almacenamiento privado con permisos de lectura/escritura y espacio suficiente;
- variables de entorno provisionadas fuera de Git;
- restricción de red/IP activa mientras no exista login corporativo;
- copias de seguridad y procedimiento de reversión definidos antes de migraciones corporativas.

En la primera instalación no se conectará todavía el número empresarial de WhatsApp. Primero se verificará aplicación, SQL Server, Redis, almacenamiento, migraciones y salud en el servidor.
