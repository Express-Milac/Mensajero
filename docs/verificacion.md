# Registro de validación

## 15/10/2026 — puerta local completa aprobada

Validación ejecutada en Windows con PHP 8.4.7 y una instancia desechable Microsoft SQL Server 2022 Developer en Docker.

| Comprobación | Resultado |
|---|---|
| `composer validate --strict` | Aprobado |
| `composer install` | Aprobado; lock instalable en la plataforma |
| Sintaxis PHP sobre `app`, `database`, `routes` y `tests` | Sin errores |
| `php vendor/laravel/pint/builds/pint --test` | 51 archivos aprobados |
| PHPUnit unidad + funcional | 18 pruebas / 55 aserciones aprobadas |
| `php artisan route:list --except-vendor` | 6 rutas cargadas correctamente antes del incremento de grupos |
| `php artisan view:cache` | Aprobado |
| `npm install` | 0 vulnerabilidades reportadas |
| `npm run build` | Vite 8.3.4 aprobado; aviso opcional de `fontaine` no bloqueante |
| Conexión Laravel → SQL Server | `mensajero_test` confirmada |
| `phpunit.integracion.xml` | 3 pruebas / 39 aserciones aprobadas |
| Repetición de integración | 3 pruebas / 39 aserciones aprobadas nuevamente |

El entorno confirmó `mbstring`, `sqlsrv`, `pdo_sqlsrv` 5.13.3 y Microsoft ODBC Driver 18. La base utilizada fue exclusivamente `mensajero_test`; las pruebas mantienen una protección que exige el sufijo `_test` antes de migrar.

También se corrigió `config/app.php` para respetar `APP_TIMEZONE=America/Mexico_City` en vez de fijar UTC.

### Estado de GitHub Actions

`.github/workflows/validacion.yml` continúa presentando `startup_failure` antes de crear jobs. La validación local anterior demuestra que el código sí supera la puerta ejecutable, pero el CI debe repararse de forma independiente revisando permisos/runner a nivel GitHub.

## 15/10/2026 — refactorización en español y revisión multidisciplinaria

Antes de disponer del entorno local completo se ejecutaron comprobaciones aisladas de sintaxis, un arnés de captura con 9/9 casos aprobados, auditoría de referencias tras renombres y validación estructural del YAML de CI. Esas evidencias quedan como antecedentes; la puerta local completa descrita arriba las sustituye como certificación del estado base.

## Próxima certificación

El incremento de administración de grupos agrega sincronización de chats descubiertos, auditoría versionada y cambios de lista permitida. Debe ejecutarse nuevamente Pint, PHPUnit normal, migraciones y `phpunit.integracion.xml` sobre `mensajero_test` antes de continuar con la pasarela real.

Nunca ejecutar migraciones de desarrollo contra la base corporativa productiva.
