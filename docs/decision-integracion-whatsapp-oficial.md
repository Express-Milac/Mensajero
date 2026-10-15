# Decisión de integración con WhatsApp

Fecha: 15/10/2026.

## Regla no negociable

Mensajero es un sistema empresarial propio. No se utilizarán bibliotecas, pasarelas, SaaS, BSP ni automatizaciones no oficiales que emulen WhatsApp Web o el protocolo privado de WhatsApp para capturar mensajes. Esto excluye, entre otros, Baileys, WPPConnect y whatsapp-web.js para producción y para pruebas de arquitectura que pretendan convertirse en producción.

La única dependencia externa admisible para la conexión con WhatsApp es la plataforma oficial de Meta/WhatsApp, integrada directamente por MILAC sin intermediarios adicionales.

## Estado de las opciones oficiales

- La antigua WhatsApp Business On-Premises API, que permitía hospedar el cliente oficial en infraestructura propia, quedó fuera de servicio el 23/10/2025. La vía oficial vigente es WhatsApp Business Platform Cloud API, alojada por Meta.
- Meta dispone de Groups API, pero no es equivalente a los grupos normales de WhatsApp Business App.
- Groups API exige una Official Business Account (OBA).
- Sus grupos son una experiencia por invitación y se crean/administran desde la plataforma oficial.
- El límite documentado es de 8 participantes por grupo.
- No está destinada a conectar y leer de forma arbitraria los grupos ya existentes de un número que opera en WhatsApp Business App.

## Consecuencia para Mensajero

El requisito original de capturar solo 5 o 6 grupos ya existentes de un número compartido de WhatsApp Business no debe implementarse mediante ingeniería inversa ni dispositivos vinculados automatizados.

Antes de construir el conector real hay que validar con MILAC si el flujo empresarial puede migrar a la Groups API oficial y si la cuenta/número cumple sus requisitos. Si los grupos actuales superan el límite oficial, deben permanecer como grupos normales de la aplicación y no existe una interfaz oficial soportada para que Mensajero los capture automáticamente.

## Lo que sí se conserva

El núcleo ya construido sigue siendo válido e independiente del proveedor de entrada:

- SQL Server y tablas de Mensajero.
- lista permitida y autorización por chat;
- eventos crudos;
- idempotencia;
- auditoría;
- almacenamiento privado desacoplado;
- contrato interno HMAC y protección antirrepetición como frontera entre un adaptador oficial futuro y el núcleo.

## Próximo paso permitido

1. Confirmar elegibilidad de la cuenta empresarial para WhatsApp Business Platform y Groups API oficial.
2. Confirmar tamaño y naturaleza de los grupos que MILAC necesita capturar.
3. Si el caso encaja en Groups API, construir integración directa contra Meta Cloud API y webhooks oficiales, sin BSP/SaaS ni SDK no oficial.
4. Si el caso no encaja, documentar la incompatibilidad del requisito con la interfaz oficial de WhatsApp y replantear el proceso operativo; no sustituirla por automatización no oficial.
