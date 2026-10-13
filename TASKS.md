# Tareas de continuidad

Estado: 2026-10-13. [x] terminado en el alcance indicado; [ ] pendiente. El roadmap completo
vive en docs/milestones.md. No marcar un milestone certificado por pasar pruebas simuladas.

## Entregado

- [x] M0-01 Documentar arquitectura, SQL Server, storage y contratos de ingestión.
- [x] M0-02 Investigar integración WhatsApp y documentar incertidumbre sobre grupos existentes.
- [x] M1-00 Crear base Laravel, login local, roles, configuración y pruebas aisladas.
- [x] INT-00 Revisar IAVE/GPSGestor/Valera y documentar integración corporativa reutilizable.
- [x] DOC-01 Preparar instrucciones, objetivos, estado y tareas para agentes.

## Prioridad inmediata: milestone 1

- [ ] M1-01 Adaptador de login corporativo servidor a servidor. Contrato {name,password},
  validar identidad/respuesta; distinguir rechazo, bloqueo y servicio no disponible. Timeout,
  TLS, sin logs de credenciales ni respuesta completa. No reutilizar permisos IAVE como roles.
  Aceptación: tests HTTP falsos para éxito, bloqueo, respuesta inválida, timeout y falta de acceso.
- [ ] M1-02 Autorización propia por identificador central alfanumérico: roles y estado activo;
  revocación verificable y sesión regenerada. No copiar hashes/contraseñas centrales. Probar
  credencial válida sin autorización, rol limitado y revocación durante sesión.
- [ ] M1-03 Definir frontera Laravel ↔ MilacServices para reutilizar dbLis con runtime compatible.
  Preparar puente autenticado o mecanismo equivalente con operaciones acotadas. No SQL libre,
  no secretos hardcodeados, no mezclar autoloaders sin comprobar. Documentar decisión.
- [ ] M1-04 Verificar conexión SQL y Redis reales en entorno autorizado. Probar migraciones solo
  en base desechable _test. Confirmar destino/aislamiento de tablas antes de instalación real.
- [ ] M1-05 Actualizar instalación, login y comandos para que reflejen el modo corporativo final.

## Siguiente: captura RAW

- [ ] M2 Allowlist auditable, descubrimiento mínimo y pruebas default deny.
- [ ] M3 RAW + outbox transaccional, ACK posterior al commit, deduplicación y recuperación.
- [ ] M4 Contrato WhatsAppProvider, gateway y verificación con grupos reales de prueba.
- [ ] M5–M6 Storage portable, media asíncrona, MIME/checksum y pruebas de fallos.
- [ ] M7–M8 Dashboard/timeline paginada, filtros, búsqueda y canales privados.
- [ ] M9 Ensayo de aceptación de OBJETIVO.md, fallos de SQL/Redis/storage y restauración.

## Después de estabilizar RAW

- [ ] M10–M11 Gemini desacoplado, schema, fuentes verificadas y contexto incremental.
- [ ] M12–M13 Eventos N:M mensajes, vista IA y navegación exacta a originales.
- [ ] M14 Revisión humana versionada: confirmar/corregir/descartar/fusionar/dividir.

## Criterio de cierre de cada tarea

Código funcional en su alcance, validación pertinente, documentación actualizada, limitaciones
explícitas y ausencia de secretos/datos operativos en Git. Registrar resultados, no suposiciones.
