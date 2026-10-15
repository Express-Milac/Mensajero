# Mensajero: instrucciones del proyecto

## Lectura obligatoria
Leer `README.md`, `OBJETIVO.md`, `RESUMEN.md`, `TASKS.md`, `docs/revision-previa-integracion.md` y los archivos de `agentes/` antes de cambios sustanciales.

## Decisiones empresariales vigentes
- WhatsApp Business.
- Solo 5–6 grupos seleccionados; los grupos existentes pueden superar 30 participantes.
- Sin servicio administrado de terceros para capturar WhatsApp; la pasarela será autohospedada por MILAC.
- Servidor propio de MILAC.
- SQL Server corporativo existente; todas las tablas propias usan prefijo `mensajero_`.
- Temporalmente no hay inicio de sesión. `MENSAJERO_MODO_AUTENTICACION=deshabilitado` permite únicamente acceso interno ordinario; nunca privilegios administrativos.
- El modo `corporativo` debe fallar cerrado hasta implementar la identidad real.

## Flujo obligatorio de agentes
Cada incremento pasa, en orden, por: arquitectura, creación, revisión, pruebas, seguridad y UI/UX. Sus criterios viven en `agentes/`.

## Invariantes
- Lista permitida por defecto denegada antes de evento crudo, multimedia o IA.
- Pegatinas se descartan.
- Tipo desconocido de un chat autorizado conserva el evento crudo.
- Evento crudo + buzón de salida se confirman antes de reconocer la recepción.
- Redis y Gemini no condicionan la persistencia del evento crudo.
- Archivos privados fuera de SQL Server; la base guarda metadatos y rutas.
- No conectar el número empresarial antes de la prueba controlada de la pasarela.
- No ejecutar migraciones de desarrollo contra la base corporativa productiva.
- No marcar un incremento como certificado si GitHub Actions falla antes de crear jobs o si no se ejecutó la capa correspondiente.

## Idioma
El dominio, tablas, columnas propias, rutas, pruebas, documentación y textos visibles deben usar español. Se permiten términos literales en inglés solo cuando son contratos obligatorios de Laravel/PHP/SQL Server, nombres de productos o valores recibidos de un proveedor externo; esas excepciones no deben propagarse al dominio.

## Archivos convencionales
`README.md`, `AGENTS.md`, `CONTRIBUTING.md`, directorios estándar de Laravel y claves internas del framework conservan sus nombres convencionales para compatibilidad con herramientas.

## Requisitos del runtime
PHP 8.4+ con las extensiones requeridas por Laravel, SQL Server y `mbstring`. Las pruebas de integración de esquema solo se ejecutan contra una base desechable cuyo nombre termine en `_test`.
