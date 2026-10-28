# Puerta de salida a servidor MILAC — PHP 7.4.13

**28 octubre 2026 — pendiente, no apto todavía para despliegue.**

## Objetivo
Instalar y operar captura, normalización, grupos autorizados, tablero y archivos privados del Mensajero en el servidor MILAC (Debian 9, Apache/mod_php 7.4 y SQL Server), sin alterar otros sistemas. Gemini por lotes, cotizador y presupuesto de la rama PHP 8.4 no forman parte del primer despliegue.

## Ya trasladado a esta rama
- Laravel 8.83.29 con PHP 7.4.13 en composer.json y composer.lock, con plataforma fija PHP 7.4.13.
- Código de captura WhatsApp, normalización, SQL Server, vistas, tablero, grupos, archivos privados, pasarela, interfaces de Redis y estructura de tareas.
- Carpeta public con recursos web; recolector-whatsapp independiente en Node; 20 migraciones propias prefijadas mensajero_.
- El servidor posee PHP 7.4.13 CLI y pdo_sqlsrv CLI, Apache tiene mod_php 7.4 configurado; aún falta validar vhost y PHP web exactos.

## Puertas de calidad restantes
1. Repetir pruebas de PHP 7.4.13 en Mensajero-PHP74-Pruebas: último resultado conocido 41 casos con 1 error y 3 fallos por API connectTimeout de Laravel 8. La rama ya cambió el cliente a withOptions(connect_timeout), pero NO hay salida posterior que certifique que pasan. Asegurar también que no quede preventStrayRequests incompatible.
2. Verificar instalación limpia local con Composer 2 aislado, platform-reqs, sintaxis, artisan --version, route:list, view:cache y pruebas de HTTP internas.
3. Probar las 20 migraciones y consultas solo en SQL Server *_test, con cambios reversibles. Ninguna migración en SQL corporativo sin respaldo y permiso.
4. Validar Redis y el recolector Node por separado en entorno Linux; Node NO se reemplaza al bajar PHP. Confirmar versión Node/npm compatible y supervisión de una sola sesión del número secundario.
5. Preparar COPIA PRIVADA de staging, instalar dependencias Linux en esa copia (no copiar vendor de Windows), establecer .env privado, storage, bootstrap/cache y almacenamiento privado MENSAJERO_RUTA_ALMACENAMIENTO.
6. Confirmar Apache/HTTPS sirviendo solo public, y autenticación corporativa o restricción de red equivalente. Diagnóstico SSL anterior sin privilegios no demuestra que falte una llave: validar con el administrador sin modificar permisos.
7. Preparar respaldos de SQL y multimedia, plan de recuperación, rutas y supervisores, y prueba de humo con mensajes sintéticos. Solicitar autorización explícita antes del despliegue productivo.

## No trasladar ahora desde la rama PHP 8.4
- PublicarLoteGemini, ReservarPresupuestoGemini, CotizarCostoReferenciaGemini, VincularCotizacionPresupuestoGemini y la migración monetaria nueva.
- No hacer merge general desde Laravel 13/PHP 8.4, ni activar llamadas Gemini o colas reales; requeriría adaptación y otra aprobación.
- No copiar secretos, sesiones WhatsApp, node_modules ni vendor de Windows al repositorio.

## Cierre de fase
Primero demostrar arranque y funcionamiento con PHP 7.4 real, SQL Server test y acceso interno seguro. Después instalar en una copia de staging del servidor; solo con pruebas HTTPS, rollback y permisos aprobados se considera producción.

Advertencia: Debian 9, PHP 7.4 y Laravel 8 están fuera de soporte estándar de seguridad. El aislamiento temporal no sustituye actualizar la infraestructura.
