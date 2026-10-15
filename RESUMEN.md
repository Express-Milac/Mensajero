# Estado y traspaso a otra IA

Actualizado: 15/10/2026.

## Decisiones confirmadas
WhatsApp Business; 5–6 grupos seleccionados; grupos existentes de 30+ participantes; captura autohospedada por MILAC; SQL Server corporativo existente; servidor propio; autenticación corporativa pospuesta.

## Implementado
- Dominio, migraciones, rutas principales, estados y pruebas con nomenclatura de negocio en español.
- Tablas de captura: `mensajero_conexiones_whatsapp`, `mensajero_chats_monitoreados`, `mensajero_contactos_whatsapp`, `mensajero_eventos_crudos_whatsapp`, `mensajero_buzon_salida` y `mensajero_mensajes_whatsapp`.
- Usuarios preparados en `mensajero_usuarios` con columnas propias `nombre`, `rol`, `creado_en` y `actualizado_en`; se conservan solo identificadores técnicos exigidos por Laravel donde corresponde.
- Lista permitida por defecto denegada y descarte de pegatinas.
- `CapturarEventoWhatsappEntrante` persiste evento crudo + buzón de salida de forma transaccional e idempotente.
- Modo temporal sin autenticación para tablero ordinario; diagnóstico administrativo bloqueado en ese modo.
- Modo corporativo falla cerrado.
- Almacenamiento privado configurable fuera de SQL Server.
- Autenticación propia organizada bajo `Autenticacion`/`autenticacion`, sin directorios `Auth` propios.
- Agentes de arquitectura, creación, revisión, pruebas, seguridad, UI/UX y calidad documentados en `agentes/`.
- Flujo de validación automática definido en `.github/workflows/validacion.yml`.

## Hallazgos corregidos en la revisión actual
- El tablero ya no falla cuando no existe usuario autenticado.
- La creación de administradores valida contra el modelo/tabla correctos.
- Las pruebas antiguas fueron alineadas con clases, rutas y tablas nuevas.
- El modo sin autenticación ya no concede acceso al diagnóstico administrativo web.
- Se completó la traducción de `name` y de la estructura propia `Auth`.
- No quedaron referencias antiguas ni archivos temporales en el árbol actual.

## Validación real de esta actualización
- Sintaxis PHP revisada sin errores en el incremento y correcciones posteriores.
- Arnés aislado de lógica de captura: 9/9 comprobaciones aprobadas.
- Auditoría de referencias tras renombres: limpia.
- GitHub Actions se intentó por `push` y `pull_request`, pero terminó en `startup_failure` antes de crear jobs. PHPUnit/Pint/rutas/vistas no se consideran ejecutados por CI.
- El contenedor local disponible no incluye `mbstring`; el runtime real debe incluir esa extensión.

## Siguiente puerta obligatoria
Antes de integrar al servidor o avanzar la captura real: ejecutar Composer, PHPUnit, Pint, rutas/vistas en un entorno funcional y certificar migraciones/pruebas contra SQL Server desechable `_test`.

Después de esa puerta: descubrimiento y selección de grupos, auditoría de cambios, contrato HMAC interno y pasarela autohospedada de WhatsApp.
