# Canales de WhatsApp de Mensajero

Mensajero no depende de un único mecanismo de entrada. El dominio recibe eventos normalizados y mantiene separadas las decisiones de cada canal.

## 1. Meta Cloud API oficial

Se conserva la integración directa con Meta para flujos soportados oficialmente. Usa Graph API, webhooks y validación de firma oficial. No interviene un BSP/SaaS adicional.

## 2. Recolector de grupos normales — POC aislado

Para los grupos normales existentes que Cloud API no expone de la forma requerida, se autorizó un POC con un número corporativo secundario dedicado.

El recolector:

- vive en `recolector-whatsapp/`;
- se autohospeda por MILAC;
- usa una sesión multidispositivo separada;
- descubre grupos;
- consulta la lista permitida de Mensajero;
- descarta grupos no autorizados y stickers;
- persiste primero en cola durable local;
- entrega por contrato HMAC;
- registra periodos de integridad no confirmada.

Baileys no es oficial de Meta y puede implicar riesgo de suspensión. Por esa razón está prohibido vincular el número principal de MILAC; solo se utiliza el número recolector secundario y reemplazable.

## Regla de producción

El POC no es una fuente directiva certificada mientras no exista descarga durable de multimedia y pruebas aprobadas de carga, reinicio, desconexión, reintentos y reconciliación.
