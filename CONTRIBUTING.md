# Contribuir a Mensajero

## Antes de modificar
1. Leer `AGENTS.md`, `OBJETIVO.md`, `RESUMEN.md`, `TASKS.md` y `agentes/`.
2. Confirmar el hito vigente y preservar cambios ajenos.
3. Mantener la nomenclatura de negocio en español.

## Flujo mínimo
1. Arquitectura valida límites y contratos.
2. Creación implementa el incremento completo.
3. Revisión busca referencias rotas y defectos.
4. Pruebas ejecuta y registra evidencia real.
5. Seguridad verifica exposición, secretos y permisos.
6. UI/UX revisa flujo y textos.

## Validación
Para cambios que no requieren SQL Server real:

```bash
composer validate --strict
composer install
php vendor/phpunit/phpunit/phpunit
php vendor/laravel/pint/builds/pint --test
php artisan route:list --except-vendor
php artisan view:cache
```

Si GitHub Actions termina antes de crear jobs, el resultado no cuenta como ejecución de estas pruebas.

Los cambios de esquema o canal de captura deben probarse además con `phpunit.integracion.xml` exclusivamente contra una base SQL Server desechable cuyo nombre termine en `_test`.

## Reglas de seguridad
- No guardar secretos, sesiones WhatsApp, payloads privados ni binarios operativos en Git.
- No ejecutar migraciones de desarrollo contra producción.
- No conectar el número empresarial como efecto secundario de una entrega de código.
- El runtime debe disponer de `mbstring` y extensiones SQL Server necesarias.

## Entrega
Actualizar `RESUMEN.md`, `TASKS.md`, `docs/verificacion.md` y, cuando corresponda, `docs/revision-previa-integracion.md`. Distinguir siempre lo implementado de lo realmente certificado.
