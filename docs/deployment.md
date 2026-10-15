# Instalación y operación

## Entorno objetivo

Servidor propio de MILAC. Laravel/PHP 8.4+ con `sqlsrv`, `pdo_sqlsrv`, `fileinfo`, Microsoft
ODBC Driver 18, Composer 2 y Redis.

Mensajero no exige una base nueva. `DB_DATABASE` apunta a la base SQL Server corporativa
autorizada y todos los objetos creados por el proyecto usan `mensajero_*`.

## Desarrollo / prueba

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
docker compose up -d redis
```

Para migraciones/pruebas de integración usar únicamente una base desechable `_test`.
No ejecutar contra producción durante desarrollo.

## Acceso temporal

Actualmente `MENSAJERO_AUTH_MODE=disabled` por decisión empresarial mientras cambia el login.
`local` queda para desarrollo/pruebas. `corporate` está reservado para el login definitivo y
falla cerrado hasta que exista el adaptador.

Antes de exponer Mensajero fuera del ámbito controlado se debe activar autenticación definitiva.

## Health

- `/up`: liveness.
- `/health`: SQL/Redis/storage. En modo `disabled` el control de red debe impedir exposición.
- `php artisan mensajero:health`: CLI.

## Gateway futuro

`services/whatsapp-gateway` correrá en infraestructura controlada por MILAC. Sesiones/llaves de
WhatsApp quedan fuera de Git. La API gateway ↔ Laravel se protegerá con TLS/HMAC.

## Producción

Servir solo `public/`, APP_DEBUG=false, SQL cifrado, Redis privado, permisos mínimos, workers
supervisados, backup coordinado SQL+storage, restauración ensayada y logs sin payloads/secretos.
No conectar el número empresarial hasta superar Milestone 4 controlado.
