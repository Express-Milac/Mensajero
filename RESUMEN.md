# Estado y traspaso a otra IA

Actualizado: 15/10/2026.

## Decisiones confirmadas
WhatsApp Business; 5–6 grupos seleccionados; grupos existentes de 30+ participantes; captura autohospedada por MILAC; SQL Server corporativo existente; servidor propio; autenticación corporativa pospuesta.

## Implementado
- Dominio, migraciones, rutas principales, estados y pruebas con nomenclatura de negocio en español.
- Tablas de captura: `mensajero_conexiones_whatsapp`, `mensajero_chats_monitoreados`, `mensajero_contactos_whatsapp`, `mensajero_eventos_crudos_whatsapp`, `mensajero_buzon_salida` y `mensajero_mensajes_whatsapp`.
- Lista permitida por defecto denegada y descarte de pegatinas.
- `CapturarEventoWhatsappEntrante` persiste evento crudo + buzón de salida de forma transaccional e idempotente.
- Modo temporal sin autenticación para tablero ordinario; diagnóstico administrativo bloqueado en ese modo.
- Modo corporativo falla cerrado.
- Almacenamiento privado configurable fuera de SQL Server.
- Agentes de arquitectura, creación, revisión, pruebas, seguridad, UI/UX y calidad documentados en `agentes/`.

## Hallazgos corregidos en la revisión actual
- El tablero ya no falla cuando no existe usuario autenticado.
- La creación de administradores valida contra el modelo/tabla correctos.
- Las pruebas antiguas fueron alineadas con clases, rutas y tablas nuevas.
- El modo sin autenticación ya no concede acceso al diagnóstico administrativo web.

## Validación real de esta actualización
`php -l` fue ejecutado sobre 41 archivos PHP del incremento y no reportó errores de sintaxis. No se afirma haber ejecutado PHPUnit, Pint, vistas ni migraciones SQL Server en este entorno.

## Siguiente puerta obligatoria
Antes de integrar al servidor o avanzar la captura real: instalar dependencias, ejecutar PHPUnit/Pint/rutas/vistas y certificar migraciones/pruebas contra SQL Server desechable `_test`.

Después de esa puerta: descubrimiento y selección de grupos, auditoría de cambios, contrato HMAC interno y pasarela autohospedada de WhatsApp.
