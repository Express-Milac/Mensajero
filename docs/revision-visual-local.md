# Revisión visual de fase 1 — Mensajero MILAC
Actualizado: 20/10/2026.

Objetivo: abrir en navegador la captura histórica ya disponible y comprobar
la navegación, filtros y evidencia multimedia del POC. **No conecta ni inicia
WhatsApp**, no publica avisos y no normaliza registros. Quedan 40 avisos
históricos sin publicar y su procedencia debe respetarse.

## Preparar configuración solo local

Trabajar sobre la base `mensajero_test` y verificar que el proyecto
tenga su `.env` ya preparado. En el entorno **local aislado**, configurar:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlsrv
DB_DATABASE=mensajero_test
MENSAJERO_MODO_AUTENTICACION=deshabilitado
MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=true
MENSAJERO_NORMALIZACION_HABILITADA=false
```

Estos valores son un ejemplo: no modificar las credenciales de SQL
existentes ni compartirlas. La excepción temporal sin autenticación
**no debe habilitarse en producción, red LAN, proxy ni túnel**. Se limita
a solicitudes desde `127.0.0.1` y `::1` en desarrollo/pruebas.

Desde la raíz del repositorio y con el resto de `.env` funcionando:

```powershell
git pull origin codex/raw-foundation-no-auth

# Validación agrupada del incremento nuevo (no repetir Redis):
vendor\bin\pint --test app\Http\Controllers\Mensajes\ControladorMensajes.php tests\Integracion\VistaMensajesSqlServerTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest

php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000
```

La consola quedará ocupada mientras Laravel sirve las páginas.
En el **mismo equipo** abrir en el navegador:

- `http://127.0.0.1:8000/mensajes`: listado de grupos permitidos.
- `http://127.0.0.1:8000/grupos`: grupos descubiertos, estado actual.
- Dentro del grupo: probar tipo de mensaje, fecha desde/hasta, limpieza
  de filtros y paginación.
- Dentro del detalle: revisar evento crudo, tipo, remitente en formato
  protegido e integridad SHA-256. Si existe multimedia con binario
  accesible en `storage/app/mensajero` (o ruta privada configurada),
  revisar su visualización y descarga.
- En móvil o ventana angosta: verificar menú de navegación visible.

**No esperar multimedia visual si el archivo físico no está presente**:
los metadatos de SQL Server no crean el binario.

## Cerrar la revisión local

Detener `php artisan serve` con `Ctrl+C`. Restaurar en `.env`:

```dotenv
MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=false
```

Después ejecutar `php artisan config:clear`.

No capturar grupos reales ni vincular el número principal para esta
revisión. El arranque del recolector WhatsApp secundario será una
etapa separada, después de confirmar que el acceso a la interfaz y
el directorio compartido para multimedia funcionan.

No publicar, modificar o eliminar los 40 avisos pendientes del entorno
de prueba (38 grupo #10020, 1 grupo #16 y 1 grupo #20).
