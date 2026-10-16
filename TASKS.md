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
- [x] Recuperación tras caída de Laravel validada a nivel de resultado: SQL permaneció en 11 mientras Laravel estaba fuera y, tras restaurarlo, subió a 16; la cola final quedó en 0. No se capturó evidencia intermedia del número exacto de `/eventos` pendientes durante la caída, por lo que esa parte queda pendiente de una prueba instrumentada.
- [ ] Repetir una caída breve de Laravel capturando explícitamente la cola intermedia y confirmar que los mensajes `/eventos` permanecen en disco hasta ACK.
- [ ] Reiniciar recolector y verificar periodo de integridad.

## Bloqueador antes de grupos reales
- [ ] Descargar foto/video/audio/documento en el recolector inmediatamente después de autorizar el grupo.
- [ ] Persistir multimedia durable fuera de SQL Server con hash SHA-256.
- [ ] Subir/entregar multimedia a Mensajero con ACK y reintentos.
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
