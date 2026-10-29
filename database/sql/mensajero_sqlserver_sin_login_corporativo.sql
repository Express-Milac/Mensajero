/*
 Mensajero MILAC: SQL Server para INSTALACION NUEVA en la base elegida por DBA.
 Fuente: migraciones de la rama compatibilidad/php74-milac-20261027.
 NO modifica tablas existentes, NO crea usuarios ni passwords del login local.
 Crea sesiones Laravel propias de Mensajero, no usuarios empresariales.
 Revisar y probar primero en BD SQL Server *_test. NO ejecutar sin permiso DBA.
 Debe ejecutarse completo en la base de datos DESTINO autorizada.
 Si existe cualquier tabla dbo.mensajero_*, el script CANCELA sin alteraciones.
 No ejecutar artisan migrate junto con este script sin consultar estado migraciones.
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
IF DB_NAME() IN (N'master',N'model',N'msdb',N'tempdb')
    THROW 51001, 'Base de sistema no permitida; seleccione una BD autorizada.', 1;
IF EXISTS (
    SELECT 1 FROM sys.tables
    WHERE schema_id = SCHEMA_ID('dbo') AND name LIKE N'mensajero[_]%'
)
    THROW 51002, 'Ya existen tablas Mensajero en dbo; NO ejecutar alta limpia sobre este esquema.', 1;
BEGIN TRY
    BEGIN TRANSACTION;

-- Migracion: 0001_01_01_000000_crear_tablas_usuarios.php
-- OMITIDA: mensajero_usuarios (identidad y contrasenas pertenecen al login empresarial).
-- OMITIDA: mensajero_tokens_restablecimiento_contrasena (identidad y contrasenas pertenecen al login empresarial).
CREATE TABLE dbo.[mensajero_sesiones] (
    [id] nvarchar(255) NOT NULL PRIMARY KEY,
    [user_id] bigint NULL,
    [ip_address] nvarchar(45) NULL,
    [user_agent] nvarchar(max) NULL,
    [payload] nvarchar(max) NOT NULL,
    [last_activity] int NOT NULL
);

-- Migracion: 0001_01_01_000001_crear_tablas_cache.php
CREATE TABLE dbo.[mensajero_cache] (
    [key] nvarchar(255) NOT NULL PRIMARY KEY,
    [value] nvarchar(max) NOT NULL,
    [expiration] bigint NOT NULL
);
CREATE TABLE dbo.[mensajero_bloqueos_cache] (
    [key] nvarchar(255) NOT NULL PRIMARY KEY,
    [owner] nvarchar(255) NOT NULL,
    [expiration] bigint NOT NULL
);

-- Migracion: 0001_01_01_000002_crear_tablas_trabajos.php
CREATE TABLE dbo.[mensajero_trabajos] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [queue] nvarchar(255) NOT NULL,
    [payload] nvarchar(max) NOT NULL,
    [attempts] smallint NOT NULL,
    [reserved_at] int NULL,
    [available_at] int NOT NULL,
    [created_at] int NOT NULL
);
CREATE TABLE dbo.[mensajero_lotes_trabajos] (
    [id] nvarchar(255) NOT NULL PRIMARY KEY,
    [name] nvarchar(255) NOT NULL,
    [total_jobs] int NOT NULL,
    [pending_jobs] int NOT NULL,
    [failed_jobs] int NOT NULL,
    [failed_job_ids] nvarchar(max) NOT NULL,
    [options] nvarchar(max) NULL,
    [cancelled_at] int NULL,
    [created_at] int NOT NULL,
    [finished_at] int NULL
);
CREATE TABLE dbo.[mensajero_trabajos_fallidos] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [uuid] nvarchar(255) NOT NULL,
    [connection] nvarchar(255) NOT NULL,
    [queue] nvarchar(255) NOT NULL,
    [payload] nvarchar(max) NOT NULL,
    [exception] nvarchar(max) NOT NULL,
    [failed_at] datetime2(6) NOT NULL DEFAULT (SYSDATETIME())
);
CREATE INDEX [failed_at] ON dbo.[mensajero_trabajos_fallidos] ([connection], [queue]);

-- Migracion: 2026_10_15_000100_crear_nucleo_captura_mensajero.php
CREATE TABLE dbo.[mensajero_conexiones_whatsapp] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [clave_conexion] nvarchar(64) NOT NULL,
    [proveedor] nvarchar(32) NOT NULL,
    [id_cuenta_externa] nvarchar(191) NULL,
    [nombre_visible] nvarchar(255) NULL,
    [estado] nvarchar(32) NOT NULL DEFAULT (N'sin_configurar'),
    [metadatos] nvarchar(max) NULL,
    [conectado_en] datetime2(6) NULL,
    [ultimo_latido_en] datetime2(6) NULL,
    [ultima_desconexion_en] datetime2(6) NULL,
    [creado_en] datetime2(6) NULL,
    [actualizado_en] datetime2(6) NULL
);
CREATE INDEX [mensajero_conexiones_proveedor_estado_idx] ON dbo.[mensajero_conexiones_whatsapp] ([proveedor], [estado]);
CREATE TABLE dbo.[mensajero_chats_monitoreados] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [conexion_id] bigint NOT NULL,
    [id_chat_proveedor] nvarchar(191) COLLATE Latin1_General_100_BIN2 NOT NULL,
    [tipo_chat] nvarchar(32) NOT NULL,
    [nombre_grupo] nvarchar(255) NULL,
    [nombre_interno] nvarchar(255) NULL,
    [es_monitoreado] bit NOT NULL DEFAULT (0),
    [es_activo] bit NOT NULL DEFAULT (1),
    [version_autorizacion] bigint NOT NULL DEFAULT (0),
    [visto_primera_vez_en] datetime2(6) NULL,
    [visto_ultima_vez_en] datetime2(6) NULL,
    [creado_en] datetime2(6) NULL,
    [actualizado_en] datetime2(6) NULL
);
CREATE UNIQUE INDEX [mensajero_chats_conexion_proveedor_uq] ON dbo.[mensajero_chats_monitoreados] ([conexion_id], [id_chat_proveedor]);
CREATE INDEX [mensajero_chats_monitoreo_idx] ON dbo.[mensajero_chats_monitoreados] ([es_monitoreado], [es_activo]);
ALTER TABLE dbo.[mensajero_chats_monitoreados] ADD CONSTRAINT [mensajero_chats_conexion_fk] FOREIGN KEY ([conexion_id]) REFERENCES dbo.[mensajero_conexiones_whatsapp] ([id]);
CREATE TABLE dbo.[mensajero_contactos_whatsapp] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [conexion_id] bigint NOT NULL,
    [id_contacto_proveedor] nvarchar(191) COLLATE Latin1_General_100_BIN2 NOT NULL,
    [telefono] nvarchar(32) NULL,
    [nombre_visible] nvarchar(255) NULL,
    [nombre_notificacion] nvarchar(255) NULL,
    [metadatos] nvarchar(max) NULL,
    [visto_primera_vez_en] datetime2(6) NULL,
    [visto_ultima_vez_en] datetime2(6) NULL,
    [creado_en] datetime2(6) NULL,
    [actualizado_en] datetime2(6) NULL
);
CREATE UNIQUE INDEX [mensajero_contactos_conexion_proveedor_uq] ON dbo.[mensajero_contactos_whatsapp] ([conexion_id], [id_contacto_proveedor]);
CREATE INDEX [mensajero_contactos_telefono_idx] ON dbo.[mensajero_contactos_whatsapp] ([telefono]);
ALTER TABLE dbo.[mensajero_contactos_whatsapp] ADD CONSTRAINT [mensajero_contactos_conexion_fk] FOREIGN KEY ([conexion_id]) REFERENCES dbo.[mensajero_conexiones_whatsapp] ([id]);
CREATE TABLE dbo.[mensajero_eventos_crudos_whatsapp] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [conexion_id] bigint NOT NULL,
    [chat_monitoreado_id] bigint NOT NULL,
    [id_evento_externo] nvarchar(191) COLLATE Latin1_General_100_BIN2 NULL,
    [clave_deduplicacion] nchar(64) NOT NULL,
    [tipo_evento] nvarchar(64) NOT NULL,
    [contenido] nvarchar(max) NOT NULL,
    [hash_contenido_sha256] nchar(64) NOT NULL,
    [version_contrato] nvarchar(32) NOT NULL DEFAULT (N'v1'),
    [estado_procesamiento] nvarchar(24) NOT NULL DEFAULT (N'capturado'),
    [intentos] smallint NOT NULL DEFAULT (0),
    [recibido_en] datetime2(6) NOT NULL,
    [procesado_en] datetime2(6) NULL,
    [mensaje_error] nvarchar(max) NULL,
    [creado_en] datetime2(6) NULL,
    [actualizado_en] datetime2(6) NULL
);
CREATE UNIQUE INDEX [mensajero_eventos_conexion_deduplicacion_uq] ON dbo.[mensajero_eventos_crudos_whatsapp] ([conexion_id], [clave_deduplicacion]);
CREATE INDEX [mensajero_eventos_chat_recibido_idx] ON dbo.[mensajero_eventos_crudos_whatsapp] ([chat_monitoreado_id], [recibido_en], [id]);
CREATE INDEX [mensajero_eventos_estado_recibido_idx] ON dbo.[mensajero_eventos_crudos_whatsapp] ([estado_procesamiento], [recibido_en]);
ALTER TABLE dbo.[mensajero_eventos_crudos_whatsapp] ADD CONSTRAINT [mensajero_eventos_conexion_fk] FOREIGN KEY ([conexion_id]) REFERENCES dbo.[mensajero_conexiones_whatsapp] ([id]);
ALTER TABLE dbo.[mensajero_eventos_crudos_whatsapp] ADD CONSTRAINT [mensajero_eventos_chat_fk] FOREIGN KEY ([chat_monitoreado_id]) REFERENCES dbo.[mensajero_chats_monitoreados] ([id]);
CREATE TABLE dbo.[mensajero_buzon_salida] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [tema] nvarchar(128) NOT NULL,
    [tipo_entidad] nvarchar(64) NOT NULL,
    [entidad_id] bigint NULL,
    [clave_deduplicacion] nchar(64) NOT NULL,
    [contenido] nvarchar(max) NOT NULL,
    [intentos] smallint NOT NULL DEFAULT (0),
    [disponible_en] datetime2(6) NOT NULL,
    [reservado_hasta] datetime2(6) NULL,
    [enviado_en] datetime2(6) NULL,
    [ultimo_error] nvarchar(max) NULL,
    [creado_en] datetime2(6) NOT NULL DEFAULT (SYSDATETIME())
);
CREATE UNIQUE INDEX [mensajero_buzon_tema_deduplicacion_uq] ON dbo.[mensajero_buzon_salida] ([tema], [clave_deduplicacion]);
CREATE INDEX [mensajero_buzon_envio_idx] ON dbo.[mensajero_buzon_salida] ([enviado_en], [disponible_en], [id]);

-- Migracion: 2026_10_15_000200_crear_mensajes_whatsapp_mensajero.php
CREATE TABLE dbo.[mensajero_mensajes_whatsapp] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [conexion_id] bigint NOT NULL,
    [chat_monitoreado_id] bigint NOT NULL,
    [contacto_remitente_id] bigint NULL,
    [evento_crudo_id] bigint NOT NULL,
    [id_mensaje_proveedor] nvarchar(191) COLLATE Latin1_General_100_BIN2 NOT NULL,
    [tipo_mensaje] nvarchar(32) NOT NULL,
    [contenido_texto] nvarchar(max) NULL,
    [pie_multimedia] nvarchar(max) NULL,
    [enviado_en] datetime2(6) NULL,
    [recibido_en] datetime2(6) NOT NULL,
    [procesado_en] datetime2(6) NULL,
    [id_mensaje_citado_proveedor] nvarchar(191) COLLATE Latin1_General_100_BIN2 NULL,
    [mensaje_citado_id] bigint NULL,
    [estado_captura] nvarchar(24) NOT NULL DEFAULT (N'normalizado'),
    [estado_multimedia] nvarchar(24) NOT NULL DEFAULT (N'no_requerido'),
    [estado_ia] nvarchar(24) NOT NULL DEFAULT (N'no_elegible'),
    [creado_en] datetime2(6) NULL,
    [actualizado_en] datetime2(6) NULL
);
CREATE UNIQUE INDEX [mensajero_mensajes_proveedor_uq] ON dbo.[mensajero_mensajes_whatsapp] ([conexion_id], [chat_monitoreado_id], [id_mensaje_proveedor]);
CREATE UNIQUE INDEX [mensajero_mensajes_evento_uq] ON dbo.[mensajero_mensajes_whatsapp] ([evento_crudo_id]);
CREATE INDEX [mensajero_mensajes_chat_enviado_idx] ON dbo.[mensajero_mensajes_whatsapp] ([chat_monitoreado_id], [enviado_en], [id]);
CREATE INDEX [mensajero_mensajes_remitente_enviado_idx] ON dbo.[mensajero_mensajes_whatsapp] ([contacto_remitente_id], [enviado_en]);
ALTER TABLE dbo.[mensajero_mensajes_whatsapp] ADD CONSTRAINT [mensajero_mensajes_conexion_fk] FOREIGN KEY ([conexion_id]) REFERENCES dbo.[mensajero_conexiones_whatsapp] ([id]);
ALTER TABLE dbo.[mensajero_mensajes_whatsapp] ADD CONSTRAINT [mensajero_mensajes_chat_fk] FOREIGN KEY ([chat_monitoreado_id]) REFERENCES dbo.[mensajero_chats_monitoreados] ([id]);
ALTER TABLE dbo.[mensajero_mensajes_whatsapp] ADD CONSTRAINT [mensajero_mensajes_remitente_fk] FOREIGN KEY ([contacto_remitente_id]) REFERENCES dbo.[mensajero_contactos_whatsapp] ([id]);
ALTER TABLE dbo.[mensajero_mensajes_whatsapp] ADD CONSTRAINT [mensajero_mensajes_evento_fk] FOREIGN KEY ([evento_crudo_id]) REFERENCES dbo.[mensajero_eventos_crudos_whatsapp] ([id]);
ALTER TABLE dbo.[mensajero_mensajes_whatsapp] ADD CONSTRAINT [mensajero_mensajes_citado_fk] FOREIGN KEY ([mensaje_citado_id]) REFERENCES dbo.[mensajero_mensajes_whatsapp] ([id]);

-- Migracion: 2026_10_15_000300_crear_auditoria_monitoreo_chats.php
CREATE TABLE dbo.[mensajero_auditoria_monitoreo_chats] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [chat_monitoreado_id] bigint NOT NULL,
    [version_autorizacion] bigint NOT NULL,
    [es_monitoreado_anterior] bit NOT NULL,
    [es_monitoreado_nuevo] bit NOT NULL,
    [actor_tipo] nvarchar(32) NOT NULL,
    [actor_id] bigint NULL,
    [motivo] nvarchar(500) NULL,
    [cambiado_en] datetime2(6) NOT NULL DEFAULT (SYSDATETIME())
);
CREATE UNIQUE INDEX [mensajero_auditoria_chat_version_uq] ON dbo.[mensajero_auditoria_monitoreo_chats] ([chat_monitoreado_id], [version_autorizacion]);
CREATE INDEX [mensajero_auditoria_cambiado_idx] ON dbo.[mensajero_auditoria_monitoreo_chats] ([cambiado_en], [id]);

-- Migracion: 2026_10_15_000400_crear_solicitudes_pasarela.php
CREATE TABLE dbo.[mensajero_solicitudes_pasarela] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [identificador_solicitud] nvarchar(128) COLLATE Latin1_General_100_BIN2 NOT NULL,
    [marca_tiempo_pasarela] bigint NOT NULL,
    [hash_cuerpo_sha256] nchar(64) NOT NULL,
    [ruta] nvarchar(191) NOT NULL,
    [recibido_en] datetime2(6) NOT NULL DEFAULT (SYSDATETIME())
);
CREATE UNIQUE INDEX [mensajero_solicitudes_pasarela_identificador_uq] ON dbo.[mensajero_solicitudes_pasarela] ([identificador_solicitud]);
CREATE INDEX [mensajero_solicitudes_pasarela_recibido_idx] ON dbo.[mensajero_solicitudes_pasarela] ([recibido_en]);

-- Migracion: 2026_10_16_000500_indexar_conexiones_meta.php
CREATE INDEX [mensajero_conexiones_proveedor_externa_idx] ON dbo.[mensajero_conexiones_whatsapp] ([proveedor], [id_cuenta_externa]);

-- Migracion: 2026_10_16_000600_crear_periodos_integridad_whatsapp.php
CREATE TABLE dbo.[mensajero_periodos_integridad_whatsapp] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [conexion_id] bigint NOT NULL,
    [identificador_periodo] nchar(64) NOT NULL,
    [inicio_en] datetime2(6) NOT NULL,
    [fin_en] datetime2(6) NOT NULL,
    [causa] nvarchar(64) NOT NULL,
    [detalles] nvarchar(max) NULL,
    [creado_en] datetime2(6) NULL,
    [actualizado_en] datetime2(6) NULL
);
CREATE UNIQUE INDEX [mensajero_integridad_conexion_periodo_uq] ON dbo.[mensajero_periodos_integridad_whatsapp] ([conexion_id], [identificador_periodo]);
CREATE INDEX [mensajero_integridad_conexion_fechas_idx] ON dbo.[mensajero_periodos_integridad_whatsapp] ([conexion_id], [inicio_en], [fin_en]);
ALTER TABLE dbo.[mensajero_periodos_integridad_whatsapp] ADD CONSTRAINT [mensajero_integridad_conexion_fk] FOREIGN KEY ([conexion_id]) REFERENCES dbo.[mensajero_conexiones_whatsapp] ([id]);

-- Migracion: 2026_10_16_000700_crear_archivos_whatsapp.php
CREATE TABLE dbo.[mensajero_archivos_whatsapp] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [conexion_id] bigint NOT NULL,
    [chat_monitoreado_id] bigint NOT NULL,
    [evento_crudo_id] bigint NOT NULL,
    [identificador_archivo] nchar(64) NOT NULL,
    [id_mensaje_proveedor] nvarchar(191) COLLATE Latin1_General_100_BIN2 NULL,
    [tipo_mensaje] nvarchar(32) NOT NULL,
    [tipo_mime] nvarchar(191) NULL,
    [nombre_original] nvarchar(255) NULL,
    [extension] nvarchar(16) NULL,
    [tamano_bytes] bigint NOT NULL,
    [hash_sha256] nchar(64) NOT NULL,
    [disco] nvarchar(64) NOT NULL DEFAULT (N'mensajero_local'),
    [ruta_relativa] nvarchar(1024) NOT NULL,
    [estado] nvarchar(24) NOT NULL DEFAULT (N'verificado'),
    [creado_en] datetime2(6) NULL,
    [actualizado_en] datetime2(6) NULL
);
CREATE UNIQUE INDEX [mensajero_archivos_conexion_identificador_uq] ON dbo.[mensajero_archivos_whatsapp] ([conexion_id], [identificador_archivo]);
CREATE UNIQUE INDEX [mensajero_archivos_evento_uq] ON dbo.[mensajero_archivos_whatsapp] ([evento_crudo_id]);
CREATE INDEX [mensajero_archivos_chat_creado_idx] ON dbo.[mensajero_archivos_whatsapp] ([chat_monitoreado_id], [creado_en]);
CREATE INDEX [mensajero_archivos_hash_idx] ON dbo.[mensajero_archivos_whatsapp] ([hash_sha256]);
ALTER TABLE dbo.[mensajero_archivos_whatsapp] ADD CONSTRAINT [mensajero_archivos_conexion_fk] FOREIGN KEY ([conexion_id]) REFERENCES dbo.[mensajero_conexiones_whatsapp] ([id]);
ALTER TABLE dbo.[mensajero_archivos_whatsapp] ADD CONSTRAINT [mensajero_archivos_chat_fk] FOREIGN KEY ([chat_monitoreado_id]) REFERENCES dbo.[mensajero_chats_monitoreados] ([id]);
ALTER TABLE dbo.[mensajero_archivos_whatsapp] ADD CONSTRAINT [mensajero_archivos_evento_fk] FOREIGN KEY ([evento_crudo_id]) REFERENCES dbo.[mensajero_eventos_crudos_whatsapp] ([id]);

-- Migracion: 2026_10_16_000800_enriquecer_eventos_crudos_whatsapp.php
ALTER TABLE dbo.[mensajero_eventos_crudos_whatsapp] ADD [tipo_mensaje] nvarchar(64) NULL;
ALTER TABLE dbo.[mensajero_eventos_crudos_whatsapp] ADD [id_remitente_proveedor] nvarchar(191) COLLATE Latin1_General_100_BIN2 NULL;
ALTER TABLE dbo.[mensajero_eventos_crudos_whatsapp] ADD [id_mensaje_citado_proveedor] nvarchar(191) COLLATE Latin1_General_100_BIN2 NULL;
ALTER TABLE dbo.[mensajero_eventos_crudos_whatsapp] ADD [id_mensaje_objetivo_proveedor] nvarchar(191) COLLATE Latin1_General_100_BIN2 NULL;
CREATE INDEX [mensajero_eventos_chat_tipo_recibido_idx] ON dbo.[mensajero_eventos_crudos_whatsapp] ([chat_monitoreado_id], [tipo_evento], [tipo_mensaje], [recibido_en]);
CREATE INDEX [mensajero_eventos_chat_remitente_idx] ON dbo.[mensajero_eventos_crudos_whatsapp] ([chat_monitoreado_id], [id_remitente_proveedor], [recibido_en]);
CREATE INDEX [mensajero_eventos_chat_objetivo_idx] ON dbo.[mensajero_eventos_crudos_whatsapp] ([chat_monitoreado_id], [id_mensaje_objetivo_proveedor]);

-- Migracion: 2026_10_20_190000_agregar_visibilidad_tablero_chats.php
ALTER TABLE dbo.[mensajero_chats_monitoreados] ADD [es_visible_tablero] bit NOT NULL DEFAULT (1);
CREATE TABLE dbo.[mensajero_auditoria_visibilidad_grupos] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [chat_monitoreado_id] bigint NOT NULL,
    [visible_anterior] bit NOT NULL,
    [visible_nuevo] bit NOT NULL,
    [actor_tipo] nvarchar(32) NOT NULL,
    [actor_id] bigint NULL,
    [registrado_en] datetime2(6) NOT NULL
);
CREATE INDEX [mensajero_auditoria_visibilidad_idx] ON dbo.[mensajero_auditoria_visibilidad_grupos] ([chat_monitoreado_id], [registrado_en]);
ALTER TABLE dbo.[mensajero_auditoria_visibilidad_grupos] ADD CONSTRAINT [mensajero_auditoria_visibilidad_chat_fk] FOREIGN KEY ([chat_monitoreado_id]) REFERENCES dbo.[mensajero_chats_monitoreados] ([id]);

-- Migracion: 2026_10_20_200000_crear_identidades_remitentes.php
CREATE TABLE dbo.[mensajero_identidades_remitentes] (
    [id] bigint IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [conexion_id] bigint NOT NULL,
    [id_remitente_proveedor] nvarchar(191) COLLATE Latin1_General_100_BIN2 NOT NULL,
    [nombre_interno] nvarchar(120) NOT NULL,
    [actor_tipo] nvarchar(32) NOT NULL,
    [actor_id] bigint NULL,
    [creado_en] datetime2(6) NULL,
    [actualizado_en] datetime2(6) NULL
);
CREATE UNIQUE INDEX [mensajero_identidad_conexion_remitente_uq] ON dbo.[mensajero_identidades_remitentes] ([conexion_id], [id_remitente_proveedor]);
ALTER TABLE dbo.[mensajero_identidades_remitentes] ADD CONSTRAINT [mensajero_identidad_conexion_fk] FOREIGN KEY ([conexion_id]) REFERENCES dbo.[mensajero_conexiones_whatsapp] ([id]);

-- Migracion: 2026_10_21_000001_identificar_remitentes_en_eventos.php
ALTER TABLE dbo.[mensajero_eventos_crudos_whatsapp] ADD [nombre_remitente] nvarchar(120) NULL;
ALTER TABLE dbo.[mensajero_eventos_crudos_whatsapp] ADD [fuente_nombre_remitente] nvarchar(16) NULL;
ALTER TABLE dbo.[mensajero_eventos_crudos_whatsapp] ADD [telefono_remitente] nvarchar(20) NULL;
