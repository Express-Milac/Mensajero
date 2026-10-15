# Revisión previa a integración — 15/10/2026

Esta revisión aplica los agentes del directorio `agentes/` al estado actual de la rama de trabajo.

## Arquitectura
Resultado: conforme con observaciones pendientes.

- Se conserva SQL Server corporativo y prefijo `mensajero_`.
- Las tablas y columnas de dominio fueron traducidas al español.
- Eventos crudos y buzón de salida continúan dentro de una sola transacción.
- La captura mantiene lista permitida por defecto denegada y no depende de Redis/Gemini.
- La ubicación de archivos sigue siendo configurable y fuera de SQL Server.

Pendiente: certificar migraciones contra SQL Server `_test` y construir la pasarela real de WhatsApp.

## Revisión de código
Hallazgos corregidos:
- El tablero dereferenciaba un usuario inexistente cuando la autenticación estaba deshabilitada; ahora usa un estado anónimo interno seguro.
- El comando para crear administradores validaba contra una tabla incorrecta; ahora usa el modelo `Usuario`.
- Pruebas, rutas, configuración y modelos quedaron alineados con la nueva nomenclatura.
- Se retiraron pruebas antiguas que dependían de clases eliminadas.

## Pruebas
Ejecutado en esta revisión:
- `php -l` sobre 41 archivos PHP del incremento: sin errores de sintaxis.
- Validación adicional de los archivos modificados por el hallazgo de seguridad: sin errores de sintaxis.

No ejecutado en este entorno y, por tanto, no certificado todavía: Composer/PHPUnit, Pint, compilación de vistas, listado de rutas, migraciones y pruebas contra SQL Server `_test`.

## Seguridad
Hallazgos corregidos:
- El modo `corporativo` continúa fallando cerrado.
- El modo temporal `deshabilitado` permite el tablero interno, pero ya no concede implícitamente nivel administrador ni expone `/estado`.
- Los diagnósticos no devuelven mensajes internos ni secretos.
- La lista permitida se aplica antes de almacenar eventos crudos.
- Las pegatinas se ignoran antes de persistencia.

Condición de despliegue: el modo sin autenticación solo puede existir detrás de controles de red internos. Antes de una exposición no controlada debe integrarse identidad corporativa.

## UI/UX
Hallazgos corregidos:
- El tablero funciona sin usuario autenticado.
- Se muestra el aviso “Acceso interno temporal” cuando corresponde.
- Rutas principales y textos visibles están en español.
- La interfaz mantiene “Sin conectar” mientras no exista una sesión real de WhatsApp.

## Dictamen
**No integrar todavía a producción.** El código queda mejor preparado y revisado, pero falta la certificación ejecutable con dependencias completas y SQL Server `_test`. Una vez aprobada esa puerta, puede continuar el hito de descubrimiento y selección de grupos.
