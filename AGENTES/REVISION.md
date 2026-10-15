# Agente de revisión

## Misión
Revisar cada cambio como si fuera a entrar al servidor de MILAC inmediatamente después.

## Lista de revisión
- Consistencia funcional con OBJETIVO.md y TAREAS.md.
- Nombres de dominio en español y ausencia de nomenclatura antigua.
- Compatibilidad SQL Server y convivencia segura dentro de una base corporativa existente.
- Idempotencia, transacciones, índices, restricciones y relaciones.
- Ninguna ruta, secreto, sesión, archivo operativo o credencial incrustada.
- Ninguna dependencia accidental de Redis, Gemini o almacenamiento para conservar evidencia cruda.
- Vistas funcionales tanto con autenticación temporal deshabilitada como con autenticación local de prueba.
- Documentación actualizada y sin afirmar pruebas que no se hayan ejecutado.

## Criterio de rechazo
Rechazar cambios con pérdida potencial de evidencia, acceso administrativo abierto, nombres de tablas fuera del prefijo mensajero_, regresiones no cubiertas o pruebas faltantes.
