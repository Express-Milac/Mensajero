# Guía de contribución

Leer `AGENTS.md`, `OBJETIVO.md`, `RESUMEN.md`, `TAREAS.md`, `docs/nomenclatura.md` y los perfiles de `AGENTES/` antes de modificar el sistema.

## Reglas de desarrollo

- Trabajar por incrementos pequeños y revisables.
- Usar español en dominio, tablas, columnas, clases, rutas visibles, estados y documentación propia.
- Conservar términos técnicos externos únicamente cuando sean necesarios para compatibilidad.
- No registrar secretos, sesiones de WhatsApp, evidencia operativa ni archivos multimedia en Git.
- No desplegar, migrar producción ni vincular el número empresarial como efecto secundario de un cambio.

## Validación mínima

```text
php artisan test
php vendor/laravel/pint/builds/pint --test
php artisan view:cache
php artisan route:list --except-vendor
composer validate --strict
```

Las pruebas de SQL Server se ejecutan únicamente contra una base desechable terminada en `_test`:

```text
php artisan test --configuration=phpunit.integration.xml
```

La validación continua de GitHub también levanta un SQL Server temporal para ejecutar estas pruebas.

## Entrega

Toda entrega debe explicar problema, solución, pruebas realmente ejecutadas, limitaciones y siguiente paso. No afirmar que una integración está certificada si se usaron dobles o si la prueba fue omitida.
