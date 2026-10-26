# Gestor de grupos — Interruptor único de captura y tablero

Actualizado: 23/10/2026.

## Regla funcional aprobada

**Cada grupo tiene un solo interruptor** en /grupos:

- **Habilitado:** se autoriza captar los mensajes nuevos y se muestra el grupo en /tablero, /mensajes y sus conversaciones consultables.
- **Deshabilitado:** no se captan mensajes nuevos, no se envía ese grupo en la lista permitida del recolector y no aparece en tablero ni conversaciones. **Los mensajes registrados previamente se conservan** en SQL Server, sin posibilidad de borrado por el interruptor.
- Los grupos inactivos no pueden habilitarse. Los grupos nuevos descubiertos aparecen deshabilitados de manera predeterminada.
- La vista de administración de grupos continúa listando habilitados y deshabilitados para permitir cambiar su estado; no es una conversación visible en el tablero.
- La autorización de envío a **Google Gemini** es distinta: habilitar la captura NO añade automáticamente un grupo a MENSAJERO_IA_GRUPOS_AUTORIZADOS.

## Implementación

Por compatibilidad con el esquema existente permanecen los campos booleanos es_monitoreado y es_visible_tablero en mensajero_chats_monitoreados, pero **no se administran por separado**. La única operación autorizada para cambiar estos valores es CambiarMonitoreoChat, que aplica ambos dentro de la **misma transacción SQL Server**, con bloqueo de fila, actualización de versión de autorización y registros de auditoría.

La ruta PATCH /grupos/{chat}/monitoreo sustituye a la antigua PATCH /grupos/{chat}/visibilidad. La llamada exige token CSRF, límites de peticiones y permisos administrativos. En el POC local se puede optar por el permiso de gestión local de grupos bajo localhost exclusivamente; fuera de ese entorno permanece cerrado.

Todas las decisiones de captura aplican simultáneamente es_activo=true, es_monitoreado=true y es_visible_tablero=true. Esto incluye:
- Lista de grupos autorizados del recolector WhatsApp.
- Filtro de recepción y creación de evidencia SQL Server.
- Normalización, despacho automático Gemini y acceso a conversaciones.
- Tarjetas del tablero y eventos IA.

### Situaciones heredadas y privacidad

El diseño anterior podía dejar es_monitoreado=true y es_visible_tablero=false (capturaba estando oculto) o viceversa. Para proteger los datos, **solo se considera habilitado cuando los dos valores son verdaderos y el grupo está activo**. No se ejecuta una actualización masiva sobre grupos existentes: al utilizar el nuevo interruptor, el servicio alinea ambos campos con el estado elegido y registra cualquier cambio.

Esto implica que grupos previamente ocultos dejan de entrar en la lista del recolector, incluso si estaban marcados como monitoreados. Revisar su estado en el gestor antes de volver a autorizarlos: nunca habilitar grupos reales sin confirmación de la empresa.

## Permisos del POC local

Durante pruebas solo en localhost, sin login corporativo, deben estar explícitamente activadas las opciones existentes:

~~~dotenv
APP_ENV=local
MENSAJERO_MODO_AUTENTICACION=deshabilitado
MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=true
MENSAJERO_GESTION_LOCAL_GRUPOS_HABILITADA=true
~~~

Este permiso de desarrollo **ahora permite cambiar captura y vista juntas**, por lo que no debe activarse en un servidor expuesto a la red. En producción se requiere autenticación corporativa administrativa y límites de acceso estrictos.

## Verificación controlada

Actualizar código y ejecutar pruebas sin enviar llamadas Gemini reales:

~~~powershell
php artisan test --filter=FiltroMensajeEntranteTest
php artisan test --filter=ConsultaEvidenciaAccesoTest
php artisan route:list --path=grupos
node --check public/js/gestor-grupos.js
php artisan view:clear
~~~

Las pruebas con SQL Server deben ejecutarse **solo en la base terminada en _test** y siempre después de verificar su nombre:

~~~powershell
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter AdministracionGruposSqlServerTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter RecolectorWhatsappSqlServerTest
~~~

Probar manualmente desde /grupos con un **grupo de prueba**: apagarlo y comprobar que desaparece de /tablero y de la lista del recolector; volver a encenderlo y confirmar que reaparece en ambos. El sistema no debe aceptar nuevos mensajes mientras está apagado, no debe borrar evidencia previa ni iniciar Gemini sobre otros grupos no autorizados.

No modificar las banderas directamente con SQL, no ejecutar correcciones masivas sin autorización y no usar esta prueba para activar grupos operativos reales.
