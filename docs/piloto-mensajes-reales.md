# Piloto Gemini con mensajes reales ya capturados

Fecha: 24 de octubre de 2026. El código está en GitHub. No se tiene acceso a SQL Server local ni se han enviado datos del grupo 10197 a Google.

## Motivación

El grupo de prueba 10020 se sincroniza, pero no se registran mensajes nuevos. Logística de Patios Milac, ID interno 10197, sí tiene mensajes reales normalizados del 24 de octubre. El piloto usará esos mensajes existentes y no pedirá enviar nuevos ejemplos ficticios.

## Autorización

Capturar un grupo en Mensajero no equivale a autorizar su contenido para Gemini. La política anterior permitía solo 10020. El envío de textos y contexto de 10197 a Google requiere autorización específica y aceptación del costo.

La nueva variable MENSAJERO_IA_FECHAS_INICIO_GRUPOS almacena un JSON por grupo; ejemplo: {"10197":"2026-10-24T09:15:40-06:00"}. El inicio efectivo es el máximo entre fecha global y fecha individual; no permite retroceder consentimiento global.

## Procedimiento

1. Sincronizar el código con scripts/actualizar-cara-operativa.ps1. No ejecuta SQL ni cambia .env.
2. Ejecutar pruebas sin llamadas reales a Google:

    php artisan test --filter=GeminiContratoTest
    php artisan test --filter=ConciliacionGeminiTest
    php artisan mensajero:comprobar-grupos-ia --grupos=10197

3. Si MILAC autoriza expresamente el envío de textos y contexto de Patios, ejecutar:

    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\autorizar-lote-real-gemini.ps1 -Grupo 10197 -Desde "2026-10-24T09:15:40-06:00"

   El script requiere escribir AUTORIZO HISTORIAL REAL y guarda un respaldo privado de .env; NO envía contenido a Google, no modifica SQL Server ni reinicia WhatsApp. Desactiva intencionadamente el procesamiento IA automático y la conciliación automática mientras se evalúa el lote.

4. Solicitar la recarga de configuración PHP sin reiniciar el recolector:

    php artisan queue:restart

5. Consultar la muestra de mensajes REALES existentes sin enviar ni escribir:

    php artisan mensajero:analizar-gemini --grupo=10197 --limite=3 --lote-real

   Muestra los IDs y las fechas de hasta tres mensajes reales normalizados, sin resultado ni reserva de publicación. No imprime contenido privado.

6. Solamente con autorización previa, enviar un lote de hasta tres mensajes reales a Gemini:

    php artisan mensajero:analizar-gemini --grupo=10197 --limite=3 --lote-real --aplicar

   Requiere confirmar AUTORIZO LOTE REAL antes de publicar trabajos en Redis. El trabajador enviará texto y contexto permitido a Google y podrá generar costos.

7. Diagnosticar resultados:

    php artisan mensajero:estado-gemini --grupo=10197 --limite=10
    php artisan queue:failed
    php artisan mensajero:proyectar-operacion --grupo=10197 --limite=10

   Luego abrir /operacion y contrastar las interpretaciones con los originales inalterados.

## Límites

- Se transmiten textos y leyendas de multimedia, nunca bytes de fotos, audio, vídeos o documentos.
- La IA ve hasta seis textos de un mismo grupo en una ventana de cinco minutos con alias de participantes, cuando está autorizado el contexto.
- La cara operativa por mensaje puede generar más de un evento para una conversación; la unión semántica completa sigue pendiente.
- Los envíos nuevos y manuales comparten el cupo SQL de diez publicaciones por grupo y día UTC por defecto.
- Una publicación con resultado incierto exige revisión y no se reintenta de forma automática.
- No ejecutar pruebas de integración SQL en la base viva mensajero_test. No publicar contenido de grupo, teléfonos ni claves en GitHub.
