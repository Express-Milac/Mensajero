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

## Incremento semántico de mensajes: certificación real completada
- [x] Separado `tipo_evento` de `tipo_mensaje`: `mensaje`, `edicion`, `eliminacion`, `reaccion` y `otro` describen lo ocurrido; `texto`, `foto`, `ubicacion`, `contacto`, etc. describen el contenido.
- [x] El recolector extrae y conserva de forma indexable `id_remitente_proveedor`, `id_mensaje_citado_proveedor` e `id_mensaje_objetivo_proveedor`, manteniendo íntegro `contenido_crudo`.
- [x] Respuestas/citas detectan `contextInfo.stanzaId`; reacciones conservan el ID objetivo; eliminaciones `REVOKE` conservan el ID objetivo; ediciones por `protocolMessage` conservan objetivo y tipo del contenido editado.
- [x] Ubicaciones, contactos y tipos no reconocidos siguen siendo capturados; los tipos no conocidos se conservan como `tipo_mensaje=otro` en vez de descartarse.
- [x] Contrato recolector→Laravel elevado a `v3-recolector` con compatibilidad de entrada para eventos anteriores sin los nuevos campos.
- [x] Deduplicación reforzada para eventos mutables: mensajes normales conservan la identidad histórica; ediciones/eliminaciones/reacciones incorporan tipo de evento, objetivo y hash del contenido para no colisionar con el mensaje original.
- [x] Nueva migración `2026_10_16_000800_enriquecer_eventos_crudos_whatsapp`: agrega a `mensajero_eventos_crudos_whatsapp` `tipo_mensaje`, remitente, mensaje citado y mensaje objetivo, con índices de consulta. Los eventos históricos quedan `NULL`; no se inventa un backfill.
- [x] Pruebas TypeScript agregadas para citas, ediciones, eliminaciones, reacciones, ubicaciones, contactos y tipos desconocidos.
- [x] Prueba SQL Server `SemanticaRecolectorSqlServerTest` agregada: valida mensaje original + dos ediciones con el mismo ID + eliminación + respuesta citada, y que solo la repetición idéntica sea deduplicada.
- [x] Validación local del recolector: `npm run verificar` sin errores y `npm run pruebas` 16/16, 0 fallos, incluidas cita, edición, eliminación, reacción, ubicación, contacto y desconocido.
- [x] Migración `2026_10_16_000800_enriquecer_eventos_crudos_whatsapp` aplicada exclusivamente sobre `mensajero_test`; Pint aprobó 89 archivos y PHPUnit normal aprobó 32/32 pruebas con 78 aserciones.
- [x] Corregida únicamente la prueba semántica para cumplir el contrato de seguridad de `X-Mensajero-Id` (mínimo 16 caracteres), sin relajar HMAC ni middleware.
- [x] Integración SQL Server semántica validada: `SemanticaRecolectorSqlServerTest` 1/1 con 26 aserciones; suite completa `phpunit.integracion.xml` 9/9 con 158 aserciones y 0 fallos.
- [x] Primera ronda real v3 certificó respuesta/cita, eliminación, ubicación y contacto; la cita y la eliminación apuntaron al mismo mensaje original y la cola final quedó en 0. La edición sí llegó, pero Baileys 6.7.24 la expuso inicialmente como `secretEncryptedMessage` y no como edición normalizada.
- [x] Agregado listener `messages.update` para ediciones legadas y descarte explícito de `secretEncryptedMessage` como mensaje de usuario falso; la segunda prueba real confirmó que la envoltura interna dejó de contaminar SQL, pero Baileys 6.7.24 no emitió `messages.update` para la edición cifrada moderna.
- [x] Implementado backport propio para `SecretEncryptedMessage{MESSAGE_EDIT}` sin actualizar Baileys: conserva localmente y de forma durable el `messageSecret` de originales autorizados, descifra la edición con AES-GCM y normaliza el resultado como `edicion/<tipo>`. Si el descifrado falla, conserva un evento auditable `edicion_cifrada_no_descifrada` en vez de perderlo silenciosamente.
- [x] Contexto criptográfico de edición limitado a grupos autorizados, persistido fuera de Git con permisos privados y retención temporal; no duplica el contenido completo del mensaje.
- [x] Pruebas de regresión agregadas para forma real `editedMessage`, envoltura `secretEncryptedMessage`, almacenamiento durable del contexto y descifrado de ediciones cifradas.
- [x] Edición cifrada real certificada: evento original `10062` quedó `mensaje/texto`; evento `10063` quedó `edicion/texto`, con remitente y objetivo presentes. El `key.id` del original, `targetMessageKey.id`, `id_evento_externo` del original e `id_mensaje_objetivo_proveedor` de la edición coincidieron exactamente; las seis verificaciones finales de relación devolvieron `true`.
- [x] Certificación real semántica completada para respuesta/cita, edición cifrada, eliminación, ubicación y contacto en el grupo de prueba, manteniendo trazabilidad hacia el mensaje objetivo y sin tareas pendientes de eventos al final de las rondas observadas.

## Bloqueadores restantes antes de grupos reales
- [x] Prueba sintética de pasarela/SQL Server: 1,000 eventos únicos, 4 grupos, 25 remitentes, 50 reintentos deduplicados, 2,121 aserciones, 0 fallos y 47.82 eventos/s; Pint aprobado. La transacción se revirtió al finalizar.
- [x] Prueba real de 25 mensajes cancelada correctamente: el operador confirmó que nunca se enviaron. Reemplazada por comprobación breve de 3 mensajes independientes (`z91`, `z92`, `z93`) en grupo de prueba 10020, sin desconectar Internet. Los tres marcadores aparecieron en SQL Server. La consulta por `z93` devolvió 2 coincidencias, pero una era histórica (evento 10032, 16/10/2026) y la nueva era 11070 (19/10/2026): identificadores distintos, no duplicación de la prueba.
- [ ] Prueba prolongada de estabilidad y reconexión: UTC y latido HMAC ya validados; se pospone la prueba de dos horas y corte físico de Internet para la etapa previa a producción. La comprobación breve del POC no sustituye resiliencia prolongada.
- [ ] Diseñar reconciliación independiente para intervalos con integridad no confirmada.

## Corrección de diagnósticos de conectividad (19/10/2026): validar localmente
- [x] Identificada doble interpretación horaria: PHP UTC y SQL UTC coincidieron en 17:19:32; ultimo_latido_en crudo fue 15:32:10, mientras el cast Eloquent devolvió 21:32:10Z al interpretar primero America/Mexico_City. Antigüedad real al medir: 1 h 47 min 22 s.
- [x] Diagnóstico: MonitorIntegridad.latido() solo actualizaba datos/estado-ejecucion.json local; Laravel actualizaba ultimo_latido_en únicamente con estado=conectado.
- [x] Implementado App/Casts/FechaUtc en conexión, evento crudo y periodo de integridad: interpreta valores datetime sin zona de SQL Server como UTC y persiste entradas en UTC, sin modificar registros ni zona horaria global.
- [x] Agregado POST /api/pasarela/v1/latido protegido por HMAC; solo actualiza ultimo_latido_en para conexiones actualmente conectadas, sin modificar estado.
- [x] Recolector envía latido remoto cada intervalo solo si Baileys reporta conectado, además del latido local; fallos de red no crean tareas en la cola durable.
- [x] Validación local inicial: Pint 8/8, integración LatidoRecolectorSqlServerTest 1 prueba / 14 aserciones, TypeScript sin errores y recolector 21/21. La suite FechasUtcMensajeroTest detectó 1 fallo (2/3): Eloquent devolvía el objeto Carbon asignado originalmente con zona -06:00 desde su caché de casts, aunque el atributo SQL ya estaba convertido a UTC.
- [x] Corrección de caché: `FechaUtc` declara `withoutObjectCaching=true` para releer siempre el atributo normalizado a UTC. Validación en Windows: `FechasUtcMensajeroTest` aprobó 3/3 pruebas, 9 aserciones, 0 fallos. Pint de los 8 archivos había aprobado en la ronda anterior.
- [x] Latido remoto real validado el 19/10/2026: Baileys confirmó `confirmacion_buffer_inicial`, conexión marcada `conectado`; `ultimo_latido_en` se leyó como `2026-10-19T17:35:49Z` frente a `now('UTC')=2026-10-19T17:36:08Z` (19.09 s de antigüedad), sin desfase futuro. Esto valida un latido reciente, no todavía la estabilidad de dos horas.
- [x] Regresión `FechasUtcMensajeroTest` aprobada: 3 pruebas / 9 aserciones. Control de latidos en UTC y actualización remota comprobados con un latido 19.09 s anterior a `now('UTC')`.
- [x] Aclarada ausencia de ráfaga real: los 25 mensajes no se enviaron; `RAFAGA-REAL-A-` devolvió `[]` como era esperable. Validación breve de tres mensajes reales aprobada para alcance de recepción: `z91=1`, `z92=1`, `z93=2` coincidencias totales, con un `z93` histórico y uno de la prueba actual. No equivale a estabilidad prolongada o carga real de 25 mensajes.

## Después de certificar captura
- [x] Incremento de normalización y buzón → Redis **implementado**, pendiente de validación local: `ExtraerContenidoWhatsapp`, `NormalizarEventoWhatsapp`, `NormalizarEventoWhatsappTrabajo`, `DespacharBuzonWhatsapp`, comando `mensajero:despachar-buzon`. Reutiliza tablas `mensajero_*` existentes, transacciones SQL, deduplicación, contacto, cita tardía y tiempos UTC. Eventos mutables quedan como `pendiente_semantica`, sin falsos mensajes originales.
- [x] Publicación del buzón con reserva recuperable, reintento exponencial y salida sin secretos; Redis desactivado por defecto para el POC hasta certificar el entorno, con programación por minuto opcional vía `MENSAJERO_NORMALIZACION_HABILITADA`.
- [x] Agregadas pruebas `ExtraerContenidoWhatsappTest` (unidad) y `NormalizacionWhatsappSqlServerTest` (citas fuera de orden, idempotencia, trabajos de Redis simulados y fallo de publicación). Documentación en `docs/normalizacion-buzon-redis.md`.
- [x] Primera validación local recibida: Pint 7/7 archivos; integración `NormalizacionWhatsappSqlServerTest` 4/4 pruebas, 51 aserciones; pruebas unitarias 3/4 y 11 aserciones antes de ajuste. El único fallo se debió a que Carbon representó UTC como `+00:00`, no como cadena `UTC`.
- [x] Regresión de unidad certificada el 19/10/2026: `ExtraerContenidoWhatsappTest` aprobado 4/4 pruebas, **11 aserciones** y cero fallos (no 12). Integración SQL Server del mismo incremento ya había aprobado 4/4 pruebas, 51 aserciones; Pint 7/7.
- [ ] **Validación de operación pendiente:** Redis real, trabajador y recuperación tras caída; ningún procesamiento histórico efectuado. No activar la programación automática hasta certificar Redis y la secuencia completa.
- [x] **Vista de mensajes originales y resumen de integridad por conexión implementada, pendiente de pruebas locales:** `/mensajes` (grupos), `/mensajes/grupos/{chat}` (eventos paginados, filtros), `/mensajes/eventos/{evento}` (detalle de fuente cruda y multimedia solo en metadatos). Texto visible se extrae en lectura de registros crudos aun con Redis desactivado; sin alterar los datos ni atribuir mensajes falsos a ediciones/eliminaciones.
- [x] Seguridad de la consulta: middleware `ProtegerConsultaEvidencia`. Sin login todas las vistas privadas responden 403 salvo opción local explícita `MENSAJERO_CONSULTA_LOCAL_SIN_AUTENTICACION=true` desde IP de bucle y `APP_ENV=local`; no está habilitada por defecto. Modo corporativo no integrado continúa cerrado.
- [x] Pruebas de vistas certificadas en Windows el 19/10/2026: Pint 5 archivos aprobado; `ConsultaEvidenciaAccesoTest` 3 pruebas, 5 aserciones; `VistaMensajesSqlServerTest` 2 pruebas, 20 aserciones; `php artisan view:cache` correcto. **Total 5 pruebas, 25 aserciones, 0 fallos.** Validadas las reglas de acceso y la representación de datos SQL Server sintéticos; revisión visual de mensajes reales en localhost aún pendiente. Documentación `docs/consulta-mensajes.md`.
- [x] Revisión visual manual POC en `http://127.0.0.1:8010/mensajes`: el operador confirmó que la pantalla carga en localhost y compartió captura mostrando 3 grupos autorizados, 43 eventos crudos, 0 mensajes normalizados y 14 periodos de integridad. Los dos grupos de integración tienen un evento cada uno; el grupo de prueba real tiene 41. Los contadores confirman visualización, no equivalen aún a certificación de historial detallado ni de integridad completa.
- [x] Historial real de `Mensajero milac Prueba` (grupo 10020) revisado visualmente: 41 eventos, `z91` evento 11068, `z92` 11069 y `z93` 11070, remitente seudonimizado, fechas y enlaces de evidencia legibles. Aún no se revisó la pantalla de detalle de evento.
- [x] Refinamiento de experiencia de usuario implementado: resumen neutro de conectividad con total de intervalos y últimos cinco en sección desplegable; elimina alarma visual que podía confundirse con pérdida de mensajes; muestra etiquetas legibles y explica que `Capturado` no equivale a `Normalizado`. Se agregó prueba SQL para comprobar la nueva presentación, **pendiente de ejecutar localmente**.
- [x] Vista real de evidencia del evento 11070 revisada visualmente el 19/10/2026: muestra texto, fecha, estado, hashes y JSON de origen. Se identificó exposición innecesaria de metadatos técnicos en la vista web. Se implementó representación protegida en pantalla, con alias del remitente; **el contenido almacenado permanece intacto**. Prueba unitaria nueva e integración SQL actualizada, pendientes de ejecución local.
- [x] Evidencia real del evento 11070 revisada nuevamente en navegador: `messageSecret`, `remoteJid` y participantes aparecen ocultos, con texto `z93` y SHA-256 visibles. Esta revisión confirma la representación en pantalla, pero la batería actualizada `PrepararContenidoEvidenciaTest` y `VistaMensajesSqlServerTest` **no tiene aún salida de ejecución compartida**.
- [x] Separación del origen técnico de grupos implementada: tablero de mensajes agrupa `recolector_whatsapp` frente a otras pasarelas; administración de grupos ofrece filtro `origen=recolector|otras` y etiquetas de proveedor. No se infiere condición operativa por el nombre ni se alteran autorizaciones de captura. Prueba SQL agregada para comprobar clasificación y filtros, **pendiente de ejecución local**.
- [x] Validación local de mejoras de seguridad, grupos y evidencia (19/10/2026): Pint 4/5 archivos; la única incidencia fue estilo de `app/Http/Controllers/Grupos/ControladorGrupos.php`. `PrepararContenidoEvidenciaTest`: 3/3 pruebas, 15 aserciones; `VistaMensajesSqlServerTest`: 3/3 pruebas, 44 aserciones; Blade `view:clear` y `view:cache` correctos. **Total: 6 pruebas, 59 aserciones, sin fallos funcionales.** Corregido estilo en el controlador de grupos, pendiente verificar Pint otra vez.
- [x] Preparado comando `mensajero:normalizar-evento <id>` en modo vista previa **sin escrituras**; `--aplicar` limita el procesamiento a un único evento de grupo autorizado solo en una base `sqlsrv` terminada en `_test` y entorno local/de prueba. Redis y ejecución periódica siguen inactivos.
- [x] Nueva integración `NormalizacionControladaSqlServerTest` para vista previa, ejecución única, idempotencia, exclusión de otros mensajes, autorización y bloqueo de base no _test. **Validada en Windows el 19/10/2026: PHPUnit 3/3 pruebas, 25 aserciones, 0 fallos; Pint 3/3 archivos aprobado.** Vista previa real del evento 11070 completada en Windows: grupo 10020, tipo texto, estado capturado; el comando confirmó que no efectuó escrituras. Posteriormente el operador mostró la pantalla de detalle con estado `normalizado` y proyección `Mensaje #5`, texto `z93`, evidencia protegida y el hash del evento visibles. Se corroboró posteriormente por Tinker directamente en SQL Server: estado `normalizado`, mensaje `#5`, texto `z93`, vínculo correcto `true`, exactamente 1 proyección y `hash('sha256', contenido)` igual al SHA-256 almacenado (`true`). **Primera normalización real y trazabilidad certificadas; no implica validación de lotes ni de Redis real.**
- [x] Primera normalización real auditada directamente en SQL Server (19/10/2026): evento #11070, grupo #10020, mensaje #5, contenido `z93`, estado `normalizado`, vínculo `evento_crudo_id` correcto, 1 proyección única y hash SHA-256 del contenido crudo idéntico al almacenado. La evidencia permanece disponible en vista protegida.
- [x] Preparado `mensajero:normalizar-lote` en modo vista previa sin escrituras, con máximo de cinco eventos `mensaje/texto/v3-recolector` de un único grupo autorizado, y ejecución limitada a IDs exactos mediante `--ids` y `--aplicar`. Guardas de entorno local/de prueba y base `_test`; no activa Redis ni procesa automáticamente el histórico.
- [x] Añadida `NormalizacionLoteSqlServerTest`: prueba vista previa, IDs exactos, idempotencia, otro grupo, duplicados, límites, desbordamiento de IDs y bloqueo de bases de producción.
- [x] Validación local de normalización por lotes (19/10/2026) aprobada en Windows tras corregir la captura de salida con `Artisan::call` / `Artisan::output`: `vendor\bin\pint --test tests\Integracion\NormalizacionLoteSqlServerTest.php` PASS (1 archivo), PHPUnit `NormalizacionLoteSqlServerTest` **4/4 pruebas, 39 aserciones, 0 fallos**; salida facilitada por el operador tras `git pull` al commit `912dc67`. Certifica únicamente el alcance automatizado en SQL Server de pruebas, no lotes operativos ni Redis.
- [x] Vista previa real sin escrituras del grupo #10020 validada en Windows (19/10/2026): `php artisan mensajero:normalizar-lote 10020 --limite=2` devolvió `2 mensaje(s) elegibles`, IDs exactos `10055,10056` y el aviso `No se ha modificado ningún registro`. La actualización Git a `d017e7a` fue correcta. **No se ejecutó `--aplicar`.**
- [x] Revisión real individual del grupo #10020 (19/10/2026) mediante vistas protegidas y CLI, sin escrituras: evento #10055 `SEM-ORIGINAL-001` y evento #10056 `SEM-CITA-001`, ambos `mensaje/texto`, `v3-recolector`, estado `capturado`, grupo #10020; el identificador citado de #10056 coincide con el identificador externo de #10055. Ambos ejecutaron correctamente `mensajero:normalizar-evento <id>` en vista previa. No se expusieron datos privados ni se aplicó el lote.
- [x] Primera aplicación real controlada de lote (19/10/2026) ejecutada por el operador en la rama `0145d69` sobre su entorno de pruebas: `mensajero:normalizar-lote 10020 --limite=2 --ids=10055,10056 --aplicar` reportó `2 normalizados, 0 ya normalizados, 2 IDs evaluados`, sin error. La consulta posterior con `mensajero:normalizar-evento` confirmó estado `normalizado` para ambos IDs. **En ese momento faltaba la auditoría posterior; su verificación 2/2 se documenta más abajo.**
- [x] Implementado comprobador de solo lectura `mensajero:verificar-lote`, restringido a SQL Server `_test`, grupo autorizado e IDs exactos: verifica estados, única proyección, integridad SHA-256 actual, identidad/proveedor y referencias a citas dentro del lote. Incluida `VerificacionLoteSqlServerTest` con casos satisfactorio y de corrupción de hash, ruptura de cita, mensaje pendiente, duplicado de IDs y bloqueo de producción. **Validación inicial Windows 19/10/2026:** `php -l` sin errores, Pint 2/2 archivos PASS, PHPUnit **3 pruebas, 24 aserciones, 1 fallo**: la prueba positiva exigía texto en `Artisan::output()` y recibió cadena vacía, aunque la ejecución de la auditoría devolvió código 0. La prueba se corrigió para comprobar código 0 y comparar instantáneas SQL antes/después, preservando los casos negativos; **pendiente repetir Pint/PHPUnit tras el cambio**.
- [x] Auditoría real, solo lectura, aprobada en Windows para grupo #10020 e IDs #10055 y #10056: `2/2 eventos correctos`, `1 cita(s) verificadas`, `0 cita(s) fuera del lote sin verificar`, `no se modificaron registros`. Confirma una proyección por evento, relación de cita e integridad SHA-256 actual; **no equivale a comparación independiente con un hash anterior**.
- [x] **Cierre de normalización manual por lotes (19/10/2026, Windows/PHP 8.4.7/SQL Server `_test`)**. Tras `git pull` hasta `6a4bb11`, `php -l tests/Integracion/VerificacionLoteSqlServerTest.php` sin errores, Pint **1/1 archivo PASS**, PHPUnit **3/3 pruebas, 28 aserciones, 0 fallos**. Se combina con la auditoría real previa 2/2 mensajes y 1 cita confirmada. Certificado el alcance de **normalización manual** y auditoría actual de integridad; no se ha probado Redis físico, programación automática, resiliencia prolongada ni integridad histórica independiente.
- [ ] **Próximo control de seguridad para Redis real:** ejecutar `php artisan mensajero:diagnostico` en el entorno local para comprobar SQL Server, Redis y almacenamiento. Confirmar de manera local (sin publicar `.env` ni secretos) base con sufijo `_test`, `MENSAJERO_NORMALIZACION_HABILITADA=false` y cola/prefijo Redis exclusivos para pruebas antes de publicar o consumir cualquier trabajo.
- [ ] Diseñar/validar prueba de un único evento sintético con Redis físico, despacho por `--evento-id`, trabajador limitado a la cola aislada, reinicio/reintento y comprobación en SQL Server. Prohibido arrancar `schedule:work`, `queue:work` genérico o despacho global del buzón mientras no exista aislamiento y aprobación de la prueba.
- [ ] Vista enriquecida de periodos con reconciliación independiente y multimedia descargable/reproducible mediante autorización estricta: no implementada aún; el resumen de periodos actual es por conexión, no por grupo verificado.
- [ ] Integración al servidor MILAC.
- [ ] Fase 2: Gemini con trazabilidad exacta hacia mensajes fuente.

## Regla de despliegue
No ejecutar migraciones de desarrollo contra producción. Usar una base `_test` durante el POC. No agregar el número recolector a grupos operativos reales hasta completar multimedia y las pruebas de integridad.