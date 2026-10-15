# Mensajero

Sistema interno de MILAC para convertir conversaciones operativas de WhatsApp Business en evidencia organizada y, posteriormente, eventos interpretados por IA que siempre puedan verificarse contra los mensajes originales.

## Principios

- WhatsApp es la fuente.
- La evidencia cruda se conserva antes de normalizar o interpretar.
- La lista blanca determina qué conversaciones consumen recursos.
- Los adhesivos se ignoran.
- SQL Server guarda datos estructurados, metadatos y rutas; los archivos viven fuera de la base.
- La bandeja de salida y la evidencia cruda se confirman en una misma transacción.
- Redis, Gemini y multimedia no condicionan la captura inicial.
- Toda la nomenclatura propia del dominio se mantiene en español.

## Decisiones vigentes

- Número: WhatsApp Business compartido con conversaciones ajenas.
- Grupos de interés: aproximadamente 5–6 grupos existentes, con 30 o más participantes.
- Captura: puerta de enlace autohospedada por MILAC; no se utilizará un proveedor SaaS de captura.
- Aplicación: Laravel 13 / PHP 8.4+.
- Datos: Microsoft SQL Server dentro de una base corporativa existente.
- Colas y caché: Redis.
- Archivos: Laravel Filesystem/Flysystem, almacenamiento privado y portable.
- Interfaz: Blade + Livewire/Alpine para las vistas operativas.
- Login: temporalmente deshabilitado; las acciones administrativas se restringen por IP hasta integrar autenticación corporativa.

## Tablas actuales

- `mensajero_usuarios`
- `mensajero_conexiones_whatsapp`
- `mensajero_conversaciones_monitoreadas`
- `mensajero_contactos_whatsapp`
- `mensajero_eventos_crudos_whatsapp`
- `mensajero_bandeja_salida`
- `mensajero_mensajes_whatsapp`
- `mensajero_migraciones`

No se crean tablas SQL para caché, sesiones o colas ordinarias porque esas responsabilidades se asignan a Redis.

## Continuidad por IA

Leer `AGENTS.md`, `OBJETIVO.md`, `RESUMEN.md`, `TAREAS.md`, `PRUEBAS.md`, `CONTRIBUCION.md`, `docs/nomenclatura.md` y los perfiles de `AGENTES/`.

## Desarrollo local

```text
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan serve --host=127.0.0.1
```

No ejecutar migraciones contra una base corporativa hasta validar primero en una base desechable terminada en `_test`.

## Validación

```text
php artisan test
php vendor/laravel/pint/builds/pint --test
php artisan view:cache
php artisan route:list --except-vendor
composer validate --strict
php artisan test --configuration=phpunit.integration.xml
```

La última orden requiere SQL Server real de pruebas. GitHub Actions tiene una validación preparada para levantar un SQL Server temporal; no se declarará la etapa como aprobada hasta que esa ejecución quede en verde.

## Estado

El núcleo de captura, lista blanca, evidencia cruda, bandeja de salida y pruebas está en refactor previo al servidor. El número empresarial de WhatsApp todavía no se conecta y no se han ejecutado migraciones productivas.
