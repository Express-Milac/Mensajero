# Gestor de grupos — visibilidad ejecutiva y captura independientes

## Alcance implementado

En `/grupos` se pueden consultar **todos los grupos detectados** en la
tabla `mensajero_chats_monitoreados` (tipo `grupo`). Se muestran el nombre,
origen, última detección, estado de captura y **visibilidad del tablero**.
La lista conserva filtros por estado, origen, nombre y nueva preferencia
`visibilidad=visibles|ocultos|todos`, paginada de 50 en 50.

**Visibilidad** (`es_visible_tablero`) únicamente controla aparecer en
`/tablero` y `/mensajes`, incluyendo indicadores y paneles de contenido.
Un grupo oculto permanece **registrado en el gestor**, conserva todos sus
mensajes y, si `es_monitoreado` es verdadero, **continúa capturando**.
Las rutas de historial individuales siguen protegidas por autorización y
permiten al usuario autorizado consultar los mensajes de un grupo oculto.

**Captura** (`es_monitoreado`) continúa independiente. El único control
para activar o desactivar captura usa el mecanismo original auditado
`CambiarMonitoreoChat` y requiere administrador autenticado.
El permiso de gestionar únicamente la visibilidad en el prototipo local
**no permite activar captura ni desactivarla**.

La migración
`database/migrations/2026_10_20_190000_agregar_visibilidad_tablero_chats.php`
agrega una columna booleana por defecto `true` (no cambia nada de los
grupos existentes al aplicarla) y la tabla de auditoría
`mensajero_auditoria_visibilidad_grupos`, con valores anterior/nuevo,
actor y fecha UTC. No borra datos ni altera la autorización de captura.

Se eliminó exclusivamente la franja de **cinco indicadores superiores**
del centro de mando, que no era útil para los directivos. Los paneles
de lectura de temas, participación, posibles acuerdos y menciones
con evidencia permanecen.

## Seguridad temporal de desarrollo

Por defecto, **Mostrar en tablero** y **Ocultar del tablero** están
restringidos a administradores autenticados. Como la versión local del
proyecto aún opera sin login, existe una excepción **solo para desarrollo
local**, cerrada por defecto, para gestionar visibilidad, nunca captura:

```dotenv
APP_ENV=local
MENSAJERO_MODO_AUTENTICACION=deshabilitado
MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=true
MENSAJERO_GESTION_LOCAL_GRUPOS_HABILITADA=true
```

Aplica **solo a localhost 127.0.0.1 / ::1**, con `php artisan serve
--host=127.0.0.1`; requiere POST/PATCH, token CSRF, control de frecuencia y
confirmación en navegador. El servidor corporativo o la red compartida
deben usar autenticación; **no habilitar** el permiso de gestión temporal
en producción.

Al terminar el ensayo, devolver
`MENSAJERO_GESTION_LOCAL_GRUPOS_HABILITADA=false` y también
`MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=false` cuando
ya no se necesite el acceso de consulta temporal.

**No se ocultan grupos por nombres que parezcan prueba** ni se
hacen desactivaciones masivas. El responsable elige cada grupo en
el gestor y confirma el cambio. Se protegen así los grupos que
realmente estén en operación.

## Instalación y validación en Windows (solo mensajero_test)

Verificar **antes de ejecutar** que la conexión SQL Server apunta a
`mensajero_test`, nunca a la BD corporativa en producción.

```powershell
git pull origin codex/raw-foundation-no-auth
php artisan migrate --path=database/migrations/2026_10_20_190000_agregar_visibilidad_tablero_chats.php --force
php artisan config:clear
php artisan view:clear
vendor\bin\pint --test app\Services\Grupos\CambiarVisibilidadTablero.php app\Http\Middleware\AutorizarGestionGrupos.php app\Http\Controllers\Grupos\ControladorGrupos.php tests\Integracion\VistaMensajesSqlServerTest.php tests\Funcional\ConsultaEvidenciaAccesoTest.php
vendor\bin\phpunit -c phpunit.integracion.xml --filter VistaMensajesSqlServerTest
vendor\bin\phpunit --filter ConsultaEvidenciaAccesoTest
node --check public\js\gestor-grupos.js
```

La suite de integración ejecuta migraciones para `mensajero_test`,
crea grupos sintéticos y realiza sus cambios dentro de transacciones que
se revierten. No toca los grupos reales ni ejecuta Redis.

Inspección visual en `http://127.0.0.1:8000/grupos`:

1. Buscar el grupo sintético deseado, comprobar su estado de captura.
2. Ocultarlo con confirmación; verificar que deja de estar en
   `/tablero` y `/mensajes`, pero sigue apareciendo en el gestor al
   filtrar por «Ocultos».
3. Comprobar que no se pierden sus mensajes.
4. Volver a mostrarlo cuando se necesite.
5. Restaurar el permiso local temporal a `false`.

No iniciar recolectores adicionales, colas o normalización ni publicar
o despachar los 40 avisos históricos como parte de esta tarea.
