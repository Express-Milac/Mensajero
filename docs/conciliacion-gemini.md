# Conciliación y recuperación controlada de Gemini

Actualización: 24/10/2026. Código publicado en la rama de desarrollo; **no probado todavía contra el SQL Server activo de MILAC**.

## Problema real observado

El grupo de prueba 10020 tenía mensajes normalizados #79, #80 y #81 sin resultado de Gemini, a pesar de servicios en ejecución, cola Redis vacía y sin trabajos fallidos. La ejecución manual del #79 creó la tarjeta «Accidente reportado». El punto débil es la publicación posterior al commit: si se pierde un despacho, el mensaje queda guardado pero no se publica una segunda vez.

## Qué corrige esta versión

- Nueva tabla mensajero_control_publicaciones_gemini: reserva única por ID de mensaje, grupo, origen (normalizacion, manual o conciliacion), estado, fecha y tipo de fallo sin texto sensible.
- La publicación desde la normalización, el comando manual y la conciliación usan la **misma reserva SQL**, por lo que no vuelven a publicar un mensaje registrado. Los resultados IA existentes también impiden una nueva publicación.
- Un trabajo Gemini tiene **un único intento automático**: si falla tras una llamada cuya facturación pudo realizarse, se marca requiere_revision. Se evita intentar cobrar nuevamente de forma ciega. Una caída entre reservar en SQL y despachar Redis deja reservado, visible para revisión, no para reintento automático.
- Se crea el comando mensajero:conciliar-gemini, **simulación por defecto**. Si se activa explícitamente, el programador lo ejecuta cada cinco minutos y rescata solo mensajes nuevos, suficientemente antiguos para esperar la publicación normal, y aún recientes (ventana 4 h de forma predeterminada).
- La conciliación se limita a grupos ya autorizados para Gemini y habilitados en captura y tablero; **no se amplía la lista** con grupos que solo capturan WhatsApp.
- Cupo compartido para toda publicación controlada: 10 mensajes por grupo y día UTC por defecto, configurable entre 1 y 30. Al alcanzar el cupo, los demás mensajes no se procesan automáticamente; el registro original permanece intacto.

## Instalación

1. Aplicar respaldo de SQL Server y comprobar el destino activo antes de migrar; una base llamada mensajero_test puede recibir mensajes reales y **no debe tratarse como desechable**.
2. Actualizar código con scripts/actualizar-cara-operativa.ps1, que respalda los archivos locales y no modifica .env ni SQL.
3. Revisar la migración nueva 2026_10_24_000100_crear_control_publicaciones_gemini.php. Aplicar solo esa migración en el SQL Server autorizado, con respaldo y plan de reversión.
4. Ejecutar pruebas de contrato sin conexión real a Google (las pruebas de integración que escriben SQL deben usar otra base aislada, NO la capturadora en vivo):

    php artisan test --filter=ConciliacionGeminiTest
    php artisan test --filter=GeminiContratoTest
    php artisan route:list --path=operacion

5. Reiniciar de manera ordenada trabajadores PHP (no arrancar un segundo recolector de WhatsApp). Los trabajadores antiguos no cargan cambios de código mientras siguen vivos.

## Diagnóstico inicial, sin nuevas publicaciones

    php artisan mensajero:estado-gemini --grupo=10020
    php artisan mensajero:conciliar-gemini --grupo=10020 --limite=2
    php artisan schedule:list

Si la conciliación está deshabilitada o no tiene corte, no hay candidatos nuevos para el rescate. Eso es esperado. Los mensajes #80 y #81 son **históricos** y no se recuperarán solos mediante esta herramienta; requerirían una publicación manual, por ID exacto, dentro del consentimiento ya otorgado.

## Activar recuperación continua — decisión explícita

Solo cuando estén comprobadas migración, pruebas y permisos, ejecutar:

    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\activar-conciliacion-gemini.ps1 -MaximoDiarioPorGrupo 10

Se solicitará la confirmación literal **AUTORIZO CONCILIACION**. El script valida que ya exista la migración y que la política de envío a Google esté autorizada; crea copia privada de .env bajo %LOCALAPPDATA%, establece un **corte nuevo en ese momento** y habilita la tarea programada. No envía ningún mensaje a Google durante la ejecución del script. Reiniciar los procesos PHP para leer la nueva configuración.

Para probar primero en seco:

    php artisan mensajero:conciliar-gemini --todos --limite=2

Para evaluar después un despacho real puntual (sí publica hacia Redis y luego puede llamar a Google), una vez activada la conciliación y bajo autorización:

    php artisan mensajero:conciliar-gemini --grupo=10020 --limite=1 --aplicar

El programador, cuando está activo, ejecuta automáticamente la misma operación cada cinco minutos sobre todos los grupos previamente autorizados. No activa otros grupos.

## Diagnóstico de incertidumbres

Consultar mensajero:estado-gemini para estados **reservado**, **publicado**, **finalizado**, **omitido** y **requiere_revision**. Los dos primeros, si se quedan sin resultado, necesitan investigación del trabajador o de Redis; NUNCA publicar el mismo ID otra vez a ciegas.

Las solicitudes a Google no disponen de exactamente-una-vez garantizado a nivel externo. Una caída entre respuesta de Google y confirmación SQL podría haber ocasionado cobro sin resultado persistido. La reserva y el único intento evitan reintentos automáticos, pero no aseguran que cada mensaje resulte analizado o que no haya operaciones pagadas sin registro.

La conciliación **no sustituye la memoria operativa**, la unión semántica de hilos entre participantes ni el análisis de imágenes. Estas fases necesitan tratamiento independiente y pruebas con ejemplos autorizados.

## Precauciones

- **No lanzar migrate:fresh, migrate:refresh, queue:retry all ni queue:flush en la base activa.**
- Las fechas de consentimiento son límites de tratamiento de datos, no mecanismos para habilitar retrospectivamente grupos.
- La normalización, la captura y Redis siguen separados de Gemini. Fallas de IA no deben borrar mensajes.
- Mantener supervisión de gastos y revisar el conteo de mensajes pendientes antes de ampliar a Nogales, Patios o Guaymas.
