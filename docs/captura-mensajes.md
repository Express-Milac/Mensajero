# Captura de mensajes

## Contrato interno

La puerta de enlace convierte el evento externo a `EventoEntranteWhatsapp`, con identificador de conexión, conversación, tipo de evento, tipo de mensaje, identificadores externos, contenido crudo, fecha de recepción y versión del contrato.

## Secuencia durable

```text
Evento recibido
    ↓
Identificar conversación
    ↓
Evaluar lista blanca y adhesivo
    ↓
[ignorado] → detener procesamiento
    ↓ aceptado
Bloquear autorización en SQL
    ↓
Comprobar deduplicación
    ↓
Guardar evidencia cruda + bandeja de salida
    ↓
COMMIT
    ↓
Normalización asíncrona
```

La evidencia cruda y la bandeja de salida se insertan en una misma transacción. Un reintento del mismo evento devuelve estado duplicado y no crea una segunda copia.

## Respuestas esperadas de la futura API interna

- aceptado durable;
- duplicado durable;
- ignorado por lista blanca/adhesivo;
- contrato inválido;
- autenticación inválida;
- error temporal de SQL, que obliga a reintentar.

No se envían binarios/base64 dentro del evento principal y nunca se procesa Gemini antes de persistir la fuente.
