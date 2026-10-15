# Modelo SQL Server

Motor operativo exclusivo: Microsoft SQL Server. Mensajero usa una base corporativa existente configurada en `.env`.

## Aislamiento
- historial de migraciones: `mensajero_migraciones`;
- autenticación futura: `mensajero_usuarios`, `mensajero_sesiones`, `mensajero_tokens_restablecimiento_contrasena`;
- infraestructura Laravel: `mensajero_cache`, `mensajero_bloqueos_cache`, `mensajero_trabajos`, `mensajero_lotes_trabajos`, `mensajero_trabajos_fallidos`;
- dominio: todas las tablas propias comienzan con `mensajero_`.

No se usa prefijo global de la conexión porque Mensajero puede requerir lecturas controladas de datos corporativos existentes.

## Captura
Tablas: `mensajero_conexiones_whatsapp`, `mensajero_chats_monitoreados`, `mensajero_contactos_whatsapp`, `mensajero_eventos_crudos_whatsapp`, `mensajero_buzon_salida` y `mensajero_mensajes_whatsapp`.

La lista permitida niega por defecto (`es_monitoreado=false`). Los identificadores del proveedor se tratan como cadenas opacas y las fechas operativas se almacenan en UTC.

## Validación
Las migraciones no se certifican con SQLite. Antes de producción: usar una base SQL Server desechable terminada en `_test`, ejecutar migraciones, correr `phpunit.integracion.xml`, revisar restricciones/índices y solo después preparar la instalación corporativa.
