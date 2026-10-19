# Consulta de mensajes originales por grupo — 19/10/2026

## Alcance

La vista de consulta se incorporó al proyecto Laravel existente sin modificar los
eventos de captura ni ejecutar tareas de normalización sobre el histórico.

Rutas:
- `GET /mensajes`: resumen de grupos autorizados, eventos y periodos registrados.
- `GET /mensajes/grupos/{chat}`: historial cronológico paginado (30 por página)
  con filtros por tipo de evento.
- `GET /mensajes/eventos/{evento}`: evidencia de origen, identificadores de
  proveedor, integridad SHA-256 y vista previa depurada en HTML. Para evitar
  cargas excesivas, el navegador omite vistas previas extensas o no estructuradas; SQL
  Server conserva el original completo.

Los datos primarios salen de `mensajero_eventos_crudos_whatsapp`; si existe una
proyección en `mensajero_mensajes_whatsapp`, se aprovecha. Cuando Redis no está
habilitado, se extraen resúmenes de texto de los eventos existentes **sin guardar
ni modificar nada**. Ediciones, eliminaciones y reacciones se muestran como
eventos distintos, nunca como falsos mensajes originales.

Los archivos multimedia privados se muestran solo como metadatos y estado de
verificación. En este incremento **no** se publican enlaces ni los binarios.
Vistas de imagen, reproducción y descarga segura quedan para una fase posterior.

El historial presenta el número total de intervalos de conectividad y conserva
los últimos cinco bajo un detalle plegable, con causas legibles en español.
El estado `Capturado` se explica como evidencia guardada pero no normalizada;
no se modifica el estado real de SQL Server.

Los periodos de integridad registrados son de la **conexión**, no afirmaciones
de pérdida en un grupo específico. La ausencia de incidencias no equivale a una
garantía de integridad.

## Separación visual de grupos por origen

El tablero `/mensajes` distingue dos apartados según el campo
`mensajero_conexiones_whatsapp.proveedor`:

- **Grupos de WhatsApp del recolector**: conexiones con el proveedor técnico
  `recolector_whatsapp`.
- **Pasarelas y otras conexiones**: cualquier otro valor del proveedor, incluidas
  conexiones de integración o pruebas.

La distinción **no determina** si un grupo se utiliza realmente en operaciones:
el grupo del recolector también puede ser de pruebas. No se usan coincidencias
de nombres para declarar que una conversación es sintética.

La página de administración `/grupos` permite filtrar los mismos orígenes
con `?origen=recolector` o `?origen=otras`; el filtro se conserva al paginar
y no modifica `es_monitoreado`, `es_activo` ni las autorizaciones registradas.

Las pruebas de integración comprueban tanto la separación del tablero como
los filtros de administración; no requieren nuevos mensajes WhatsApp ni Redis.

## Vista previa protegida

La página de detalle prepara una representación depurada de los datos técnicos de WhatsApp, y muestra un alias de remitente. La evidencia original y su SHA-256 no cambian en SQL Server. Si el contenido es excesivo o no es JSON válido, la vista no publica fragmentos sin filtrar. La revisión de protección se comprueba con las pruebas unitarias PrepararContenidoEvidenciaTest y la integración VistaMensajesSqlServerTest.

## Seguridad mientras no exista inicio de sesión

En modo `MENSAJERO_MODO_AUTENTICACION=deshabilitado`, las tres rutas nuevas
devuelven **403 por defecto**.

Excepción temporal y explícita para probar únicamente en el propio equipo:

```env
APP_ENV=local
MENSAJERO_MODO_AUTENTICACION=deshabilitado
MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=true
```

Es imprescindible acceder desde `127.0.0.1` o `::1`. La excepción no se habilita
para peticiones remotas ni en `APP_ENV=production`, incluso si la variable
está activada. No abrir estas rutas mediante túneles ni proxies públicos
sin control de identidad. Volver a `false` después del POC.

Al integrar autenticación corporativa, se reutilizará el middleware general
`mensajero.acceso`; el modo corporativo todavía no implementado sigue
fallando cerrado, sin una excepción por la bandera local.

El contenido de mensajes y los identificadores de proveedores son datos
personales. El resumen oculta identificadores largos de remitentes tras un alias
estable; el detalle de evidencia conserva el identificador para consulta
autorizada. El contenido visible se escapa automáticamente mediante Blade.

## Pruebas previstas

Sobre `mensajero_test` exclusivamente:

```powershell
vendor\bin\pint --test app\Http\Controllers\Mensajes app\Http\Middleware\ProtegerConsultaEvidencia.php app\Models\EventoCrudoWhatsapp.php tests\Funcional\ConsultaEvidenciaAccesoTest.php tests\Integracion\VistaMensajesSqlServerTest.php
vendor\bin\phpunit --filter ConsultaEvidenciaAccesoTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
php artisan route:list --path=mensajes
```

La integración inserta grupos/eventos sintéticos en una transacción que se
revierte; **no necesita WhatsApp, Redis ni migraciones nuevas**.

Luego, para revisión visual POC, habilitar la excepción local anterior,
ejecutar `php artisan config:clear`, iniciar Laravel solo en
`127.0.0.1:8000` y abrir:

```text
http://127.0.0.1:8000/mensajes
```

El POC y la interfaz no sustituyen aún pruebas prolongadas de disponibilidad,
normalización con Redis real ni reconciliación independiente de los intervalos
de integridad.
