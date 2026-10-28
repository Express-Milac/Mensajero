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

## Siguiente etapa: Redis local sin escritura

Herramientas nuevas:

- `herramientas/diagnosticar-redis-php74.php`: comprueba PHP 7.4.13,
  Laravel 8, Predis 1.x, configuracion de pruebas y PING a Redis
  `127.0.0.1:6379`. No lee ni altera claves; desactiva colas y AI.
- `herramientas/ejecutar-diagnostico-redis74.ps1`: aplica variables
  temporales en PowerShell, configura SQLite en memoria para que la
  prueba no consulte SQL Server y restaura el entorno al terminar.

**Aun no ejecutado en el equipo del usuario.** Aprobar PING no verifica
publicacion de trabajos, procesamiento de colas ni workers. Esas pruebas
requieren un ensayo adicional exclusivamente en Redis local y con
una cola ficticia unica; nunca procesar colas reales del recolector.

## Pendientes para preparar despliegue MILAC

- Confirmar Redis local y despues una cola de ensayo con trabajador limitado.
- Verificar `php7.4` de Apache (se detecto configuracion mod_php 7.4),
  extension SQL y permisos del usuario web.
- Resolver advertencia de certificado Apache con el administrador y validar TLS
  SQL Server sin confiar ciegamente en un certificado no verificado.
- Comprobar autenticacion/red privada; no exponer mensajes a Internet sin login.
- Construir paquete aislado y procedimiento de respaldo/reversion.
- No modificar servicios Apache, PHP, certificados, Redis o SQL corporativo
  durante las pruebas sin autorizacion administrativa.
