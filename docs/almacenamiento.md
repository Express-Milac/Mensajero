# Almacenamiento privado y portable

El disco inicial es `mensajero_local`. Su raíz se configura con `MENSAJERO_RUTA_ALMACENAMIENTO`; si queda vacía se utiliza `storage/app/mensajero`.

SQL Server solo guarda metadatos y rutas relativas. Fotos, video, audio y documentos viven en el almacenamiento privado. No se crean enlaces públicos permanentes para la evidencia.

La descarga/visualización multimedia privada en Laravel ya revalida
autorización vigente del grupo, tamaño máximo, tipo MIME real con
`fileinfo` y SHA-256 antes de transmitir el archivo. Los tipos
no visualizables se descargan con disposición de adjunto y la respuesta
evita cachés compartidas. Una verificación en Windows el 20/10/2026
aprobó 2 pruebas y 43 aserciones; la operación real de Node/WhatsApp
y la revisión visual siguen pendientes.

Para mover el volumen: pausar descargas, copiar conservando rutas, verificar conteos y sumas, cambiar la raíz configurada y probar lectura antes de reactivar trabajadores. Las copias de seguridad de SQL Server y archivos deben coordinarse y probar restauración.
