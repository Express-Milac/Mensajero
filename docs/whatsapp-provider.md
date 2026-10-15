# ADR: proveedor WhatsApp

Actualizado: 2026-10-15.

## Contexto confirmado

- El número usa WhatsApp Business.
- Es compartido con otros chats no relacionados con Mensajero.
- Los grupos operativos que interesan ya existen y tienen al menos ~30 participantes.
- Negocio no acepta un proveedor/SaaS de terceros para capturar WhatsApp.
- El servicio se alojará en infraestructura propia de MILAC.

## Problema

Necesitamos escuchar 5–6 grupos existentes sin procesar el resto del número. La Groups API
oficial no debe asumirse compatible con ese escenario; antes de depender de ella tendría que
demostrarse que puede consumir exactamente los grupos existentes, tamaño y coexistencia real.

## Opciones

### API oficial / Groups API

Ventajas: soporte oficial, webhooks y contrato del proveedor.

Desventaja crítica: no existe evidencia de que pueda adjuntarse libremente a estos grupos
existentes de ~30+ personas. No diseñar el producto alrededor de una capacidad no demostrada.

### Proveedor administrado

Descartado por decisión empresarial: Mensajero debe ser un sistema propio y la captura de
WhatsApp no dependerá de un SaaS intermedio.

### Gateway WhatsApp Web autohospedado

Decisión de trabajo. Servicio Node.js/TypeScript operado por MILAC, usando una librería
compatible con WhatsApp Web/multi-device detrás de la interfaz `WhatsAppProvider`.

Una librería open-source es una dependencia técnica local, no un servicio externo. Aun así,
este mecanismo no es una integración oficial y tiene riesgos de cambios de protocolo,
desconexión o medidas sobre la cuenta. Por ello la librería concreta debe ser reemplazable.

## Contrato

`WhatsAppProvider` deberá ofrecer conceptualmente: estado, grupos, metadata/participantes,
recepción, normalización mínima, descarga de media bajo demanda y reconexión.

El gateway no contiene reglas de transporte, Gemini, eventos operativos ni SQL empresarial.

## Regla de seguridad

No vincular el número empresarial durante desarrollo inicial. Antes se debe demostrar con una
sesión controlada: grupos, texto, citas, foto, audio, video, documento, IDs estables, reinicio,
desconexión/reconexión, descarte de chats ignorados y deduplicación.

La sesión y llaves del gateway viven fuera de Git.
