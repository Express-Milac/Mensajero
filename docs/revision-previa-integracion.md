# Revisión previa a integración — 15/10/2026

Esta revisión aplica los agentes del directorio `agentes/` al estado actual de la rama de trabajo.

## Arquitectura
Resultado: conforme con observaciones pendientes.

- Se conserva SQL Server corporativo y prefijo `mensajero_`.
- Las tablas, columnas y estados propios de dominio usan español.
- Eventos crudos y buzón de salida continúan dentro de una sola transacción.
- La captura mantiene lista permitida por defecto denegada y no depende de Redis/Gemini.
- La ubicación de archivos sigue siendo configurable y fuera de SQL Server.
- La autenticación queda separada del dominio para permitir la integración corporativa futura.

Pendiente: certificar migraciones contra SQL Server `_test` y construir la pasarela real de WhatsApp.

## Revisión de código
Hallazgos corregidos:
- El tablero dereferenciaba un usuario inexistente cuando la autenticación estaba deshabilitada; ahora usa un estado interno seguro.
- El comando para crear administradores validaba contra una tabla incorrecta; ahora usa el modelo `Usuario`.
- `name` y marcas de tiempo propias del usuario fueron sustituidas por `nombre`, `creado_en` y `actualizado_en`.
- El controlador y la vista de autenticación fueron movidos de `Auth`/`auth` a `Autenticacion`/`autenticacion`.
- Pruebas, rutas, configuración, modelos y migraciones quedaron alineados con la nomenclatura vigente.
- La auditoría del árbol no encontró referencias antiguas ni archivos temporales.

Excepciones técnicas documentadas: identificadores exigidos por Laravel para autenticación/sesión, como `email`, `password`, `remember_token`, `user_id`, `payload` y equivalentes del contrato del framework.

## Pruebas
Ejecutado en esta revisión:
- `php -l` sobre el incremento y correcciones posteriores: sin errores de sintaxis reportados.
- Arnés aislado de la lógica de captura: 9/9 comprobaciones aprobadas.
- Auditoría estática de referencias tras renombres: limpia.

La validación automática de GitHub se intentó por `push` y `pull_request`, pero terminó en `startup_failure` antes de crear jobs, incluso después de eliminar la acción externa de preparación de PHP. Por tanto, PHPUnit, Pint, rutas y compilación de vistas todavía no están certificados por CI.

El contenedor local disponible no incluye `mbstring`; el runtime de Mensajero debe incluir esa extensión.

No ejecutado/certificado todavía: migraciones ni pruebas contra SQL Server `_test`.

## Seguridad
Hallazgos corregidos:
- El modo `corporativo` continúa fallando cerrado.
- El modo temporal `deshabilitado` permite el tablero interno, pero no concede nivel administrador ni expone `/estado`.
- Los diagnósticos no devuelven mensajes internos ni secretos.
- La lista permitida se aplica antes de almacenar eventos crudos.
- Las pegatinas se ignoran antes de persistencia.

Condición de despliegue: el modo sin autenticación solo puede existir detrás de controles de red internos. Antes de una exposición no controlada debe integrarse identidad corporativa.

## UI/UX
Hallazgos corregidos:
- El tablero funciona sin usuario autenticado.
- Se muestra el aviso “Acceso interno temporal” cuando corresponde.
- Rutas principales, estructura propia de autenticación y textos visibles están en español.
- La interfaz mantiene “Sin conectar” mientras no exista una sesión real de WhatsApp.

## Dictamen
**No integrar todavía a producción.** El refactor y la revisión multidisciplinaria están aplicados, pero falta la puerta ejecutable completa: Composer, PHPUnit, Pint, rutas/vistas y, especialmente, migraciones/pruebas contra SQL Server `_test`. Después de aprobarla puede continuar el descubrimiento y selección de grupos.
