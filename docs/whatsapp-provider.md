# ADR: proveedor WhatsApp

Fecha de análisis: 2026-10-10. Estado: decisión provisional; sin cuenta empresarial vinculada.

## Problema

Escuchar 5–6 grupos existentes usando un número compartido con otros chats. No migrar el
número ni vincular una sesión de producción como efecto de instalar el proyecto.

## Opciones, ventajas y desventajas

1. Cloud API / Groups API oficial: soporte oficial y webhooks. Su existencia no demuestra
   acceso a cualquier grupo existente. Es necesario comprobar elegibilidad y compatibilidad
   de coexistencia, tamaño de grupos, recepción y multimedia con la cuenta real.
2. Proveedor administrado: reduce operación del gateway, pero puede usar mecanismos no
   oficiales; pedir evidencia de soporte de grupos existentes y condiciones de servicio.
3. Baileys en servicio aislado: biblioteca TypeScript para WhatsApp Web. Permite explorar
   eventos de grupos con una sesión vinculada; no es integración oficial ni garantía de entrega.

## Fuentes y verificación

- [Meta Groups API](https://developers.facebook.com/documentation/business-messaging/whatsapp/groups):
  consulta directa devolvió HTTP 429; **no se certificaron sus restricciones actuales**.
- [Bird, documentación de su integración de grupos](https://bird.com/docs/guides/whatsapp/groups):
  describe grupos pequeños y límites de Meta; no acredita acceso a los grupos de esta empresa.
- [Baileys, repositorio mantenido por sus autores](https://github.com/WhiskeySockets/Baileys):
  declara explícitamente no estar afiliado ni autorizado por WhatsApp y advierte cambios
  incompatibles en la versión 7. Debe fijarse versión y probar reconexiones.

## Riesgos

Bloqueo de cuenta, pérdida de sesión, cambios de protocolo, identificación mediante LID,
multimedia expirada y huecos de entrega. La sesión vinculada puede recibir transitoriamente
chats personales: prohibir sincronización histórica persistente, logs de payloads y descargas
automáticas. Secretos de sesión cifrados y fuera de Git. QR solo administrador, corta vigencia.

## Decisión recomendada

Implementar primero contrato neutral y proveedor simulado. Evaluar Baileys en una cuenta
de prueba para grupos existentes; adoptar producción solo tras prueba de compatibilidad y
aceptación empresarial del riesgo sobre el número compartido. No implementar ahora un
adaptador Cloud que prometa capacidades no verificadas.

Puerta de salida de milestone 4: demostrar descubrimiento de grupos reales, recepción,
respuesta/cita, fotografía/audio/video/documento, reinicio, expiración de sesión, desconexión
y deduplicación; documentar mensajes omitidos y no almacenar contenido de chats ignorados.

`WhatsAppProvider`: getChat, getGroup, getGroups, getParticipants, getConnectionStatus,
receiveMessage (suscripción), normalizeMessage (mínima), downloadMedia (stream bajo demanda).
No contiene Gemini ni reglas de transporte. Servicio previsto: services/whatsapp-gateway.
