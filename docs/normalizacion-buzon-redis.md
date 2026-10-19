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
admisible exige `--evento-id` y `--limite=1`, un evento en estado
`capturado` de un grupo aún autorizado, entorno local/de pruebas y SQL Server
`_test`. Cuando se habilite el modo automático en un despliegue validado,
la orden programada podrá despachar lotes; **no activar ese indicador todavía**.

Pruebas SQL Server añadidas, aún pendientes de ejecución local:

```powershell
php -l app\Console\Commands\DespacharBuzonWhatsapp.php
php -l app\Console\Commands\RecuperarPublicacionWhatsapp.php
vendor\bin\pint --test app\Console\Commands\DespacharBuzonWhatsapp.php app\Console\Commands\RecuperarPublicacionWhatsapp.php tests\Integracion\ProteccionDespachoSqlServerTest.php tests\Integracion\RecuperacionPublicacionSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter "ProteccionDespachoSqlServerTest|RecuperacionPublicacionSqlServerTest"
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
