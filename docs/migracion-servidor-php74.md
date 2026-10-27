# Migración de Mensajero MILAC a PHP 7.4.13

**Estado: EN DESARROLLO. NO APTO PARA DESPLIEGUE.**

Condición inamovible de infraestructura de MILAC: **PHP 7.4.13**. No requiere reemplazar el PHP predeterminado del servidor. Esta rama está separada tanto de `respaldo/mensajero-20261026` como de `desarrollo/lotes-contextuales-gemini-20261026`.

## Arquitectura objetivo

- PHP 7.4.13, Laravel 8.83.x y Composer resolviendo dependencias para 7.4.13.
- Mismas tablas corporativas SQL Server con prefijo `mensajero_`. No regenerar datos ni ejecutar migraciones en la base activa durante esta adaptación.
- Cola Redis, captura independiente de WhatsApp, protección de grupos y controles Gemini sin relajar autorizaciones.
- Raíz pública de Apache **solo** `/home/sistemas/laravelProjects/Mensajero/public`. Nunca hacer visible `.env`, `vendor`, `storage` o el repositorio completo mediante el enlace de `/var/www/html/Mensajero`.
- Acceso privado; el modo sin inicio de sesión del piloto NO es seguro para una publicación abierta en un dominio de la empresa.

## Avance inicial del port, todavía sin pruebas de ejecución

- `composer.json` plantea Laravel 8 y dependencias compatibles con PHP 7.4.13. Se fijó `config.platform.php` para impedir que Composer resuelva paquetes exclusivos de PHP 8.
- `bootstrap/app.php`, `artisan` y `public/index.php` reemplazan el mecanismo de arranque de Laravel 13 con los Kernel del esqueleto de Laravel 8.
- Nuevos `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, `app/Providers/RouteServiceProvider.php` y middleware de invitado.
- `config/app.php` registra proveedores y alias tradicionales. La programación de tareas pasa a `Console/Kernel.php`; se quita de `routes/console.php` para evitar duplicidad.

**Esto NO significa que la aplicación arranque en PHP 7.4.** Hay incompatibilidades de sintaxis y API aún pendientes.

## Pendientes críticos de migración

1. Eliminar construcciones de PHP 8+ de **todos** los archivos PHP: `enum`, `match`, `readonly`, promoción de propiedades en constructor, operador `?->`, atributos `#[...]`, tipo `mixed`, tipos unión e invocaciones con argumentos nombrados. No quitar solo la anotación: reescribir la lógica preservando significado, tipos y permisos.
2. Reescribir los DTO de WhatsApp y los enums con equivalentes PHP 7.4 sin romper los consumidores (`->value`, `::from`, igualdad, etc.).
3. Adaptar modelos Laravel 13 que usan `protected function casts(): array`, atributos `Fillable`/`Hidden`, cast `hashed` y enum cast. En Laravel 8 usar `protected $casts`, `$fillable`, `$hidden` y hash de contraseña explícito.
4. Revisar opciones Laravel modernas: `array_is_list`, `str_contains`, `str_starts_with`, `str_ends_with`, `Http`, `Schedule`, migraciones y los discos privados. Las funciones nuevas deben tener sustituto seguro; no usar stubs que oculten fallos.
5. Verificar migraciones SQL Server, nombres `mensajero_`, claves únicas, claves foráneas, manejo UTC, transacciones y columnas incompatibles con el Schema Builder de Laravel 8.
6. Resolver Composer **de cero en un directorio de pruebas** para PHP 7.4, regenerando `composer.lock`. El `composer.lock` heredado corresponde a Laravel 13 y no sirve; no ejecutar `composer install` con este estado inicial.
7. Probar con un intérprete PHP 7.4.13 real: lint de todos los archivos, `composer check-platform-reqs`, `php artisan list`, rutas, configuración y suite compatible con PHPUnit 9.6.
8. Probar SQL Server/Redis/WhatsApp/Gemini con credenciales y datos ficticios o bases aisladas; nunca en la empresa en producción. El PHP7.4 necesita controlador PDO SQL Server específicamente compatible y extensión ODBC.
9. Adaptar pruebas automatizadas PHPUnit 12 a PHPUnit 9, evitando `assert...` y métodos agregados después.
10. Solo después preparar un paquete de despliegue privado que no modifique las aplicaciones existentes de MILAC y conserve copia de seguridad y reversión.

## Diagnóstico de solo lectura para Francisco

Ejecutar en el servidor sin `artisan`, sin instalar dependencias y sin exponer contraseñas:

```bash
php -v
php -m | grep -Ei 'pdo|sqlsrv|mbstring|openssl|xml|curl|fileinfo|bcmath|ctype'
composer --version
cat /etc/os-release
ls -ld /home/sistemas/laravelProjects/Mensajero /var/www/html/Mensajero
readlink -f /var/www/html/Mensajero
```

Si `php -m` no muestra `pdo_sqlsrv`, el servidor debe disponer de una versión del controlador Microsoft para PHP 7.4. Una rama de compatibilidad no reemplaza una extensión nativa ausente. Verificar también permisos del usuario web y el servidor SQL Server/ODBC.

## Restricciones de seguridad

PHP 7.4 y Laravel 8 no tienen mantenimiento de seguridad estándar desde 2022/2023. La aplicación deberá permanecer detrás de autenticación corporativa, VPN o control de acceso equivalente; usar mínimo privilegio SQL Server, aislamiento de archivos, firewall y monitoreo. Una publicación sin autenticación en Internet queda descartada para mensajes operativos reales.

**Prohibido durante el port:** `--ignore-platform-reqs`, cambiar `composer.json` en el servidor manualmente, copiar `vendor` de PHP 8, ejecutar `php artisan migrate` en SQL corporativo, habilitar el trabajo Gemini por lotes o apuntar Apache a la raíz del repositorio.
