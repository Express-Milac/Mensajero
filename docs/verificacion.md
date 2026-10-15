# Registro de validación

## 15/10/2026 — refactorización en español y revisión multidisciplinaria

### Ejecutado realmente

| Comprobación | Resultado |
|---|---|
| `php -l` sobre 41 archivos PHP del incremento inicial | Sin errores de sintaxis |
| `php -l` tras correcciones de seguridad y nomenclatura | Sin errores de sintaxis |
| Arnés aislado de lógica de captura | 9/9 comprobaciones aprobadas |
| Auditoría del árbol tras renombres | Sin referencias a `Controllers/Auth`, `views/auth`, `_tmp` ni `.keep` |

El arnés aislado comprobó: aceptación de chat autorizado, rechazo por lista permitida, rechazo de chat inactivo/desconocido, descarte de pegatinas, conservación de tipos futuros autorizados y comportamiento de deduplicación/hash.

### Validación automática en GitHub

Se agregó `.github/workflows/validacion.yml` para ejecutar Composer, sintaxis, PHPUnit, Pint, rutas y compilación de vistas. Se intentó ejecutar tanto por `push` como por `pull_request`, pero GitHub Actions respondió `startup_failure` antes de crear cualquier `job`. También se eliminó la dependencia de una acción externa de preparación de PHP y el resultado siguió siendo el mismo.

Por lo anterior, el fallo observado no certifica ni desaprueba PHPUnit/Pint: esas capas **no llegaron a ejecutarse** en GitHub Actions.

### Limitación del contenedor local

El PHP disponible para el arnés aislado no incluía `mbstring`. La lógica se probó con un sustituto mínimo únicamente dentro del arnés. El runtime real de Mensajero debe incluir `mbstring`; no se modificó el código productivo para ocultar esa dependencia.

### Aún pendiente de certificación

- `composer validate` / `composer install` en un entorno funcional.
- PHPUnit completo de `tests/Unidad` y `tests/Funcional`.
- Pint.
- `php artisan route:list --except-vendor`.
- `php artisan view:cache`.
- Migraciones y `phpunit.integracion.xml` contra SQL Server desechable cuyo nombre termine en `_test`.

No ejecutar migraciones de desarrollo contra la base corporativa productiva.

## 13/10/2026 — resultado histórico

Se habían reportado 9 pruebas/41 aserciones, Pint aprobado y vistas/rutas correctas para el código de aquel momento. Ese resultado histórico no certifica la refactorización del 15/10/2026.
