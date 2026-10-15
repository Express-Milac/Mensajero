# Guía de contribución

Leer `AGENTS.md`, `OBJETIVO.md`, `RESUMEN.md`, `TASKS.md`, `docs/revision-previa-integracion.md` y los criterios de `agentes/`. Trabajar por hitos y mantener cambios revisables. Las ramas de trabajo usan `codex/<descripcion>` salvo instrucción distinta.

## Preparación
PHP 8.4 y Composer. Instalar desde `composer.lock`; no actualizar dependencias sin necesidad. Copiar `.env.example` a `.env`, generar `APP_KEY` y configurar servicios de desarrollo. Nunca registrar secretos.

## Validación
```text
php vendor/phpunit/phpunit/phpunit
php vendor/laravel/pint/builds/pint --test
php artisan view:cache
php artisan route:list --except-vendor
composer validate --strict
git diff --check
```

Para SQL Server usar únicamente una base desechable `_test` y `phpunit.integracion.xml`. Revisar `docs/despliegue.md` y `docs/verificacion.md`.

## Entrega
Explicar problema, comportamiento resultante, validación realmente ejecutada y limitaciones. Actualizar `TASKS.md`, `RESUMEN.md` y el hito correspondiente si cambia el estado. Revisar que `.env`, llaves, registros, sesiones de proveedores y evidencia operativa no entren al cambio.

No afirmar despliegue, disponibilidad o aprobación de pruebas basándose únicamente en código, simulaciones o pruebas no ejecutadas.
