# Mensajero

Sistema interno de MILAC para convertir mensajes de grupos seleccionados de WhatsApp Business en evidencia operativa consultable y, más adelante, en eventos interpretados por IA.

## Decisiones vigentes
- Solo se capturan los grupos expresamente autorizados; el resto de chats no consume almacenamiento ni procesamiento.
- No se usa un servicio administrado de terceros para la captura. La pasarela de WhatsApp será autohospedada por MILAC.
- SQL Server corporativo existente es el único motor operativo.
- Los archivos se almacenan fuera de SQL Server mediante una ruta configurable; la base guarda metadatos y rutas.
- Temporalmente no existe inicio de sesión corporativo. El modo `deshabilitado` sirve únicamente para acceso interno controlado y no concede funciones administrativas.

## Estado implementado
- Laravel 13 / PHP 8.4.
- Tablas de Mensajero aisladas con prefijo `mensajero_` y nombres de negocio en español.
- Conexión WhatsApp, chats monitoreados, contactos, eventos crudos, buzón de salida y mensajes originales modelados.
- Lista permitida por defecto denegada.
- Pegatinas descartadas antes de persistir.
- Captura idempotente: repetir el mismo evento no duplica evidencia.
- Evento crudo y buzón de salida se guardan en una sola transacción.
- Diagnóstico de SQL Server, Redis y almacenamiento sin exponer errores internos.
- Frontera de autenticación preparada para integración futura.
- Código propio de autenticación organizado bajo `Autenticacion` y vista bajo `autenticacion`.
- Agentes de arquitectura, creación, revisión, pruebas, seguridad, UI/UX y calidad documentados y aplicados.

Todavía no existe una sesión real de WhatsApp, descubrimiento de grupos, descarga multimedia, pasarela autohospedada ni integración con Gemini.

## Requisitos de ejecución
Además de las extensiones requeridas por Laravel y SQL Server, el runtime debe incluir `mbstring`; Mensajero utiliza funciones multibyte en normalización y acceso.

## Configuración principal
Copiar `.env.example` a `.env` y completar SQL Server/Redis. Variables propias principales:
- `MENSAJERO_MODO_AUTENTICACION=deshabilitado|local|corporativo`
- `MENSAJERO_RUTA_ALMACENAMIENTO=`
- `MENSAJERO_BYTES_LIBRES_MINIMOS=`
- `PROVEEDOR_WHATSAPP=web_autohospedado`
- `WHATSAPP_PASARELA_URL=`
- `WHATSAPP_PASARELA_SECRETO=`
- `IA_HABILITADA=false`

Con autenticación temporal deshabilitada, abrir `/tablero`. El diagnóstico administrativo web `/estado` queda bloqueado; puede usarse `php artisan mensajero:diagnostico` desde consola.

## Verificación antes de integrar al servidor
El flujo está definido en `.github/workflows/validacion.yml`, pero al 15/10/2026 GitHub Actions presenta `startup_failure` antes de crear jobs. Por ello la validación completa debe ejecutarse también en un entorno funcional:

```powershell
composer validate --strict
composer install
php vendor/phpunit/phpunit/phpunit
php vendor/laravel/pint/builds/pint --test
php artisan route:list --except-vendor
php artisan view:cache
php artisan mensajero:diagnostico
```

Las pruebas de integración SQL deben ejecutarse únicamente contra una base desechable cuyo nombre termine en `_test`, usando `phpunit.integracion.xml`. No ejecutar migraciones de desarrollo contra la base corporativa productiva.

La lógica aislada de captura fue comprobada con 9/9 casos; esto no sustituye PHPUnit completo ni la certificación SQL Server. Ver `docs/verificacion.md`.

## Continuidad
Leer `AGENTS.md`, `OBJETIVO.md`, `RESUMEN.md`, `TASKS.md`, `docs/revision-previa-integracion.md` y `agentes/`.
