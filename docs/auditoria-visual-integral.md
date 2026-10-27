# Auditoría visual integral — Mensajero MILAC

**Fecha:** 21 de octubre de 2026. **Rama:** `codex/raw-foundation-no-auth`.
**Alcance:** todas las rutas web HTML y vistas Blade del repositorio.
**Estado:** correcciones aplicadas al código; aún requiere pruebas PHP en Windows
y comprobación visual del navegador con `mensajero_test`.

## Causa raíz de la apariencia antigua

`resources/views/components/layout.blade.php` cargaba
`panorama-claro.css` y `catalogo-grupos.css` **solo para una lista
manual de rutas**. La vista `/mensajes/eventos/{evento}` y
`/mensajes/tecnico` estaban omitidas: heredaban CSS antiguo de
`mensajero.css`, lo que producía navegación oscura, tipografía verde
y componentes sin identidad unificada. La captura compartida del
evento 11166 es consistente con esa causa.

**Solución estructural:** el layout ahora carga SIEMPRE, en orden:

1. `public/css/mensajero.css` — estilos base y componentes funcionales.
2. `public/css/panorama-claro.css` — identidad clara corporativa.
3. `public/css/catalogo-grupos.css` — elementos de catálogos,
   conversaciones y administración.
4. `public/css/interfaz-unificada.css` — adaptación global del sistema,
   detalle de evidencia, diagnóstico, acceso e historial.

El elemento `body` recibe la clase `panorama-claro` en todas las
páginas. Solo se añaden modificadores específicos `pagina-acceso`,
`pagina-evidencia` y `pagina-tecnica`. Las clases se generan con
la directiva Blade `@class` y nunca dependen de olvidar/agregar un
nombre de ruta a una lista de cargas CSS.

## Inventario de pantallas y responsables

| Dirección | Vista Blade | Situación |
| --- | --- | --- |
| `/` | redirección | Lleva a `/tablero`; no HTML independiente |
| `/tablero` | `mensajes/indice` | Tema claro, contenido y carruseles |
| `/grupos` | `grupos/indice` | Gestor, tarjetas y controles protegidos |
| `/mensajes` | `mensajes/conversaciones` | Explorador diferenciado del tablero |
| `/mensajes/grupos/{chat}` | `mensajes/grupo` | Historial y multimedia con estilo claro |
| `/mensajes/eventos/{evento}` | `mensajes/evento` | **Corregido:** lectura destacada, navegación clara, trazabilidad desplegable |
| `/mensajes/remitentes` | `remitentes/indice` | Directorio con estilo claro |
| `/mensajes/remitentes/eventos/{evento}` | `remitentes/editar` | Formulario de nombre corporativo |
| `/mensajes/tecnico` | `mensajes/tecnico` | **Corregido:** estilo claro; sigue requiriendo administrador |
| `/acceso` | `autenticacion/acceso` | **Corregido:** título de marca y formulario; no ofrece navegación de datos protegidos a invitados |
| `/mensajes/actualizacion` | `mensajes/partes/panorama-dinamico` | **Fragmento HTML:** no debe incluir layout, por diseño |
| `/mensajes/archivos/{archivo}` | respuesta binaria | Multimedia privada: no es página con tema |
| `/mensajes/archivos/{archivo}/descargar` | respuesta binaria | Descarga privada: no es página con tema |
| `/estado` | respuesta JSON | Diagnóstico de API: no debe cargar CSS |

Los formularios PATCH/POST de grupos, remitentes y autenticación son
acciones del servidor, no pantallas HTML independientes; respetan la
misma navegación mediante sus páginas de origen.

### Plantillas compartidas y residuos del sistema anterior

- `resources/views/components/layout.blade.php`:
  única fuente visual global y navegación.
- `mensajes/partes/panorama-tarjetas`,
  `mensajes/partes/panorama-indicadores`,
  `mensajes/partes/panorama-dinamico`:
  partes del panorama, no documentos separados.
- `mensajes/partes/multimedia-privada`:
  elemento incrustado en conversación o detalle, con enlace protegido.
- `mensajes/partes/tarjeta-grupo`:
  tarjeta utilizada por diagnóstico técnico, adaptada con CSS común.
- `resources/views/tablero.blade.php`:
  **plantilla no utilizada por `/tablero`**; se reemplazó su contenido
  obsoleto por una pantalla breve de compatibilidad coherente y enlace
  al tablero real.

## Salvaguardas y regresión

- No se modificaron controladores de captura, consultas de medios,
  filtros de grupos, autorizaciones, SQL Server, Redis ni los 40 avisos
  históricos.
- El diagnóstico técnico y las acciones de administración conservan
  los mismos permisos.
- Se preservan las comprobaciones de XSS, ocultamiento de identificadores
  privados y SHA-256 del contenido original.
- El material de evidencia conserva toda su información; solo se
  reorganizó la presentación visual y se aclaró la navegación.
- El catálogo corporativo sigue pudiendo incorporar logos oficiales sin
  suplantarlos con recursos inventados.

## Validación local en Windows

Usar exclusivamente **`mensajero_test`**, nunca la BD de producción.
No activar normalización ni ningún despachador de avisos históricos.

```powershell
git pull origin codex/raw-foundation-no-auth

vendor\bin\pint --test tests\Integracion\VistaMensajesSqlServerTest.php tests\Integracion\IdentificacionRemitentesSqlServerTest.php tests\Funcional\AccesoTest.php

vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
vendor\bin\phpunit -c phpunit.integracion.xml --filter IdentificacionRemitentesSqlServerTest
vendor\bin\phpunit --filter ConsultaEvidenciaAccesoTest
vendor\bin\phpunit --filter AccesoTest

php artisan view:clear
```

Después abrir con el servidor vinculado únicamente a
`127.0.0.1`:
`/tablero`, `/mensajes`, `/grupos`, `/mensajes/remitentes`,
`/mensajes/grupos/{chat}`, `/mensajes/eventos/11166`, y
`/mensajes/remitentes/eventos/{evento}`.
Probar `/acceso` en modo `local` y `/mensajes/tecnico` únicamente
con una cuenta de administrador autorizada; no ampliar permisos para
inspeccionar el diseño.

En navegador, hacer **una recarga forzada** después de actualizar CSS,
verificar tema claro en escritorio y móvil, navegación activa, contraste
de textos, scroll de carruseles, vista multimedia, `<details>` de
trazabilidad y enlaces al remitente. Los archivos CSS se versionan por
fecha de modificación para evitar reutilizar un recurso antiguo en caché.

**Pendiente:** estas inspecciones dinámicas requieren el entorno Windows
con SQL Server y los datos locales; la auditoría estática del repositorio
no equivale a una captura real de cada pantalla.
