# Mensajero — instrucciones para agentes

Este archivo es la entrada técnica estándar para agentes de IA que continúen el proyecto. Todo el trabajo de dominio se realiza en español.

## Lectura obligatoria

Leer en este orden:

1. `README.md`
2. `OBJETIVO.md`
3. `RESUMEN.md`
4. `TAREAS.md`
5. `docs/nomenclatura.md`
6. los perfiles especializados de `AGENTES/`
7. la documentación técnica aplicable al cambio

## Agentes especializados

Antes de dar por terminado un incremento, aplicar las listas de revisión de:

- `AGENTES/CREACION.md`
- `AGENTES/REVISION.md`
- `AGENTES/PRUEBAS.md`
- `AGENTES/SEGURIDAD.md`
- `AGENTES/UI_UX.md`

No son procesos autónomos en producción: son responsabilidades y controles obligatorios que deben aplicarse al código y dejar evidencia en pruebas/documentación.

## Decisiones vigentes

- WhatsApp Business con grupos existentes grandes.
- Puerta de enlace WhatsApp autohospedada por MILAC; sin proveedor SaaS de captura.
- Laravel/PHP 8.4+, SQL Server, Redis, Flysystem y Blade/Livewire.
- Las tablas viven en una base SQL Server corporativa existente y usan prefijo `mensajero_`.
- Tablas, columnas, clases, estados, rutas visibles y documentación propia en español.
- Acceso temporal sin login; las acciones administrativas se restringen por IP hasta integrar autenticación corporativa.
- Lista blanca por defecto denegado antes de evidencia cruda, multimedia e IA.
- Adhesivos se ignoran.
- Evidencia cruda y bandeja de salida se confirman en una misma transacción antes de considerar durable el evento.
- Redis, Gemini y almacenamiento de multimedia no condicionan la persistencia inicial de evidencia cruda.
- Archivos fuera de SQL Server.

## Protocolo de trabajo

1. Revisar el estado de Git y las tareas vigentes.
2. Modificar únicamente lo necesario para el incremento actual.
3. Añadir o actualizar pruebas que cubran éxito y fallos.
4. Ejecutar la validación disponible y registrar resultados reales.
5. Revisar seguridad, UI/UX, nomenclatura y secretos.
6. Actualizar documentación si cambió una decisión o estado.
7. No desplegar ni tocar producción como efecto secundario de publicar código.

## Criterio de preparación para servidor

No considerar Mensajero listo para desplegar hasta que PHPUnit, Pint, vistas, rutas, Composer y la integración SQL Server temporal estén en verde y no haya fallos de seguridad o nomenclatura pendientes.
