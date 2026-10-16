# Proveedor de WhatsApp

Mensajero utilizará integración oficial directa con Meta / WhatsApp Business Platform Cloud API.

## Permitido

- APIs y webhooks oficiales de Meta/WhatsApp.
- Graph API.
- código PHP/Laravel desarrollado por MILAC para autenticación, adaptación, almacenamiento, filtros, normalización y procesamiento.
- infraestructura propia de MILAC para Mensajero, SQL Server, Redis y archivos.

## No permitido

- Baileys.
- WPPConnect.
- whatsapp-web.js.
- automatización de WhatsApp Web o del protocolo privado.
- BSP/SaaS intermediario cuando la integración pueda realizarse directamente con Meta.

## Flujo

```text
WhatsApp / Meta Cloud API
        ↓ webhook oficial firmado
Mensajero Laravel
        ↓
lista permitida
        ↓
evento crudo + buzón SQL Server
        ↓
normalización / Redis / IA
```

Los grupos normales existentes en WhatsApp Business App no se consideran automáticamente accesibles desde Cloud API. La funcionalidad de grupos se implementará únicamente donde la Groups API oficial de Meta sea elegible y compatible con el proceso de MILAC.
