# Revisión previa a integración — 15/10/2026

Esta revisión aplica los agentes del directorio `agentes/` al estado de la rama de trabajo.

## Arquitectura
Resultado: conforme para continuar con el siguiente incremento.

- Se conserva SQL Server corporativo y prefijo `mensajero_`.
- Las tablas, columnas y estados propios de dominio usan español.
- Eventos crudos y buzón de salida continúan dentro de una sola transacción.
- La captura mantiene lista permitida por defecto denegada y no depende de Redis/Gemini.
- La ubicación de archivos sigue siendo configurable y fuera de SQL Server.
- La autenticación queda separada del dominio para permitir la integración corporativa futura.
- La administración de grupos conserva `es_monitoreado=false` al descubrir nuevos chats y versiona cada cambio explícito de autorización.

## Revisión de código
Hallazgos corregidos:
- El tablero funciona sin usuario cuando la autenticación está temporalmente deshabilitada.
- El comando para crear administradores usa el modelo `Usuario` y la tabla correcta.
- Usuario y marcas de tiempo propias usan nomenclatura española.
- Autenticación propia se encuentra bajo `Autenticacion`/`autenticacion`.
- `APP_TIMEZONE` ya se obtiene del entorno y no queda fijado en UTC.
- Modelos, migraciones, pruebas y rutas están alineados con el esquema vigente.

Las excepciones técnicas de nombres en inglés se limitan a contratos impuestos por Laravel, SQL Server, protocolos o proveedores.

## Pruebas
Puerta base ejecutada realmente:
- Composer: validación e instalación aprobadas.
- Sintaxis PHP: sin errores.
- Pint: 51 archivos aprobados.
- PHPUnit normal: 18 pruebas / 55 aserciones aprobadas.
- Rutas y vistas: cargadas/compiladas correctamente.
- Vite: compilación aprobada y `npm install` reportó 0 vulnerabilidades.
- SQL Server 2022 `mensajero_test`: 3 pruebas / 39 aserciones aprobadas dos veces.

GitHub Actions continúa con `startup_failure` previo a jobs. Es una incidencia de infraestructura de CI separada y no invalida la certificación local.

## Seguridad
- El modo `corporativo` falla cerrado.
- El modo temporal `deshabilitado` no concede nivel administrador ni expone cambios de lista permitida por web.
- Durante esta etapa, el servidor puede cambiar la lista permitida mediante un comando de consola auditado.
- Los grupos descubiertos nacen no monitoreados y el redescubrimiento no cambia silenciosamente su autorización.
- Cada cambio efectivo incrementa `version_autorizacion` y deja auditoría.
- Las pegatinas se ignoran antes de persistencia y la lista permitida se aplica antes del evento crudo.

## UI/UX
- El tablero comunica que la base fue validada.
- Existe una vista de grupos descubiertos con estados de monitoreo, actividad, versión de autorización y última detección.
- En modo sin autenticación los cambios administrativos aparecen bloqueados en la web y se explica la operación temporal por consola.

## Dictamen
**Puerta técnica base aprobada para continuar desarrollo e integración controlada en servidor de pruebas.** No se autoriza todavía conectar el número empresarial ni desplegar a producción: primero debe validarse el incremento de grupos, construir el punto interno HMAC/pasarela autohospedada y probar una sesión controlada.
