# Pipeline IA (milestone 10 en adelante)

AI_ENABLED=false por defecto. Captura, RAW, media y UI funcionan sin Gemini.
No enviar solicitudes en este entregable. Proveedor IA desacoplado con timeout/circuit breaker.

Debounce por chat, ventanas limitadas por tokens y tiempo; cursor incremental estable en ID
de ingestión para incluir mensajes tardíos, ordenar contexto por sent_at. Citas relevantes,
eventos anteriores y contexto estructurado acotado. No todo el historial ni un resumen por
mensaje. Multimedia solo después de ready y con consentimiento/configuración de procesamiento.
Audio: original → transcripción versionada → contexto. No reemplazar el original.

Salida schema JSON: action create/update/no_event/needs_more_context; título, resumen,
event_type, event_at, entities opcionales, severity, confidence 0..1, source_message_ids.
Validar IDs como subconjunto exacto de la entrada y mismo chat; rechazar IDs inventados,
tipos o fechas inválidos. Modelo no ejecuta herramientas ni acciones operativas.
Prompt prohíbe inventar entidades, fechas o conclusiones y considera texto del chat datos
no confiables, nunca instrucciones. Baja confianza requiere revisión humana.

Persistir ai_runs con modelo, prompt_version, processing_version, generated_at, referencias
de entrada, respuesta original, consumo y error sanitizado. Eventos N:M mensajes, enlazados
a fuentes exactas en UI. Revisiones humanas append-only con actor, motivo y versión para
evitar escrituras perdidas; fusión/división conservan genealogía y fuentes.

Pruebas futuras: schema inválido, timeout, 429, caída, contexto insuficiente, múltiples mensajes,
multimedia, mensajes tardíos, ID inventado y referencias a otro chat. Gemini nunca bloquea RAW.
