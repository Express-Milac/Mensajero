# Mensajero

Sistema interno de MILAC para convertir mensajes de grupos seleccionados de WhatsApp Business en evidencia operativa consultable y, más adelante, en eventos interpretados por IA.

## Decisiones vigentes
- Solo se capturan los grupos expresamente autorizados; el resto de chats no consume almacenamiento ni procesamiento persistente.
- La integración con WhatsApp será directa contra la plataforma oficial de Meta/WhatsApp, sin BSP/SaaS intermediario ni automatización no oficial de WhatsApp Web.
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
- Webhook oficial de Meta con reto GET y validación `X-Hub-Signature-256` en POST.
- Adaptación de mensajes oficiales al núcleo existente y descubrimiento de `group_id` desactivado por defecto.
- Cliente PHP propio para llamadas directas a Graph API.
- Contrato HMAC interno reservado para integraciones entre servicios MILAC.

Todavía falta realizar la primera prueba real con un número de prueba oficial de Meta, completar descarga multimedia, normalización hacia `mensajero_mensajes_whatsapp`, Redis e integración con Gemini.

## Requisitos de ejecución
Además de las extensiones requeridas por Laravel y SQL Server, el runtime debe incluir `mbstring`; Mensajero utiliza funciones multibyte en normalización y acceso.

## Configuración principal
Copiar `.env.example` a `.env` y completar SQL Server/Redis. Variables propias principales:
- `MENSAJERO_MODO_AUTENTICACION=deshabilitado|local|corporativo`
- `MENSAJERO_RUTA_ALMACENAMIENTO=`
- `MENSAJERO_BYTES_LIBRES_MINIMOS=`
- `PROVEEDOR_WHATSAPP=meta_cloud_api`
- `META_WHATSAPP_GRAPH_VERSION=v26.0`
- `META_WHATSAPP_APP_SECRET=`
- `META_WHATSAPP_VERIFY_TOKEN=`
- `META_WHATSAPP_ACCESS_TOKEN=`
- `META_WHATSAPP_WABA_ID=`
- `META_WHATSAPP_PHONE_NUMBER_ID=`
- `IA_HABILITADA=false`

Con autenticación temporal deshabilitada, abrir `/tablero`. El diagnóstico administrativo web `/estado` queda bloqueado; puede usarse `php artisan mensajero:diagnostico` desde consola.

## Webhook oficial

Meta debe configurarse con una URL HTTPS pública que apunte a:

```text
GET|POST /api/meta/whatsapp/webhook
```

Antes de registrar el webhook real, asociar el Phone Number ID con una conexión local mediante:

```bash
php artisan mensajero:registrar-conexion-meta milac PHONE_NUMBER_ID --waba=WABA_ID --nombre="WhatsApp MILAC"
```

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

## Continuidad
Leer `AGENTS.md`, `OBJETIVO.md`, `RESUMEN.md`, `TASKS.md`, `docs/integracion-meta-cloud-api.md`, `docs/revision-previa-integracion.md` y `agentes/`.
