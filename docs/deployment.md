# Instalación y operación

## Requisitos

PHP 8.4+ con extensiones Laravel, fileinfo, sqlsrv y pdo_sqlsrv; Microsoft ODBC Driver 18;
Composer 2; SQL Server empresarial con base `mensajero` provisionada; Redis.
El compose local inicia **solo Redis**, sin modificar la base empresarial ni aceptar
automáticamente licencias SQL Server. Docker Desktop debe estar iniciado.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
docker compose up -d redis
```

Editar `.env`: host/base/usuario/password SQL Server y Redis. Para producción usar certificado
SQL válido, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS y `SESSION_SECURE_COOKIE=true`.
No confiar en certificado SQL por defecto. Identidad de migración separada de runtime.

```powershell
php artisan migrate
php artisan mensajero:create-admin
php artisan serve --host=127.0.0.1
```

No registro público ni cuenta/contraseña inicial compartida. El comando solicita contraseña
oculta y confirmación. Recuperación de cuentas queda en procedimiento administrativo hasta
incorporar correo y flujo de restablecimiento. La pantalla de acceso no exige npm: CSS local.
Livewire está instalado para las vistas operativas posteriores.

## Verificación

`php artisan test`: pruebas aisladas sin base de datos, con dobles explícitos de autenticación
y dependencias. No certifican SQL Server, Redis real ni compatibilidad WhatsApp.
`php artisan route:list --except-vendor` y `php artisan view:cache`: arranque/rutas/vistas.
`php artisan test --configuration=phpunit.integration.xml`: integración SQL Server opt-in,
requiere `.env.testing` con base desechable terminada en `_test` y drivers reales. No ejecutar
contra producción. Esta suite migra y usa transacciones para sus usuarios de prueba.

`/up`: liveness público sin detalles. `/health`: diagnóstico autenticado solo administrador,
comprueba SELECT SQL, PING Redis y escritura/lectura/borrado de un objeto temporal en el disco
configurado más espacio libre local. Devuelve 503 ante fallo. WhatsApp `not_implemented` y
Gemini `disabled` son estados honestos, no comprobación remota. Los checks de ambos y alertas
automáticas quedan para milestones 4/6/10. Un 200 no certifica captura operativa completa.

Si Redis falla, sesiones/rate limit pueden impedir acceder al diagnóstico web; usar `/up`,
monitor externo de Redis y diagnóstico CLI `php artisan mensajero:health` independiente
de sesiones. No volcar excepciones con DSN o credenciales.

## Workers y producción futura

Ejecutar Linux con servicio supervisado para workers y scheduler. Comando de referencia:
`php artisan queue:work redis --queue=raw,media,broadcast,ai --tries=5 --timeout=120`.
En operación separar workers por cola para evitar que media/IA bloqueen RAW.
`retry_after=180` debe superar timeout. Outbox y scheduler se implementan en milestone 3;
no asumir que instalar Redis implementa entrega durable por sí solo.

Servir únicamente `public/`, permisos mínimos en storage y bootstrap/cache, secretos fuera
de Git; logs sin contenido WhatsApp; Redis en red privada con autenticación en producción.
Cachear configuración/vistas al desplegar y reiniciar workers. Mantener backup coordinado
SQL/storage y probar restauración antes de activar captura. No exponer Horizon públicamente.

## Entorno inspeccionado

2026-10-10: PHP 8.4.7 instalado sin sqlsrv/pdo_sqlsrv. Fileinfo y ZIP disponibles como DLL
pero no habilitados por defecto. Se pueden habilitar por comando mediante
`php -d extension=fileinfo -d extension=zip ...` sin modificar el PHP global.
Docker CLI instalado, daemon no disponible al inspeccionar. No se recibió conexión SQL
empresarial ni sesión WhatsApp. No se ejecutaron migraciones contra una base real.
