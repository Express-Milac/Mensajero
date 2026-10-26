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

`phpunit-lotes.xml` restringe los tests a los seis archivos de esta fase y fuerza SQLite en memoria, autorización IA desactivada y clave de prueba. Ninguna prueba intenta ejecutar las migraciones ni usar Google real. Antes de considerar aprobada la rama deben ejecutarse todas y registrar resultado.

**Aún falta la cadena de producción:** la reserva no se despacha a Redis; no existe trabajo de Gemini para lotes ni proyección de los resultados múltiples hacia la bitácora. Los límites diarios aún tienen un máximo compartido con el piloto, por lo que esta versión **no resuelve todavía** el volumen de cientos de mensajes. No habilitar `MENSAJERO_IA_LOTES_HABILITADOS` ni ejecutar migraciones en SQL activo.

## Pendientes antes de habilitar procesamiento real

1. Definir y autorizar un nuevo corte específico para tratamiento contextual entre remitentes, distinto de los permisos del piloto de un solo mensaje.
2. Implementar persistencia SQL Server para identidad y reservas únicas por lote, estado de publicación, resultados múltiples, evidencias, y estados inciertos no reintentables.
3. Introducir un trabajo de cola Redis que relea y revalide permisos y cortes antes de transmitir. Nunca ejecutar Google dentro de una transacción SQL.
4. Crear un contrato nuevo de solicitud y respuesta de Gemini para **varios** acontecimientos del mismo lote. El contrato actual identifica uno asociado al último mensaje y NO puede reutilizarse.
5. Adaptar la proyección: un lote puede originar varios acontecimientos, cada uno con su propia identidad e IDs de evidencia, sin contradecir la restricción de un análisis por mensaje del piloto.
6. Presupuesto explícito por grupo de solicitudes y tokens, alertas de acumulación y retención. No sustituir el límite de seguridad existente por una cifra ilimitada.
7. Pruebas con SQL Server **aislado**, Redis simulado, HTTP falso, cancelaciones de consentimiento, fallas de red, concurrencia, idempotencia y ausencia de fugas entre grupos.
8. Activación gradual únicamente en grupos autorizados, sin enviar mensajes históricos ni volver a interpretar IDs ya publicados.

## Seguridad de este cambio

- No modifica `.env`, migraciones, programador, cola de IA ni recolector.
- No crea solicitudes Gemini ni reservas.
- No elimina mensajes ni transfiere adjuntos.
- No amplía consentimiento, cupos diarios ni ventanas de recuperación.
- Los resultados del simulador **no son** acontecimientos ni publicaciones reales.

## Pruebas aisladas

Con Composer instalado y `DB_CONNECTION=sqlite` forzado en configuración temporal de PHPUnit, ejecutar:

```powershell
php .\vendor\bin\phpunit --configuration=phpunit-aislado.local.xml --filter='AgruparMensajesGeminiTest|ValidarAcontecimientosLoteGeminiTest|SimularLotesGeminiTest'
```

Estas pruebas no necesitan ejecutar migraciones ni conectar SQL Server. Antes de usarlas, verificar que el archivo XML temporal exista y que no contenga credenciales ni conexiones reales. El simulador SQL de datos reales es **opcional y exclusivamente de lectura**, pero requiere entorno autorizado; no es necesario para probar las clases puras.
