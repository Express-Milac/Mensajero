# Allowlist

## Regla

`default deny`.

Un chat descubierto nace con `is_monitored = false` e `is_active = true`.

Solo `is_monitored && is_active` permite persistir contenido. Un sticker se descarta incluso
si el chat está autorizado. Un tipo desconocido se conserva si el chat sí está autorizado.

La identidad de un chat es `connection_id + provider_chat_id`; el nombre visible o interno no
forma parte de la clave y puede cambiar.

## Estado implementado

`mensajero_monitored_chats` contiene ID proveedor, tipo, nombres, monitoreo, actividad,
`authorization_version` y first/last seen.

`InboundMessageGate` aplica la política antes de RAW. `CaptureIncomingWhatsappEvent` vuelve a
consultar la fila dentro de una transacción con bloqueo para evitar autorizar un mensaje usando
un estado obsoleto.

## Pendiente

- descubrimiento real desde gateway;
- UI para activar/desactivar;
- auditoría append-only;
- incremento de `authorization_version`;
- endpoint mínimo para refrescar allowlist del gateway;
- permisos por grupo cuando se integre login.
