# Normalización y buzón hacia Redis — incremento del POC

## Propósito y límites

La recepción WhatsApp -> pasarela HMAC -> SQL Server **permanece intacta**.
El nuevo procesamiento no utiliza el número de WhatsApp ni requiere enviar mensajes.
Todas las tablas, variables nuevas de negocio, clases, comandos, diagnósticos y
pruebas del incremento se nombran en español, salvo términos técnicos de los marcos.

La tabla fuente `mensajero_eventos_crudos_whatsapp` permanece como evidencia
inmutable del mensaje recibido. `mensajero_buzon_salida` contiene una notificación
transaccional creada por el capturador al aceptar un evento. No se necesita una
nueva migración para este incremento: la tabla
`mensajero_mensajes_whatsapp` ya dispone de las relaciones y restricciones únicas.

## Recorrido

1. El capturador escribe evento crudo y buzón en la **misma transacción SQL Server**.
2. `mensajero:despachar-buzon` reserva un registro del buzón durante dos minutos.
3. Publica el identificador del evento en Redis, cola `mensajero_normalizacion`.
4. Solo después de publicar marca `enviado_en`; si Redis falla, conserva el registro,
   libera la reserva, registra un error sin secretos y aplaza el siguiente intento.
5. El trabajador de Redis ejecuta `NormalizarEventoWhatsappTrabajo`.
6. `NormalizarEventoWhatsapp` bloquea el evento crudo en una transacción SQL Server.
   Si ya está normalizado, devuelve `duplicado`, sin alterar el mensaje.
7. Los eventos originales `mensaje` se proyectan en
   `mensajero_mensajes_whatsapp`; se asocia el contacto por identificador del
   proveedor, se extrae contenido consultable y se resuelven referencias a citas.
   Las respuestas recibidas antes de su mensaje original se enlazan posteriormente.
8. Ediciones, eliminaciones y reacciones quedan en estado
   `pendiente_semantica` para una fase posterior. No se añaden a la tabla de mensajes
   como originales nuevos ni se pierden del historial crudo.
9. Los mensajes sin identificador externo reciben una identidad **interna**
   `sin_id_evento_crudo_<id>`, que no se presenta como identificador de WhatsApp.

## Garantías y límites

- La publicación es **al menos una vez**, no exactamente una vez: un fallo
  entre publicar Redis y marcar el buzón podría republicar el trabajo.
  La normalización usa bloqueo, deduplicación y restricciones únicas SQL.
- El estado `enviado_en` significa **publicado a Redis**, no completado. Una
  tarea fallida en Redis debe vigilarse con la cola de trabajos fallidos.
- Un conflicto de identidad no sobrescribe otro evento. Falla y requiere revisión.
- Los archivos multimedia siguen en almacenamiento privado fuera de SQL:
  el normalizador consulta el registro ya verificado y solo proyecta su estado.
- Para contenido desconocido conserva `otro` sin inventar texto.
- No se implementa aún aplicación final de ediciones/reacciones, conciliación
  externa de huecos ni visualización; continúan pendientes.

## Activación segura

De manera predeterminada:

```env
MENSAJERO_NORMALIZACION_HABILITADA=false
MENSAJERO_COLA_NORMALIZACION=mensajero_normalizacion
```

**No habilitar la ejecución automática antes de pasar todas las pruebas en
`mensajero_test`**, ni usar comandos sobre producción durante el POC.

### Pruebas aisladas sin Redis físico

Desde la raíz del proyecto:

```powershell
vendor\bin\pint --test app\Services\Normalizacion app\Jobs\NormalizarEventoWhatsappTrabajo.php app\Console\Commands\DespacharBuzonWhatsapp.php tests\Unidad\ExtraerContenidoWhatsappTest.php tests\Integracion\NormalizacionWhatsappSqlServerTest.php
vendor\bin\phpunit --filter ExtraerContenidoWhatsappTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter NormalizacionWhatsappSqlServerTest
```

La prueba SQL verifica que la conexión sea `sqlsrv` y que la base termine en
`_test` antes de migrar; usa transacción reversible y una cola Redis simulada.

### Cuando Redis esté instalado y validado en servidor

Configurar y arrancar un trabajador persistente:

```powershell
php artisan queue:work redis --queue=mensajero_normalizacion --sleep=1 --tries=5
```

Conectar `php artisan schedule:work` (o programador habitual del servidor) y
establecer `MENSAJERO_NORMALIZACION_HABILITADA=true` únicamente tras certificar
su entorno. El programador invoca una vez por minuto:

```text
php artisan mensajero:despachar-buzon --limite=100
```

Durante el POC, el parámetro opcional `--evento-id=123` permite despachar
un evento específico. No ejecutar el comando en una base operativa hasta
aprobar la integración, porque procesaría eventos históricos pendientes.

## Pendientes antes de operación

- Verificar trabajo real contra Redis (la prueba actual lo simula).
- Observar entregas repetidas, caídas de Redis y recuperación tras reinicio.
- Diseñar seguimiento de trabajos fallidos de Redis y alertas.
- Construir vista por grupo y estado de integridad por periodo.
- Reconciliar intervalos no confirmados con origen externo.


## Ensayo controlado de un único evento

Antes de activar Redis o el procesamiento automático, se agregó un comando con
vista previa sin escrituras:

```powershell
php artisan mensajero:normalizar-evento 11070
```

Muestra únicamente el identificador, el grupo, el tipo y el estado. No imprime
el texto, el remitente ni el JSON original. No escribe en SQL Server sin la
opción explícita `--aplicar`.

Tras aprobar la nueva prueba de integración SQL Server, y únicamente con
autorización del operador, puede ensayarse ese evento concreto:

```powershell
php artisan mensajero:normalizar-evento 11070 --aplicar
```

Restricciones comprobadas antes de procesar: entorno `local` o `testing`,
conexión activa `sqlsrv`, nombre de BD terminado en `_test`, evento existente
de tipo `mensaje` y grupo que permanezca activo y monitoreado. La aplicación
es idempotente y solo alcanza el ID solicitado. No ejecuta colas, no consume
automáticamente el histórico y no utiliza WhatsApp.

**Pruebas nuevas:**

```powershell
vendor\bin\pint --test app\Console\Commands\NormalizarEventoEspecificoWhatsapp.php tests\Integracion\NormalizacionControladaSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter NormalizacionControladaSqlServerTest
```

No ejecutar `--aplicar` sobre datos reales mientras no estén revisadas estas
pruebas ni se haya aprobado el mensaje específico. La prueba automatizada usa
transacciones reversibles en `mensajero_test`.


## Normalización controlada de un pequeño lote (aún sin Redis)

Se implementó `mensajero:normalizar-lote` como una extensión manual del POC,
sin programar un procesamiento periódico y sin modificar el capturador.

**Vista previa sin escritura:**

```powershell
php artisan mensajero:normalizar-lote 10020 --limite=3
```

El comando enumera hasta tres eventos pendientes de un único grupo autorizado,
en orden de identificador ascendente. Muestra exclusivamente IDs y cantidades:
no imprime remitentes, contenido, identificadores externos ni multimedia. Si
no hay mensajes elegibles, devuelve una lista vacía sin fallo.

Para **aplicar**, el operador debe escribir explícitamente los IDs previamente
revisados, por ejemplo:

```powershell
php artisan mensajero:normalizar-lote 10020 --limite=3 --ids=11068,11069 --aplicar
```

El ejemplo es únicamente ilustrativo: siempre cotejar los IDs que muestre la
vista previa. La ejecución sin `--ids` se bloquea, aunque incluya `--aplicar`.

Protecciones:
- Entorno `local` o `testing`, conexión SQL Server y base terminada en
  `_test`.
- Grupo de tipo `grupo`, activo y expresamente autorizado para monitoreo.
- Máximo 5 eventos por ejecución, sin IDs duplicados.
- Solo eventos `mensaje`, tipo `texto`, contrato `v3-recolector`. No
  procesa ediciones, eliminaciones ni multimedia.
- Todos los IDs deben pertenecer al mismo grupo y estar en estado
  `capturado` o `normalizado`. Un ID inválido impide comenzar el lote.
- La normalización de cada evento usa el servicio transaccional idempotente.
  Repetir IDs ya normalizados no duplica mensajes.
- Si falla un evento, se detiene la ejecución y **los eventos ya completados
  pueden permanecer normalizados**. El operador puede volver a consultarlos;
  no se promete atomicidad entre diferentes eventos del lote.

Las pruebas de integración cubren vista previa sin escrituras, selección exacta,
idempotencia, rechazo de grupos mezclados, IDs repetidos y base no autorizada.
Se ejecutan contra `mensajero_test` con transacciones reversibles:

```powershell
vendor\bin\pint --test app\Console\Commands\NormalizarLoteWhatsapp.php tests\Integracion\NormalizacionLoteSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter NormalizacionLoteSqlServerTest
```

No utilizar `--aplicar` con mensajes de la cuenta real hasta haber aprobado
la suite de integración y revisado la vista previa. La ejecución automática
mediante Redis sigue deshabilitada.

## Auditoría de solo lectura posterior a la normalización por lotes

Cuando el operador haya ejecutado explícitamente un lote, **no basta con ver dos mensajes `normalizado`**. Es preciso comprobar la persistencia y las relaciones.

El comando independiente de verificación no realiza escritura ni inicia Redis:

```powershell
php artisan mensajero:verificar-lote 10020 --ids=10055,10056
```

Condiciones de seguridad: `APP_ENV=local` o `testing`, conexión `sqlsrv`, nombre de base terminado en `_test`, grupo activo y autorizado. Acepta entre uno y cinco IDs explícitos, sin repetidos.

Comprueba para cada ID que:
- Exista en el grupo, sea original `mensaje/texto/v3-recolector` y esté `normalizado`.
- Exista **exactamente una** proyección en `mensajero_mensajes_whatsapp`.
- Coincidan la conexión, el grupo, el evento original, la identidad de proveedor, el tipo y la referencia citada.
- El SHA-256 del contenido crudo **actual** coincida con el hash registrado. Esto no demuestra por sí solo que sea idéntico a una captura anterior si no se dispone de un hash previo independiente.
- Para las citas cuyo mensaje original esté entre los IDs seleccionados, `mensaje_citado_id` apunte a la proyección esperada. Las citas fuera del lote se cuentan como no verificadas, nunca como aprobadas.

La salida únicamente expone IDs internos, cantidades y motivos genéricos de fallo. No muestra texto, identificadores de WhatsApp ni material criptográfico.

Pruebas de regresión **aprobadas el 19/10/2026 en Windows/PHP 8.4.7 con SQL Server de pruebas** (después de sustituir la aserción frágil de salida Artisan por comprobaciones de retorno y comparación de registros): PHP `-l` sin errores, Pint 1/1, PHPUnit `VerificacionLoteSqlServerTest` 3/3 pruebas, 28 aserciones, 0 fallos:

```powershell
vendor\bin\pint --test app\Console\Commands\VerificarLoteWhatsapp.php tests\Integracion\VerificacionLoteSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter VerificacionLoteSqlServerTest
```

Una verificación fallida no debe corregirse alterando el contenido crudo original. Investigar la discrepancia antes de continuar.

## Próxima etapa: comprobación de Redis real sin procesar datos

Antes de iniciar un trabajador o publicar trabajos reales, verificar conexión y configuración local, manteniendo `MENSAJERO_NORMALIZACION_HABILITADA=false` y sin mostrar secretos del `.env`:

```powershell
git pull origin codex/raw-foundation-no-auth
php artisan mensajero:diagnostico
```

`mensajero:diagnostico` comprueba conectividad SQL Server, conectividad Redis y almacenamiento (con un archivo temporal borrado al finalizar). **No despacha el buzón, no normaliza mensajes y no inicia trabajadores**. En el estado actual puede informar `whatsapp: no_implementado`, campo genérico que no certifica ni invalida la captura ya probada del recolector.

Para permitir una prueba con Redis físico, primero deberá verificarse **localmente**:

- `DB_CONNECTION=sqlsrv` y `DB_DATABASE` terminado en `_test` (nunca la base operativa).
- `MENSAJERO_NORMALIZACION_HABILITADA=false` hasta completar la certificación; no arrancar `schedule:work`.
- El servidor Redis, prefijo y nombre de cola utilizados son **exclusivos del POC**, diferentes de cualquier proceso de MILAC en producción. No reutilizar colas compartidas para `queue:work`.
- El ensayo de publicación futuro utilizará un único evento sintético elegido por ID explícito y un trabajador de ejecución acotada; no `mensajero:despachar-buzon --limite=100` sin filtro.

**PENDIENTE:** recibir el diagnóstico local, documentar la separación de Redis y ejecutar la comprobación punta a punta con recuperación ante fallos. La aprobación de la normalización manual no certifica Redis ni el procesamiento automático.

## Decisión de arquitectura para producción: Redis con SQL Server como evidencia

**Decisión confirmada (19/10/2026):** conservar Redis como intermediario de trabajos
para Mensajero MILAC. No sustituye SQL Server ni se considera fuente primaria de
mensajes. La recepción guarda primero el evento y su aviso en la misma transacción
SQL Server; el fallo de Redis no debe impedir conservar una captura ya aceptada.

No existe garantía de cero fallos: la seguridad operativa depende de recuperación,
copias de seguridad, supervisión y pruebas de fallos, además del propio Redis.

### Riesgo detectado: aviso publicado, trabajo no consumido

La columna `enviado_en` del buzón indica que se publicó el trabajo en Redis,
**no** que se procesó. Una cola perdida, un fallo del trabajador o un reinicio sin
persistencia suficiente pueden dejar avisos marcados como enviados aunque el
evento siga `capturado`.

Para estudiar este caso en el POC se añadió un comando conservador de recuperación
**manual**, restringido a `sqlsrv`, base terminada en `_test`, entorno
`local/testing`, un evento y grupo autorizado:

```powershell
# Vista previa: no escribe, no usa Redis.
php artisan mensajero:recuperar-publicacion 12345 --minutos=30

# Solo tras revisar el evento exacto en la BD de pruebas.
php artisan mensajero:recuperar-publicacion 12345 --minutos=30 --aplicar
```

El comando únicamente restablece `enviado_en`, `reservado_hasta` y
`disponible_en` del aviso de salida. Exige una publicación de antigüedad
mínima de 30 minutos y un evento aún `capturado`; rechaza grupos suspendidos,
mensajes normalizados y bases no `_test`. No accede a WhatsApp, Redis ni modifica
el evento crudo. Una republicación posterior aún requiere despacho explícito,
cola real y trabajador aislado. La entrega podría repetirse: depende de que la
normalización siga siendo idempotente.

**Pendiente para producción:** crear conciliación automática paginada, acotada
y observable de avisos publicados cuyo evento no haya completado; cubrir
trabajos fallidos, colas perdidas, apagados abruptos, reintentos y alertas.
El comando manual anterior no cumple por sí solo ese objetivo.

### Protección adicional del despachador

Mientras `MENSAJERO_NORMALIZACION_HABILITADA=false`, el comando
`mensajero:despachar-buzon` **rechaza el despacho global**. El único ensayo
admisible exige `--evento-id`, `--limite=1` y `--cola-ensayo` con
una cola única vinculada a una conexión **sintética** creada por
`mensajero:preparar-ensayo-redis`, además de evento `capturado`, grupo
autorizado, entorno local/de pruebas y SQL Server `_test`. Cuando se habilite el modo automático en un despliegue validado,
la orden programada podrá despachar lotes; **no activar ese indicador todavía**.

Pruebas SQL Server añadidas, aún pendientes de ejecución local:

```powershell
php -l app\Console\Commands\DespacharBuzonWhatsapp.php
php -l app\Console\Commands\RecuperarPublicacionWhatsapp.php
vendor\bin\pint --test app\Console\Commands\DespacharBuzonWhatsapp.php app\Console\Commands\RecuperarPublicacionWhatsapp.php tests\Integracion\ProteccionDespachoSqlServerTest.php tests\Integracion\RecuperacionPublicacionSqlServerTest.php
# En PowerShell, ejecutar cada clase por separado: el filtro con | puede
# ser interpretado como tubería al atravesar el iniciador de Windows.
vendor\bin\phpunit -c phpunit.integracion.xml --filter ProteccionDespachoSqlServerTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter RecuperacionPublicacionSqlServerTest
```

### Requisitos de liberación a producción (no certificados todavía)

- Redis aislado de otros sistemas, protegido de acceso público; configurar ACL,
  autenticación y persistencia AOF/RDB según política de recuperación aprobada.
- Servicio Redis y trabajadores reiniciables y supervisados; definir redundancia
  si el negocio exige disponibilidad durante el fallo de un nodo.
- Aislar prefijos, base Redis y nombre de cola entre pruebas y producción.
- Alinear tiempo de ejecución, `retry_after` y tiempo de espera del trabajador
  para evitar reintentos simultáneos; ensayar cortes de red y reinicios.
- Vigilar retraso y tamaño del buzón SQL, edad máxima de mensajes capturados
  sin normalizar, cola Redis, trabajos fallidos, almacenamiento e integridad por grupo.
- Ensayar recuperación sin duplicar proyecciones, incluyendo avisos publicados
  que desaparecieron de Redis, y demostrar reanudación tras caída.
- Respaldar SQL Server y archivos multimedia privados; los datos de Redis
  no reemplazan dichas copias.
- No activar grupos operativos o el número corporativo principal antes de pasar
  pruebas de carga, estabilidad y seguridad.

### Aislamiento de sesiones y caché

Como protección contra la caída de Redis, las configuraciones predeterminadas
`SESSION_DRIVER` y `CACHE_STORE` se han cambiado a `database`, utilizando
las tablas de Mensajero existentes. La cola asíncrona permanece en
`QUEUE_CONNECTION=redis`. Esto evita que el procesamiento de sesiones y
las limitaciones de solicitudes tengan que depender del mismo Redis que el
trabajador, aunque aumenta algunas consultas SQL y requiere pruebas de carga.

**Importante:** el `.env` real del operador no ha sido modificado; si todavía
contiene `SESSION_DRIVER=redis` o `CACHE_STORE=redis`, esos valores se
imponen a los nuevos predeterminados. Verificar solo los valores efectivos de
configuración, sin publicar el archivo `.env` ni contraseñas. Revalidar caché
y sesiones en SQL Server de pruebas antes de desplegar.


## Instalación local de Redis en Debian WSL2 (POC Windows)

El 19/10/2026 se confirmó que Debian corre en WSL2 y que no se encontraron
ejecutables `redis-server` ni `redis-cli`. Se aprobaron **7 pruebas de
protección/recuperación SQL Server con 57 aserciones**, sin utilizar Redis físico.

Para preparar Redis **solo en el equipo de desarrollo** (no en producción):

```powershell
& "$env:WINDIR\System32\wsl.exe" -d Debian -- bash -lc "sudo apt-get update && sudo apt-get install -y redis-server"
```

Antes de iniciar el servicio, confirmar configuración de Redis dentro de Debian:

```powershell
& "$env:WINDIR\System32\wsl.exe" -d Debian -- bash -lc "grep -E '^[[:space:]]*(bind|protected-mode|port)[[:space:]]+' /etc/redis/redis.conf"
```

Debe estar limitado a las interfaces de bucle local `127.0.0.1` / `::1`,
con `protected-mode yes` y puerto previsto `6379`; si muestra una interfaz
externa, no iniciar y revisar seguridad. No abrir reglas de firewall ni reenviar
6379 al exterior.

Solo después, iniciar y comprobar:

```powershell
& "$env:WINDIR\System32\wsl.exe" -d Debian -- bash -lc "sudo service redis-server start && redis-cli ping"
Test-NetConnection 127.0.0.1 -Port 6379 | Select-Object ComputerName, RemotePort, TcpTestSucceeded
php artisan mensajero:diagnostico
```

Se espera `PONG` del servidor Linux y `redis=correcto` en el diagnóstico de
Laravel, si el puente localhost de WSL2 funciona y `.env` apunta al mismo
servidor. Redis de WSL2 **no es el Redis de producción** y no está diseñado en
esta guía para una operación ininterrumpida 24/7.

Mientras tanto, mantener `MENSAJERO_NORMALIZACION_HABILITADA=false`; no
iniciar `queue:work`, `schedule:work` ni `despachar-buzon`. No compartir
`.env`, contraseñas ni identificadores privados en capturas de consola.


## Ensayo aislado punta a punta con Redis local

Desde el 19/10/2026, Redis en Debian WSL2 respondió `PONG`,
Windows conectó a `127.0.0.1:6379` y `mensajero:diagnostico` informó
`redis=correcto`, `sql_server=correcto` y `almacenamiento=correcto`.
Esto solo acredita conectividad, **no** entrega y procesamiento de trabajos.

### Primero: validar los comandos de ensayo (sin publicar Redis)

```powershell
git pull origin codex/raw-foundation-no-auth

php -l app\Console\Commands\PrepararEnsayoRedis.php
php -l app\Console\Commands\DespacharBuzonWhatsapp.php
vendor\bin\pint --test app\Console\Commands\PrepararEnsayoRedis.php app\Console\Commands\DespacharBuzonWhatsapp.php tests\Integracion\PrepararEnsayoRedisSqlServerTest.php tests\Integracion\ProteccionDespachoSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter PrepararEnsayoRedisSqlServerTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter ProteccionDespachoSqlServerTest
```

Con `MENSAJERO_NORMALIZACION_HABILITADA=false`, el comando del ensayo
sintético solo escribe en SQL Server `_test`, nunca a WhatsApp ni a Redis:

```powershell
php artisan mensajero:preparar-ensayo-redis
# Solo cuando las pruebas sean correctas y con autorización del operador:
php artisan mensajero:preparar-ensayo-redis --aplicar
```

La segunda orden crea **una conexión ficticia, un grupo ficticio y un evento
de texto sintético**, insertando también su aviso en el buzón transaccional.
Imprime `Evento sintético`, `Grupo ficticio` y una cola irrepetible con
nombre `mensajero_ensayo_<16 hex>`. No usa número de WhatsApp real.

### Después, únicamente cuando el operador apruebe el ID sintético exacto

```powershell
# SUSTITUIR ID y COLA por los impresos por el comando anterior.
php artisan mensajero:despachar-buzon --evento-id=ID --limite=1 --cola-ensayo=COLA
php artisan queue:work redis --queue=COLA --once --tries=1 --timeout=60
php artisan mensajero:verificar-lote GRUPO --ids=ID
```

La protección del despachador exige que, en modo manual, la cola y el evento
se correspondan por el sufijo aleatorio de la conexión ficticia, que el grupo
esté activo y autorizado y que el evento siga `capturado`. Solo se permite
`--limite=1` en SQL Server `_test` y entorno local/de pruebas. Los ejemplos
`ID`, `COLA` y `GRUPO` no son literales ejecutables: deben reemplazarse
con la salida verificada. **No ejecutar trabajadores genéricos ni el
programador automático.**

La orden `--once` limita el trabajador a un único trabajo de la cola
exclusiva. La auditoría posterior debe informar `1/1 eventos correctos`.
Si falla, no repetir publicaciones a ciegas: conservar salida, investigar
estado SQL/buzón/cola y, cuando corresponda, usar recuperación controlada.

La aprobación de este ensayo **no equivale** a certificación de producción:
continúan pendientes la conciliación automática de entregas perdidas,
reintentos supervisados, pruebas de reinicio y carga, autorización corporativa
y puesta en servicio en la infraestructura MILAC.


## Control adicional al consumir tareas Redis

Después del primer ensayo físico exitoso (evento sintético `#11117`,
grupo ficticio `#10089`, **DONE en 172.69 ms** y auditoría de solo
lectura **1/1 correctos**), se identificó una condición de carrera de
autorización: entre aceptar o publicar un evento y ejecutar el trabajador,
un grupo podría perder el permiso de monitoreo.

`NormalizarEventoWhatsapp` ahora vuelve a comprobar, dentro de la
transacción de normalización y con bloqueo SQL, que el grupo siga activo,
autorizado y asociado a la misma conexión; los grupos revocados o
inactivos se rechazan sin generar proyecciones nuevas ni borrar la
captura original. El trabajador deja que el error se maneje mediante
el sistema de reintentos/fallidos de Laravel. **Esta protección todavía
necesita validación local tras el cambio.**

Ejecutar desde PowerShell (solo integración SQL Server `_test`):

```powershell
git pull origin codex/raw-foundation-no-auth
php -l app\Services\Normalizacion\NormalizarEventoWhatsapp.php
php -l tests\Integracion\NormalizacionWhatsappSqlServerTest.php
vendor\bin\pint --test app\Services\Normalizacion\NormalizarEventoWhatsapp.php tests\Integracion\NormalizacionWhatsappSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter NormalizacionWhatsappSqlServerTest
```

No crear eventos nuevos, no republicar `#11117`, no iniciar
`schedule:work` ni trabajadores continuos durante esta validación.
Pendiente completar conciliación automática de trabajos publicados
pero no normalizados y alertas de reintento/fallo.


## Ensayo de recuperación simulada sin detener Redis (POC)

**Paso previo completado, 19/10/2026:** `NormalizacionWhatsappSqlServerTest`
aprobó **6 pruebas / 68 aserciones**, sin errores, después de añadir la
revalidación de autorización en el momento de consumir.

Para probar el caso `aviso enviado a Redis pero no procesado` sin tocar
servidores compartidos, el comando `mensajero:preparar-ensayo-redis` ahora
acepta `--simular-perdida`. Al combinarlo con `--aplicar`, crea
**otro** mensaje ficticio nuevo (no reutiliza el #11117) en SQL Server
`_test`. En la misma transacción SQL marca solamente su aviso como
`enviado_en = ahora - 31 minutos`, conservando estado `capturado`.
**Nunca envía un trabajo a Redis ni borra otros trabajos**. Este estado
representa una simulación, no una caída real de Redis.

Antes de crear datos, ejecutar **solo las pruebas automatizadas**:

```powershell
git pull origin codex/raw-foundation-no-auth

php -l app\Console\Commands\PrepararEnsayoRedis.php
php -l tests\Integracion\PrepararEnsayoRedisSqlServerTest.php
vendor\bin\pint --test app\Console\Commands\PrepararEnsayoRedis.php tests\Integracion\PrepararEnsayoRedisSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter PrepararEnsayoRedisSqlServerTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter RecuperacionPublicacionSqlServerTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter ProteccionDespachoSqlServerTest
```

La prueba nueva simula el aviso publicado hace 31 minutos, verifica que
`mensajero:recuperar-publicacion` no modifica datos en la vista previa,
permite reabrir el aviso por ID con `--aplicar`, y preserva el hash y
el evento crudo. También confirma que no se publica nada a Redis.

**Únicamente después de aprobar las pruebas**, con un evento ficticio y
su ID generado durante el ensayo, se podrán ejecutar por etapas:

```powershell
# Vista previa sin escrituras:
php artisan mensajero:preparar-ensayo-redis --simular-perdida

# Paso posterior, con autorización para crear un evento ficticio:
# php artisan mensajero:preparar-ensayo-redis --simular-perdida --aplicar

# Reemplazar ID por el NUEVO ID ficticio mostrado por el comando:
# php artisan mensajero:recuperar-publicacion ID --minutos=30
# php artisan mensajero:recuperar-publicacion ID --minutos=30 --aplicar
# php artisan mensajero:despachar-buzon --evento-id=ID --limite=1 --cola-ensayo=COLA
# php artisan queue:work redis --queue=COLA --once --tries=1 --timeout=60
# php artisan mensajero:verificar-lote GRUPO --ids=ID
```

Las órdenes comentadas **no deben ejecutarse en bloque**: cada salida
requiere comprobación antes de proceder. Mantener
`MENSAJERO_NORMALIZACION_HABILITADA=false`; no ejecutar
`schedule:work`, `queue:work` general, `FLUSHDB` o `DEL` contra Redis.
Esta simulación no sustituye pruebas posteriores de fallo real de red,
reinicio, concurrencia, recuperación automática y supervisión.


## Conciliación de avisos enviados sin normalizar: fase de pruebas

El 19/10/2026 se **completó el ensayo manual extremo a extremo** del
aviso sintético `#11135`: trabajo Redis **DONE en 106.72 ms**, evento
`#11135` auditado **1/1 correcto**, sin escrituras durante auditoría.

Se añadió un comando de **conciliación por lote** para el siguiente paso:

```powershell
# Vista previa sin escritura y sin publicar Redis:
php artisan mensajero:conciliar-publicaciones --minutos=30 --limite=10

# Solo tras verificar la lista exacta, volver a dejar pendientes los avisos:
# php artisan mensajero:conciliar-publicaciones --minutos=30 --limite=10 --aplicar
```

Restricciones del prototipo: `APP_ENV=local/testing`, SQL Server con
nombre terminado en `_test`, normalización automática desactivada,
máximo **20 avisos por ejecución** y antigüedad mínima **30 minutos**.
Además, solo contempla conexiones, chats y eventos **ficticios**
generados por el ensayo Redis, con clave `ensayo-redis-<16 hex>`.
Excluye registros ya normalizados, grupos inactivos o no autorizados,
avisos recientes o reservados. Verifica de nuevo cada candidato bajo
bloqueo SQL antes de escribir.

El comando **no publica mensajes en Redis**, no inicia trabajadores ni
activa tareas programadas. `--aplicar` solo restablece avisos SQL, que
podrían ser publicados posteriormente mediante un despacho separado.
No cuenta como conciliación automática desplegable en producción.

### Validación pendiente en la máquina Windows / SQL Server `_test`

```powershell
git pull origin codex/raw-foundation-no-auth
php -l app\Console\Commands\ConciliarPublicacionesWhatsapp.php
php -l tests\Integracion\ConciliacionPublicacionesSqlServerTest.php
vendor\bin\pint --test app\Console\Commands\ConciliarPublicacionesWhatsapp.php tests\Integracion\ConciliacionPublicacionesSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter ConciliacionPublicacionesSqlServerTest
php artisan mensajero:conciliar-publicaciones --minutos=30 --limite=10
```

La última orden es solo vista previa. **No ejecutar `--aplicar`
hasta revisar resultados de las pruebas y los IDs mostrados**. Para
producción faltan alertas, criterios para autorización a escala,
reconciliación programada, métricas, concurrencia y pruebas de fallos
reales. Nunca ejecutar `schedule:work` ni activar
`MENSAJERO_NORMALIZACION_HABILITADA` durante estos ensayos.


## Supervisión de atrasos de la cola SQL (solo lectura)

La conciliación de avisos antiguos pasó en Windows **3 pruebas, 42 aserciones**
con cero fallos. La vista previa real devolvió **0 examinados / 0 elegibles /
0 restablecidos**. Ningún aviso histórico fue reabierto.

Para detectar atrasos sin aplicar recuperaciones, se añadió
`mensajero:vigilar-buzon`, respaldado por un medidor reutilizable. Informa
conteos agregados de eventos aún `capturado` en grupos **activos y
monitoreados**, con el mismo origen SQL Server:

- Pendientes por publicar.
- Pendientes por publicar con antigüedad mayor al umbral (`creado_en`).
- Publicados a Redis, pero aún sin normalizar.
- Publicados antiguos sin normalizar (`enviado_en`).
- Reservas vencidas que todavía no se publican.

**Un aviso antiguo no demuestra pérdida en Redis**: podría seguir en cola,
estar esperando un reintento o tener un trabajador detenido. La comprobación
contra Redis y el análisis de trabajadores fallidos son pasos independientes.
La herramienta excluye grupos revocados e inactivos de su alcance principal;
estos requieren también vigilancia diferenciada antes de producción.

En esta versión solo se ejecuta en entorno local/testing con SQL Server
`_test`, no escribe ni usa Redis ni envía alertas. La opción `--alertar`
devuelve código no cero si detecta avisos atrasados, útil para conectar
futuros supervisores una vez aprobados. No hay programación automática.

### Validaciones pendientes de ejecutar en Windows

```powershell
git pull origin codex/raw-foundation-no-auth

php -l app\Services\Normalizacion\MedirEstadoBuzonWhatsapp.php
php -l app\Console\Commands\VigilarBuzonWhatsapp.php
php -l tests\Integracion\VigilanciaBuzonSqlServerTest.php

vendor\bin\pint --test app\Services\Normalizacion\MedirEstadoBuzonWhatsapp.php app\Console\Commands\VigilarBuzonWhatsapp.php tests\Integracion\VigilanciaBuzonSqlServerTest.php

vendor\bin\phpunit -c phpunit.integracion.xml --filter VigilanciaBuzonSqlServerTest

php artisan mensajero:vigilar-buzon --minutos=30
```

La última orden es **solo lectura**, incluso si hay avisos atrasados. No
ejecutar `--alertar` hasta revisar el primer informe, ni activar
`schedule:work` o un trabajador continuo. Antes de producción se necesita
observación de cola Redis y trabajos fallidos, métricas agregadas con
umbrales aprobados, procedimientos para alertas y pruebas de carga y caída.


## Investigación del atraso detectado el 19/10/2026

La primera ejecución local de `php artisan mensajero:vigilar-buzon
--minutos=30` informó **40 avisos sin publicar, todos atrasados**,
**0 publicados sin normalizar** y **0 reservas vencidas**. Pasaron
**3 pruebas, 28 aserciones**, sintaxis PHP (3/3) y Pint (3/3).
Esta medición incluye eventos `capturado` en grupos activos y
monitoreados de SQL Server `_test`; no permite determinar todavía
si son pruebas históricas o registros que necesiten procesamiento.

Se incorporó `--desglose` para mostrar la distribución exclusivamente
por **identificador interno de grupo y conexión**, sin datos personales,
nombres de grupos, texto, multimedia ni ID externo. Cuenta pendientes
totales, mayores al umbral indicado, mayores a 24 horas y 7 días.
Muestra a lo sumo 50 grupos, e indica si el resultado fue truncado.
El indicador de prefijo de ensayo es orientativo, no demuestra que
todos los datos de la conexión sean ficticios.

Para verificar este cambio antes de consultar de nuevo el atraso:

```powershell
git pull origin codex/raw-foundation-no-auth
php -l app\Services\Normalizacion\MedirEstadoBuzonWhatsapp.php
php -l app\Console\Commands\VigilarBuzonWhatsapp.php
php -l tests\Integracion\VigilanciaBuzonSqlServerTest.php
vendor\bin\pint --test app\Services\Normalizacion\MedirEstadoBuzonWhatsapp.php app\Console\Commands\VigilarBuzonWhatsapp.php tests\Integracion\VigilanciaBuzonSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter VigilanciaBuzonSqlServerTest

# Solo lectura, consultar agrupación sin mostrar contenido:
php artisan mensajero:vigilar-buzon --minutos=30 --desglose
```

**No ejecutar** `mensajero:despachar-buzon` sin ID ficticio exacto,
`mensajero:conciliar-publicaciones --aplicar`, `queue:work`
genérico ni tareas programadas por estos 40 avisos. Primero revisar
la procedencia y autorización de los grupos. Para producción
seguirá faltando la supervisión propia de Redis, fallos de
trabajadores, historial de reintentos, alertas y un tratamiento
diferenciado de grupos revocados.


## Distribución de los 40 avisos y clasificación privada (19/10/2026)

La lectura de `mensajero:vigilar-buzon --minutos=30 --desglose`
se ejecutó correctamente en `mensajero_test`, con
**4 pruebas de integración y 52 aserciones aprobadas**:

| Grupo interno | Conexión interna | Pendientes | Mayores a 24 h | Mayores a 7 días |
|---|---|---:|---:|---:|
| 10020 | 10014 | 38 | 32 | 0 |
| 16 | 16 | 1 | 1 | 0 |
| 20 | 20 | 1 | 1 | 0 |

Todos los pendientes superan 30 minutos, los 40 siguen sin publicar,
no hay avisos publicados sin normalizar ni reservas vencidas. Ninguno
de estos tres grupos tiene el prefijo `ensayo-redis-`, pero esto
**no demuestra que los registros sean reales o ficticios**. El grupo
10020 fue utilizado también para pruebas manuales anteriores. Su
historial debe preservarse hasta determinar si requiere procesamiento.

Se añadió al diagnóstico la opción **`--clasificar`**, obligatoriamente
acompañada de un grupo interno autorizado. Cuenta los pendientes
por `tipo_evento`, `tipo_mensaje` y `version_contrato`, y expone
solo etiquetas permitidas o valores neutros. Limita a 50 combinaciones,
sin textos, nombres, remitentes, números telefónicos, identificadores
externos, multimedia ni fechas exactas. **Es solo lectura**.

Validar primero la nueva implementación:

```powershell
git pull origin codex/raw-foundation-no-auth

php -l app\Services\Normalizacion\MedirEstadoBuzonWhatsapp.php
php -l app\Console\Commands\VigilarBuzonWhatsapp.php
php -l tests\Integracion\VigilanciaBuzonSqlServerTest.php

vendor\bin\pint --test app\Services\Normalizacion\MedirEstadoBuzonWhatsapp.php app\Console\Commands\VigilarBuzonWhatsapp.php tests\Integracion\VigilanciaBuzonSqlServerTest.php

vendor\bin\phpunit -c phpunit.integracion.xml --filter VigilanciaBuzonSqlServerTest
```

Después, **solo si pasan las pruebas**, consultar por separado:

```powershell
php artisan mensajero:vigilar-buzon --grupo=10020 --minutos=30 --clasificar
php artisan mensajero:vigilar-buzon --grupo=16 --minutos=30 --clasificar
php artisan mensajero:vigilar-buzon --grupo=20 --minutos=30 --clasificar
```

No usar `--aplicar`, no modificar estados, no publicar trabajos Redis,
no reiniciar colas ni activar procesamiento general por este hallazgo.
La clasificación tampoco valida por sí sola que sea seguro normalizar
todo el historial.
