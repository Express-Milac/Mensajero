# Tareas de continuidad

Estado: 16/10/2026. `[x]` significa código entregado; `[x]` con resultados explícitos significa además validado en ese alcance. Nada equivale todavía a certificación productiva.

## Base entregada y validada
- [x] Arquitectura SQL Server, Redis y almacenamiento privado configurable.
- [x] Lista permitida, descarte de pegatinas e idempotencia.
- [x] Administración/auditoría de grupos.
- [x] Contrato HMAC interno.
- [x] Meta Cloud API oficial directa.
- [x] Pint base: 78 archivos.
- [x] PHPUnit base: 32 pruebas / 78 aserciones.
- [x] SQL Server base: 6 pruebas / 100 aserciones, repetidas dos veces.

## Decisión actual para grupos normales
- [x] Usar un número corporativo secundario dedicado y reemplazable como recolector.
- [x] Prohibido vincular el número principal de MILAC al recolector no oficial.
- [x] Mantener Meta Cloud API como canal oficial independiente.
- [x] Documentar el riesgo y el criterio de cero pérdida silenciosa.

## Incremento actual: Recolector WhatsApp POC
- [x] Directorio separado `recolector-whatsapp/`.
- [x] Baileys fijado a 6.7.24 para reproducir la versión previamente probada en el entorno local.
- [x] QR y sesión fuera de Git.
- [x] Descubrimiento periódico de grupos.
- [x] Lista permitida local con refresco desde Laravel.
- [x] Solo grupos `@g.us`; chats privados no entran por este canal.
- [x] Pegatinas descartadas.
- [x] Cola local durable antes de transmisión.
- [x] Reintento sin eliminar tareas hasta ACK.
- [x] Estado de conexión y periodos de integridad.
- [x] Detección de reinicio no limpio mediante latido local.
- [x] Comando `mensajero:registrar-conexion-recolector`.
- [x] Migración `mensajero_periodos_integridad_whatsapp`.
- [x] Pruebas TypeScript para firma, clasificación y cola durable agregadas.
- [x] Prueba SQL Server del contrato recolector/integridad agregada.
- [x] `npm install`: 99 paquetes auditados y 0 vulnerabilidades reportadas en la validación actual.
- [x] `npm run verificar`: TypeScript aprobado con el parche de recepción, watchdog corregido y clasificación protobuf corregida.
- [x] `npm run pruebas`: 9/9 pruebas aprobadas, incluida limpieza de temporales huérfanos y regresión `stickerMessage:null + conversation:texto`.
- [x] `phpunit.integracion.xml`: 7 pruebas / 119 aserciones aprobadas.
- [x] Integración SQL Server repetible: 7 pruebas / 119 aserciones aprobadas dos veces consecutivas sobre `mensajero_test`.
- [x] `route:list`: 15 rutas cargadas, incluidas chats autorizados, estado, eventos e integridad.
- [x] Conexión `recolector-pruebas` registrada en `mensajero_test` con ID local 10014.
- [x] Secreto HMAC compartido entre Laravel y recolector configurado localmente y verificado sin exponerlo.
- [x] Número recolector dedicado vinculado; nunca se vinculó el número principal de MILAC.
- [x] Grupos reales del número recolector descubiertos en `/grupos`.
- [x] Grupo de prueba `Mensajero milac Prueba` autorizado como único grupo monitoreado del recolector.
- [x] Detectado fallo de Baileys 6.7.24: sesión/grupos sanos pero sin `messages.upsert` entregado a la aplicación.
- [x] Agregado parche local reproducible para tratar `offline="0"` como mensaje en línea; `postinstall` falla cerrado si la dependencia cambia.
- [x] Agregado watchdog de recepción: si el socket abre pero no hay confirmación de buffer ni `messages.upsert` en 30 s, se marca error de integridad.
- [x] Corregida carrera del watchdog para no rearmarlo después de `confirmacion_buffer_inicial`.
- [x] Agregado marcador positivo `Flujo de mensajes de WhatsApp confirmado.` cuando la recepción queda efectivamente habilitada.
- [x] Agregada telemetría sanitizada de lotes `messages.upsert`: recibidos, candidatos de grupo y normalizados, sin JID ni contenido.
- [x] Agregado `npm run diagnosticar-cola` para probar tareas pendientes contra Laravel sin borrarlas ni mostrar secretos.
- [x] Confirmado que `/estado-conexion` es aceptado por Laravel; el diagnóstico no elimina la tarea por diseño.
- [x] Sesión vinculada regenerada desde cero; el primer `515` se recuperó con reconexión y la nueva sesión confirmó `confirmacion_buffer_inicial`.
- [x] Agregado `npm run drenar-cola` para transmitir, recibir ACK y retirar explícitamente tareas durables antiguas.
- [x] `npm run drenar-cola` validado con `pendientesRestantes: 0`; una ejecución posterior confirmó que no había tareas pendientes.
- [x] Confirmado que los seis archivos residuales observados eran temporales `.tmp`, no tareas JSON; limpieza automática de temporales huérfanos agregada y validada.
- [x] Logs internos crudos de Baileys silenciados para no exponer JID/nodos cifrados; Mensajero conserva sus logs sanitizados.
- [x] Primer mensaje real de grupo alcanzó `messages.upsert`: `recibidos=1`, `candidatos_grupo=1`, `normalizados_grupo=1`.
- [x] Detectado bug de clasificación protobuf: un mensaje de texto real fue marcado como `sticker` porque los campos presentes con valor `null` se evaluaban mediante `in`.
- [x] Corregida la clasificación para exigir valores no nulos/no indefinidos y agregada prueba de regresión `stickerMessage:null + conversation:texto`.
- [x] Primer mensaje de texto real confirmado de extremo a extremo: cola durable → HMAC → Laravel → SQL Server; conteo del grupo pasó de 0 a 1 y la cola quedó en 0 tras ACK.
- [x] Lote normal de 10 mensajes de texto confirmado sin pérdida: SQL pasó de 1 a 11, los 10 fueron clasificados como `texto`, normalizados como grupo y la cola terminó con 0 tareas JSON.
- [x] Recuperación tras caída de Laravel validada con 5 mensajes: SQL permaneció en 11 mientras Laravel estaba fuera y, tras restaurarlo, subió a 16; la cola final quedó en 0.
- [x] Cola durable durante caída de Laravel certificada de forma instrumentada con 2 mensajes: con Laravel apagado quedaron exactamente 2 tareas `/api/pasarela/v1/eventos` en disco; al restaurar Laravel se recibieron ambos POST, SQL pasó de 16 a 18 y la cola volvió a 0.
- [x] Reinicio abrupto del recolector certificado: antes había 0 periodos `reinicio_no_confirmado`; tras matar los procesos Node a la fuerza, esperar 10 s y reiniciar sin nuevo QR, se creó exactamente 1 periodo para la conexión 10014 (`id=5`) con intervalo `2026-10-16 18:49:44.846000` → `2026-10-16 18:50:27.933000`; recepción confirmada y cola final en 0.
- [x] Recuperación offline del recolector certificada con 2 mensajes enviados mientras `principal.ts` estaba totalmente detenido: no quedó ningún proceso Node del recolector, al reiniciar sin nuevo QR ambos mensajes fueron entregados inmediatamente por WhatsApp/Baileys como `notify`, clasificados como `texto`, persistidos en cola durable y entregados a SQL; el conteo pasó de 18 a 20, los periodos `reinicio_no_confirmado` de 1 a 2 y la cola final quedó en 0. El periodo se conserva como no confirmado aunque los mensajes observados hayan sido recuperados, para mantener el criterio de cero pérdida silenciosa.

## Incremento multimedia: certificación básica real completada
- [x] Descarga diferida de foto/video/audio/documento: Baileys expone la fuente y la descarga solo se inicia después de validar que el grupo está autorizado.
- [x] Descarga mediante `downloadMediaMessage` con `reuploadRequest`, para permitir recuperación de URLs multimedia expiradas cuando WhatsApp lo soporte.
- [x] Almacenamiento privado configurable en el recolector mediante `RECOLECTOR_RUTA_ALMACENAMIENTO`; por defecto comparte `storage/app/mensajero` con Laravel durante el POC local.
- [x] Persistencia atómica de multimedia: archivo temporal privado → `fsync` → renombrado final.
- [x] Cálculo de SHA-256 y tamaño durante la escritura; ruta relativa determinística fuera de SQL Server.
- [x] Nueva tabla `mensajero_archivos_whatsapp`: SQL conserva ruta, hash, tamaño, MIME y metadatos; nunca el binario.
- [x] Laravel verifica existencia, ruta privada, tamaño y SHA-256 antes de registrar los metadatos multimedia.
- [x] Contrato `v2-recolector` entrega por HMAC los metadatos/ruta junto con el evento y conserva el ACK de la cola durable.
- [x] Si la descarga falla o la fuente multimedia está ausente, se registra un periodo de integridad específico en vez de ocultar la falla.
- [x] Pruebas automatizadas agregadas para almacenamiento durable/reutilización del archivo y para validación SQL Server del contrato multimedia.
- [x] Validación automática del recolector posterior al incremento: `npm run verificar` aprobado; `npm run pruebas` 11/11, incluidas persistencia multimedia durable y reutilización idempotente; `npm install` reportó 0 vulnerabilidades.
- [x] Migración `2026_10_16_000700_crear_archivos_whatsapp` aplicada sobre `mensajero_test`; Pint aprobó 87 archivos; PHPUnit normal 32/32 pruebas y 78 aserciones; integración SQL Server 8/8 pruebas y 132 aserciones, incluida `MultimediaRecolectorSqlServerTest`.
- [x] Foto real certificada de extremo a extremo: eventos SQL 20→21 y archivos SQL 0→1; `foto` `image/jpeg`, 84,355 bytes, SHA-256 registrado, ruta privada `multimedia/whatsapp/recolector-pruebas/...jpg`, estado `verificado`, archivo físico localizable por `mensajero_local=true` y cola final con 0 tareas JSON.
- [x] Audio real certificado de extremo a extremo: eventos SQL 21→22 y archivos SQL 1→2; `audio` `audio/ogg; codecs=opus`, 11,474 bytes, extensión `ogg`, ruta privada, estado `verificado`; el archivo físico existe y su SHA-256 recalculado coincide exactamente con `hash_sha256` de SQL; cola final con 0 tareas JSON.
- [x] Documento real certificado de extremo a extremo: eventos SQL 22→23 y archivos SQL 2→3; `documento` `application/pdf`, nombre original `_1731519625.pdf`, 234,588 bytes, extensión `pdf`, ruta privada, estado `verificado`; el archivo físico existe y su SHA-256 recalculado coincide exactamente con `hash_sha256` de SQL; cola final con 0 tareas JSON.
- [x] Video real certificado de extremo a extremo: eventos SQL 23→24 y archivos SQL 3→4; `video` `video/mp4`, 2,306,688 bytes, extensión `mp4`, ruta privada y estado `verificado`; tamaño SQL y tamaño físico coinciden exactamente, el SHA-256 recalculado del archivo coincide con `hash_sha256` de SQL y la cola final quedó en 0 tareas JSON.
- [x] Caída de Laravel con multimedia ya persistida certificada en la ejecución local: con Laravel fuera, el recolector descargó y dejó el archivo físico durable antes del envío, mantuvo exactamente el evento `/api/pasarela/v1/eventos` pendiente, y SQL no avanzó; al restaurar Laravel el evento se drenó, SQL avanzó en un evento y un archivo, la cola volvió a 0 y el archivo conservó tamaño, SHA-256 y marca de modificación, demostrando que no fue reescrito ni descargado de nuevo.
- [x] Reinicio abrupto del recolector alrededor de una recepción multimedia certificado: con `principal.ts` completamente detenido se envió una foto al grupo de prueba; al reiniciar sin nuevo QR, WhatsApp/Baileys recuperó el mensaje, Mensajero persistió el archivo multimedia, SQL avanzó exactamente un evento y un archivo, se creó un nuevo periodo `reinicio_no_confirmado`, tamaño/hash físico coincidieron con SQL y la cola de `/api/pasarela/v1/eventos` terminó en 0.
- [x] Implementado reintento durable previo a la descarga multimedia: antes de acceder al archivo, el mensaje autorizado se persiste en `datos/descargas-multimedia/pendientes`; la tarea conserva `contenido_crudo` serializable y puede reconstruirse con `BufferJSON.reviver`. Solo se confirma después de persistir el archivo y colocar el evento en la cola HMAC; si el proceso cae tras escribir el archivo pero antes de encolar el evento, el reintento reutiliza el archivo determinístico existente sin descargarlo otra vez.
- [x] Agregadas pruebas automatizadas para persistencia entre instancias y deduplicación de la cola durable de descargas multimedia.
- [x] Validación local del reintento durable aprobada: `npm run verificar` completó sin errores y `npm run pruebas` aprobó 13/13, incluidas persistencia entre instancias y deduplicación de descargas multimedia.
- [x] Reintento durable certificado con fallo real controlado: se forzó una raíz de almacenamiento inválida y una foto quedó en `datos/descargas-multimedia/pendientes`; tras restaurar el almacenamiento, el recolector reconstruyó el mensaje y recuperó la foto (`recuperada=true`, 98,259 bytes) sin reenviarla. Un override temporal todavía activo hizo que el archivo quedara inicialmente en una raíz distinta y Laravel respondió HTTP 422; el evento permaneció durable en `datos/cola/pendientes`. Tras alinear la raíz física compartida, el mismo evento se drenó sin reenviar ni redescargar: SQL pasó de 24→25 eventos y 4→5 archivos, cola de eventos quedó en 0, estado `verificado`, tamaño físico=SQL 98,259 y SHA-256 `9014ec38e6f2744b7cd55151a552e7be1ce83619ca17edf0ebbf8fff00954104` coincidió exactamente.
- [x] Reforzada la configuración del recolector: al arrancar informa si `RECOLECTOR_RUTA_ALMACENAMIENTO` proviene de un override explícito o del valor predeterminado, sin exponer secretos; `.env.example` recalca que Laravel y recolector deben compartir la misma raíz física y que variables temporales de PowerShell se heredan por sesión.
- [x] Validación posterior al refuerzo de configuración: `git pull` aplicado sin conflictos; `npm run verificar` aprobado y `npm run pruebas` mantiene 13/13 pruebas, 0 fallos, 0 omitidas y 0 canceladas.

## Bloqueadores restantes antes de grupos reales
- [ ] Probar citas/respuestas, mensajes editados, eliminados, ubicaciones, contactos y tipos desconocidos.
- [ ] Prueba de carga >= 1,000 mensajes y varios remitentes/grupos.
- [ ] Prueba prolongada de estabilidad y reconexión.
- [ ] Diseñar reconciliación independiente para intervalos con integridad no confirmada.

## Después de certificar captura
- [ ] Normalizador idempotente y buzón → Redis.
- [ ] Vista de mensajes originales y estado de integridad por grupo/periodo.
- [ ] Integración al servidor MILAC.
- [ ] Fase 2: Gemini con trazabilidad exacta hacia mensajes fuente.

## Regla de despliegue
No ejecutar migraciones de desarrollo contra producción. Usar una base `_test` durante el POC. No agregar el número recolector a grupos operativos reales hasta completar multimedia y las pruebas de integridad.
