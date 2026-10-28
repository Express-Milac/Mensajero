# Puerta de salida a servidor MILAC — PHP 7.4.13

**Estado PHP 7.4.13:** pruebas locales aprobadas (41 SQLite, 56 SQL Server
y trabajo Redis ficticio). La instalación del compañero todavía requiere
configuración privada, validación Apache/TLS, permisos y autenticación;
no se ha modificado el servidor corporativo.

## Objetivo
Instalar y operar captura, normalización, grupos autorizados, tablero y archivos privados del Mensajero en el servidor MILAC (Debian 9, Apache/mod_php 7.4 y SQL Server), sin alterar otros sistemas. Gemini por lotes, cotizador y presupuesto de la rama PHP 8.4 no forman parte del primer despliegue.

## Ya trasladado a esta rama
- Laravel 8.83.29 con PHP 7.4.13 en composer.json y composer.lock, con plataforma fija PHP 7.4.13.
- Código de captura WhatsApp, normalización, SQL Server, vistas, tablero, grupos, archivos privados, pasarela, interfaces de Redis y estructura de tareas.
- Carpeta public con recursos web; recolector-whatsapp independiente en Node; 20 migraciones propias prefijadas mensajero_.
- El servidor posee PHP 7.4.13 CLI y pdo_sqlsrv CLI, Apache tiene mod_php 7.4 configurado; aún falta validar vhost y PHP web exactos.

## Puertas de calidad restantes
1. Las 41 pruebas PHP 7.4.13 de la suite funcional pasaron (89 aserciones); repetirlas únicamente si se modifica código PHP de ejecución.
2. Verificar instalación limpia local con Composer 2 aislado, platform-reqs, sintaxis, artisan --version, route:list, view:cache y pruebas de HTTP internas.
3. La suite SQL Server pasó 56 pruebas y 880 aserciones sobre base aislada *_test. Ninguna migración en SQL corporativo sin respaldo y permiso.
4. Redis local ya pasó PING y trabajo único de cola. Aún falta validar Redis corporativo y recolector Node en entorno Linux; confirmar Node/npm, libc y supervisión de una sola sesión.
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
