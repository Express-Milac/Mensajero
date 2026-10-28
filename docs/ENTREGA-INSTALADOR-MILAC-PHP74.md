# Entrega técnica a instalación — Mensajero MILAC / PHP 7.4.13

**Estado al 28/oct/2026: preparado para entrega de código y validación en un entorno aislado. NO autorizado para activar producción.**

**Responsabilidades:** el equipo de desarrollo entrega esta rama y evidencia de pruebas; el compañero responsable de infraestructura ejecutará instalación, configuración privada, respaldo, pruebas de sitio, revisión de seguridad y eventual cambio de publicación. No se requiere ejecutar más comandos de instalación ni modificar el servidor desde desarrollo.

## 1. Identificación del artefacto

- Repositorio: `Express-Milac/Mensajero`.
- Rama específica: `compatibilidad/php74-milac-20261027`.
- El responsable debe fijar el **SHA completo del commit** que reciba, en vez de descargar cambios sin revisión. No mezclar con la rama Laravel 13/PHP 8.4.
- Objetivo: PHP **7.4.13 exacto**, Laravel **8.83.29**, SQL Server corporativo existente, Redis y Apache.
- `composer.lock` ya está versionado con Laravel 8.83.29 y plataforma Composer 7.4.13. `vendor/` y `node_modules/` no se distribuyen desde Windows.
- Vistas, CSS/JS y recursos estáticos se encuentran en `public/`; el proyecto no depende de ejecutar `npm run dev` para servir la interfaz actual.

## 2. Qué se probó realmente (Windows, entorno aislado)

| Puerta | Evidencia | Resultado |
|---|---|---|
| PHP 7.4.13 / Laravel 8 / pruebas funcionales SQLite | `phpunit-lotes.xml` | **41 pruebas, 89 aserciones, 0 fallos** |
| PDO_SQLSRV / SQL Server local | Docker, base de prueba nueva `mensajero_php74_231a8c29f3_test` | **56 pruebas, 880 aserciones, 0 fallos** |
| Laravel/Predis/Redis | `PING` local a 127.0.0.1:6379 | **PONG** |
| Cola Redis | Un trabajo `Tests\Soporte\TrabajoColaRedisPhp74`, `queue:work --once` | **Publicado, procesado, cola vacía y claves de ensayo limpiadas** |

**Estas pruebas NO demuestran:** funcionamiento en Debian 9; conexión a SQL corporativo; flujo de operación completo de recolector Node + SQL + Redis; autenticación corporativa; PHP efectivo del virtual host; configuración de certificados; disponibilidad permanente; Gemini o WhatsApp real.

El documento de evidencia ampliada es [avance-validacion-php74-20261028.md](avance-validacion-php74-20261028.md).

## 3. Inventario del servidor que ya conocemos

- Debian GNU/Linux 9 (Stretch); PHP **CLI 7.4.13** y extensiones `pdo_sqlsrv`, `sqlsrv`, `mbstring`, `curl`, `openssl`, `xml`, `zip`, `intl`, `fileinfo` y `bcmath` cargadas.
- Apache con `mod_php 7.4` y `mod_rewrite` configurados; también existe `php-fpm7.3` para posibles usos independientes. **Aún falta verificar la versión web real por virtual host.**
- Composer global **1.6.5**. No ejecutar `self-update` ni reemplazarlo para otras aplicaciones; si es necesario, usar Composer **2 compatible con PHP 7.4**, de forma independiente.
- `/var/www/html/Mensajero` apunta a `/home/sistemas/laravelProjects/Mensajero/public`. **No sobrescribir este destino publicado.**
- Apache corre como `www-data` (uid 33). `storage`, `storage/framework/{cache,cache/data,sessions,views}`, `storage/logs` y `bootstrap/cache` tienen propietario `sistemas:sistemas`, modo **0755**, sin ACL adicionales observadas. `www-data` **no tiene escritura** sobre estos directorios en el estado actual. Se requiere asignar permisos específicos a la instancia, sin dar 0777.
- `/var/www/html` tiene modo **0777**. Es un hallazgo previo del servidor a revisar por su administrador; no modificarlo unilateralmente ni usarlo para almacenar código, `.env` o multimedia.
- `apache2ctl -t` no aprobó como usuario `sistemas`; un diagnóstico previo mostró acceso denegado a una llave SSL privada. **No concluir corrupción/falta de certificado** sin que un administrador autorizado valide el resultado con privilegios.

## 4. Preparaciones que entregamos al instalador

1. `composer.json`, `composer.lock` y código Laravel 8 adaptado a PHP 7.4.
2. `.env.example` con valores **seguros para servidor**: `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS de ejemplo **no válida hasta sustituirla**, autenticación `corporativo` (falla cerrada, aún sin integración), cookie segura, SQL cifrado/validación de certificado activa, IA y normalización automática apagadas.
3. Rutas web y API; `public/.htaccess` y archivos CSS/JS; directorio privado de multimedia configurable por `MENSAJERO_RUTA_ALMACENAMIENTO`.
4. `herramientas/verificar-entrega-php74.php`: comprobación **solo lectura** del paquete en un PHP 7.4.13 con extensiones, `composer.lock`, plantilla segura y archivos mínimos. No exige credenciales ni arranca Laravel.
5. Pruebas locales aisladas en `tests/`, `phpunit-lotes.xml` y `phpunit.integracion.xml`; nunca ejecutar la suite SQL contra la BD corporativa.
6. Herramientas de ensayo Redis **solo locales**. No se deben ejecutar contra el Redis de producción.

## 5. Procedimiento propuesto para el compañero (sin ejecutar por desarrollo)

### Etapa A — Recepción e inspección sin impacto

- Recibir el SHA exacto, verificar integridad y revisar diferencias contra la versión anterior.
- Clonar/preparar una **copia privada distinta** de `/home/sistemas/laravelProjects/Mensajero` y distinta de `/var/www/html`; usar otro directorio de pruebas. Nunca hacer `git pull` en el directorio actualmente publicado.
- Con PHP 7.4.13 de la copia, ejecutar `php herramientas/verificar-entrega-php74.php` (sin conectarse a servicios).
- Confirmar binario/versión y extensiones en el SAPI web efectivo; comprobar `composer check-platform-reqs` cuando las dependencias Linux estén instaladas en esa copia.
- Registrar el resultado de `apache2ctl -t` con administrador autorizado. Sin examen de la llave privada ni alteración del certificado.

### Etapa B — Instalación SOLO en copia aislada, con permisos autorizados

- Usar Composer 2 de ejecución **privada** compatible con PHP 7.4, **con `composer.lock`**; instalar dependencias Linux en esa copia, no reutilizar `vendor` de Windows ni ejecutar `composer update` en el servidor. No usar `--ignore-platform-reqs`, TLS sin verificar ni plugins innecesarios.
- Crear `.env` **fuera de Git** con credenciales propias del entorno; sustituir `APP_URL`, `APP_KEY`, SQL, Redis y rutas de almacenamiento únicamente tras autorización del responsable. Nunca compartir ese archivo ni mostrarlo en registros.
- Mantener `APP_DEBUG=false`, `DB_ENCRYPT=yes`, `DB_TRUST_SERVER_CERTIFICATE=false`, `IA_HABILITADA=false`, `MENSAJERO_IA_ENVIO_AUTORIZADO=false`, `MENSAJERO_IA_LOTES_HABILITADOS=false` y `MENSAJERO_NORMALIZACION_HABILITADA=false`.
- Asignar a `www-data` permisos **específicos** de lectura/ejecución sobre código y escritura solo en `storage`, `bootstrap/cache` y disco privado multimedia. Confirmar dueño/grupo/ACL de **la copia nueva**, evitando `chmod -R 777`, y preservar los permisos de las demás aplicaciones.
- Definir `MENSAJERO_RUTA_ALMACENAMIENTO` en un volumen privado **fuera de `public`** y, para el recolector Node en un despliegue posterior, hacer coincidir su ruta física `RECOLECTOR_RUTA_ALMACENAMIENTO`. No migrar historiales, QR, sesiones de WhatsApp ni archivos reales sin autorización.
- Ejecutar en la copia, cuando exista entorno, `php artisan --version`, `php artisan route:list`, compilación de vistas y pruebas de disponibilidad **sin usar la BD real**. La compilación escribe caché solo en la copia.

### Etapa C — Puertas obligatorias antes de la instalación productiva

- **Autenticación:** `MENSAJERO_MODO_AUTENTICACION=corporativo` actualmente devuelve 503 para accesos; la integración de identidad está **pendiente**. No cambiar a `deshabilitado` para abrir conversaciones en red. El responsable debe integrar/probar identidad o aprobar y validar explícitamente un perímetro autenticado compatible con el producto.
- **SQL corporativo:** inventariar tablas y versión de migraciones; registrar respaldo verificado y revisión del DBA. No ejecutar `artisan migrate` en producción por defecto. Las pruebas `phpunit.integracion.xml` **ejecutan migraciones**, por lo que se prohíbe apuntarlas a la base corporativa.
- **Redis:** confirmar conectividad y ACL para una instancia propia, sin arrancar workers en colas preexistentes. El ensayo local no autoriza procesar colas reales.
- **WhatsApp:** instalar Node/dependencias para Linux por separado; comprobar versión requerida, supervisión, almacenamiento durable, autorizaciones y número secundario dedicado; no conectar número principal ni escanear QR todavía.
- **Apache/TLS:** validar `DocumentRoot`/alias solo a `public`, HTTPS, cabeceras, certificados y protección de acceso; comprobar que `.env`, `storage` y `vendor` no son accesibles desde web.
- **Reversión:** respaldar código, BD, archivos privados y configuración según política MILAC; definir prueba de restauración y una forma de volver a la publicación anterior. Prohibidos reinicios, cambios de symlink o configuración sin ventana y autorización del administrador.

### Etapa D — Aprobación formal

El instalador entrega un registro firmado o documentado con revisión de seguridad, respaldos, permisos, PHP web real, dependencias, pruebas en staging y decisión de autorización. **Hasta entonces, la entrega está lista para revisión técnica, no para operación productiva.**

## 6. Lo que NO debe incluirse en el paquete

Nunca distribuir `.env` real, passwords, certificados privados, archivos de sesión de WhatsApp, `recolector-whatsapp/datos`, logs, multimedia privada, `vendor`/ `node_modules` de Windows ni bases SQL de pruebas. Transferir **el código versionado y el SHA**, y generar secretos/instalaciones en el entorno autorizado.

**Contacto operativo:** el compañero instalador debe recibir este documento junto con `docs/avance-validacion-php74-20261028.md` y el SHA exacto de entrega.
