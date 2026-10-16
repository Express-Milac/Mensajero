# Decisión: número recolector dedicado para grupos normales de WhatsApp

Fecha: 16/10/2026.

## Motivo

El requisito central de Mensajero es captar automáticamente los mensajes de 5–6 grupos normales ya existentes, con más participantes de los que admite el modelo oficial Groups API. Cloud API permanece disponible para los flujos oficiales compatibles, pero no resuelve por sí sola esos grupos operativos existentes.

MILAC dispone de un **número corporativo secundario y un teléfono dedicados**. Ese número se utilizará como participante recolector y nunca como número principal de atención o identidad comercial.

## Arquitectura aprobada para POC

1. El número recolector se registra en WhatsApp/WhatsApp Business en el teléfono corporativo.
2. Se agrega únicamente a grupos controlados de prueba durante la validación.
3. El componente `recolector-whatsapp/` se vincula como dispositivo multidispositivo mediante Baileys.
4. Los grupos se descubren, pero nacen no monitoreados.
5. Solo los grupos autorizados por Mensajero se aceptan localmente.
6. Cada mensaje autorizado se escribe primero en una cola durable local.
7. Laravel confirma la recepción después de persistir el evento crudo y su buzón transaccional.
8. Solo después del ACK se elimina la tarea local.
9. Reinicios y desconexiones generan periodos de integridad no confirmada.

## Riesgo aceptado

Baileys no es un cliente oficial de Meta. Existe riesgo de limitación o suspensión de la cuenta recolectora. El riesgo se aísla mediante un número secundario reemplazable; **queda prohibido vincular el número principal de MILAC** a este componente.

Este POC no se declara apto para producción ni para decisiones directivas hasta certificar multimedia, carga, reinicios, recuperación, huecos y reconciliación.

## Criterio de integridad

Mensajero no prometerá una imposibilidad técnica de pérdida absoluta. La meta es **cero pérdida silenciosa**: cuando no pueda demostrarse continuidad de captura, el sistema debe registrar el intervalo como integridad no confirmada y excluirlo de métricas directivas hasta reconciliarlo.

## Multimedia

El incremento inicial conserva el evento y metadatos de foto, video, audio y documento, pero todavía no persiste el binario. La descarga durable del archivo en el recolector es requisito obligatorio antes de incorporar grupos operativos reales.
