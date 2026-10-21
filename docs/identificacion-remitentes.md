# Identificación de remitentes — Mensajero MILAC

## Problema y solución

La vista anterior mostraba pseudónimos como `Remitente 4393E87C`
porque la normalización sigue desactivada; el nombre del contacto
normalizado normalmente está vacío. Mensajero ya conserva en SQL
Server los identificadores de quien envió cada mensaje, y la evidencia
cruda de Baileys puede incluir `pushName`.

La nueva resolución se aplica **en tiempo de lectura**, sin consumir
la cola de Redis ni modificar los mensajes:

1. Nombre corporativo asignado en el directorio de Mensajero.
2. `pushName` del mensaje, si WhatsApp lo suministra. Es nombre de
   perfil elegido por el participante, **no identidad verificada**.
3. Nombre visible o notificación de contacto normalizado, si existe.
4. Número telefónico si el identificador tiene formato
   `@s.whatsapp.net` válido o si el mismo mensaje de WhatsApp incluye
   `key.participantPn` vinculado explícitamente al `participant`
   del identificador LID observado. Nunca inferirlo de los dígitos del LID.
5. «Persona sin identificar» para un LID u otro identificador que no
   contenga nombre o teléfono fiable.

NUNCA tratar `@lid` como número telefónico ni inventar la identidad.

La identificación telefónica en las páginas de mensajes es **solo para
usuarios autorizados**: se muestra `Teléfono +...` cuando hay un número
aportado por WhatsApp y todavía no hay un nombre disponible. No implica
exponer el identificador técnico completo `...@s.whatsapp.net`: la vista
protegida de JSON crudo sigue ocultando los identificadores internos y los
secretos, y las rutas continúan sujetas a `mensajero.consulta_evidencia`.
La prueba `VistaMensajesSqlServerTest` verifica las dos cosas de manera
independiente.

## Identificar o corregir un nombre

Nueva sección **Remitentes** en menú, ruta
`/mensajes/remitentes`. Lista remitentes detectados en los grupos
activos y autorizados, con su último grupo, último mensaje y nombre
disponible; se pueden filtrar por grupo. Usa una consulta SQL agrupada
y paginada de 30 personas; no divulga el JID completo al navegador.

Desde el directorio o desde cualquier mensaje se puede acceder a
`/mensajes/remitentes/eventos/{evento}` y asignar un nombre interno.
Ese nombre se guarda por combinación exacta
`conexion_id + id_remitente_proveedor` en
`mensajero_identidades_remitentes`. Aparece también en los mensajes
anteriores y futuros del mismo identificador. Las identidades de
conexiones diferentes nunca se mezclan por tener el mismo JID.

El guardado es transaccional y registra el actor y los tiempos de
modificación. La evidencia original (contenido JSON, SHA-256, estado de
procesamiento) **no se cambia**. Tampoco se necesitan trabajos de
normalización.

## Seguridad

La lectura del directorio exige `mensajero.consulta_evidencia`, igual
que el historial. Para asignar un nombre requiere permiso de
**administrador** cuando la autenticación local está activa.

Excepción solo en la prueba local sin login, deshabilitada por defecto:

```dotenv
APP_ENV=local
MENSAJERO_MODO_AUTENTICACION=deshabilitado
MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=true
MENSAJERO_GESTION_LOCAL_REMITENTES_HABILITADA=true
```

La autorización temporal solo funciona con IP `127.0.0.1` / `::1`,
y se combina con token CSRF y limitación de solicitudes. No expongas
este servidor a la red ni actives el permiso en producción. Al
terminar, devuelve
`MENSAJERO_GESTION_LOCAL_REMITENTES_HABILITADA=false`.

No activar `MENSAJERO_NORMALIZACION_HABILITADA` para esta función.

## Instalación y prueba

Trabajar **solo con `mensajero_test`**; verificar el nombre antes de
ejecutar la migración. No tocar `milacdb` ni bases corporativas.

```powershell
git pull origin codex/raw-foundation-no-auth
php artisan tinker --execute="echo config('database.connections.sqlsrv.database');"
# Si y solo si imprime mensajero_test:
php artisan migrate --path=database/migrations/2026_10_20_200000_crear_identidades_remitentes.php --force
php artisan config:clear
php artisan view:clear
vendor\bin\pint --test app\Services\Mensajes\IdentificarRemitente.php app\Http\Controllers\Remitentes\ControladorRemitentes.php app\Http\Middleware\AutorizarGestionRemitentes.php tests\Unidad\IdentificarRemitenteTest.php tests\Integracion\IdentificacionRemitentesSqlServerTest.php
vendor\bin\phpunit --filter IdentificarRemitenteTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter IdentificacionRemitentesSqlServerTest
vendor\bin\phpunit --filter ConsultaEvidenciaAccesoTest
```

Después comprobar `http://127.0.0.1:8000/mensajes/remitentes` y
`/tablero`, seleccionando una persona de prueba y asignándole un
nombre. La nueva identidad debe verse en los mensajes de ese mismo
remitente sin recargar la sesión WhatsApp ni reiniciar el recolector.

Si el remitente no tiene identificador alguno en el mensaje, Mensajero
no debe inventarlo: el formulario no permitirá asignar un nombre por
esa evidencia; deberá identificarse primero la causa de la captura sin
remitente.

## Limitación de WhatsApp

WhatsApp no garantiza un nombre de perfil visible ni el número en
cada mensaje, especialmente con los identificadores privados LID.
El nombre corporativo necesita asignación responsable cuando el
proveedor no aporta nombre. Vincular LID, número y empleado de
forma definitiva requiere un directorio corporativo de identidades
y evidencias comprobadas, no solo coincidencia de nombre de perfil.

No abrir colas ni despachar los 40 avisos históricos pendientes.

## Captura del nombre y del celular directamente desde el recolector

A partir del cambio del 21/10/2026, los mensajes **nuevos** de grupos
autorizados transportan opcionalmente
`nombre_remitente`, `fuente_nombre_remitente` y
`telefono_remitente`. Baileys utiliza los nombres aportados por su
flujo `contacts.upsert`, `contacts.update` o
`messaging-history.set`, además del `pushName` del mensaje. Cuando
no existe nombre guardado, se usa el nombre de perfil si está presente;
ambos se marcan con fuentes diferentes.

La migración
`database/migrations/2026_10_21_000001_identificar_remitentes_en_eventos.php`
agrega tres columnas anulables en SQL Server al evento crudo:
`nombre_remitente` (120), `fuente_nombre_remitente` (16) y
`telefono_remitente` (20). La pasarela firmada admite los campos sin
exigirlos a integraciones previas y valida longitudes, procedencia y
formato de número. No se modifica la carga JSON original, su SHA-256
ni su deduplicación.

Mensajero muestra el nombre y el celular cuando ambos están presentes
en el tablero, en conversaciones, en el detalle del mensaje y en el
directorio. El nombre corporativo asignado manualmente mantiene la
máxima prioridad. Los mensajes históricos no se reescriben.

### Orden de despliegue en pruebas

**Primero Laravel y SQL Server; después el recolector**. Antes de
ejecutar la migración verifica que la conexión SQL se llame
`mensajero_test`:

```powershell
git pull origin codex/raw-foundation-no-auth
php artisan tinker --execute="echo config('database.connections.sqlsrv.database');"

# Solo si la salida es mensajero_test:
php artisan migrate --path=database/migrations/2026_10_21_000001_identificar_remitentes_en_eventos.php --force

vendor\bin\pint --test app\Data\WhatsApp\EventoWhatsappEntrante.php app\Http\Controllers\Pasarela\ControladorEntradaPasarela.php app\Services\Capture\CapturarEventoWhatsappEntrante.php app\Services\Mensajes\IdentificarRemitente.php tests\Unidad\IdentificarRemitenteTest.php tests\Integracion\SemanticaRecolectorSqlServerTest.php

vendor\bin\phpunit --filter IdentificarRemitenteTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter SemanticaRecolectorSqlServerTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter IdentificacionRemitentesSqlServerTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
```

Luego, desde `recolector-whatsapp`:

```powershell
npm run verificar
npm run pruebas
```

Solo después de pruebas aprobadas, reinicia **la única** instancia
del recolector de ensayo mediante su mecanismo normal. No crear una
segunda sesión, no usar el número principal de la empresa ni
despachar los 40 avisos históricos. Un mensaje de un participante
conocido servirá para verificar qué entrega realmente WhatsApp.

Nota: `syncFullHistory: false` limita la información de contactos
que puede suministrar la sesión. Ni Baileys ni Mensajero garantizan
que el nombre guardado o el número de todos los participantes esté
disponible. Si falta, no se inventa: el directorio permite asignar un
nombre interno con permisos controlados.
