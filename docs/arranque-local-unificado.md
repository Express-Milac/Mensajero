# Arranque unificado de Mensajero MILAC — Windows 11 (POC)

**Actualizado: 22/10/2026.** Entorno local sin privilegios de administrador de Windows. Esta automatización **no constituye un servicio de producción**.

El script **scripts/mensajero-local.ps1** administra con una sola orden:
- Redis en WSL Debian (lo inicia si no responde PONG).
- Laravel web en **127.0.0.1:8000**.
- Programador Laravel (schedule:work).
- Normalizador Redis (mensajero_normalizacion).
- Trabajador Gemini Redis (mensajero_ia) con certificado SSL verificado.
- Recolector WhatsApp (npm run desarrollo), conservando sesión en disco.

El lanzador abre **cinco ventanas identificadas**: web, programador, normalizador, gemini y recolector. Redis queda en WSL. Los procesos se mantienen en ejecución mientras el equipo y las ventanas sigan activos.

## Comando único para iniciar

Desde la raíz del repositorio:

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\mensajero-local.ps1
~~~

El lanzador bloquea dos arranques simultáneos de una misma instalación y **se niega a duplicar** trabajadores anteriores, sesiones del recolector o un servidor que ocupe el puerto 8000. La primera vez, si están abiertas terminales antiguas de Laravel, recolector, programador, normalizador o Gemini, cerrarlas con Ctrl+C antes de iniciar el conjunto. Nunca termina procesos ajenos o iniciados manualmente.

Verifica que existen ambos .env privados, vendor, node_modules, comandos PHP, npm, WSL, certificado HTTPS, normalización programada, política Gemini completamente autorizada y **solo grupo IA 10020**. Las claves se leen de los .env locales y **no se imprimen ni copian al archivo de estado**. También elimina de sus procesos las variables temporales IA heredadas de ensayos previos. **No** modifica fecha de corte, grupos, credenciales, SQL Server ni contenidos WhatsApp.

Si Redis WSL no responde, intenta iniciar redis-server. Si falta configuración, se detiene antes de abrir servicios Laravel. Si un servicio falla al iniciar, intenta cerrar únicamente los procesos que acababa de crear.

El registro de procesos se conserva en **%LOCALAPPDATA%\Mensajero\supervisor** y contiene PID, tipo y marca; no incluye mensajes ni claves.

## Estado y comprobación de Gemini

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\mensajero-local.ps1 -Accion estado
php artisan mensajero:estado-gemini --grupo=10020 --limite=10
~~~

El primer comando muestra si **las ventanas lanzadas** siguen abiertas; no garantiza que SQL Server, WhatsApp o Google respondan. El segundo lee los resultados IA de SQL Server y contadores de Redis sin consultar Google ni gastar tokens. Un mensaje clasificado sin_evento no crea tarjeta en Eventos IA pero sí aparece contabilizado en SQL.

Para comprobar realmente el flujo automático, enviar un **mensaje ficticio nuevo** al grupo autorizado después de que todos los trabajadores estén funcionando y comprobar en el diagnóstico un resultado para ese mensaje. No publicar manualmente otro análisis.

## Detener exclusivamente los procesos propios

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\mensajero-local.ps1 -Accion detener
~~~

El script valida la marca antes de terminar el árbol de cada proceso gestionado. Redis WSL **no se apaga** porque puede ser compartido. Si se corta un trabajo en ejecución, Redis puede reintentarlo, incluso con costo adicional de Gemini; detener preferentemente fuera de una ejecución activa.

## Límites

- Los procesos no sobreviven a suspensión, cierre de sesión o reinicio de Windows: repetir el comando tras volver a iniciar sesión. Un arranque al iniciar sesión necesitaría tareas programadas adicionales.
- Baileys es un cliente WhatsApp no oficial y solo se utiliza el **número secundario dedicado** en el piloto; nunca iniciar dos instancias.
- El modo completo exige autorización Gemini y solo grupo 10020. No habilita automáticamente otros grupos.
- El lanzador no instala dependencias, no habilita cuotas ilimitadas por decisión propia y no asegura funcionamiento continuo 24/7.
- Para servidor MILAC, sustituirlo por procesos systemd/Supervisor, PHP-FPM/HTTPS, health checks y supervisión real.

Guías: [Operación permanente de Gemini](operacion-gemini-permanente.md) y [Canalización de IA](canalizacion-ia.md).
