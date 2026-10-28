# Validacion PHP 7.4.13 de Mensajero MILAC (28/oct/2026)

## Resultados comprobados en Windows / Docker local

- PHP 7.4.13 con Laravel 8 y PHPUnit 9.6.38.
- Pruebas funcionales SQLite anteriormente aprobadas: **41 tests y 89 aserciones**.
- Conectividad SQL Server real con el controlador PDO_SQLSRV: aprobado el
  diagnostico de solo lectura de la base local `mensajero_test` (28 tablas).
- Suite completa de integracion SQL Server en base NUEVA y aislada
  `mensajero_php74_231a8c29f3_test`: **56 tests y 880 aserciones, 0 fallos**.
  Las migraciones, autorizaciones, archivos, normalizacion y pantallas fueron
  verificadas sobre datos ficticios. La base se conservo para auditoria.
- Rama Git: `compatibilidad/php74-milac-20261027`; estado al certificar SQL:
  `c741280`.

Estos resultados solo corresponden al ensayo Windows / SQL Server Docker.
No son una certificacion de despliegue en Debian 9 ni autorizacion para
ejecutar migraciones en SQL Server corporativo.

## Redis local: conexion y consumo real aprobados

En Windows, PHP 7.4.13 / Laravel 8 / Predis recibieron `PONG` de
Redis 127.0.0.1:6379, sin leer claves ni publicar trabajos.

A continuacion el usuario ejecuto un unico trabajo ficticio en la cola
exclusiva `mensajero_ensayo_php74_01e6982b7721436faf95736961babeaa`:

```text
TRABAJOS_PUBLICADOS: 1
PREPARACION_COLA_REDIS74_APROBADA
Processing: Tests\\Soporte\\TrabajoColaRedisPhp74
Processed: Tests\\Soporte\\TrabajoColaRedisPhp74
TRABAJO_PROCESADO: SI
COLA_VACIA: SI
CLAVES_DE_ENSAYO_LIMPIADAS: SI
ENSAYO_COLA_REDIS74_APROBADO
```

El trabajador se limito a `--once --tries=1 --timeout=15`; el
resultado comprobo escritura de acuse, consumo y limpieza del prefijo
aleatorio. El ensayo uso SQLite en memoria y no accedio a SQL Server,
WhatsApp o Gemini.

**El paso Redis local esta aprobado**. No se han probado aun Redis en
Debian 9, el flujo conjunto real SQL+Redis, el PHP del servidor web ni
la autenticacion y permisos corporativos.

## Pendientes para preparar despliegue MILAC

- Redis local y trabajador limitado: APROBADOS en Windows (no en Debian 9).
- Verificar `php7.4` de Apache (se detecto configuracion mod_php 7.4),
  extension SQL y permisos del usuario web.
- Resolver advertencia de certificado Apache con el administrador y validar TLS
  SQL Server sin confiar ciegamente en un certificado no verificado.
- Comprobar autenticacion/red privada; no exponer mensajes a Internet sin login.
- Construir paquete aislado y procedimiento de respaldo/reversion.
- No modificar servicios Apache, PHP, certificados, Redis o SQL corporativo
  durante las pruebas sin autorizacion administrativa.

## Puerta siguiente: inventario SIN cambios del servidor

Se agrego `herramientas/auditar-servidor-milac-solo-lectura.sh` para
inspeccionar PHP CLI y extensiones, mod_php7.4 y mod_rewrite configurados,
sintaxis Apache del usuario actual (resultado no concluyente si hay permisos
insuficientes), y la ruta a `public`. No lee `.env`, certificados,
bases de datos ni Redis y no modifica servicios ni archivos. Este
diagnostico esta **pendiente de ejecucion por el usuario en Debian 9**;
no copiar ni hacer pull en el directorio publicado para ejecutarlo.
El PHP WEB efectivo, TLS corporativo y autenticacion siguen pendientes.

## Inspeccion de composer.lock en la rama (28/oct/2026)

Se leyo el lock actualmente versionado en GitHub. Contiene
`laravel/framework v8.83.29`, `platform.php: ^7.4.13` y
`platform-overrides.php: 7.4.13`; se comprobaron las claves
`composer.json/config.platform.php=7.4.13` y `content-hash` presente.
Esto corrige referencias historicas del expediente que describian un
lock de Laravel 13. No equivale a certificar todas las dependencias
instaladas del **servidor**: ejecutar `composer check-platform-reqs`
en copia aislada de PHP7.4, nunca modificar el Composer global 1.6.5
ni instalar paquetes en produccion sin plan aprobado.
