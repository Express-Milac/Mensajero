# Mensajero MILAC — PHP 7.4.13

Aplicación Laravel 8.83.29 para capturar y consultar evidencia de los grupos
de WhatsApp autorizados, con SQL Server corporativo, Redis y almacenamiento
privado de archivos. La rama `compatibilidad/php74-milac-20261027` contiene
el código adaptado a PHP 7.4.13 para que el equipo de infraestructura lo
instale en Debian 9, sin modificar desde desarrollo el servidor existente.

## Componentes

- Interfaz Laravel bajo `public/`, con acceso a mensajes sujeto a autenticación;
  la integración de identidad corporativa todavía está pendiente.
- SQL Server con tablas propias `mensajero_*` e historial aislado
  `mensajero_migraciones`; no se debe migrar directamente la base activa.
- Redis / Predis para colas de trabajos; no ejecutar trabajadores hasta
  verificar configuración y colas exclusivas del entorno.
- Archivos multimedia fuera de SQL Server, en disco privado configurable
  mediante `MENSAJERO_RUTA_ALMACENAMIENTO`.
- `recolector-whatsapp/`: componente Node separado de Laravel, con su propia
  sesión, directorio privado y configuración de conexión. Solo grupos permitidos,
  sin stickers ni captura de otros chats.

## Compatibilidad verificada localmente

- PHPUnit y PHP 7.4.13: **41 pruebas / 89 aserciones** (SQLite).
- SQL Server aislado: **56 pruebas / 880 aserciones**.
- Redis local: conexión `PING/PONG` y un trabajo sintético publicado y
  procesado por `queue:work --once`, con cola vacía al terminar.

Estas pruebas fueron ejecutadas **antes de los ajustes recientes de
preparación de la rama** y no sustituyen probar las dependencias Linux,
la versión PHP web, el sistema de archivos ni la integración con
los servicios corporativos.

## Condiciones de ejecución en el servidor

El código se despliega desde esta rama en una carpeta aislada, con
dependencias Linux resueltas a partir del `composer.lock` existente
(mediante Composer 2 compatible con PHP 7.4). Nunca copiar `vendor` o
`node_modules` de Windows ni actualizar el Composer global del servidor.
Laravel usa variables de entorno o un archivo privado `.env`
(no versionado) suministrado por infraestructura: `APP_KEY`, `APP_URL`,
conexión SQL Server con TLS, Redis, ruta de almacenamiento, colas y políticas
de autenticación. La carpeta `public/` es la única que debe exponerse.

La autenticación `corporativo` falla cerrada hasta integrar la identidad
MILAC. Para desarrollo existen modos locales, no adecuados para abrir
mensajes operativos en una red compartida sin controles de acceso.

Antes de operar, el responsable de infraestructura verifica los permisos de
escritura efectivos de `www-data` en `storage/` y `bootstrap/cache/`;
la versión PHP efectiva de Apache; las condiciones TLS, respaldos, acceso
autorizado y la compatibilidad Linux del recolector Node. No se deben
alterar otros sistemas, colas o tablas corporativas durante esa verificación.

**Estado:** port PHP 7.4 probado en desarrollo. Pendiente de validación
y autorización en el servidor real; no es un despliegue automático.
