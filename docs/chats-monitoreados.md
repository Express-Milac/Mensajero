# Chats monitoreados

La política es **denegar por defecto**.

Un chat descubierto nace con `es_monitoreado = false` y `es_activo = true`. Solo `es_monitoreado && es_activo` permite persistir contenido. Una pegatina se descarta incluso cuando el chat está autorizado.

La identidad es `conexion_id + id_chat_proveedor`; los nombres pueden cambiar y no forman parte de la clave.

La tabla `mensajero_chats_monitoreados` contiene identificador del proveedor, tipo, nombres, estado de monitoreo, actividad, `version_autorizacion` y fechas de primera/última observación.

Pendiente: descubrimiento real, pantalla de activación/desactivación, auditoría de cambios, incremento de versión de autorización y sincronización mínima con la pasarela.
