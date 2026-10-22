# Arquitectura de Mensajero

Mensajero es un monolito modular Laravel/PHP con SQL Server corporativo como motor operativo y Redis para trabajo asíncrono. Dispone de dos canales de entrada desacoplados: integración oficial Meta Cloud API para los flujos soportados oficialmente y un recolector aislado para el POC de grupos normales existentes.

## Flujo oficial
Meta/WhatsApp Cloud API → webhook firmado → lista permitida → evento crudo + buzón de salida en SQL Server → procesamiento posterior.

## Flujo de grupos normales
Número recolector corporativo dedicado → sesión multidispositivo aislada → `recolector-whatsapp` → cola durable local → contrato HMAC interno → lista permitida → evento crudo + buzón de salida.

El número principal de MILAC no se vincula al recolector no oficial. El recolector solo se habilita con un número secundario reemplazable y comienza como POC.

## Integridad
La lista permitida se evalúa antes de persistir eventos en Laravel. El recolector mantiene además una copia local de la lista permitida para descartar grupos no autorizados antes de transmitirlos.

Cada mensaje autorizado se persiste primero en una cola local en disco. Si Laravel, SQL Server o la red no responden, la tarea permanece y se reintenta. La idempotencia del núcleo evita duplicar evidencia.

Las desconexiones y reinicios no limpios se registran en `mensajero_periodos_integridad_whatsapp`. Un intervalo con integridad no confirmada no debe presentarse como completo en reportes directivos.

`CapturarEventoWhatsappEntrante` bloquea la fila de `mensajero_chats_monitoreados`, verifica autorización, deduplica y crea el evento crudo y el mensaje del buzón de salida dentro de una misma transacción. Redis y Gemini quedan fuera de esa transacción.

## Datos
Tablas principales: `mensajero_conexiones_whatsapp`, `mensajero_chats_monitoreados`, `mensajero_contactos_whatsapp`, `mensajero_eventos_crudos_whatsapp`, `mensajero_buzon_salida`, `mensajero_mensajes_whatsapp` y `mensajero_periodos_integridad_whatsapp`.

Los archivos no se guardan como binarios en SQL Server. Se usa almacenamiento privado configurable y la base conserva metadatos/rutas.

## Acceso
`MENSAJERO_MODO_AUTENTICACION=deshabilitado` permite temporalmente el tablero ordinario dentro de una red controlada, pero no funciones administrativas. `corporativo` falla cerrado hasta implementar la identidad MILAC.


## Fase 2 — Canalización Gemini comprobada (22/10/2026)

Después del commit de normalización SQL Server, y **fuera de la ruta crítica de captura**, se comprueba la política Gemini. Si está habilitada la ejecución automática en .env, el mensaje es **nuevo, normalizado, texto**, pertenece a grupo activo/monitoreado expresamente autorizado y supera la fecha de corte, se encola AnalizarMensajeGeminiTrabajo en **mensajero_ia**. Esta cola se atiende **separadamente** de mensajero_normalizacion.

El cliente HTTPS Google envía solo texto limpiado e identificadores internos del mensaje objetivo por defecto (MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO=true), nunca JID ni multimedia. El modelo devuelve un único evento JSON sustentado por mensajes fuente. El validador PHP verifica estructura, categoría, longitudes, referencias y mensaje objetivo antes de registrar el análisis. Los resultados residen en **mensajero_analisis_operativos_ia**: pendiente_revision o sin_evento, con tokens, modelo, resumen, fecha UTC y evidencias; los originales permanecen intactos.

La operación continua exige .env autorizado, trabajador IA, supervisor de procesos y política de Google aprobada; **no basta establecer una variable en una terminal ni actualizar GitHub**. La primera prueba real #74 y su visualización se verificaron el 22/10/2026. Los acuerdos detectados no son confirmados sin evidencia explícita y toda decisión requiere revisión humana.

**Límites conocidos:** el disparo IA posterior al commit no tiene aún un buzón transaccional propio para recuperar todos los fallos de publicación; múltiples reintentos pueden consumir varias llamadas de Google aunque solo se permita una fila resultado por mensaje. No existen topes internos de costo ni aprobación humana auditable. No declarar lista esta fase para producción.

Ver [Instrucciones exactas de Gemini](canalizacion-ia.md) y [Operación permanente](operacion-gemini-permanente.md).
