# Registro de validación

## 2026-10-15 — decisiones empresariales + base allowlist/RAW

Validación ejecutada en esta actualización:

| Comando | Resultado |
|---|---|
| `php -l` sobre todos los PHP nuevos/modificados | Sin errores de sintaxis |

No fue posible clonar el repositorio dentro del contenedor de ejecución porque ese entorno no
tenía resolución de red hacia GitHub. Por ello **no se afirma** haber ejecutado Composer,
PHPUnit, Pint, migraciones ni conexión SQL Server en este cambio.

Se añadieron tests unitarios/feature y SQL opt-in para esquema, allowlist, idempotencia y
captura RAW. Las pruebas de integración deben correr exclusivamente contra SQL Server `_test`.

## 2026-10-13 — base inicial

Resultado histórico: 9 pruebas/41 assertions, Pint aprobado, vistas/rutas correctas y Composer
válido. Ese resultado corresponde al commit inicial y no certifica el código del 15/10/2026.
