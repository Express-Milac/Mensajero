# Decisión de integración con WhatsApp

Fecha inicial: 15/10/2026. Actualizada: 16/10/2026.

## Canal oficial

Mensajero mantiene integración directa con Meta/WhatsApp Cloud API, sin BSP/SaaS intermediario. Este es el canal preferido siempre que el flujo requerido esté expuesto oficialmente por Meta.

No se utilizarán Baileys, WPPConnect, whatsapp-web.js ni automatizaciones similares con el **número principal de MILAC**.

## Limitación detectada

Los grupos normales ya existentes de la operación no son equivalentes al modelo oficial Groups API y pueden superar sus límites. Por ello Cloud API no satisface por sí sola el requisito central de capturar automáticamente esos grupos actuales.

## Excepción controlada para POC

MILAC dispone de un número corporativo secundario y un teléfono dedicados. El 16/10/2026 se autorizó evaluar un recolector aislado para esos grupos normales bajo estas condiciones:

- el número es secundario, dedicado y reemplazable;
- nunca se vincula el número principal de MILAC;
- el componente se autohospeda por MILAC;
- no se usa para campañas ni envío masivo;
- empieza únicamente en grupos de prueba;
- debe tener cola durable, idempotencia y registro de huecos de integridad;
- no entra a grupos operativos reales hasta certificar multimedia, carga, reinicios y reconciliación;
- el riesgo de suspensión del número recolector se acepta conscientemente porque el mecanismo no es una API oficial de Meta.

La especificación completa vive en `docs/recolector-whatsapp-dedicado.md`.

## Lo que se conserva

El núcleo sigue siendo independiente del canal de entrada:

- SQL Server y tablas de Mensajero;
- lista permitida;
- eventos crudos;
- idempotencia;
- auditoría;
- almacenamiento privado;
- contrato interno HMAC;
- Meta Cloud API oficial;
- trazabilidad hacia IA futura.

## Criterio de seguridad

La existencia del POC no reduce las restricciones del canal oficial. Ningún secreto, sesión o token se almacena en Git. La sesión del recolector es un secreto operativo y el número principal de MILAC queda fuera de esa arquitectura.
