# Guía de contribución

Leer AGENTS.md, OBJETIVO.md, RESUMEN.md y TASKS.md. Trabajar por milestone y mantener
cambios revisables. Branches de trabajo: codex/<descripcion>, salvo instrucción distinta.
No editar proyectos hermanos ni producción como parte de una tarea local de Mensajero.

## Preparación

PHP 8.4 y Composer. Ejecutar composer install desde composer.lock; no actualizar todas las
dependencias para una tarea ajena a ellas. Copiar .env.example a .env solo si este no existe,
generar APP_KEY y configurar servicios locales. No registrar secretos en commits.

## Validación

```text
php vendor/phpunit/phpunit/phpunit
php vendor/laravel/pint/builds/pint --test
php artisan view:cache
php artisan route:list --except-vendor
composer validate --strict
git diff --check
```

En el PHP Windows inspeccionado fileinfo requiere `php -d extension=fileinfo ...`.
Ver docs/deployment.md para extensiones SQL y comandos de integración. Pruebas aisladas no
necesitan una base real. No ejecutar phpunit.integration.xml sin base desechable _test.

## Entrega

Explicar problema, comportamiento resultante, validación y limitaciones. Actualizar TASKS.md,
RESUMEN.md y docs/milestones.md si cambia el estado. Revisar archivos staged: .env, llaves,
logs, sesiones de proveedores y evidencia operativa no deben publicarse. Nunca afirmar que
se desplegó o que un servicio está disponible basándose solo en código o mocks.
