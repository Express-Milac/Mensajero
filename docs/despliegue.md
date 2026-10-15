# Despliegue y operación

## Requisitos del servidor MILAC

- PHP 8.4+ en runtime aislado.
- Extensiones `sqlsrv`, `pdo_sqlsrv`, `fileinfo` y requisitos normales de Laravel.
- Microsoft ODBC Driver compatible.
- Redis en red privada.
- Acceso autorizado a la base SQL Server corporativa donde vivirán las tablas `mensajero_*`.
- Almacenamiento privado con permisos de lectura/escritura.

## Orden de preparación

1. Instalar código y dependencias sin ejecutar migraciones.
2. Configurar `.env` fuera de Git.
3. Verificar PHP/extensiones.
4. Verificar SQL Server con una consulta de solo lectura.
5. Verificar Redis.
6. Verificar almacenamiento y espacio libre.
7. Ejecutar pruebas unitarias/funcionales.
8. Ejecutar migraciones primero en una base o entorno autorizado de prueba terminado en `_test`.
9. Ejecutar pruebas de integración SQL Server.
10. Solo después preparar migración corporativa real.

Mientras no exista login corporativo, la aplicación debe permanecer restringida por red y la administración solo acepta IP explícitamente permitidas.

La primera instalación en servidor no conecta todavía el número empresarial de WhatsApp. Esa conexión pertenece al hito de puerta de enlace después de certificar aplicación, base, Redis y almacenamiento.
