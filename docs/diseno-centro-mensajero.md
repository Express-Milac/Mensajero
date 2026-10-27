# Diseño y funcionalidad del nuevo Mensajero MILAC

## Alcance del diseño

Se implementó una versión clara del concepto aprobado en la conversación:
- **Mensajero MILAC** como único título principal.
- Cabecera con ilustración de un **tractocamión** en ruta, incluida localmente
  en `public/identidad/tractocamion-milac.svg` (es ilustración, **no
  fotografía ni logotipo corporativo oficial**).
- Menú lateral claro, sin la frase promocional inferior.
- Cinco indicadores del día, cuatro paneles de lectura y grupos independientes
  en cuadrícula de tres columnas cuando el espacio lo permite.
- Cada grupo mantiene su carrusel vertical propio con hasta ocho mensajes;
  datos originales y citas pueden abrirse con controles existentes de acceso.
- El diseño no muestra nombres de usuarios ficticios, fotografías generadas de
  empleados, porcentajes inventados, ni acuerdos/urgencias no confirmados.
- El logotipo institucional verdadero continúa pendiente de recibir sus archivos
  oficiales (ver `docs/identidad-visual-milac.md`).

## Fuentes de datos y límites explícitos

La información procede de `mensajero_eventos_crudos_whatsapp`,
únicamente para grupos autorizados (activos + monitoreados).

1. **Mensajes del día y grupos activos:** conteo real de todos los mensajes
   desde la medianoche de la zona de `app.timezone`; se convierte a UTC
   para SQL Server.
2. **Último mensaje:** fecha de recepción de los últimos mensajes por grupo.
3. **Participación:** identificadores distintos no vacíos observados en una
   muestra de hasta **300 mensajes recientes de hoy**. Se muestran
   pseudónimos estables; el número no equivale necesariamente a personas
   identificadas, y es limitado a la muestra.
4. **Temas clave:** presencia literal de términos de logística, unidades,
   clientes, incidencias o documentos en la misma muestra. Es una
   clasificación de menciones, **no una interpretación de IA**.
5. **Posibles acuerdos:** muestra hasta cuatro mensajes con términos
   explícitos de acuerdo, confirmación o autorización, siempre con un
   enlace a la evidencia. No afirma que estén resueltos o cumplidos.
6. **Menciones para revisar:** cuenta todos los mensajes de la muestra que
   contienen expresiones como pendiente, demora, retraso o incidencia.
   Expone hasta cuatro enlaces. No determina gravedad ni urgencia.
7. **Tarjetas:** incluyen ocho mensajes recientes, se lee su remitente
   provisional, extracto y fecha, y se enlaza al mensaje original.
   El «resumen del grupo» indica el número del día y **cita literalmente**
   el último texto; no se hace pasar por un análisis semántico.

La muestra limitada evita recorrer el historial completo en cada consulta.
Si hay más de 300 mensajes en el día, las cifras generales sí cubren el día
completo, pero temas, remitentes y menciones solo cubren el subconjunto
observado. **No prometer exactitud completa en indicadores de muestra**.

## Interacciones implementadas

- Actualización automática por GET a `/mensajes/actualizacion` cada 12
  segundos, respetando filtros y autorización; solo lectura, con
  `Cache-Control: no-store` y limitación de peticiones.
- Botón **Actualizar ahora**, con estado de última consulta visible.
- Filtros de grupos por actividad y orden, procesados del lado servidor.
- Búsqueda instantánea por nombre de grupo y últimos ocho mensajes de
  las tarjetas disponibles. **No es una búsqueda de historial completo**;
  no busca en metadatos JSON ni grupos sin autorización.
- Cuadrícula/lista con botones que reflejan el estado mediante
  `aria-pressed`. Adaptación a escritorio, tableta y móvil.
- Carrusel propio por grupo, scroll/flechas independientes y conservación
  de lectura ante nuevos mensajes cuando el mismo mensaje aún está visible.
- Navegación a Temas, acuerdos y revisión mediante anclas reales.
- Mensajes y menciones se pueden abrir a través de las rutas ya
  protegidas por `mensajero.consulta_evidencia`.

## Funciones que NO se declaran terminadas

- Gemini u otro análisis semántico de documentos, audios, imágenes,
  acuerdos, responsables y tareas.
- Confirmación de riesgos, niveles de urgencia, plazos o pendientes.
- Directorio corporativo verificado de remitentes, fotografías de personal
  y vinculación de números LID con empleados.
- Reportes descargables, notificaciones y bandeja persistente de seguimiento.
- Fotografía real y logos oficiales; habrá que incorporarlos con los
  archivos autorizados por Comunicación Organizacional.
- Usuarios y permisos definitivos de producción.

## Validación en Windows

Únicamente sobre `mensajero_test`, manteniendo
`MENSAJERO_NORMALIZACION_HABILITADA=false` y las salvaguardas de
acceso local:

```powershell
git pull origin codex/raw-foundation-no-auth
vendor\bin\pint --test app\Http\Controllers\Mensajes\ControladorMensajes.php app\Services\Mensajes\AnalizarPanoramaContenido.php tests\Integracion\VistaMensajesSqlServerTest.php tests\Unidad\AnalizarPanoramaContenidoTest.php
vendor\bin\phpunit --filter AnalizarPanoramaContenidoTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
vendor\bin\phpunit --filter ConsultaEvidenciaAccesoTest
node --check public\js\panorama.js
php artisan view:clear
```

Revisar `/tablero` en pantalla amplia y angosta, búsqueda, filtros,
cuadrícula/lista, navegación a eventos y nuevos mensajes sin recarga
(hasta 12 segundos + captura). Si no hay evidencias del día, los paneles
deben presentar estados vacíos, nunca contenido de muestra inventado.

**Prohibido** despachar, normalizar o publicar masivamente los 40 avisos
históricos conocidos, ejecutar `redis-cli FLUSH*` o arrancar trabajadores
de manera permanente como efecto de este rediseño.

## Menú con destinos verificables (20/10/2026)

Se corrigieron enlaces duplicados y la inconsistencia de diseño detectada
tras validar la nueva interfaz en Windows.

- **Centro de mando** (`/tablero`): indicadores, análisis literal de
  menciones y grupos en seguimiento; permanece como vista ejecutiva.
- **Conversaciones** (`/mensajes`): **vista independiente** para explorar
  los grupos autorizados, sus últimos ocho mensajes por grupo y enlaces al
  historial. Cuenta con filtros, búsqueda entre las tarjetas visibles,
  selección cuadrícula/lista y actualización automática cada 12 segundos,
  sin mostrar los KPI completos del tablero.
- **Grupos** (`/grupos`): **catálogo administrativo rediseñado** con
  búsqueda, filtros de estado (`todos|monitoreados|no_monitoreados|inactivos`),
  filtro por origen, paginación de 50 y tarjetas. Se muestran grupos
  descubiertos, cuántos están autorizados y cuántos no se monitorean.
  «Ver conversación» solo aparece para grupos activos y monitoreados.
  Activar o detener monitoreo sigue requiriendo administrador, sin
  excepciones por estar en modo local sin login.
- **Temas clave / Posibles acuerdos / Menciones para revisar**:
  **accesos del tablero**, no sistemas independientes. Cada enlace lleva
  a una sección real del Centro de mando. No hay páginas vacías ni
  notificaciones falsas.
- **Diagnóstico técnico**: solo administradores autenticados.

Se añadió el estilo `public/css/catalogo-grupos.css` para que catálogo,
conversaciones y lectura de un grupo compartan la identidad visual del
Centro de mando, sin modificar su lógica de autorización o evidencia.
Se corrigió una referencia errónea al PNG del logotipo a color.

**Privacidad**: la ruta `/grupos` ahora exige también la autorización
`mensajero.consulta_evidencia`, porque su catálogo contiene nombres
e identificadores internos. En el POC solo se permite desde
`127.0.0.1` con el permiso temporal explícito; no se expone a red.
El resto de rutas conserva sus protecciones.

**Validación local** (no ejecutar contra producción):

```powershell
git pull origin codex/raw-foundation-no-auth
vendor\bin\pint --test app\Http\Controllers\Grupos\ControladorGrupos.php app\Http\Controllers\Mensajes\ControladorMensajes.php routes\web.php tests\Integracion\VistaMensajesSqlServerTest.php tests\Funcional\ConsultaEvidenciaAccesoTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
vendor\bin\phpunit --filter ConsultaEvidenciaAccesoTest
node --check public\js\panorama.js
php artisan view:clear
```

Comprobar en navegador los tres destinos, los filtros «Monitoreados /
Sin monitorear», los enlaces al historial y el funcionamiento de las
tarjetas en móvil. Confirmar que el modo local de prueba no ofrece
botones que puedan cambiar la lista autorizada sin autenticación.
No se realizó migración ni se procesaron los 40 avisos históricos.
