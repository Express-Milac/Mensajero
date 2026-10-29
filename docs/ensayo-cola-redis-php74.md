# Ensayo de trabajo real en Redis con PHP 7.4.13

Estado: **APROBADO en Windows con PHP 7.4.13 y Redis local, 28/oct/2026**.

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

Resultado real aportado por el usuario en Windows PHP 7.4.13:
```text
COLA_EXCLUSIVA_VERIFICADA: mensajero_ensayo_php74_01e6982b7721436faf95736961babeaa
TRABAJOS_PUBLICADOS: 1
PREPARACION_COLA_REDIS74_APROBADA
Processing: Tests\\Soporte\\TrabajoColaRedisPhp74
Processed:  Tests\\Soporte\\TrabajoColaRedisPhp74
TRABAJO_PROCESADO: SI
COLA_VACIA: SI
CLAVES_DE_ENSAYO_LIMPIADAS: SI
ENSAYO_COLA_REDIS74_APROBADO
```

El trabajador uso `queue:work redis --once --tries=1 --timeout=15`
para consumir un unico trabajo ficticio, y los chequeos confirmaron
acuse y cola vacia; las claves temporales de ese ensayo se limpiaron.

**Alcance:** certifica PHP 7.4.13 / Laravel 8 / Predis y worker en Redis
local de Windows, pero **no** certifica procesamiento de eventos reales,
integracion con SQL + Redis en un mismo flujo, Redis en Debian 9 ni
instalacion o exposicion publica en MILAC. No iniciar trabajadores de
produccion hasta auditar el servidor y contar con aprobacion expresa.
