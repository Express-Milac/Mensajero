# Proveedor de WhatsApp

## Decisión

El número empresarial usa WhatsApp Business y participa en grupos existentes con 30 o más personas. MILAC no quiere depender de un proveedor/SaaS externo para la captura, por lo que Mensajero utilizará una puerta de enlace autohospedada.

El núcleo Laravel no se acoplará a una librería concreta. La puerta de enlace implementará un contrato equivalente a:

- recibir mensaje;
- consultar conversación/grupo;
- descubrir grupos;
- consultar participantes cuando sea necesario;
- descargar multimedia bajo demanda;
- consultar estado de conexión;
- normalizar datos externos hacia el contrato interno en español.

## Límites

La puerta de enlace no contiene reglas de transporte, Gemini ni construcción de eventos operativos. Su responsabilidad termina al entregar un evento mínimo autenticado al backend.

## Seguridad

- Sesión y credenciales fuera de Git.
- Servicio en infraestructura MILAC.
- Comunicación interna autenticada y posteriormente firmada con HMAC.
- Sin sincronización histórica indiscriminada.
- No descargar multimedia de conversaciones no autorizadas.
- No registrar cuerpos de mensajes ni secretos en logs.

Antes de conectar el número empresarial deberá probarse descubrimiento, recepción, citas/respuestas, multimedia, reinicio, desconexión, reconexión y deduplicación con una sesión controlada.
