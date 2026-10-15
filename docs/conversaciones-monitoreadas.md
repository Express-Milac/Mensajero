# Conversaciones monitoreadas

Mensajero utiliza una lista blanca con denegación por defecto. Una conversación descubierta empieza con `esta_monitoreada = false`; descubrirla no autoriza guardar su contenido.

La identidad real es la combinación conexión + ID de conversación del proveedor. El nombre del grupo es solo metadata y puede cambiar.

## Reglas

- Aceptar únicamente conversaciones `esta_monitoreada = true` y `esta_activa = true`.
- Adhesivos se ignoran incluso si la conversación está autorizada.
- Desactivar una conversación conserva evidencia histórica y detiene nuevas capturas.
- Reactivar no importa automáticamente mensajes pasados.
- Los trabajos asíncronos deberán volver a comprobar autorización cuando puedan consumir recursos sensibles.
- Cambios de monitoreo deberán quedar auditados cuando exista la administración visual.
- Nunca usar IDs reales hardcodeados en código o pruebas.

Pruebas obligatorias: monitoreada acepta; no monitoreada, desconocida e inactiva no crean evidencia ni bandeja de salida; adhesivo nunca crea contenido.
