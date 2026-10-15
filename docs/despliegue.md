# Instalación y operación

Entorno objetivo: servidor propio de MILAC, PHP 8.4+, extensiones `sqlsrv`, `pdo_sqlsrv`, `fileinfo`, ODBC 18, Composer 2, SQL Server y Redis.

Mensajero usa una base SQL Server corporativa existente; no se crea una base productiva separada. Todos los objetos propios comienzan con `mensajero_`.

## Acceso temporal
`MENSAJERO_MODO_AUTENTICACION=deshabilitado` solo es válido detrás de controles de red internos. En ese modo `/tablero` funciona sin usuario, mientras `/estado` permanece prohibido. El diagnóstico administrativo puede ejecutarse con `php artisan mensajero:diagnostico`.

`local` existe para desarrollo/pruebas. `corporativo` está reservado a la identidad definitiva y falla cerrado.

## Producción
Servir únicamente `public/`, `APP_DEBUG=false`, SQL cifrado, Redis privado, permisos mínimos, archivos privados y registros sin contenido sensible. Antes de integrar, las migraciones deben certificarse en SQL Server `_test`. No vincular el número empresarial hasta superar una prueba controlada de la pasarela.
