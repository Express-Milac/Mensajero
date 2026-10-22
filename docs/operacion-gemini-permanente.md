# Mensajero MILAC — Operación continua de Google Gemini

**Actualizado: 22 de octubre de 2026.** Alcance habilitable: únicamente el grupo piloto interno **10020**, «Mensajero milac Prueba», hasta autorizar expresamente otros grupos.

## Estado confirmado y límites

El 22/10/2026 el mensaje ficticio de WhatsApp **#74** se capturó y normalizó, un trabajo Redis finalizó **DONE**, Gemini 3.5 Flash-Lite generó una **incidencia** y SQL Server registró **pendiente_revision**. La página /mensajes/ia mostró el resumen y su vínculo al mensaje #74. Consumo observado: **223 tokens de entrada y 84 de salida**. La suite GeminiContratoTest aprobó **9 pruebas, 19 aserciones** en Windows. Esto **no certifica operación 24/7 ni producción**.

Los scripts y documentos en GitHub no activan por sí solos Gemini en el equipo del usuario. Se requiere escribir configuración privada en .env, disponer de servicios activos y ejecutar el trabajador continuo.

## Circuito automático actual

1. Número recolector secundario → filtro de grupos → pasarela HMAC → SQL Server (evento crudo y buzón).
2. El programador Laravel despacha el buzón a Redis cada minuto, si MENSAJERO_NORMALIZACION_HABILITADA=true.
3. El trabajador mensajero_normalizacion proyecta el mensaje en SQL Server.
4. El normalizador publica AnalizarMensajeGeminiTrabajo solo si MENSAJERO_IA_EJECUCION_AUTOMATICA=true y la política autoriza fecha, grupo y envío. Solo **mensajes nuevos de texto normalizados**, grupo activo y monitoreado, entre los ID expresamente permitidos y posterior a MENSAJERO_IA_FECHA_INICIO.
5. El trabajador separado mensajero_ia llama a Google; por defecto MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO=true impide transmitir textos anteriores.
6. Validador PHP → tabla mensajero_analisis_operativos_ia → pendiente_revision o sin_evento. Un evento es **propuesta**, nunca instrucción confirmada.

Errores de Google no revierten captura WhatsApp. Redis admite reintentos; SQL Server previene duplicación del registro por mensaje, **pero no garantiza exactamente una llamada facturable** si un trabajo se repite.

## Activación local persistente en Windows

**Antes de activar:** confirmar autorización de MILAC para ese grupo, envío de texto a Google, facturación y condiciones de datos. Recomendamos proyecto Google dedicado, ya que su cuota por proyecto puede compartirse con otros endpoints de MILAC.

Desde la raíz de Mensajero, después de descargar la rama y revisar el script:

~~~powershell
php artisan test --filter=GeminiContratoTest
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\activar-gemini.ps1 -GrupoId 10020
~~~

El script exige confirmación y clave oculta; escribe **solo en .env local**, ignorado por Git, y crea respaldo local .env.backup. Aislar permisos de lectura de ambos archivos, mantenerlos fuera de repositorios y copias compartidas. **No** sube secretos a GitHub ni llama a Google durante la activación. Por defecto fija la fecha de corte **al momento de activación**, para NO transmitir mensajes históricos del piloto. Si se reactiva tras una parada y se autoriza analizar trabajos anteriores, usar -ConservarCorte deliberadamente tras revisar la cola y las fechas.

Configuración esperada (valores de ejemplo sin secretos):

~~~dotenv
IA_HABILITADA=true
MENSAJERO_IA_ENVIO_AUTORIZADO=true
MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO=true
MENSAJERO_IA_EJECUCION_AUTOMATICA=true
MENSAJERO_NORMALIZACION_HABILITADA=true
MENSAJERO_IA_GRUPOS_AUTORIZADOS=10020
MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO=true
MENSAJERO_IA_FECHA_INICIO=<fecha ISO-8601 autorizada, con zona>
MENSAJERO_COLA_IA=mensajero_ia
MODELO_GEMINI=gemini-3.5-flash-lite
CLAVE_API_GEMINI=<solo en .env privado>
~~~

El programador de normalización es **global**: puede tratar mensajes históricos pendientes de otros grupos autorizados aunque la política IA no permita enviarlos a Google. No retroceder el corte IA ni activar otros grupos sin aprobación.

## Arranque único en Windows (22/10/2026)

Para el entorno **local de pruebas**, ahora existe un lanzador que evita ejecutar cinco comandos en ventanas creadas manualmente:

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\mensajero-local.ps1
~~~

Consulta y parada:

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\mensajero-local.ps1 -Accion estado
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\mensajero-local.ps1 -Accion detener
~~~

**Usar una opción u otra, no ambas a la vez:** cerrar primero los servicios manuales anteriores con Ctrl+C, porque el lanzador se niega a duplicar workers, recolector y servidor HTTP. Solo se gestionan procesos que haya iniciado el propio lanzador. **Guía completa:** [Arranque local unificado](arranque-local-unificado.md).

## Procesos que deben permanecer funcionando

Ejecutarlos en terminales separadas o con supervisor; instalar una sola instancia de cada proceso:

~~~powershell
# Redis en WSL; debe devolver PONG:
wsl -d Debian -- redis-cli ping

# Laravel HTTP solo para POC, no producción:
php artisan serve --host=127.0.0.1 --port=8000

# Recolector Node desde carpeta recolector-whatsapp:
npm run desarrollo

# Desde la raíz Laravel: programador, un solo ejemplar:
php artisan schedule:work

# Desde la raíz Laravel: Redis normalización:
php artisan queue:work redis --queue=mensajero_normalizacion --sleep=2 --tries=5 --timeout=60

# Desde la raíz Laravel: Gemini, cola separada, SSL seguro:
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\ejecutar-gemini-windows.ps1
~~~

El trabajador IA usa el archivo CA local en %LOCALAPPDATA%\Mensajero\certificados\cacert.pem y establece curl.cainfo / openssl.cafile. **Nunca desactivar verificación SSL.** Mantener un solo trabajador IA mientras no existan controles específicos de consumo.

En Windows, **tener terminales abiertas no garantiza 24/7**. Configurar una tarea de inicio de sesión con el Programador de tareas para el trabajador IA (sin ejecutar dos instancias) y supervisar también Redis/WSL, Laravel, recolector y normalizador. Una suspensión del equipo o sesión no atendida interrumpe la captura.

En el **servidor MILAC** usar systemd o Supervisor: un servicio por recolector Node, programador Laravel, normalizador Redis, trabajador IA; PHP-FPM y proxy HTTPS para servir la aplicación. Reinicio automático, usuario de servicio con privilegios mínimos, archivos secretos protegidos, registro de errores, monitoreo y alertas. No desplegar php artisan serve como servidor HTTP productivo.

## Comprobaciones sin enviar a Google

~~~powershell
php artisan config:clear
php artisan schedule:list
php artisan test --filter=GeminiContratoTest
php artisan tinker --execute="echo (config('mensajero.ia.habilitada') && config('mensajero.ia.ejecucion_automatica')) ? 'IA_CONFIGURADA' : 'IA_APAGADA';"
php artisan queue:failed
~~~

La variable de configuración por sí sola **no demuestra** que el trabajador esté encendido. Supervisar procesos, estados de Redis, mensajes nuevos y filas SQL Server. No imprimir config('mensajero.ia') completa: contiene la clave API.


## Diagnóstico de mensajes sin análisis (22/10/2026)

Si los WhatsApp aparecen en el tablero pero /mensajes/ia no agrega tarjetas, **no asumir que Gemini está caído**: la interfaz lista solo pendiente_revision, mientras los mensajes clasificados sin_evento quedan ocultos. El diagnóstico de solo lectura distingue esos estados:

~~~powershell
php artisan mensajero:estado-gemini --grupo=10020 --limite=10
php artisan schedule:list
php artisan queue:failed
~~~

El comando de estado **no llama Google, no publica trabajos y no imprime textos ni claves**. Informa habilitación/autorización, fecha de corte, total de analizados con y sin evento, textos normalizados desde el corte aún sin resultado IA y tres colas Redis (pendientes, reservados, retrasados). También lista los ID recientes y sus estados. La vista /mensajes/ia ahora muestra un resumen similar para grupos autorizados, sin pretender certificar la salud de los trabajadores.

**Causa muy común:** se activó IA en .env después de que ya se había iniciado el trabajador de normalización. Los trabajadores queue:work de Laravel conservan la configuración cargada al inicio: aunque Gemini y su trabajador estén encendidos, el normalizador antiguo puede seguir leyendo MENSAJERO_IA_EJECUCION_AUTOMATICA=false y no publicar ningún trabajo. En esa situación, detener **solo** el trabajador de normalización con Ctrl+C e iniciarlo nuevamente en una **nueva** terminal:

~~~powershell
php artisan queue:work redis --queue=mensajero_normalizacion --sleep=2 --tries=5 --timeout=60
~~~

No levantar un segundo trabajador si el primero sigue vivo. Revisar también si php artisan schedule:list incluye mensajero:despachar-buzon; si no aparece, reiniciar el programador Laravel con la configuración actual. El worker Gemini de la cola mensajero_ia se mantiene separado y puede seguir encendido.

**Los mensajes que ya se normalizaron con el normalizador antiguo no se publicarán retrospectivamente** cuando ese trabajador se reinicie. **No retrasar la fecha de corte ni reenviar automáticamente históricos**: recuperar un ID único y autorizado solo después de revisar su elegibilidad, contenido y límites de consumo. Para probar el circuito automático, usar un mensaje **nuevo y ficticio**, posterior al reinicio, y observar su ID y estado con el diagnóstico sin ejecutar manualmente el comando aplicar.

El resumen de la vista no es un latido de trabajador; para afirmar 24/7 falta implementar heartbeat y supervisión real de servicios.


## Suspender sin perder mensajes

1. Detener el trabajador IA y desactivar su inicio automático.
2. En el .env local poner IA_HABILITADA=false, MENSAJERO_IA_ENVIO_AUTORIZADO=false, MENSAJERO_IA_EJECUCION_AUTOMATICA=false.
3. Ejecutar php artisan config:clear y php artisan queue:restart; reiniciar también el trabajador de normalización para recargar la política.
4. Auditar colas IA (disponibles, reservados y retrasados) sin borrarlas; SQL Server y WhatsApp conservan la evidencia.

## Pendientes reales antes de producción

- Implementar topes efectivos de costo/tokens, umbrales y alertas; 1 trabajador NO es una cuota monetaria.
- Probar 429, 401, 5xx, recuperación por reinicios, duplicados, caída Redis justo tras normalización y control de carga.
- Implementar aprobación, rechazo, corrección y auditoría humana; hoy solamente aparece **Por verificar**.
- La limpieza actual de texto elimina teléfonos, correos y ciertos identificadores, **no garantiza** retirar nombres, direcciones o datos privados escritos en el cuerpo. Revisar confidencialidad/consentimiento antes de autorizar grupos reales.
- Completar autenticación corporativa, supervisión del recolector y pruebas multimedia en servidor.
