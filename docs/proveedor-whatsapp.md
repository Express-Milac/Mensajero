# Decisión de integración con WhatsApp

El número usa WhatsApp Business, participa en conversaciones ajenas y solo interesan 5–6 grupos existentes que pueden tener 30 o más participantes.

No se utilizará un servicio administrado de terceros para capturar estos grupos. La decisión de trabajo es una pasarela Node.js/TypeScript autohospedada por MILAC, con una biblioteca de WhatsApp Web reemplazable detrás de un contrato neutral.

La pasarela deberá proporcionar estado, grupos, metadatos, recepción, normalización mínima, descarga bajo demanda y reconexión. No contendrá reglas de transporte, Gemini ni acceso directo a tablas corporativas.

Antes de vincular el número empresarial se probará con una sesión controlada: grupos, texto, citas, foto, audio, video, documento, identificadores, reinicio, desconexión/reconexión, descarte de chats no autorizados y deduplicación. Sesiones y llaves quedan fuera de Git.
