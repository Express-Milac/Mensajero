# Control de Gemini desde Mensajero MILAC

## Cambio de octubre de 2026: autorización completa en la interfaz

La pantalla ahora incorpora los grupos **Logística Guaymas (#10198)** y **LOGÍSTICA NOGALES (#10196)** con botón Activar, además de **Logística de Patios Milac (#10197)** y **Mensajero milac Prueba (#10020)**. Los cuatro permanecen apagados hasta que el operador autorice explícitamente su interpretación.

El consentimiento se registra con fecha actual en UTC y no produce publicaciones ni recupera el historial al hacer clic. Los trabajos nuevos solo se despachan con la normalización de mensajes posteriores a ese corte, si tanto el interruptor general como el individual están encendidos y el servicio de Google está correctamente configurado. Las pantallas de acontecimientos muestran resultados históricos de grupos visibles aunque Gemini sea pausado.

Esta actualización NO necesita una migración adicional porque reutiliza las tablas que ya creó 2026_10_24_000200. Debe actualizarse el código y reiniciar **ambos trabajadores** PHP (normalizador y Gemini); el recolector de WhatsApp sigue funcionando. Antes de activar, comprobar que ambos trabajadores estén actualizados y sin tareas antiguas. Si se están procesando trabajos, no detener un proceso Gemini durante una solicitud facturable.


## Finalidad

El menú **Control de Gemini** permite autorizar, encender y apagar la interpretación de mensajes de cualquier grupo monitoreado sin editar .env, reiniciar WhatsApp ni operar comandos diariamente. Contiene un control general y un control por grupo.

- Captura de WhatsApp: permanece independiente.
- Interpretación: depende del control general, el control individual, el consentimiento previo de Google y las fechas de corte.
- Al apagar: no se inician nuevos análisis. Las solicitudes ya iniciadas pueden finalizar.
- Al encender: se establece un nuevo corte UTC. Los mensajes anteriores a la activación no se envían automáticamente.
- Ningún evento generado requiere validación humana.
- Los resultados y mensajes históricos no se borran.

## Restricciones y privacidad

Todos los grupos de WhatsApp que tengan captura habilitada, estén activos y sean visibles se muestran con su propio control. La autorización del envío a Google Gemini se concede al marcar la confirmación del grupo y pulsar Activar. Esa aprobación individual, con fecha UTC de inicio, se almacena en mensajero_control_interpretacion_ia y se audita en mensajero_auditoria_interpretacion_ia. El antiguo MENSAJERO_IA_GRUPOS_AUTORIZADOS no limita los controles del panel y no hay que editar .env para incorporar un grupo. El interruptor general por sí solo nunca autoriza un grupo: también se necesita el control individual.

Cada clic de activación requiere confirmación explícita en la página. Los cambios se registran en SQL Server mediante la tabla de auditoría y solo pueden ejecutarlos administradores autenticados. Para el piloto local sin autenticación, existe el permiso independiente MENSAJERO_GESTION_LOCAL_IA_HABILITADA, restringido a localhost. Nunca usar esta excepción con acceso de red o en producción.

La aplicación sigue respetando IA_HABILITADA, MENSAJERO_IA_ENVIO_AUTORIZADO, MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO, la clave y la fecha de autorización. El control web NO habilita Google si alguna barrera anterior falta.

El límite diario configurado para los envíos por grupo se conserva mediante las reservas únicas ya existentes. Trabajos de respuesta incierta no se reenvían automáticamente.

## Instalación única y segura

Desde la carpeta raíz del proyecto, primero actualizar el código:

    git fetch origin fase-2-gemini-eventos-operativos-20261021
    git restore --source=FETCH_HEAD --worktree -- scripts/actualizar-cara-operativa.ps1
    powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\actualizar-cara-operativa.ps1
    php artisan test --filter=ControlGeminiWebTest
    php artisan test --filter=GeminiContratoTest

**Comprobar antes la base SQL Server a la que se conecta Mensajero**. No ejecutar migraciones en otra base ni en una base de pruebas no identificada.

    php artisan tinker --execute="echo config('database.default').' | '.config('database.connections.sqlsrv.database);"
    php artisan migrate:status

Solo después de verificar la base de datos, aplicar **únicamente** la migración de control:

    php artisan migrate --path=database/migrations/2026_10_24_000200_crear_control_interpretacion_ia.php --force

Esta migración crea DOS tablas de control y auditoría y deja el encendido general en **apagado**. No altera las tablas de WhatsApp ni las de eventos existentes.

En la configuración local .env, habilitar UNA sola vez el permiso del panel:

    MENSAJERO_GESTION_LOCAL_IA_HABILITADA=true

Después:

    php artisan config:clear
    php artisan view:clear
    php artisan route:clear
    php artisan queue:restart

La orden queue:restart solicita reinicio de trabajadores Laravel con el código nuevo. No detiene el recolector WhatsApp. Los supervisores existentes deben relanzar normalizador y Gemini. Verificar sus procesos desde la ventana supervisora. Si el sistema de cola no se reinicia, no encender Gemini hasta diagnosticar.

Abrir http://127.0.0.1:8000/configuracion/gemini. Si se desea habilitar Gemini, primero activar el grupo previamente autorizado y luego el interruptor general (o viceversa). A partir de ese momento se despacharán los mensajes nuevos elegibles. Para desactivar, apagar el general: no se requieren consola ni más reinicios.

## Estados

- **Apagado:** permiso individual desactivado, sin envíos de ese grupo.
- **Pausado:** permiso individual encendido, interruptor general apagado.
- **Activo:** ambos controles encendidos y autorización externa completa.
- **No autorizado:** ya no es un estado del panel; los grupos nuevos aparecen apagados hasta su autorización explícita desde la misma página.

No habilitar interpretaciones en grupos con contenidos de terceros sin su autorización aplicable. El almacenamiento original sigue privado y los mensajes no necesitan aprobación humana.

## Pruebas

Las pruebas funcionales usan vistas y políticas simuladas y no requieren consultar Google ni crear acontecimientos de prueba en la BD operativa. Las pruebas integradas de SQL Server deben realizarse en una base aislada terminada en _test.
