# Gemini: preparación para procesamiento contextual por lotes

Estado: **desarrollo experimental sin activación**, 26/10/2026. Ningún archivo de esta fase reemplaza todavía el envío por mensaje individual.

## Motivo

El circuito actual contabiliza hasta 10 publicaciones por grupo y día UTC, con máximo configurable de 30. Las publicaciones de normalización y conciliación comparten el cupo. El prompt actual analiza **un acontecimiento por mensaje objetivo**, lo cual no escala para grupos con cientos de mensajes diarios.

El 26/10/2026 los grupos 10196, 10197 y 10198 no tenían análisis identificados sin acontecimiento pendiente de proyección. La tarea automática de proyección preventiva del PR #3 no repara la falta de cobertura de interpretación.

## Componentes experimentales añadidos

- `AgruparMensajesGemini`: planificador determinista. Ordena por fecha e ID, separa por grupo, admite hasta seis mensajes, pausa máxima de 90 segundos y ventana de cinco minutos.
- `ValidarAcontecimientosLoteGemini`: acepta de cero a seis acontecimientos en `{"acontecimientos":[...]}`. Requiere evidencias procedentes solamente de los IDs del lote, texto mínimo, categoría permitida, protección básica de datos privados y rechaza duplicados idénticos.
- `mensajero:simular-lotes-gemini --grupo=<ID> --limite=60`: **solo lectura**. Consulta mensajes normalizados recientes sin reserva previa ni análisis IA, de grupos ya autorizados y posteriores a ambos cortes efectivos; solo imprime IDs. No envía texto a Google.
- Pruebas unitarias sin BD y pruebas de argumentos inválidos sin Google.

**Advertencia:** la cercanía temporal no demuestra que los mensajes pertenezcan a una misma operación, y un conjunto cronológico puede contener operaciones distintas. El modelo futuro debe distinguirlas por sus evidencias, no fusionarlas ciegamente.


## Avance posterior: cliente, política y reserva (aún sin activación)

- `ClienteGeminiLote`: contrato HTTP nuevo de cero a seis acontecimientos; re-limpia contenido antes de formar la solicitud, no envía archivos ni nombres reales, valida referencias y errores de respuesta. Los tests de HTTP usan `Http::fake` y no generan costos.
- `PoliticaLotesGemini`: habilitación independiente **false** por defecto, fecha posterior de consentimiento y cupo entre 1 y 30 solicitudes por grupo/día. Además debe mantenerse la política general y el interruptor SQL del grupo.
- `ReservarLoteGemini`: **no llama Google ni Redis**. Reserva bajo transacción/bloqueo por grupo, valida uno a seis mensajes normalizados del mismo grupo, cortes global/grupo/lote, máximo cuatro horas de antigüedad, pausa 90 s y ventana 5 min. Excluye mensajes ya analizados o reservados por el piloto y cuenta sus publicaciones para un techo diario compartido.
- Nueva migración `2026_10_26_000300_crear_lotes_gemini.php` para reserva, asociación única mensaje-lote, resultados múltiples y evidencia. **No ejecutar esta migración todavía**; falta validar el esquema en SQL Server de prueba aislado y preparar reversión.
- El publicador individual comprueba la asociación a lote, solo si la tabla nueva existe, para evitar envíos duplicados de ese mismo mensaje.

### Ejecución de pruebas sin SQL Server

Desde una copia de trabajo con Composer instalado:

```powershell
php .\\vendor\\bin\\phpunit --configuration=phpunit-lotes.xml --testsuite=LotesAislados
```

`phpunit-lotes.xml` restringe los tests a los archivos de esta fase y fuerza SQLite en memoria, autorización IA desactivada y clave de prueba. Ninguna prueba intenta ejecutar las migraciones ni usar Google real. Antes de considerar aprobada la rama deben ejecutarse todas y registrar resultado.

**Aún falta la cadena de producción:** la reserva no se despacha a Redis; no existe trabajo de Gemini para lotes ni proyección de los resultados múltiples hacia la bitácora. Los límites diarios aún tienen un máximo compartido con el piloto, por lo que esta versión **no resuelve todavía** el volumen de cientos de mensajes. No habilitar `MENSAJERO_IA_LOTES_HABILITADOS` ni ejecutar migraciones en SQL activo.

## Pendientes antes de habilitar procesamiento real

1. Definir y autorizar un nuevo corte específico para tratamiento contextual entre remitentes, distinto de los permisos del piloto de un solo mensaje.
2. **Pendiente validación SQL Server:** probar tablas, reserva única y persistencia de resultados ya codificadas; preparar manejo manual de estados inciertos no reintentables.
3. Introducir un trabajo de cola Redis que relea y revalide permisos y cortes antes de transmitir. Nunca ejecutar Google dentro de una transacción SQL.
4. **Creado, pendiente integración:** conectar el nuevo contrato multiacontecimiento a un trabajador autorizado. El contrato individual existente NO puede reutilizarse.
5. Adaptar la proyección: un lote puede originar varios acontecimientos, cada uno con su propia identidad e IDs de evidencia, sin contradecir la restricción de un análisis por mensaje del piloto.
6. **Implementado en reservas:** cupo compartido de solicitudes por grupo y UTC. Pendientes topes por tokens, alertas y retención. No sustituir el límite de seguridad existente por una cifra ilimitada.
7. Pruebas con SQL Server **aislado**, Redis simulado, HTTP falso, cancelaciones de consentimiento, fallas de red, concurrencia, idempotencia y ausencia de fugas entre grupos.
8. Activación gradual únicamente en grupos autorizados, sin enviar mensajes históricos ni volver a interpretar IDs ya publicados.

## Avance: persistencia y presupuesto compartido

- `PresupuestoPublicacionesGemini`: el circuito individual y los lotes consultan las reservas combinadas del día UTC bajo el bloqueo transaccional que ya usan ambos publicadores. Las reservas inciertas consumen cupo de seguridad.
- `PersistirResultadosLoteGemini`: recibe exclusivamente respuestas ya obtenidas, exige estado `publicado`, valida pertenencia de todas las fuentes al mismo lote y grupo, persiste hasta seis acontecimientos con sus evidencias en una transacción y finaliza el lote. Repetir su ejecución devuelve `ya_finalizado` sin duplicar filas.
- Se agregó columna `modelo` para auditoría de consumo. La migración **no se ha ejecutado**.
- Las pruebas de persistencia y presupuesto usan esquemas mínimos de SQLite en memoria, sin migraciones de MILAC, SQL Server real, Redis o Google. **Se deben ejecutar en la copia de trabajo; todavía no están validadas en Windows.** Si PDO SQLite no está instalado, PHPUnit mostrará pruebas omitidas que deben resolverse antes de dar por aprobada esta fase.
- Todavía no existe trabajo Redis para llamar al cliente nuevo ni proyección desde resultados de lote a `mensajero_acontecimientos_operativos`; por ello no reemplaza el piloto.

## Seguridad de este cambio

- No modifica `.env`, programador, cola de IA ni recolector; añade una migración nueva pero NO la ejecuta.
- No crea solicitudes Gemini ni reservas automáticamente; no hay despacho ni ejecución de los servicios nuevos.
- No elimina mensajes ni transfiere adjuntos.
- No amplía consentimiento, cupos diarios ni ventanas de recuperación.
- Los resultados del simulador **no son** acontecimientos ni publicaciones reales.

## Pruebas aisladas

Con Composer instalado, usar el XML versionado:

```powershell
php .\\vendor\\bin\\phpunit --configuration=phpunit-lotes.xml --testsuite=LotesAislados
```

El XML fuerza SQLite en memoria, servicio Gemini deshabilitado y cliente HTTP simulado. Esta suite no ejecuta migraciones corporativas y no requiere una base SQL Server. El simulador de datos reales es opcional y solo de lectura.
