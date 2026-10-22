# Mensajero MILAC

Sistema propio de Express MILAC para **capturar mensajes de grupos WhatsApp expresamente seleccionados**, conservar evidencia y proponer eventos operativos mediante Google Gemini. Laravel 13 / PHP 8.4, SQL Server corporativo (tablas mensajero_*), Redis y recolector Node/Baileys.

## Estado al 22/10/2026

- **Fase 1, POC funcional:** recepción de WhatsApp desde número recolector secundario, selección de grupos, pasarela HMAC, SQL Server, buzón, normalización asíncrona Redis, dashboard, historiales y evidencias. El recolector no oficial nunca debe vincular el número principal.
- **Fase 2, piloto Gemini funcional:** el mensaje ficticio **#74** del grupo interno **10020, «Mensajero milac Prueba»**, produjo la incidencia «Retraso en descarga por falta de anden», almacenada en estado **pendiente_revision**. La vista /mensajes/ia mostró el resumen y vínculo al mensaje fuente. Modelo **gemini-3.5-flash-lite**, 223 tokens entrada, 84 salida.
- **Pruebas Gemini:** 9 aprobadas, 19 aserciones (resultado obtenido en Windows el 22/10/2026). Esto verifica un flujo básico real, **no** operación ininterrumpida ni seguridad de producción.
- **Ejecución automática implementada:** la normalización puede encolar Gemini para mensajes **nuevos, texto, grupo autorizado y posterior al corte**, con trabajador Redis separado. La activación persistente es por configuración privada del equipo, NO por el mero contenido de GitHub.
- **Pendientes:** revisión/aprobación humana operativa, cuotas/costo con límites locales, tolerancia a fallos y conciliación de tareas perdidas, monitorización, autenticación corporativa, certificación multimedia/carga y despliegue supervisado.

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

## Seguridad y límites

- Solo grupos autorizados; no capturar conversaciones ajenas.
- La integración oficial Meta Cloud API se mantiene independiente para flujos compatibles.
- Sin login corporativo integrado todavía: no publicar libremente el dashboard en Internet.
- Los mensajes de grupos reales pueden contener información sensible **aunque la limpieza actual oculte teléfonos y correos**. No autorizarlos hasta revisar privacidad y condiciones de uso de Google.
- Los resultados de Gemini son **propuestas sujetas a verificación humana**, no hechos confirmados ni instrucciones.
- En el repositorio .env.example permanece apagado a propósito; la activación permanente se realiza en .env de cada instalación, nunca con claves en GitHub.
