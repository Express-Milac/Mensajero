# Mensajero

Sistema interno de evidencia operativa para transporte. SQL Server guarda datos y referencias;
storage privado guarda archivos; allowlist delimita captura; RAW permanece como fuente verificable.

## Continuar con una IA

Leer [AGENTS.md](AGENTS.md) para instrucciones y responsabilidades, [OBJETIVO.md](OBJETIVO.md)
para alcance y aceptación, [RESUMEN.md](RESUMEN.md) para estado real y [TASKS.md](TASKS.md)
para el siguiente trabajo. [CONTRIBUTING.md](CONTRIBUTING.md) explica validaciones y entrega.
No es necesario disponer del historial del chat para continuar.

## Entrega actual: arquitectura y base de aplicación

Siguiendo el requerimiento, se inicia por milestones 0 y 1, sin mezclar todas las fases.
Implementado: Laravel 13 / PHP 8.4, dependencias bloqueadas en composer.lock, acceso mediante
sesión, roles Administrador/Supervisor/Visualizador, rate limit de login, comando para crear
administrador, configuración SQL Server/Redis, disco privado configurable y diagnóstico
web/CLI. Blade para acceso e inicio; Livewire instalado para las próximas vistas operativas.

**Todavía no captura WhatsApp.** No hay gateway conectado, ingestión, timeline, multimedia,
broadcasting ni llamadas Gemini. El modelo operativo y sus contratos están diseñados en docs,
no presentados como funcionalidad implementada. No hay datos demo ni credenciales por defecto.

## Inicio

Requiere PHP con sqlsrv/pdo_sqlsrv/fileinfo, ODBC 18, SQL Server y Redis.
Consultar [instalación detallada](docs/deployment.md).

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
# Completar credenciales SQL y Redis en .env.
docker compose up -d redis
php artisan migrate
php artisan mensajero:create-admin
php artisan serve --host=127.0.0.1
```

Abrir http://localhost:8000/login. No se debe exponer `artisan serve` en producción.
No ejecutar migraciones con credenciales empresariales sin preparar antes base y permisos.

## Pruebas y estado

```powershell
php -d extension=fileinfo vendor/phpunit/phpunit/phpunit
php artisan route:list --except-vendor
php artisan view:cache
php artisan mensajero:health
```

Resultado local: **9 pruebas aisladas, 41 assertions, todas pasan**. Rutas, vistas y manifest
Composer validados. Las pruebas usan dobles para servicios y no utilizan SQLite ni sustituyen
la validación SQL Server. Suite SQL real disponible en `phpunit.integration.xml`; requiere
base desechable terminada en `_test`. No ejecutada en este entorno.

Bloqueos de entorno: drivers SQL Server no instalados, Docker daemon no iniciado, sin datos
de conexión SQL ni sesión WhatsApp. Milestone 1 **no está certificado de extremo a extremo**.
Milestone 0 documentado; compatibilidad del proveedor con cuenta real sigue pendiente.

## Documentación

- [Revisión de login y SQL corporativos: IAVE/GPSGestor](docs/milac-integration-review.md)

- [Arquitectura y decisiones](docs/architecture.md)
- [Modelo SQL Server](docs/sql-server.md)
- [Análisis del proveedor WhatsApp](docs/whatsapp-provider.md)
- [Allowlist](docs/monitored-chats.md)
- [Contrato e ingestión durable](docs/message-ingestion.md)
- [Storage portable](docs/storage.md)
- [Pipeline IA](docs/ai-pipeline.md)
- [Despliegue y operación](docs/deployment.md)
- [Milestones y aceptación](docs/milestones.md)

Próximo trabajo: adaptar login y autorización al patrón corporativo documentado y preparar
reutilización de la conexión existente, sin exigir secretos para avanzar con pruebas aisladas.
Después certificar milestone 1 con SQL/Redis reales y continuar allowlist (2), RAW/outbox (3)
y gateway (4), antes de multimedia y Gemini.
