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
- [x] `npm install`: 98 paquetes instalados y 0 vulnerabilidades reportadas.
- [x] `npm run verificar`: TypeScript aprobado sin errores.
- [x] `npm run pruebas`: 4/4 pruebas aprobadas.
- [x] `phpunit.integracion.xml`: 7 pruebas / 119 aserciones aprobadas.
- [x] Integración SQL Server repetible: 7 pruebas / 119 aserciones aprobadas dos veces consecutivas sobre `mensajero_test`.
- [x] `route:list`: 15 rutas cargadas, incluidas chats autorizados, estado, eventos e integridad.
- [x] Conexión `recolector-pruebas` registrada en `mensajero_test` con ID local 10014 y estado `sin_configurar`.
- [x] Secreto HMAC compartido entre Laravel y recolector configurado localmente y verificado sin exponerlo.
- [ ] Vincular únicamente el número recolector dedicado.
- [ ] Confirmar descubrimiento del grupo de prueba en `/grupos`.
- [ ] Autorizar un solo grupo de prueba.
- [ ] Enviar lote de texto y confirmar cero pérdida en condiciones normales.
- [ ] Cortar Laravel/red, enviar mensajes, restaurar y verificar drenaje completo de cola.
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
