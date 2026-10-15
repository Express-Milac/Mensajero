# Mensajero

Sistema interno de evidencia operativa para transporte. SQL Server guarda datos estructurados,
metadata y rutas; el storage privado guarda archivos; la allowlist delimita captura; RAW
permanece como fuente verificable y Gemini se integra después de estabilizar la captura.

## Decisiones vigentes — 15/10/2026

- El número operativo usa **WhatsApp Business** y participa en grupos existentes de al menos
  ~30 personas.
- No se usarán proveedores/SaaS de terceros para capturar WhatsApp. El gateway será
  **autohospedado por MILAC** y aislado detrás de `WhatsAppProvider`.
- Mensajero vivirá en el **servidor propio de MILAC**.
- Sus tablas vivirán dentro de una **base SQL Server corporativa existente**. Por ello todos
  los objetos propios usan prefijo `mensajero_`, incluido `mensajero_migrations`.
- Temporalmente el producto funciona **sin login** (`MENSAJERO_AUTH_MODE=disabled`). El
  middleware queda preparado para `local` y para la futura integración `corporate`; este
  último modo falla cerrado mientras no exista el adaptador real.

## Estado actual

Implementado:

- Laravel 13 / PHP 8.4, Livewire y Redis configurables.
- SQL Server como único motor operativo.
- Storage privado configurable mediante Flysystem.
- Health checks de infraestructura existentes.
- Aislamiento de tablas de framework dentro de la BD compartida.
- Modelo inicial de conexión WhatsApp, allowlist, contactos, RAW, outbox y mensajes originales.
- Política `default deny`: chat desconocido/no monitoreado/inactivo se descarta; sticker se
  descarta; tipo futuro/desconocido se conserva si el chat está autorizado.
- Servicio transaccional `CaptureIncomingWhatsappEvent`: RAW + outbox se confirman juntos,
  con deduplicación e idempotencia.
- Modo de autenticación temporal desacoplado del dominio.

Todavía no existe conexión real al número empresarial, gateway operativo, descubrimiento de
grupos, descarga multimedia, timeline final, broadcasting ni llamadas Gemini. No afirmar
captura real hasta completar Milestone 4 con una sesión de prueba.

## Inicio local

Requiere PHP 8.4 con `sqlsrv`, `pdo_sqlsrv`, `fileinfo`, ODBC 18, SQL Server y Redis.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
# Configurar la base SQL Server de prueba/autorizada y Redis.
docker compose up -d redis
php artisan migrate
php artisan serve --host=127.0.0.1
```

Con `MENSAJERO_AUTH_MODE=disabled`, abrir `/dashboard`. No ejecutar migraciones en la BD
corporativa de producción como parte de desarrollo o pruebas. La suite SQL de integración
solo se ejecuta contra una base desechable cuyo nombre termine en `_test`.

## Verificación

```powershell
php -d extension=fileinfo vendor/phpunit/phpunit/phpunit
php vendor/laravel/pint/builds/pint --test
php artisan route:list --except-vendor
php artisan view:cache
php artisan mensajero:health
```

La validación SQL real sigue pendiente hasta disponer de una base `_test` autorizada. Los
archivos nuevos se validan además con `php -l`.

## Continuidad

Leer, en orden: `AGENTS.md`, `OBJETIVO.md`, `RESUMEN.md`, `TASKS.md`, `docs/milestones.md`,
`docs/architecture.md`, `docs/sql-server.md`, `docs/whatsapp-provider.md` y
`docs/message-ingestion.md`.
