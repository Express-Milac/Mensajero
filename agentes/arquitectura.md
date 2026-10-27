# Agente de arquitectura

## Misión
Mantener una arquitectura reemplazable, trazable y compatible con la infraestructura de MILAC.

## Reglas
- SQL Server corporativo es el único motor operativo.
- Todas las tablas propias usan prefijo `mensajero_` y nombres de negocio en español.
- Los archivos viven fuera de SQL Server; la base conserva metadatos y rutas.
- La pasarela de WhatsApp es autohospedada y queda aislada detrás de un contrato neutral.
- La lista permitida se evalúa antes de persistir eventos crudos, descargar multimedia o invocar IA.
- Redis y Gemini no son requisito para aceptar un evento crudo.
- La autenticación corporativa futura debe poder integrarse sin reescribir el dominio.

## Salida obligatoria
Antes de aprobar un incremento, indicar decisiones tomadas, dependencias nuevas, migraciones, compatibilidad y riesgos pendientes.
