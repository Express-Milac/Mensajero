# Contrato interno HMAC — v1

Este contrato queda reservado para integraciones entre servicios propios de MILAC. Ya no representa el transporte oficial desde WhatsApp: los eventos reales de WhatsApp entran directamente por `/api/meta/whatsapp/webhook` y se autentican con la firma oficial de Meta.

## Rutas internas existentes

- `POST /api/pasarela/v1/chats-descubiertos`: sincroniza metadatos desde un servicio interno autorizado.
- `POST /api/pasarela/v1/eventos`: entrega un evento neutral al núcleo de captura.

Estas rutas no deben configurarse como webhook de Meta.

## Encabezados obligatorios

- `X-Mensajero-Id`: identificador único de 16 a 128 caracteres.
- `X-Mensajero-Marca-Tiempo`: Unix timestamp en segundos.
- `X-Mensajero-Firma`: `sha256=<hexadecimal de 64 caracteres>`.

La ventana predeterminada es de 300 segundos y se configura con `MENSAJERO_VENTANA_FIRMA_SEGUNDOS`.

## Construcción de la firma

1. Serializar el cuerpo JSON final exactamente como se enviará.
2. Calcular `SHA256(cuerpo)`.
3. Construir `v1\n<ID>\n<TIMESTAMP>\n<SHA256(cuerpo)>`.
4. Calcular HMAC-SHA256 con `WHATSAPP_PASARELA_SECRETO`.
5. Enviar `X-Mensajero-Firma: sha256=<resultado>`.

La protección antirrepetición se conserva en `mensajero_solicitudes_pasarela`.

## Diferencia con Meta

Meta usa su propio esquema oficial: `X-Hub-Signature-256` calculado sobre el cuerpo crudo con el App Secret. No se traduce ni sustituye por `X-Mensajero-Firma`.

En ambos casos, autenticar el origen no concede permiso de negocio: `mensajero_chats_monitoreados.es_monitoreado` sigue siendo la autoridad para capturar o descartar.
