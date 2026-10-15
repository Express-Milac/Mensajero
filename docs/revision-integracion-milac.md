# Revisión para integración con infraestructura MILAC

Mensajero debe convivir con una base SQL Server corporativa existente sin afectar otros sistemas. No usa prefijo global de conexión; cada tabla propia declara explícitamente `mensajero_`.

Antes de instalar en servidor MILAC se requiere: pruebas de unidad/funcionales, Pint, compilación de vistas, revisión de rutas, migraciones sobre SQL Server `_test`, revisión de permisos y plan de reversión.

El almacenamiento debe apuntar inicialmente a una ubicación privada y poder moverse posteriormente cambiando `MENSAJERO_RUTA_ALMACENAMIENTO` sin cambiar el modelo de datos.

La autenticación corporativa sigue pendiente. Mientras tanto el modo sin autenticación debe quedar limitado por red y no habilita diagnóstico administrativo web.
