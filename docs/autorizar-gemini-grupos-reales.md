# Interpretación de mensajes reales con Google Gemini

Actualización: 24/10/2026. Esta integración exige aprobación de tratamiento de mensajes empresariales por parte de MILAC. No compartir claves ni información de clientes en chats de soporte.

## Objetivo

Mensajero conserva mensajes y archivos originales en /mensajes. Gemini interpreta **textos y pies de multimedia** de grupos expresamente permitidos, identifica acontecimientos y los presenta como propuestas en /operacion con vínculos a los originales. Las fotografías no se envían a Google ni son inspeccionadas por el modelo. Un texto, un hilo de respuestas o un reporte con fotografías pueden generar una propuesta, pero los mensajes sin contenido operativo no tienen por qué producir ninguna tarjeta.

El modo contextual añade alias anónimos por emisor y responde_a para citas reales dentro del mismo lote. Selecciona un máximo de seis textos/leyendas en cinco minutos, exclusivamente del mismo grupo, nunca anteriores al consentimiento; el modelo debe evitar relacionar temas diferentes por cercanía. La correlación semántica de reportes de días diferentes aún está pendiente.

## Activación real, voluntaria y no retroactiva

1. Actualizar código y aplicar únicamente las dos migraciones de cara operativa en SQL Server autorizado, tras comprobar destino.

   Para ver los IDs sin abrir ni exportar mensajes:

    php artisan mensajero:comprobar-grupos-ia --listar
2. Comprobar qué grupos tienen captura y tablero habilitados. Requiere sus IDs internos, no el nombre del grupo:

    php artisan mensajero:comprobar-grupos-ia --grupos=10020

   Sustituir por los IDs reales elegidos y aprobados. Esto solo lee metadatos y no consulta Gemini.

3. Para un grupo expresamente autorizado (ejemplo del piloto, sustituir identificador):

    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\autorizar-grupos-reales-gemini.ps1 -Grupos 10020

   Para varios grupos expresamente autorizados:

    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\autorizar-grupos-reales-gemini.ps1 -Grupos 10020,10021

   Para comprender respuestas entre participantes:

    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\autorizar-grupos-reales-gemini.ps1 -Grupos 10020 -ContextoConversacional

   La opción contextual envía hasta seis mensajes potencialmente de varios remitentes a Google, por lo cual requiere un permiso consciente para ese alcance. La herramienta exige confirmación exacta **AUTORIZO DATOS REALES** y verifica los grupos en SQL. Si no se confirma, no cambia configuración.

4. El comando hace copia privada de .env bajo %LOCALAPPDATA% y cambia **solo** grupos autorizados, fecha global de inicio y modo de contexto. El corte se actualiza al instante de autorizar, por lo que **no se vuelven a transmitir mensajes históricos**, incluso si los grupos estaban capturando antes. Si se incluyen varios grupos, la lista reemplaza la anterior, y todos deben haber sido autorizados.
5. Reiniciar SOLO los procesos PHP trabajador de normalización y Gemini, o gestionar la salida de queue:restart según corresponda; NO duplicar ni reiniciar innecesariamente el recolector WhatsApp. El lanzador Windows acepta ahora de 1 a 6 grupos expresamente configurados.
6. Comprobar resultados con:

    php artisan mensajero:estado-gemini --grupo=10020 --limite=10

   Ese diagnóstico muestra actividad sin enviar nuevos mensajes. Revisar /operacion, y /mensajes/ia para "sin evento". La ausencia de tarjetas no demuestra por sí misma una falla.

## Advertencias

- La captura de WhatsApp NO implica autorización para transferir esos datos a Google. La lista de IA es independiente.
- La opción "ContextoConversacional" permite recuperar hasta cinco minutos de textos/leyendas de un solo grupo, exclusivamente posteriores al corte. No implica análisis visual ni historial completo. Los alias de autor solo son temporales.
- El piloto **no tiene límites monetarios globales, cuotas diarias internas ni deduplicación garantizada de llamadas a Google en fallas/reintentos**. No activar varios grupos de alto volumen sin añadir esos controles y alertas.
- Las respuestas de Gemini son interpretaciones por revisar; no constituyen hechos físicos verificados. Las fotografías ligadas por tiempo se etiquetan "Posible adjunto" y podrían pertenecer a otro evento.
- No ejecutar tests destructivos sobre la base activa. Para pruebas automatizadas usar BD _test y peticiones Google simuladas. Para evaluación real controlada, empezar con un grupo operativo autorizado y supervisar costos y revisión humana.
- El permiso administrativo de revisión operativa es independiente del permiso Gemini. No concederlo a usuarios sin autenticación en redes públicas.

## Archivos relevantes

- scripts/autorizar-grupos-reales-gemini.ps1
- app/Console/Commands/ComprobarGruposIa.php
- app/Services/IA/ConstruirDialogoGemini.php
- app/Services/IA/ProcesarAnalisisGemini.php
- app/Services/IA/PoliticaEnvioGemini.php
- app/Http/Controllers/Operacion/ControladorOperacion.php
