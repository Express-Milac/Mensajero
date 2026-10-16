# Registro de validación

## 16/10/2026 — integración oficial Meta Cloud API aprobada localmente

Validación ejecutada en Windows con PHP 8.4.7 y Microsoft SQL Server 2022 Developer desechable en Docker sobre `mensajero_test`.

| Comprobación | Resultado |
|---|---|
| `composer dump-autoload` | Aprobado |
| `php artisan optimize:clear` | Aprobado |
| `php vendor/laravel/pint/builds/pint --test` | 78 archivos aprobados |
| PHPUnit unidad + funcional | 32 pruebas / 78 aserciones aprobadas |
| `phpunit.integracion.xml` | 6 pruebas / 100 aserciones aprobadas |
| Repetición inmediata de integración | 6 pruebas / 100 aserciones aprobadas nuevamente |
| `php artisan route:list --except-vendor` | 12 rutas cargadas correctamente |
| GET `/api/meta/whatsapp/webhook` | Ruta confirmada |
| POST `/api/meta/whatsapp/webhook` | Ruta confirmada |
| `git status` | Árbol limpio y rama sincronizada |

Este incremento valida localmente el reto GET de Meta, la firma `X-Hub-Signature-256`, el cliente Graph API propio, la resolución por Phone Number ID, el descubrimiento de grupos oficiales desactivados por defecto, la autorización explícita, el descarte de pegatinas y la idempotencia del núcleo sobre SQL Server.

La siguiente validación debe hacerse contra la plataforma oficial de Meta con un número de prueba: callback HTTPS público, alta del webhook, suscripción del WABA al aplicativo y recepción de un evento real. No se utilizarán bibliotecas de WhatsApp Web ni BSP/SaaS intermediarios.

## 15/10/2026 — puerta local completa aprobada

Validación ejecutada en Windows con PHP 8.4.7 y una instancia desechable Microsoft SQL Server 2022 Developer en Docker.

| Comprobación | Resultado |
|---|---|
| `composer validate --strict` | Aprobado |
| `composer install` | Aprobado; lock instalable en la plataforma |
| Sintaxis PHP sobre `app`, `database`, `routes` y `tests` | Sin errores |
| `php vendor/laravel/pint/builds/pint --test` | 51 archivos aprobados |
| PHPUnit unidad + funcional | 18 pruebas / 55 aserciones aprobadas |
| `php artisan route:list --except-vendor` | 6 rutas cargadas correctamente antes de los incrementos posteriores |
| `php artisan view:cache` | Aprobado |
| `npm install` | 0 vulnerabilidades reportadas |
| `npm run build` | Vite 8.3.4 aprobado; aviso opcional de `fontaine` no bloqueante |
| Conexión Laravel → SQL Server | `mensajero_test` confirmada |
| `phpunit.integracion.xml` | 3 pruebas / 39 aserciones aprobadas |
| Repetición de integración | 3 pruebas / 39 aserciones aprobadas nuevamente |

El entorno confirmó `mbstring`, `sqlsrv`, `pdo_sqlsrv` 5.13.3 y Microsoft ODBC Driver 18. La base utilizada fue exclusivamente `mensajero_test`; las pruebas mantienen una protección que exige el sufijo `_test` antes de migrar.

También se corrigió `config/app.php` para respetar `APP_TIMEZONE=America/Mexico_City` en vez de fijar UTC.

### Estado de GitHub Actions

`.github/workflows/validacion.yml` continúa presentando `startup_failure` antes de crear jobs. La validación local demuestra que el código supera la puerta ejecutable, pero el CI debe repararse de forma independiente revisando permisos/runner a nivel GitHub.

## 15/10/2026 — refactorización en español y revisión multidisciplinaria

Antes de disponer del entorno local completo se ejecutaron comprobaciones aisladas de sintaxis, un arnés de captura con 9/9 casos aprobados, auditoría de referencias tras renombres y validación estructural del YAML de CI. Esas evidencias quedan como antecedentes; las certificaciones posteriores las sustituyen para el estado actual.

Nunca ejecutar migraciones de desarrollo contra la base corporativa productiva.
