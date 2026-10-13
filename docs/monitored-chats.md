# Allowlist

Default deny. Chat nuevo: solo metadata mínima para descubrimiento, monitored=false.
La clave real es conexión + provider_chat_id; nombre interno es etiqueta modificable.
Solo administrador cambia monitoreo/actividad; registrar antes/después, actor y fecha.
Supervisor consulta/revisa eventos; visualizador consulta. Todos los endpoints, canales,
descargas y búsquedas deben aplicar la misma política de acceso por chat.

Aceptar únicamente `is_monitored && is_active`. Filtrar stickers incluso si el chat está
autorizado. Desactivar conserva evidencia histórica, revoca nuevos trabajos y detiene
multimedia/IA pendientes al volver a comprobar autorización en cada job. Reactivar no
importa automáticamente historial. Invalidación de cache con versión y comprobación final
en SQL durante captura evita que una cache vieja autorice nuevos mensajes.

En una carrera entre desactivación y captura, ambos bloquean la fila de autorización dentro
de transacción: el orden de commit define el instante de revocación.

Pruebas de aceptación previstas: monitoreado acepta; no monitoreado, privado y desactivado
no crean RAW, mensajes, outbox ni llamadas media/IA; sticker nunca crea contenido; desconocido
de chat autorizado conserva RAW. Fixtures explícitas, nunca IDs reales hardcodeados.
