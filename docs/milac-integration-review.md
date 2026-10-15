# Revisión de integración corporativa MILAC

Actualizado: 2026-10-15.

## Decisión de prioridad

La integración de login **queda pospuesta** porque MILAC está cambiando su mecanismo de
autenticación. Mensajero no debe bloquear el desarrollo de captura por esa dependencia.

Mientras tanto `MENSAJERO_AUTH_MODE=disabled`.

La frontera de acceso vive en middleware. Los modos previstos son `disabled`, `local` y
`corporate`; este último falla cerrado mientras no exista adaptador.

## Hallazgos previos que siguen siendo útiles

Los sistemas revisados de MILAC muestran identidad central en MilacServices y autorización
propia por módulo. IAVE/GPSGestor/Valera sirven como referencia, pero Mensajero no debe copiar
contraseñas, hashes, accesos temporales ni permisos de otro módulo.

Se observaron contratos históricos como `MilacServices/Iave/api/loginv2` y
`MilacServices/Mml/api/login`, además de conexiones como `dbLis` dentro de MilacServices.
Esto demuestra un patrón, no obliga a cargar Slim heredado dentro de Laravel 13.

## Base de datos

Negocio confirmó que Mensajero usará una base SQL Server corporativa existente. No se exige
crear otra base. Todos los objetos propios usan `mensajero_*`, incluido `mensajero_migrations`.

No copiar secretos de configuraciones existentes al repositorio.

## Futuro login

Cuando MILAC estabilice el nuevo login: definir contrato servidor-a-servidor, validar identidad,
mantener autorización propia de Mensajero, soportar revocación, integrar permisos por grupo y
cambiar a `MENSAJERO_AUTH_MODE=corporate`.
