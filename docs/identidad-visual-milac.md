# Identidad visual — Mensajero Express MILAC

**Estado:** rediseño ejecutivo aplicado. Integración de los archivos de
logotipo oficial pendiente de contar con los originales aprobados.

## Logotipos

El repositorio de Mensajero no incluía logotipos corporativos, solamente
`public/favicon.ico`. Por eso **no se han dibujado ni improvisado logos**.
El diseño comprueba la existencia de archivos oficiales dentro de
`public/identidad/` y los muestra solo si están presentes:

- `public/identidad/logo-milac-blanco.png` — logotipo oficial de Express
  MILAC apto para el menú lateral oscuro.
- `public/identidad/logo-milac-color.png` — logotipo oficial de Express
  MILAC para cabecera clara.

Mientras no existan, se usa la denominación **EXPRESS MILAC** en
tipografía, sin representar el texto como logotipo auténtico.
Los archivos deben provenir de Comunicación Organizacional y Branding.
No agregar versiones no autorizadas, redibujar el logotipo ni hacer
solicitudes externas para mostrarlo: el navegador deberá recibirlo
localmente desde Mensajero.

Para incorporar los originales cuando estén disponibles:

```powershell
New-Item -ItemType Directory -Force public\identidad
# Copiar los PNG oficiales autorizados a las rutas de arriba.
php artisan view:clear
```

## Colores y fuentes

La paleta aplicada en `public/css/mensajero.css` es de **trabajo,
inspirada en la apariencia de las unidades de la flota visibles en el
sitio corporativo**: grafito, blanco y naranja. Los valores CSS están
centralizados mediante `--milac-naranja`, `--milac-grafito`, etc.
No se presentan como valores oficiales porque aún no se incorporó
el manual de identidad de **Express MILAC**.

**No utilizar los valores de un manual de otra marca** como si fueran
los de MILAC. Reemplazar los valores provisionales por los aprobados
antes de integrar el diseño a producción. Tampoco se agrega ninguna
dependencia visual externa (tipografías de red o CDNs).

## Reglas del tablero ejecutivo

- Enfatizar el contenido de mensajes y personas, no infraestructura.
- Los resúmenes de actividad se calculan de eventos crudos reales.
- Sin clasificar urgencia o importancia por volumen de mensajes.
- Cada grupo mantiene su carrusel independiente.
- La consulta automática sigue cada 12 segundos, solo lectura.
- El acceso al diagnóstico técnico continúa restringido.
- Sin alterar la base de datos, Redis, la cola histórica ni el recolector.

## Validación local

```powershell
git pull origin codex/raw-foundation-no-auth
vendor\bin\pint --test app\Http\Controllers\Mensajes\ControladorMensajes.php tests\Integracion\VistaMensajesSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
node --check public\js\panorama.js
php artisan view:clear
```

Abrir `http://127.0.0.1:8000/tablero`, hacer una sola recarga forzada
para obtener el nuevo CSS y el script, verificar el diseño en escritorio
y móvil, probar los filtros, el botón «Actualizar ahora», los carruseles
independientes y la llegada de un mensaje al grupo autorizado sin
recargar toda la página.

**Límites:** las suites de integración prueban representaciones y
seguridad, no reemplazan la inspección visual real del navegador ni la
aprobación de identidad corporativa.
