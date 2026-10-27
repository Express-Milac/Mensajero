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

- Se migraron los métodos `casts(): array` de diez modelos de Mensajero a la propiedad `protected $casts` de Laravel 8, conservando los nombres de campos y tipos declarados.
- En `config/database.php`, `migrations` volvió al formato de cadena `mensajero_migraciones`, que Laravel 8 requiere para conservar el historial existente.
- `herramientas/comprobar-sintaxis-php74.sh` permite enumerar archivos aún incompatibles ejecutando `php -l` con el intérprete PHP 7.4 del servidor. **Es solo lectura, no arranca Laravel.**

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

## Prueba de sintaxis cuando el código de la rama esté en una copia de trabajo

```bash
bash herramientas/comprobar-sintaxis-php74.sh
```

No ejecutar este archivo contra la instalación actual de producción ni hacer pull de la rama experimental sobre su directorio activo. La prueba inicial debe hacerse en una copia aparte y se esperan errores mientras no esté terminado el port.


## Inventario confirmado del servidor por Francisco, 27/oct/2026

- SO: Debian GNU/Linux 9 (Stretch).
- PHP CLI: 7.4.13 (NTS); `php --ini` usa `/etc/php/7.4/cli/php.ini`.
- Extensiones PHP CLI: `pdo_sqlsrv`, `sqlsrv`, `mbstring`, `curl`, `fileinfo`, `bcmath`, `openssl`, `xml`, `zip`, `intl` y otras. **Esto no prueba que estén cargadas por Apache o PHP-FPM**.
- Composer CLI global: 1.6.5 (2018). Es una herramienta heredada; no cambiarla globalmente. Resolver las dependencias de Mensajero con Composer 2 en una copia y con `config.platform.php=7.4.13`, generando un lock exclusivo de PHP 7.4.
- OpenSSL CLI: 1.1.0l (2019). Podrían existir restricciones TLS/CA al bajar dependencias; no deshabilitar verificación TLS para resolverlas.
- Enlace publicado: `/var/www/html/Mensajero` se resuelve a `/home/sistemas/laravelProjects/Mensajero/public` (directorio correcto).
- `apache2ctl -M 2>/dev/null | grep -Ei 'php|proxy_fcgi|rewrite'` devolvió vacío, pero **ocultó stderr y el código de salida**, por lo que no se conoce aún el SAPI PHP web.

### Comprobaciones no destructivas pendientes

```bash
apache2ctl -t
apache2ctl -M 2>&1 | head -80
echo "estado_apache=$?"
ps -eo comm,args | grep -E '[a]pache2|[h]ttpd|[n]ginx|[p]hp-fpm'
ls -ld /home /home/sistemas /home/sistemas/laravelProjects /home/sistemas/laravelProjects/Mensajero /home/sistemas/laravelProjects/Mensajero/public
```

**Precaución**: en comandos con `|` el código de salida mostrado suele corresponder al último comando del pipeline. Si se requiere el verdadero estado de `apache2ctl`, usar `apache2ctl -M >/tmp/mensajero-modulos-check.txt 2>&1; echo $?` y leer el archivo (si se dispone de permiso); no reiniciar Apache.

### Composer sin afectar programas existentes

La página oficial `https://getcomposer.org/download/` describe el canal Composer 2.2 LTS y el instalador con `--install-dir`, `--filename` y `--2.2`. Este canal funciona con PHP 7.4. Para construir Mensajero, preferir el Composer 2 estable más reciente admitido por PHP 7.4, siempre en **una ruta privada y aislada**, sin `self-update` sobre el Composer global de 1.6.5. Validar firma y cadena de certificados HTTPS; jamás usar `--disable-tls`, `secure-http=false` o `--ignore-platform-reqs`.

**No ejecutar aún Composer 2 sobre el directorio de despliegue**: primero terminar el port completo, regenerar lock y verificar módulos web.


## Advertencia Apache / PHP web detectada (27/oct/2026)

El servidor respondió a `/usr/sbin/apache2ctl -t` ejecutado por el usuario `sistemas`:

```text
AH00526: Syntax error on line 33 of /etc/apache2/sites-enabled/default-ssl.conf:
SSLCertificateKeyFile: file '/etc/ssl/private/track_milac_com_mx.key' does not exist or is empty
```

**No está demostrado que falte la llave**: ese resultado puede deberse a que la prueba se ejecutó sin privilegios para leer archivos privados de SSL. Se prohíbe reiniciar, recargar, modificar permisos del archivo o desactivar SSL hasta que el administrador verifique `sudo /usr/sbin/apache2ctl -t`. Tampoco debe difundirse ni copiarse el contenido de la llave.

El proceso `php-fpm7.3` está activo y existe `/run/php/php7.3-fpm.sock`. La instalación contiene directorios `/etc/php/5.6` a `/etc/php/7.4`, pero **no se observó** `php7.4-fpm.sock` ni proceso `php-fpm7.4`. PHP CLI 7.4 **NO demuestra PHP web 7.4**. No cambiar el objetivo a PHP 7.3 sin identificar cómo Apache maneja realmente el vhost de Mensajero.

Para diagnóstico de solo lectura, sin exponer llaves:

```bash
# Ejecutar solo si Francisco tiene privilegios administrativos ya autorizados:
sudo -n /usr/sbin/apache2ctl -t
# Si no tiene sudo, solicitar esta comprobación al administrador; no tocar certificados.

ls -1 /etc/apache2/mods-enabled/ | grep -Ei 'php|proxy|fcgi|rewrite'
readlink -f /run/php/php-fpm.sock
ls -l /etc/php/7.4/fpm /etc/php/7.3/fpm 2>&1
ls -l /etc/apache2/sites-enabled/
grep -RinE 'SetHandler|ProxyPassMatch|php[0-9][.]|AddHandler|FilesMatch' \
  /etc/apache2/sites-enabled /etc/apache2/conf-enabled 2>/dev/null
```

No crear un archivo `phpinfo()` público ni ejecutar `composer install` o `artisan migrate` en la instancia activa para diagnosticar el PHP web.

## Resolución de SAPI Apache (27/oct/2026)

La inspección de módulos habilitados confirmó estos enlaces y directivas:

```text
/etc/apache2/mods-enabled/php7.4.load
LoadModule php7_module /usr/lib/apache2/modules/libphp7.4.so
/etc/apache2/mods-enabled/php7.4.conf
SetHandler application/x-httpd-php
/etc/apache2/mods-enabled/rewrite.load
```

**Resultado:** Apache tiene configurado **mod_php 7.4** para PHP. El PHP-FPM 7.3 en `/run/php/php7.3-fpm.sock` existe, pero no se encontraron `SetHandler`, `ProxyPassMatch` ni directivas PHP-FPM por sitio. Por ello el objetivo de Mensajero se mantiene **PHP 7.4.13 / Laravel 8**; no migrar a PHP 7.3 solo por encontrar dicho proceso. Falta una comprobación final desde un vhost restringido con PHP web para confirmar la versión exacta de ejecución.

El usuario `sistemas` recibió en `apache2ctl -t` una advertencia de certificado privado SSL no accesible. No afirmar que falta el archivo; podría ser falta de permisos de lectura. No reiniciar/recargar Apache hasta que un administrador autorizado valide su sintaxis como usuario privilegiado.

**Precaución adicional:** el hecho de que `readlink -f /var/www/html/Mensajero` apunte a `public` no demuestra por sí solo que el virtual host sirva el directorio correcto ni que el dominio tenga autenticación. Verificar ambos antes de publicar WhatsApp.
