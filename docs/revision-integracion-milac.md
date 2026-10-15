# Revisión de integración con MILAC

## Infraestructura

Mensajero se alojará en servidor propio de MILAC y sus tablas vivirán dentro de una base SQL Server corporativa existente. No se crea una base independiente como requisito del sistema.

Laravel 13 requiere PHP 8.4+, por lo que debe ejecutarse en un runtime compatible y aislado de aplicaciones heredadas que utilicen versiones anteriores de PHP.

## Identidad

El login corporativo está temporalmente pospuesto mientras MILAC cambia su integración de autenticación. Mensajero conserva una frontera de acceso propia:

- `deshabilitado`: acceso interno temporal; administración restringida por IP.
- `local`: solo desarrollo y pruebas.
- `corporativo`: reservado para el adaptador definitivo y actualmente falla cerrado.

La futura autenticación no debe obligar a reescribir controladores de negocio ni mezclar contraseñas centrales con tablas de Mensajero.

## Base corporativa

- Identidad de ejecución con permisos mínimos.
- Identidad separada para migraciones cuando sea posible.
- Todas las tablas propias usan prefijo `mensajero_`.
- No ejecutar `migrate:fresh` contra la base corporativa.
- Las pruebas destructivas solo usan bases desechables terminadas en `_test`.

## WhatsApp

La sesión empresarial no se conecta durante la instalación inicial. Primero se certifican aplicación, SQL Server, Redis y almacenamiento; después se prueba la puerta de enlace con una sesión controlada.
