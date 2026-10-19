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
