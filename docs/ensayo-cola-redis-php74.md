# Ensayo de trabajo real en Redis con PHP 7.4.13

Estado: **preparado en el repositorio, pendiente de ejecucion local**.

## Resultado ya comprobado

El usuario verifico `Redis::ping()` a `127.0.0.1:6379` con
PHP 7.4.13, Laravel 8 y Predis: PONG. No se consultaron claves ni
se publicaron trabajos en ese diagnostico.

Adicionalmente, la suite SQL Server en la base desechable
`mensajero_php74_231a8c29f3_test` aprobo 56 tests y 880 aserciones.

## Nuevo ensayo limitado

Archivos:

- `tests/Soporte/TrabajoColaRedisPhp74.php` contiene un trabajo de
  pruebas (autoload-dev) que solo escribe un acuse con TTL de diez minutos.
- `herramientas/ensayar-cola-redis-php74.php` publica **exactamente un
  trabajo** a una cola generada con identificador aleatorio, verifica
  que inicialmente hay uno, y posteriormente comprueba que el trabajador
  dejo el acuse y que la cola esta vacia. Solo entonces limpia las
  claves de **esa misma cola exclusiva**.
- `herramientas/ejecutar-ensayo-cola-redis74.ps1` crea el prefijo exclusivo
  `mensajero:prueba_php74:<identificador>:`, fuerza `testing`,
  Redis local en 127.0.0.1:6379, SQLite **en memoria**, y deshabilita
  IA. Ejecuta `queue:work redis --once --tries=1 --timeout=15` con
  `--queue` igual al identificador exclusivo. Restaura variables
  del proceso al terminar.

No se usa SQL Server, WhatsApp, Gemini ni las colas de Mensajero.
El ensayo SI escribe temporalmente claves en Redis bajo un prefijo
aleatorio; no es de solo lectura. El trabajo es inofensivo. Las
claves de cola reciben TTL de seguridad y el acuse caduca solo.
No ejecutar sobre servidores corporativos ni cambiar los puertos.

## Ejecucion

En PowerShell desde la copia aislada `Mensajero-PHP74-Pruebas`,
tras actualizar desde la rama PHP74, ejecutar:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File ".\herramientas\ejecutar-ensayo-cola-redis74.ps1"
```

La salida final `ENSAYO_COLA_REDIS74_APROBADO` demuestra el procesamiento
real con el trabajador local. En caso de error no declarar aprobacion
ni iniciar trabajadores adicionales; revisar la salida sin imprimir
ninguna credencial. Redis en Debian 9 y el servidor MILAC siguen sin
validar y no deben tocarse.
