# Integración oficial directa con Meta / WhatsApp Business Platform

Fecha de referencia: 16/10/2026.

Mensajero se integra directamente con la plataforma oficial de Meta. No utiliza BSP, SaaS intermediario ni bibliotecas que automaticen WhatsApp Web.

## Componentes oficiales

- WhatsApp Business Platform Cloud API.
- Graph API de Meta.
- Webhooks oficiales de Meta.
- `X-Hub-Signature-256` para autenticar cada POST.
- token de verificación definido por MILAC para el alta inicial del webhook.
- token de acceso de Meta para las llamadas salientes a Graph API.

La versión de Graph API queda configurable. El valor inicial es `v26.0`, versión vigente al momento de este incremento.

## Variables

```text
PROVEEDOR_WHATSAPP=meta_cloud_api
META_WHATSAPP_GRAPH_VERSION=v26.0
META_WHATSAPP_APP_SECRET=
META_WHATSAPP_VERIFY_TOKEN=
META_WHATSAPP_ACCESS_TOKEN=
META_WHATSAPP_WABA_ID=
META_WHATSAPP_PHONE_NUMBER_ID=
```

Nunca se registran estos secretos en Git.

## Webhook

Callback de Mensajero:

```text
GET|POST /api/meta/whatsapp/webhook
```

El GET implementa el reto de verificación de Meta. Compara `hub.verify_token` con `META_WHATSAPP_VERIFY_TOKEN` y devuelve `hub.challenge` solo cuando `hub.mode=subscribe`.

El POST exige `X-Hub-Signature-256`. Mensajero calcula HMAC-SHA256 sobre los bytes exactos del cuerpo usando `META_WHATSAPP_APP_SECRET` y compara en tiempo constante antes de interpretar JSON.

## Registro de la conexión

Antes de aceptar eventos de un número oficial hay que asociar su Phone Number ID con una conexión de Mensajero:

```bash
php artisan mensajero:registrar-conexion-meta milac NUMERO_ID --waba=WABA_ID --nombre="WhatsApp MILAC"
```

`NUMERO_ID` es el Phone Number ID de Meta, no el número telefónico visible.

## Mensajes y grupos

Los eventos `messages` se adaptan al núcleo existente. Si un mensaje oficial contiene `group_id`, Mensajero registra ese grupo como descubierto y desactivado por defecto. El servicio `CapturarEventoWhatsappEntrante` conserva la regla de lista permitida antes del evento crudo.

Los chats 1:1 no se registran automáticamente como chats monitoreados. Si no existe autorización explícita, se ignoran antes de persistir el evento crudo.

Los webhooks oficiales de administración de Groups API (`group_*`) pueden sincronizar identificadores y estado de grupos oficiales.

La Groups API oficial no debe confundirse con grupos normales preexistentes de WhatsApp Business App. La elegibilidad y límites de Meta se revisan por separado antes de migrar operación empresarial.

## Multimedia

`ClienteMetaWhatsapp` contiene llamadas directas a Graph API para consultar metadatos de medios. La descarga y persistencia física se integrarán únicamente después de comprobar que el chat está autorizado. Los binarios seguirán fuera de SQL Server.

## Seguridad

- No confiar en IP como autenticación primaria del webhook.
- Verificar siempre `X-Hub-Signature-256` sobre el cuerpo crudo.
- No registrar App Secret, Access Token ni cuerpos sensibles completos en logs.
- Una firma válida prueba origen/integridad de Meta, pero no sustituye la lista permitida de Mensajero.
- La idempotencia por `wamid` se mantiene en el núcleo de captura.
