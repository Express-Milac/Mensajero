# Política de nomenclatura en español

Mensajero usa español para el dominio, la base de datos, rutas visibles, estados internos, documentación, pruebas y componentes propios.

## Obligatorio
- Tablas: `mensajero_*` y nombres españoles.
- Columnas de dominio: español (`conexion_id`, `estado`, `contenido_crudo`, `recibido_en`).
- Clases, métodos y propiedades propias: español.
- Rutas visibles: español (`/tablero`, `/acceso`, `/salud`).
- Estados propios: español (`capturado`, `normalizado`, `ignorado`).
- Documentación y mensajes al usuario: español.

## Excepciones técnicas
Se conservan nombres exigidos por herramientas o protocolos cuando traducirlos rompe compatibilidad: PHP/Laravel (`APP_ENV`, `DB_CONNECTION`, métodos de interfaces), SQL Server, GitHub Actions, MIME, SHA-256, Redis, Gemini, WhatsApp y valores crudos que lleguen del proveedor antes de la normalización.

La excepción técnica no debe propagarse al dominio. El adaptador convierte el contrato externo a términos españoles antes de entrar al núcleo de Mensajero.
