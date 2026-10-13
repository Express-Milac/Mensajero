# Estado y traspaso a otra IA

Actualizado: 2026-10-13. Este archivo describe lo implementado, no el producto final.

## Lo que existe

- Laravel 13 / PHP 8.4; Composer lock, Livewire y Predis instalables.
- Login local por email/contraseña, sesión, logout, limitación de intentos y roles.
- Comandos mensajero:create-admin y mensajero:health.
- Configuración exclusiva SQL Server y Redis; compose local solo para Redis.
- Disco privado mensajero_local con raíz configurable y health de lectura/escritura/espacio.
- Pantallas Blade de acceso e inicio; no muestran datos de operación simulados como reales.
- Diagnóstico protegido /health y liveness /up.
- Pruebas aisladas y suite opt-in para SQL Server real con base terminada en _test.
- Diseño de arquitectura, entidades, allowlist, ingestión/outbox, storage y pipeline IA.

## Lo que todavía no existe

Login corporativo, permisos corporativos persistidos, puente MilacServices, tablas operativas,
allowlist administrable, ingestión RAW/outbox, gateway, descargas multimedia, timeline,
búsqueda operativa, broadcasting, Gemini y revisión humana. No hay sesión WhatsApp vinculada.
Livewire instalado no significa vistas operativas Livewire implementadas.

## Descubrimiento que cambia la siguiente implementación

IAVE valida mediante MilacServices/Iave/api/loginv2. GPSGestor también usa Mml/api/login,
luego exige autorización propia. Valera carga el bootstrap de MilacServices para recuperar
dbLis sin copiar secretos. Los contratos y archivos observados están documentados en
docs/milac-integration-review.md, incluyendo diferencias entre documentación antigua y código.

No se necesita que el usuario entregue credenciales o SSH para continuar desarrollando.
El login local inicial debe evolucionar al patrón corporativo. No imponer una base nueva.
La documentación del servidor menciona PHP 7.4; dato aún no verificado en vivo. Laravel
requiere runtime separado y una frontera compatible con la integración heredada.

## Evidencia y límites

En la entrega inicial: 9 pruebas aisladas / 41 assertions aprobadas; Pint, rutas y vistas
validados. No se han certificado SQL Server ni Redis reales. Diagnóstico inicial: storage OK,
SQL/Redis failed, WhatsApp not_implemented, Gemini disabled. Son observaciones históricas,
no estado garantizado del entorno actual. Ver docs/verification.md para la revisión reciente.

No se ejecutaron migraciones empresariales ni se desplegó en producción. Las referencias a
repositorios hermanos son evidencia de investigación; no están incluidos en este repositorio.

## Próximo paso

Tarea M1-01 de TASKS.md: diseñar e implementar adaptador de autenticación corporativa con
pruebas HTTP simuladas y autorización propia, conservando aislamiento de credenciales y
roles. Después resolver la conexión reutilizable y certificar milestone 1 en entorno disponible.
