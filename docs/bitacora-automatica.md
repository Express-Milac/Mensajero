# Mensajero MILAC: bitácora autónoma de acontecimientos

## Objetivo del sistema

Mensajero captura únicamente grupos autorizados, conserva mensajes/archivos originales y utiliza Gemini para interpretar textos y descripciones de archivos, identificar acontecimientos operativos y presentarlos en una bitácora AUTOMÁTICA. **No requiere Validar ni Descartar cada resultado.** Ningún resumen generado es una orden operativa ni prueba visual de imágenes.

## Funcionamiento

1. Mensaje WhatsApp autorizado -> mensaje SQL Server normalizado.
2. Con autorización y política Gemini de ese grupo, el trabajo IA interpreta texto y hasta seis mensajes de contexto reciente (si se autoriza).
3. Si Gemini devuelve es_evento=true y supera las validaciones TÉCNICAS de formato, referencias a mensajes y controles de acceso, se almacena el análisis estado identificado_ia.
4. Se crea automáticamente un acontecimiento estado generado_ia y se vinculan los mensajes citados como sustento_ia; otros adjuntos pueden asociarse por proximidad del mismo remitente como posible_adjunta, sin afirmar verificación visual.
5. /operacion muestra todo sin botón de aprobación; los mensajes y archivos originales permanecen intactos.

Los análisis y acontecimientos históricos estado pendiente_revision siguen siendo legibles y aparecen como Generado por IA en la vista. Se respetan los antiguos registros descartados y no se presentan como acontecimientos activos. No se reescribe SQL Server, ni se ejecutan migraciones ni se borran columnas o filas.

El antiguo endpoint de validación humana se retiró de routes/web.php. El código de auditoría histórico se conserva sin ruta pública para evitar cambios destructivos.

## Diferencia entre automatizar la bitácora y autorizar gastos de Gemini

Eliminar la aprobación humana de RESULTADOS no significa autorizar automáticamente envíos pagados a Google. La publicación IA del mensaje depende de los grupos, fecha de consentimiento, límite diario y de las variables de entorno de Gemini.

Actualmente, durante el piloto de mensajes reales, el archivo local .env fue configurado con MENSAJERO_IA_EJECUCION_AUTOMATICA=false y MENSAJERO_IA_CONCILIACION_AUTOMATICA=false; por eso el procesamiento de un lote histórico se publicó manualmente. La bitácora, por su parte, ya se crea sin aprobación una vez que el trabajo se ejecuta. Antes de habilitar IA permanente para nuevos mensajes se debe revisar la política de costos, privacidad y límites por grupo. No activar otras conversaciones sin permiso.

## Instalación sin tocar la base viva

En Windows PowerShell, desde la raíz Mensajero:

    git fetch origin fase-2-gemini-eventos-operativos-20261021
    git restore --source=FETCH_HEAD --worktree -- scripts/actualizar-cara-operativa.ps1
    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\actualizar-cara-operativa.ps1
    php artisan test --filter=OperacionAccesoTest
    php artisan test --filter=GeminiContratoTest
    php artisan view:clear
    php artisan route:clear

No ejecutar tests/Integracion/AcontecimientosOperativosSqlServerTest.php contra bases de datos operativas. La integración usa migraciones SQL y se permite únicamente en una base de prueba completamente aislada.

Para que un trabajador PHP continuo nuevo use el nuevo estado identificado_ia, verificar primero el estado de Redis y utilizar el reinicio selectivo del trabajador Gemini:

    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\reiniciar-solo-gemini.ps1
    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\reiniciar-solo-gemini.ps1 -Aplicar
    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\diagnosticar-trabajador-gemini.ps1

El reinicio selectivo valida el supervisor y que Redis no tenga tareas. No reiniciar ni duplicar WhatsApp.

Recargar /operacion. La interfaz debe mostrar Generado por IA en todas las tarjetas, incluso el acontecimiento histórico #5. Los tres registros anteriores del piloto no requieren actualización SQL.

## Limitaciones

El modelo valida ESTRUCTURALMENTE el resultado y referencias, no garantiza exactitud factual. Puede equivocarse en nombres de patios, números, eventos y relaciones. Debe etiquetarse como interpretación de IA para consultar contra evidencia original cuando sea necesario, sin imponer trabajo de revisión repetitivo al usuario.

La interpretación visual completa de archivos sigue pendiente; actualmente las fotografías se procesan mediante su pie de foto. La asociación tentativa de archivos por remitente/tiempo no equivale a comprensión de su contenido.
