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

Los archivos multimedia privados conservan metadatos y estado de verificación.
Cuando el binario está disponible y el grupo continúa autorizado, la vista de
evidencia permite reproducir formatos compatibles y descargar el archivo mediante
una ruta protegida. No se publican rutas físicas ni enlaces permanentes.
La validación de acceso y contenido se realiza nuevamente en cada solicitud.

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

En modo `MENSAJERO_MODO_AUTENTICACION=deshabilitado`, las rutas protegidas de consulta de mensajes y multimedia
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


## Incremento funcional: visualizar y descargar multimedia privada (20/10/2026)

La fase 1 incorpora dos rutas nuevas, ambas bajo los **mismos dos
middlewares de acceso y consulta de evidencia** que los mensajes:

- `GET /mensajes/archivos/{archivo}`: muestra imagen, audio o video
  compatible en el navegador, y fuerza descarga de otros tipos.
- `GET /mensajes/archivos/{archivo}/descargar`: descarga el archivo.

La página de evidencia `/mensajes/eventos/{evento}` ofrece fotografía,
reproductor de audio o video, según el tipo declarado, y enlace de
descarga. La respuesta vuelve a determinar el MIME desde el archivo
real; **PDF, documentos y tipos no admitidos nunca se representan como
HTML ejecutable**. No se crean enlaces permanentes y la ruta física no
aparece en la interfaz.

Cada solicitud comprueba de nuevo: autenticación/autorización, chat aún
activo y monitoreado, correspondencia del archivo con evento/grupo/
conexión, estado verificado, disco privado, estructura segura de ruta,
ubicación real dentro de la raíz (también frente a enlaces simbólicos),
existencia, tamaño y SHA-256. El límite de consulta es **250 MiB** por
defecto y puede ajustarse mediante
`MENSAJERO_LIMITE_CONSULTA_MULTIMEDIA_BYTES`. Al devolver el archivo se
incluyen `Cache-Control: private, no-store` y
`X-Content-Type-Options: nosniff`. Hay límites de solicitudes por
ruta. **La descarga no normaliza mensajes ni altera el buzón SQL**.

Requisitos: los binarios ya deben existir en el mismo
`MENSAJERO_RUTA_ALMACENAMIENTO` que utiliza el recolector; una
instalación en dos máquinas necesita un volumen privado compartido o
traslado durable de archivos verificado. La ruta no se infiere desde la
BD sin comprobaciones. Esta función no descarga archivos de WhatsApp
por sí sola.

### Validación conjunta pendiente en Windows / `mensajero_test`

```powershell
git pull origin codex/raw-foundation-no-auth

php -l app\Http\Controllers\Mensajes\ControladorArchivosWhatsapp.php
php -l tests\Integracion\MultimediaRecolectorSqlServerTest.php
php -l routes\web.php

vendor\bin\pint --test app\Http\Controllers\Mensajes\ControladorArchivosWhatsapp.php routes\web.php tests\Integracion\MultimediaRecolectorSqlServerTest.php

vendor\bin\phpunit -c phpunit.integracion.xml --filter MultimediaRecolectorSqlServerTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
vendor\bin\phpunit --filter ConsultaEvidenciaAccesoTest

php artisan route:list --path=mensajes
```

La nueva prueba de integración cubre **acceso denegado por defecto**,
visualización privada, descarga, integridad alterada y revocación del
grupo. La pantalla debe revisarse posteriormente en un navegador local
con un archivo real del recolector. **No activar procesamiento masivo
ni tocar los 40 avisos pendientes por este incremento**.


## Filtros funcionales del historial por grupo (20/10/2026)

La pantalla `/mensajes/grupos/{chat}` incorpora filtros de consulta sin
escrituras por **tipo de evento**, **tipo de mensaje** (texto, fotografía,
video, audio, documento, ubicación, contacto u otros) y fechas
`desde`/`hasta`. Las fechas que captura el usuario se interpretan en la
zona `config('app.timezone')` de Mensajero y se convierten a UTC
para consultar `recibido_en` en SQL Server. Ambas fechas son inclusivas.
Es posible utilizar solo una de las fechas.

Los filtros usan condiciones SQL parametrizadas, no ejecutan trabajos de
normalización y se conservan durante la paginación. La navegación nunca
concede permiso a un grupo no autorizado.

Para comprobar este incremento, ejecutar **una batería conjunta**
en `mensajero_test` (no sobre producción):

```powershell
git pull origin codex/raw-foundation-no-auth
vendor\bin\pint --test app\Http\Controllers\Mensajes\ControladorMensajes.php resources\views\mensajes\grupo.blade.php tests\Integracion\VistaMensajesSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
```

Después se debe revisar la interfaz desde un navegador **solo local**.
Una prueba visual no debe usar túneles públicos ni modificar los
40 avisos históricos que permanecen pendientes de publicación.

## Mejora conjunta de experiencia operativa (20/10/2026)

Después de revisar visualmente las pantallas de grupo #10020 y evento
#11070, el historial pasó a presentar los mensajes como una
**conversación de solo lectura**: separadores de fecha local, remitente,
hora, contenido en bloques legibles, adjuntos privados integrados y
enlace visible a la evidencia original. El orden continúa siendo
**del más reciente al más antiguo**; no se infiere dirección
entrante/saliente ni estados de entrega que la captura no justifica.
Los estados de procesamiento permanecen visibles en segundo plano.
Los filtros anteriores y la paginación no cambian.

La pantalla individual presenta primero el mensaje, remitente y fecha.
Identificadores, hash SHA-256 y versión de contrato pasan a un panel
desplegable. El contenido crudo, depurado para ocultar secretos e
identificadores sensibles, también se muestra bajo un panel desplegable
con el mismo rótulo `Vista protegida del contenido crudo`.
**No se borra ni altera ningún dato de SQL Server** por este cambio;
el detalle forense sigue disponible y la vista protegida no es
equivalente al original completo.

La visualización de multimedia en historial y detalle comparte la misma
plantilla y las mismas rutas privadas de Laravel. Un grupo revocado
continúa sin permisos de lectura, y la entrega revalida tamaño y SHA-256.

**Validación conjunta pendiente en Windows:**

```powershell
git pull origin codex/raw-foundation-no-auth
vendor\bin\pint --test app\Http\Controllers\Mensajes\ControladorMensajes.php tests\Integracion\VistaMensajesSqlServerTest.php tests\Integracion\MultimediaRecolectorSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter "VistaMensajesSqlServerTest|MultimediaRecolectorSqlServerTest"
```

Una vez aprobadas, actualizar `http://127.0.0.1:8000/mensajes/grupos/10020`
y `http://127.0.0.1:8000/mensajes/eventos/11070`, únicamente
en el equipo local. No se activan tareas Redis ni se despachan los
40 avisos pendientes. Deshabilitar nuevamente el acceso sin inicio
de sesión al finalizar la revisión.

## Carrusel vertical y mensajes nuevos sin recarga (20/10/2026)

Tras la captura real de un mensaje por el recolector dedicado, se corrigió
el tablero directivo (`/tablero` y `/mensajes`) para que las tarjetas
**y sus extractos** se apilen verticalmente. El CSS utiliza una sola columna
y scroll vertical con límite de altura, sin scroll horizontal de tarjetas.
El CSS y JavaScript llevan versión basada en la fecha de modificación del
archivo para no reutilizar estilos viejos del navegador.

El panorama se **actualiza automáticamente cada 12 segundos**, sin
recargar el documento. El navegador consulta de forma asíncrona la ruta
GET `/mensajes/actualizacion`, reutilizando los filtros aplicados;
el servidor responde solo con indicadores y tarjetas representados en
Blade. El navegador sustituye fragmentos solo si su contenido cambia,
conserva el desplazamiento y procura conservar el foco por ID de grupo.
En pestañas ocultas no solicita actualización. Si hay un error, la página
sigue visible y reintenta en el siguiente intervalo.

La nueva ruta utiliza los mismos middlewares de control de acceso que
`/mensajes`, además de limitación de peticiones. La respuesta es
`private, no-store` y `nosniff`. En el POC no permite acceso desde redes
externas, y el contenido de cada mensaje se escapa como texto de Blade.
**No usa publicación Redis ni cambios de tablas**.

La actualización es por **consultas periódicas**, no por eventos push
instantáneos. Se espera una demora típica de hasta aproximadamente
12 segundos más la latencia de captura/red. Si se detiene el recolector,
el tablero no puede mostrar mensajes que no llegaron a SQL Server.

### Validación agrupada en Windows

Con `DB_DATABASE=mensajero_test`, entorno local y sin modificar el POC:

```powershell
git pull origin codex/raw-foundation-no-auth
vendor\bin\pint --test app\Http\Controllers\Mensajes\ControladorMensajes.php routes\web.php tests\Integracion\VistaMensajesSqlServerTest.php tests\Funcional\ConsultaEvidenciaAccesoTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
vendor\bin\phpunit --filter ConsultaEvidenciaAccesoTest
node --check public\js\panorama.js
php artisan view:clear
```

Después, en `http://127.0.0.1:8000/tablero`, realizar una recarga
forzada **única tras instalar la mejora** para adquirir CSS/JS nuevos,
mandar un mensaje de texto **nuevo** al grupo de ensayo autorizado y
esperar hasta 12–20 segundos **sin recargar la página**. Confirmar que
los indicadores y el extracto del grupo cambian por sí solos. Si no,
revisar la pestaña de red de las herramientas del navegador para
`/mensajes/actualizacion` y su código HTTP. Esta función no actualiza
todavía el historial de grupo abierto: su alcance es el tablero.
