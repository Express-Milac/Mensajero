# Mensajero MILAC

> **Control de Gemini desde la interfaz (24/oct/2026):** página `/configuracion/gemini`, con interruptor general y opción de autorizar y activar **cualquier grupo monitoreado** desde la interfaz, consentimiento expreso individual, auditoría SQL Server y corte temporal para no transmitir historial. No depende de modificar `.env` en cada encendido. Primera instalación requiere una migración aislada y habilitar el permiso local una sola vez. Ver [Control de interpretación Gemini](docs/control-interpretacion-gemini.md).

> **Actualización de diseño (24/oct/2026):** Mensajero genera y muestra acontecimientos de Gemini sin pedir validación humana. Los estados nuevos son `identificado_ia` (análisis) y `generado_ia` (bitácora). Las referencias antiguas a `pendiente_revision` y aprobación se conservan como historial del piloto y no describen el funcionamiento vigente. Véase [Bitácora automática](docs/bitacora-automatica.md).


Sistema propio de Express MILAC para **capturar mensajes de grupos WhatsApp expresamente seleccionados**, conservar evidencia y proponer eventos operativos mediante Google Gemini. Laravel 13 / PHP 8.4, SQL Server corporativo (tablas mensajero_*), Redis y recolector Node/Baileys.

## Estado confirmado del piloto y avance al 23/10/2026

- **Fase 1, POC funcional:** recepción de WhatsApp desde número recolector secundario, selección de grupos, pasarela HMAC, SQL Server, buzón, normalización asíncrona Redis, dashboard, historiales y evidencias. El recolector no oficial nunca debe vincular el número principal.
- **Fase 2, piloto Gemini funcional:** el mensaje ficticio **#74** del grupo interno **10020, «Mensajero milac Prueba»**, produjo la incidencia «Retraso en descarga por falta de anden», almacenada en estado **pendiente_revision**. La vista /mensajes/ia mostró el resumen y vínculo al mensaje fuente. Modelo **gemini-3.5-flash-lite**, 223 tokens entrada, 84 salida.
- **Pruebas Gemini:** 9 aprobadas, 19 aserciones (resultado obtenido en Windows el 22/10/2026). Esto verifica un flujo básico real, **no** operación ininterrumpida ni seguridad de producción.
- **Ejecución automática implementada:** la normalización puede encolar Gemini para mensajes **nuevos, texto, grupo autorizado y posterior al corte**, con trabajador Redis separado. La activación persistente es por configuración privada del equipo, NO por el mero contenido de GitHub.
- **Pendientes:** revisión/aprobación humana operativa, cuotas/costo con límites locales, tolerancia a fallos y conciliación de tareas perdidas, monitorización, autenticación corporativa, certificación multimedia/carga y despliegue supervisado.

## Gemini con datos reales YA capturados (24/10/2026)

Se agregó un piloto por grupo para evaluar interpretaciones a partir de los mensajes reales existentes en SQL Server, sin exigir mensajes ficticios nuevos en WhatsApp. El grupo de Patios (ID 10197) tiene actividad real; el permiso previo de Google aplicaba solo al grupo de prueba (10020).

La nueva autorización por grupo y corte horario permite un lote manual pequeño y desactiva temporalmente los envíos IA automáticos. Los mensajes originales permanecen intactos. Se requiere consentimiento expreso para enviar datos empresariales a Google.

Véase [Piloto de mensajes reales](docs/piloto-mensajes-reales.md).

## Recuperación controlada de mensajes sin Gemini (24/10/2026)

Se añadió una conciliación **desactivada por defecto** para cubrir despachos omitidos entre SQL Server y Redis. La tabla `mensajero_control_publicaciones_gemini` registra una reserva única por mensaje, compartida por normalización, envío manual y recuperación. Los trabajos tienen un único intento automático; cualquier publicación incierta requiere revisión humana, sin reintento facturable ciego.

La programación cada cinco minutos necesita autorización adicional con fecha de corte nueva; solo recupera mensajes recientes y elegibles de los grupos Gemini existentes. Hay un límite conservador de hasta 10 publicaciones nuevas por día UTC y por grupo (configurable entre 1 y 30). La captura y los originales no se modifican.

El código y las pruebas están en la rama, **no se ha aplicado la nueva migración a la base activa ni comprobado el SQL del entorno**. Ver [Conciliación y recuperación de Gemini](docs/conciliacion-gemini.md).

## Gemini con conversaciones reales (24/10/2026)

Ahora existe un proceso explícito para seleccionar hasta seis grupos reales de manera autorizada, sin asumir que capturar un grupo autoriza transferirlo a Google. La fecha de corte se actualiza al momento de otorgar el nuevo permiso; se procesan solo mensajes nuevos. La herramienta lista y comprueba IDs con `mensajero:comprobar-grupos-ia` y configura el envío con `scripts/autorizar-grupos-reales-gemini.ps1`.

El modo `-ContextoConversacional` añade hasta seis textos o leyendas (cinco minutos, un mismo grupo), alias temporales de participantes y referencias a respuestas, para que Gemini interprete conversaciones reales y no solo mensajes sueltos. Por defecto solo se envía el mensaje objetivo. Se conservan los originales y la revisión humana.

**No hay envío automático a nuevos grupos por actualizar el código**: la habilitación real requiere confirmación expresa. No activar varios grupos de alto volumen sin límite y supervisión de gasto. Más detalles en [Autorizar Gemini para grupos reales](docs/autorizar-gemini-grupos-reales.md).

## Dos caras: cruda y operativa (23/10/2026)

- **Mensajes originales**: consulta sin alteraciones de textos, fotografías, audios, videos y documentos recibidos por grupos autorizados.
- **Operación interpretada**: nueva pantalla /operacion con acontecimientos propuestos por Gemini, referencias a mensajes y posibles adjuntos multimedia, estado de revisión y auditoría.
- Los mensajes con leyenda de foto/video/documento ahora pueden iniciar un análisis **solo textual**: Gemini no recibe las fotografías ni certifica visualmente su contenido.
- Las fotos sin explicación solo se sugieren como adjuntos si comparten emisor/grupo y no existe ambigüedad. La asociación no demuestra que la fotografía corresponda al acontecimiento.
- Código y pruebas publicados, **pendientes de ejecución en SQL Server _test y prueba real en el recolector**. No equivale a producción ni a correlación semántica completa de incidentes repetidos.

Guía: [Cara operativa](docs/cara-operativa.md). Migraciones nuevas de 23/10/2026. Los resultados IA históricos del piloto pueden proyectarse localmente sin volver a consultar Google.

## Arquitectura resumida

WhatsApp → recolector aislado (solo grupos autorizados) → cola local durable → pasarela HMAC → SQL Server (evento crudo y buzón) → Redis (normalización) → SQL Server (mensaje).

Rama opcional autorizada: mensaje nuevo normalizado → **cola mensajero_ia** → Gemini → validador PHP → tabla **mensajero_analisis_operativos_ia** → /mensajes/ia.

La captura **no espera** a Gemini. Pegatinas descartadas; multimedia en almacenamiento privado externo a SQL Server. Gemini no envía mensajes por WhatsApp ni modifica los originales.

## Un comando para levantar todos los servicios (Windows, entorno local)

Después de configurar Redis en WSL, Laravel, el recolector y Gemini, ejecutar desde la raíz:

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\mensajero-local.ps1
~~~

También existen las acciones **-Accion estado** y **-Accion detener**. El lanzador abre cinco ventanas (web, programador, normalizador, Gemini y recolector), comprueba que Redis responda y **evita duplicar instancias anteriores**. No habilita IA si no está autorizada en .env y no reinicia sesiones WhatsApp ajenas.

**La primera vez**, detener las terminales del lanzamiento manual anterior con Ctrl+C; el script no mata procesos ajenos. No sobrevive a reinicio/suspensión Windows y no sustituye al administrador de servicios del servidor.

Guía y limitaciones: [Arranque local unificado](docs/arranque-local-unificado.md).

## Activar Gemini de forma continua

Para habilitar la prueba automática de forma **persistente** solo en el grupo de ensayo **10020**, revisar y ejecutar el instalador PowerShell en el equipo que va a operar Mensajero (tras autorización de MILAC para transmitir datos a Google y facturar su uso):

~~~powershell
php artisan test --filter=GeminiContratoTest
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\activar-gemini.ps1 -GrupoId 10020
~~~

El script solicita la clave de forma oculta, establece fecha de corte **actual** (sin analizar histórico), guarda variables únicamente en .env local e ignora las credenciales en Git. No envía mensajes a Google por sí solo. Para conservar deliberadamente una fecha de corte anterior al reactivar existe el parámetro -ConservarCorte, **solo después de revisar lo pendiente**.

Mantener activos recolector, servidor web, programador Laravel, normalizador Redis y trabajador IA. Windows POC:

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\ejecutar-gemini-windows.ps1
~~~

Para servidor MILAC, usar supervisor de procesos, SSL y usuario restringido. **Configurar una clave y un proyecto Google separados** es lo aconsejable: las cuotas se comparten entre aplicaciones del mismo proyecto.

**Guía principal:** [Operación permanente de Gemini](docs/operacion-gemini-permanente.md).
**Reglas exactas de Gemini:** [Canalización y prompt](docs/canalizacion-ia.md).
**Prueba funcional:** [Fase 2: piloto validado](docs/fase-2-gemini-piloto.md).

## Gestor de grupos: interruptor único

Desde la actualización del 23/10/2026, el interruptor de /grupos tiene **una única función combinada**: encendido significa **capturar nuevos mensajes y mostrar el grupo en el tablero**; apagado significa **no capturar y no mostrar**. Los mensajes históricos permanecen conservados, no se eliminan. La captura y la visibilidad se cambian en una transacción SQL Server auditada; los grupos nuevos descubiertos nacen deshabilitados. La autorización **Gemini** es independiente y continúa limitada al grupo 10020.

Ver [Gestor de grupos](docs/gestor-grupos.md) para prueba y seguridad del modo local.

## Seguridad y límites

- Solo grupos autorizados; no capturar conversaciones ajenas.
- La integración oficial Meta Cloud API se mantiene independiente para flujos compatibles.
- Sin login corporativo integrado todavía: no publicar libremente el dashboard en Internet.
- Los mensajes de grupos reales pueden contener información sensible **aunque la limpieza actual oculte teléfonos y correos**. No autorizarlos hasta revisar privacidad y condiciones de uso de Google.
- Los resultados de Gemini son **propuestas sujetas a verificación humana**, no hechos confirmados ni instrucciones.
- En el repositorio .env.example permanece apagado a propósito; la activación permanente se realiza en .env de cada instalación, nunca con claves en GitHub.
