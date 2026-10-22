# Mensajero MILAC — Fase 2: piloto Gemini (grupo 10020)

## Alcance de este incremento

- Analizar únicamente mensajes **normalizados de texto**, de un grupo autorizado y recibidos a partir de una fecha de corte explícita.
- Utilizar un máximo de seis mensajes del mismo grupo como contexto; el último es el evento objetivo.
- Nunca transmitir números de WhatsApp, remitentes, archivos adjuntos ni el JSON crudo. El texto se limita y se aplican filtros de teléfonos, correos e identificadores; estos filtros **no garantizan eliminar toda información confidencial**.
- Gemini devuelve JSON estructurado, validado de nuevo en PHP. Todo evento propuesto queda como **pendiente_revision** y vinculado a identificadores de mensajes normalizados (desde ellos se accede a la evidencia original).
- Si no hay acontecimiento nuevo, registrar **sin_evento** para no volver a pagar por el mismo mensaje.
- SQL Server guarda los resultados en la tabla nueva \`mensajero_analisis_operativos_ia\`; **no modifica eventos crudos ni mensajes normalizados**.
- Una llamada fallida a Gemini no revierte capturas. La cola IA \`mensajero_ia\` está separada de la de normalización.
- Ejecución manual simulada por defecto. La publicación automática tras normalizar existe, pero está cerrada por configuración.

## Paso 1: descarga y validación (sin enviar datos a Google)

Desde la raíz de Mensajero:

\`\`\`powershell
git fetch origin fase-2-gemini-eventos-operativos-20261021
# Recuperar sólo los archivos de esta fase; revisar primero "git status"
php artisan test --filter GeminiContratoTest
php artisan migrate:status
\`\`\`

Antes de aplicar migraciones, verificar en cuál base corporativa está configurada la conexión; hacer respaldo de SQL Server. Aplicar solo esta migración:

\`\`\`powershell
php artisan migrate --path=database/migrations/2026_10_21_235959_crear_analisis_operativos_ia.php
\`\`\`

Esta migración **crea únicamente una tabla nueva** (con índice único por mensaje y índice por grupo/fecha). No ejecutar \`migrate:fresh\`, \`db:wipe\` ni borrar datos para preparar IA.

## Paso 2: simulación local segura

Configurar en \`.env\` de Laravel las siguientes variables, sin activar envíos:

\`\`\`dotenv
MODELO_GEMINI=gemini-3.5-flash-lite
IA_HABILITADA=false
MENSAJERO_IA_ENVIO_AUTORIZADO=false
MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO=false
MENSAJERO_IA_GRUPOS_AUTORIZADOS=10020
MENSAJERO_IA_FECHA_INICIO=2026-10-21T20:00:00-06:00
MENSAJERO_COLA_IA=mensajero_ia
MENSAJERO_IA_EJECUCION_AUTOMATICA=false
MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO=true
CLAVE_API_GEMINI=
\`\`\`

**La fecha anterior es un ejemplo, no una fecha que debas copiar sin comprobar**. Colocar una fecha de corte real posterior al inicio del piloto, con zona horaria; ningún mensaje anterior se seleccionará. Recomendación: iniciar con mensajes ficticios nuevos.

Después:

\`\`\`powershell
php artisan config:clear
php artisan mensajero:analizar-gemini --grupo=10020 --limite=2
\`\`\`

La simulación no envía mensajes a Google ni publica trabajos en Redis. Si no aparece ningún mensaje elegible, comprobar que los datos del grupo ya fueron normalizados; no habilitar IA ni repetir la captura para compensarlo.

## Paso 3: piloto externo autorizado

**No ejecutar hasta tener aprobación de uso de datos corporativos, un proyecto de Gemini API con facturación activa y pruebas locales satisfactorias.** Según los términos de Google, la cuota gratuita puede usarse para mejorar sus servicios. Un proyecto de pago ofrece reglas diferentes; confirmar requisitos contractuales, ubicación y retención antes de cargar contenido real.

Una vez aprobados, y **únicamente para datos ficticios del grupo 10020**, colocar en el \`.env\`:

\`\`\`dotenv
CLAVE_API_GEMINI=<clave de un proyecto con facturación aprobada>
IA_HABILITADA=true
MENSAJERO_IA_ENVIO_AUTORIZADO=true
MENSAJERO_IA_SERVICIO_PAGADO_CONFIRMADO=true
MENSAJERO_IA_EJECUCION_AUTOMATICA=false
MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO=true
\`\`\`

Limpiar configuración, mantener Redis operativo, iniciar en una terminal dedicada:

\`\`\`powershell
php artisan queue:work redis --queue=mensajero_ia --tries=3 --timeout=45
\`\`\`

En otra terminal:

\`\`\`powershell
php artisan mensajero:analizar-gemini --grupo=10020 --mensaje-id=<ID_DE_UN_MENSAJE_FICTICIO_NUEVO> --limite=1 --aplicar
\`\`\`

Ver resultados en \`http://127.0.0.1:8000/mensajes/ia\`. Cada evento cita enlaces a evidencia original de MILAC. \`--aplicar\` encola trabajos; la comunicación externa sucede en el trabajador \`mensajero_ia\`, no en la terminal que publica.

Solo después de comprobar que las pruebas, reintentos e idempotencia funcionan, se puede evaluar \`MENSAJERO_IA_EJECUCION_AUTOMATICA=true\`. Este ajuste despacha nuevos mensajes recién normalizados, nunca mensajes anteriores a la fecha de corte. El comando manual permite recuperar publicaciones fallidas.

### Regla de privacidad para el primer ensayo integral

- **No usar los mensajes históricos #25 y #70**: su procedencia operativa no está confirmada como ficticia.
- Capturar un mensaje nuevo **100 % ficticio** dentro de un grupo de pruebas autorizado; localizar su ID interno tras normalizarse y seleccionarlo con `--mensaje-id=ID`.
- El comando exige `--mensaje-id` cuando lleva `--aplicar`, para que el operador elija exactamente qué enviar.
- `MENSAJERO_IA_SOLO_MENSAJE_OBJETIVO=true` (predeterminado) impide transmitir los mensajes anteriores del grupo como contexto; nunca cambiar esta bandera a false sin aprobar explícitamente los datos y la fecha de corte.
- El trabajador IA debe iniciar con una configuración SSL que verifique una autoridad certificadora válida (en Windows puede proporcionarse un `cacert.pem` verificado a través de `curl.cainfo` y `openssl.cafile`); **nunca usar `verify=false`**.
- La clave Google puede compartir cuota y facturación con otros proyectos: limitar la prueba a un solo mensaje.

## Condiciones para producción

- Resolver los pendientes de Redis del buzón sin asumir que se perdieron; la prueba anterior mostraba 45 avisos sin publicar.
- Certificar que la captura y su normalización funcionan con el trabajador correspondiente.
- Validar contractualmente privacidad, confidencialidad, límites de acceso a IA y retención de datos en Google.
- Medir costos y limitar la concurrencia; primera etapa: solo un trabajador IA y límite de cinco mensajes por invocación del comando.
- Verificar al menos: duplicados, contenido malicioso en mensajes, referencias falsas, errores 429/5xx, caídas del trabajador, desactivación de un grupo entre lectura y escritura, y respuestas vacías.
- Añadir revisión/aprobación editorial de eventos antes de convertirlos en tareas oficiales o indicadores operativos.
- Mantener secretos fuera de Git y no registrar cuerpos de las peticiones a Gemini.
