# Mensajero: instrucciones del proyecto

## Lectura obligatoria y continuidad

Leer en orden: README.md, OBJETIVO.md, RESUMEN.md, TASKS.md, docs/milestones.md y
docs/milac-integration-review.md. Después consultar los documentos técnicos del módulo.
Este archivo es la entrada estándar para agentes; no crear un agends.md divergente.
La documentación debe bastar sin acceso al historial del chat o a repositorios hermanos.

## Papeles que debe asumir la IA

- Arquitecto de software: proteger límites del monolito modular, contratos versionados y
  decisiones justificadas. Documentar problema, opciones, ventajas, desventajas, riesgos y decisión.
- Desarrollador senior: entregar incrementos funcionales, legibles, probados e idempotentes;
  revisar el código existente antes de modificar y evitar infraestructura innecesaria.
- Diseñador de base de datos: diseñar constraints, índices, relaciones, migraciones SQL Server,
  transacciones y recuperación; conservar evidencia y separar datos de archivos.
- Responsable de infraestructura: configuración portable, workers, backups, restauración,
  health checks y despliegue compatible con los runtimes existentes.
- Responsable de seguridad y trazabilidad: identidad central, autorización por módulo/chat,
  firmas, secretos fuera de Git, mínimo privilegio y auditoría desde fuente hasta interpretación.

Los papeles son responsabilidades de trabajo, no autorización para cambiar producción.

## Protocolo de trabajo

1. Inspeccionar Git, instrucciones, código, configuración de ejemplo y pruebas. Preservar
   cambios ajenos. No asumir que una función existe porque esté descrita en arquitectura.
2. Elegir la siguiente tarea de TASKS.md dentro del milestone vigente. Resolver decisiones
   menores técnicamente; no exigir credenciales para trabajo que admite pruebas aisladas.
3. Implementar un incremento concreto y comprobar fallos además del camino exitoso.
4. Ejecutar validaciones pertinentes; indicar fecha, comandos y resultados reales.
5. Actualizar RESUMEN.md, TASKS.md y milestones cuando cambie su estado. Dejar el siguiente
   paso explícito y distinguir pendiente técnico, configuración y verificación real.
6. Revisar el diff y secretos antes de commit/push. No desplegar ni migrar producción como
   efecto secundario de publicar código. No usar force-push para resolver divergencias.

## Integración empresarial

La integración observada usa login de MilacServices y autorización propia de cada módulo.
Valera/GPSGestor reutilizan dbLis desde el contenedor existente en servidor; no necesitan
copiar credenciales. No pedir de nuevo settings.php, SSH o una base nueva como condición
para avanzar con adaptadores y pruebas. No incluir dependencias Slim antiguas directamente
en Laravel sin validar compatibilidad. Ver docs/milac-integration-review.md.

El login local actual es provisional. No declarar identidad corporativa implementada ni
dar acceso por el simple hecho de que IAVE/VALERA autentiquen. No copiar accesos de preview.

## Reglas invariantes

Leer README.md y docs/milestones.md antes de ampliar el alcance. Trabajar por milestone.
SQL Server es el único motor operativo; nunca introducir PostgreSQL o usar SQLite para
certificar SQL Server. PHP 8.4+, Laravel, Blade/Livewire; gateway separado en TypeScript.

Allowlist default deny antes de RAW, descargas o IA. Stickers se descartan. RAW es evidencia
inmutable. Persistir RAW + outbox antes de ACK. Redis y Gemini no deben ser condiciones de
persistencia RAW. Archivos privados mediante Flysystem, SQL solo metadata y rutas relativas.

No conectar el número empresarial sin verificar la decisión de proveedor documentada.
No afirmar conectividad, ejecución de migraciones o tests de integración sin evidencia real.
No guardar secretos, sesiones WhatsApp, payloads privados o binarios operativos en Git.

Verificación: PHPUnit aislado, Pint y compilación de vistas. Integración SQL requiere base
desechable terminada en _test. No lanzar migraciones contra producción para ejecutar tests.
Actualizar documentación y estado del milestone, distinguiendo diseño de código implementado.
