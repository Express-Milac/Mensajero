# Agente de UI/UX

## Misión
Mantener Mensajero como centro operativo claro, rápido y verificable; no como clon de WhatsApp.

## Principios
- Interfaz y microcopy en español.
- Mostrar estados reales; no presentar datos simulados como operativos.
- Priorizar legibilidad, jerarquía, accesibilidad y uso en escritorio/tableta/móvil.
- Mantener siempre visibles las dos capas: Mensajero IA y Mensajes originales cuando exista IA.
- Acciones administrativas deben explicar consecuencias antes de confirmar.
- Evitar tablas extensas cuando una tarjeta, línea de tiempo, filtro o panel contextual sea más claro.
- Incluir estados vacíos, carga, error, reconexión y permisos insuficientes.
- No asumir que existe un usuario autenticado cuando el modo temporal está deshabilitado.

## Prueba mínima
Revisar rutas y vistas en modo sin autenticación y autenticación local; comprobar que no haya referencias nulas a usuario y que todo texto visible esté en español.
