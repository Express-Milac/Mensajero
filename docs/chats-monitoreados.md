# Chats monitoreados — política única de captura y visibilidad

Actualización: 23/10/2026.

La política es **denegar por defecto**. Todo grupo descubierto a partir de esta versión nace con es_monitoreado=false y es_visible_tablero=false. Su estado activo refleja si el origen WhatsApp lo mantiene vigente, no autoriza por sí solo capturas.

**Un grupo se considera habilitado solo si es_activo=true, es_monitoreado=true y es_visible_tablero=true.** En cualquier otro caso no entra a la lista permitida del recolector, no se acepta el contenido entrante, no se muestra en el tablero ni se envía para análisis Gemini. Las pegatinas se descartan incluso con el grupo autorizado.

El interruptor de /grupos aplica la captura y la vista juntas dentro de una transacción SQL Server con bloqueo de fila y auditoría de cambios. Apagado equivale a dejar de capturar y de mostrar; no borra mensajes históricos. No se permiten dos interruptores independientes.

Por compatibilidad la tabla mensajero_chats_monitoreados conserva dos columnas booleanas, pero ambos campos se actualizan con el mismo valor por CambiarMonitoreoChat. En registros anteriores con estados contradictorios prevalece el criterio **más restrictivo**: deben estar los dos verdaderos. No se aplica ninguna activación masiva en la base; el operador puede seleccionar manualmente cada grupo para reparar su estado bajo los permisos existentes.

La identidad de cada grupo es conexion_id + id_chat_proveedor; los nombres pueden cambiar y no forman parte de la clave. La versión de autorización aumenta al cambiar la captura; la auditoría de visibilidad conserva trazabilidad si cambia ese campo.

**La autorización IA es independiente**: habilitar la captura no habilita el envío de contenido a Google. La política Gemini exige además su lista expresa de grupos, fecha de corte, servicio pagado y permiso explícito.

Ver [Gestor de grupos](gestor-grupos.md) y [Arquitectura](arquitectura.md).
